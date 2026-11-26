<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Models\LabBatch;
use App\Services\Batches\LabBatchService;
use PDO;

class LabBatchController extends BaseController
{
    private LabBatchService $batchService;

    public function __construct()
    {
        $this->batchService = new LabBatchService();
    }

    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);

        $filters = [];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['instrument_id'])) $filters['instrument_id'] = (int)$_GET['instrument_id'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        $batches = LabBatch::allWithRelations($filters);
        $db = Database::connect();
        $instruments = $db->query("SELECT id, instrument_code, instrument_name FROM instruments WHERE is_active = TRUE ORDER BY instrument_name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('lab-batches.index', [
            'batches' => $batches,
            'instruments' => $instruments,
            'filters' => $filters
        ]);
    }

    public function create(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);

        $db = Database::connect();
        $analyses = $db->query("SELECT id, analysis_code, analysis_name FROM analyses WHERE is_active = TRUE ORDER BY analysis_code")->fetchAll(PDO::FETCH_ASSOC);
        $instruments = $db->query("SELECT id, instrument_code, instrument_name FROM instruments WHERE is_active = TRUE ORDER BY instrument_name")->fetchAll(PDO::FETCH_ASSOC);
        $methods = $db->query("SELECT id, method_name FROM methods ORDER BY method_name")->fetchAll(PDO::FETCH_ASSOC);
        $analysts = $db->query("SELECT id, full_name FROM users WHERE role IN ('Analyst', 'Admin') AND is_active = TRUE ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('lab-batches.create', [
            'analyses' => $analyses,
            'instruments' => $instruments,
            'methods' => $methods,
            'analysts' => $analysts
        ]);
    }

    public function store(): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);

        try {
            $res = $this->batchService->createLabBatch($_POST, Auth::id() ?? 1);
            session_flash('success', "Lab Batch '{$res['batch_code']}' created.");
            redirect('/lab-batches/' . $res['batch_id']);
        } catch (\Throwable $e) {
            session_flash('error', 'Error creating lab batch: ' . $e->getMessage());
            redirect('/lab-batches/create');
        }
    }

    public function show(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);

        $batch = LabBatch::findWithRelations($id);
        if (!$batch) {
            session_flash('error', 'Lab analytical batch not found.');
            redirect('/lab-batches');
        }

        $db = Database::connect();
        // Fetch candidate pending sample tests for this analysis to add to sequence
        $candidateTests = [];
        if (!empty($batch['analysis_id'])) {
            $stStmt = $db->prepare("
                SELECT st.id as sample_test_id, s.id as sample_id, s.sample_code, s.sample_stage, p.product_name
                FROM sample_tests st
                JOIN samples s ON st.sample_id = s.id
                LEFT JOIN products p ON s.product_id = p.id
                WHERE st.analysis_id = ? AND st.status IN ('Pending', 'In Progress') AND st.deleted_at IS NULL
                  AND NOT EXISTS (SELECT 1 FROM lab_batch_items lbi WHERE lbi.sample_test_id = st.id AND lbi.lab_batch_id = ?)
                ORDER BY s.created_at ASC
            ");
            $stStmt->execute([$batch['analysis_id'], $id]);
            $candidateTests = $stStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $this->render('lab-batches.show', [
            'batch' => $batch,
            'items' => $batch['items'] ?? [],
            'candidateTests' => $candidateTests
        ]);
    }

    public function addSequence(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);

        $itemType = trim($_POST['item_type'] ?? 'sample');

        try {
            if ($itemType === 'sample') {
                $testIds = $_POST['sample_test_ids'] ?? [];
                if (empty($testIds)) {
                    session_flash('error', 'Select at least one sample test to add to the analytical batch.');
                    redirect('/lab-batches/' . $id);
                    return;
                }

                $db = Database::connect();
                $items = [];
                foreach ($testIds as $stId) {
                    $stStmt = $db->prepare("SELECT sample_id FROM sample_tests WHERE id = ?");
                    $stStmt->execute([(int)$stId]);
                    $sampleId = (int)$stStmt->fetchColumn();

                    $items[] = [
                        'item_type' => 'sample',
                        'sample_id' => $sampleId,
                        'sample_test_id' => (int)$stId,
                        'vial_position' => null,
                        'injection_volume_ul' => 10.0
                    ];
                }
                $this->batchService->addSequenceItems($id, $items);
            } else {
                // Control item (blank, calibration_standard, qc_control, etc.)
                $items = [[
                    'item_type' => $itemType,
                    'control_name' => trim($_POST['control_name'] ?? ucfirst($itemType)),
                    'vial_position' => trim($_POST['vial_position'] ?? ''),
                    'injection_volume_ul' => (float)($_POST['injection_volume_ul'] ?? 10.0),
                    'expected_value' => isset($_POST['expected_value']) && $_POST['expected_value'] !== '' ? (float)$_POST['expected_value'] : null,
                    'tolerance_min' => isset($_POST['tolerance_min']) && $_POST['tolerance_min'] !== '' ? (float)$_POST['tolerance_min'] : null,
                    'tolerance_max' => isset($_POST['tolerance_max']) && $_POST['tolerance_max'] !== '' ? (float)$_POST['tolerance_max'] : null,
                ]];
                $this->batchService->addSequenceItems($id, $items);
            }

            session_flash('success', 'Sequence item(s) added successfully.');
        } catch (\Throwable $e) {
            session_flash('error', 'Failed adding sequence items: ' . $e->getMessage());
        }

        redirect('/lab-batches/' . $id);
    }

    public function recordControlReading(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);

        $itemId = (int)($_POST['item_id'] ?? 0);
        $reading = isset($_POST['actual_value']) && $_POST['actual_value'] !== '' ? (float)$_POST['actual_value'] : null;

        if ($itemId > 0 && $reading !== null) {
            $db = Database::connect();
            $db->prepare("UPDATE lab_batch_items SET actual_value = ? WHERE id = ? AND lab_batch_id = ?")
               ->execute([$reading, $itemId, $id]);
            session_flash('success', 'Control value recorded.');
        } else {
            session_flash('error', 'Reading value is required.');
        }

        redirect('/lab-batches/' . $id);
    }

    public function evaluateQc(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer']);

        try {
            $res = $this->batchService->evaluateBatchQc($id, Auth::id() ?? 1);
            if ($res['is_passed']) {
                session_flash('success', 'Batch QC Evaluation PASSED: All analytical controls met acceptance criteria.');
            } else {
                session_flash('warning', "Batch QC FAILED: {$res['requeued_tests_count']} test(s) invalidated and requeued with audit reason.");
            }
        } catch (\Throwable $e) {
            session_flash('error', 'Batch QC evaluation error: ' . $e->getMessage());
        }

        redirect('/lab-batches/' . $id);
    }

    public function exportWorklist(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);

        $format = $_GET['format'] ?? 'empower_csv';
        $batch = LabBatch::findWithRelations($id);
        if (!$batch) {
            session_flash('error', 'Batch not found.');
            redirect('/lab-batches');
            return;
        }

        try {
            $content = $this->batchService->generateInstrumentWorklist($id, $format);
            $filename = "Worklist_{$batch['batch_code']}_{$format}.csv";

            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            echo $content;
            exit;
        } catch (\Throwable $e) {
            session_flash('error', 'Worklist export error: ' . $e->getMessage());
            redirect('/lab-batches/' . $id);
        }
    }
}
