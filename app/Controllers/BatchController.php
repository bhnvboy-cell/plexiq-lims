<?php
namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductTest;
use App\Models\TestItem;
use App\Models\Sample;
use App\Models\Customer;
use App\Models\User;

class BatchController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $batches = Batch::allWithRelations();
        return $this->render('batches.index', ['batches' => $batches]);
    }

    public function create(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $products = Product::active();
        $analysts = User::analysts();
        return $this->render('batches.create', ['products' => $products, 'analysts' => $analysts]);
    }

    public function getProductTestsJson(int $productId): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $tests = ProductTest::findByProduct($productId);
        $result = array_map(function ($t) {
            $spec = $t['spec_limit_text']
                ?? ($t['min_spec_limit'] !== null ? $t['min_spec_limit'] . ' - ' . $t['max_spec_limit'] : '');
            return [
                'test_id' => $t['test_id'],
                'test_name' => $t['test_name'],
                'test_code' => $t['test_code'],
                'method_name' => $t['method_name'],
                'unit_code' => $t['unit_code'],
                'spec_limit_text' => $t['spec_limit_text'],
                'min_spec_limit' => $t['min_spec_limit'],
                'max_spec_limit' => $t['max_spec_limit'],
                'effective_spec' => $spec ?: 'No spec',
            ];
        }, $tests);
        return $this->json($result);
    }

    public function store(): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO batches (batch_number, product_id, batch_size, manufacture_date, expiry_date, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id
            ");
            $stmt->execute([
                $_POST['batch_number'],
                $_POST['product_id'] ?: null,
                $_POST['batch_size'] ?: null,
                $_POST['manufacture_date'] ?: null,
                $_POST['expiry_date'] ?: null,
                $_POST['notes'] ?: null,
                Auth::id(),
            ]);
            $batchId = (int)$stmt->fetchColumn();

            // Auto-create a sample for this batch
            $sampleCode = Sample::generateCode();
            $sStmt = $db->prepare("
                INSERT INTO samples (sample_code, product_id, batch_number, batch_size, batch_id,
                    manufacture_date, expiry_date, received_date, priority, status, registered_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_DATE, 'Normal', 'Registered', ?) RETURNING id
            ");
            $sStmt->execute([
                $sampleCode,
                $_POST['product_id'] ?: null,
                $_POST['batch_number'],
                $_POST['batch_size'] ?: null,
                $batchId,
                $_POST['manufacture_date'] ?: null,
                $_POST['expiry_date'] ?: null,
                Auth::id(),
            ]);
            $sampleId = (int)$sStmt->fetchColumn();

            // Auto-assign all product tests to the sample
            $productTests = ProductTest::findByProduct($_POST['product_id']);
            if (!empty($productTests)) {
                $ptStmt = $db->prepare("
                    INSERT INTO sample_tests (sample_id, test_id, assigned_to, status)
                    VALUES (?, ?, ?, 'Pending')
                ");
                foreach ($productTests as $pt) {
                    $ptStmt->execute([$sampleId, $pt['test_id'], $_POST['assigned_analyst_id'] ?: null]);
                }
            }

            $db->commit();
            Audit::log('Batch Created', 'batches', $batchId, null, ['batch_number' => $_POST['batch_number']]);
            session_flash('success', "Batch {$_POST['batch_number']} created with sample {$sampleCode} and " . count($productTests) . " tests auto-assigned.");
            redirect('/batches');
        } catch (\Exception $e) {
            $db->rollBack();
            session_flash('error', 'Error creating batch: ' . $e->getMessage());
            redirect('/batches/create');
        }
    }

    public function show(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $batch = Batch::findWithRelations($id);
        if (!$batch) {
            session_flash('error', 'Batch not found.');
            redirect('/batches');
        }

        // Gather result status per test across all samples
        $db = \App\Helpers\Database::connect();
        $testResults = [];
        if (!empty($batch['tests'])) {
            $sampleIds = array_column($batch['samples'], 'id');
            if (!empty($sampleIds)) {
                $placeholders = implode(',', array_fill(0, count($sampleIds), '?'));
                $rStmt = $db->prepare("
                    SELECT st.id AS sample_test_id, st.sample_id, st.test_id, st.status AS test_status,
                           r.result_value, r.result_text, r.is_within_spec,
                           st.assigned_to, u.full_name AS analyst_name
                    FROM sample_tests st
                    LEFT JOIN results r ON r.sample_test_id = st.id
                    LEFT JOIN users u ON st.assigned_to = u.id
                    WHERE st.sample_id IN ($placeholders)
                ");
                $rStmt->execute($sampleIds);
                $allResults = $rStmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($allResults as $r) {
                    $testResults[$r['test_id']][] = $r;
                }
            }
        }
        $batch['testResults'] = $testResults;

        $customers = Customer::active();
        $analysts = User::analysts();

        return $this->render('batches.show', [
            'batch' => $batch,
            'customers' => $customers,
            'analysts' => $analysts,
        ]);
    }

    public function updateWorkflow(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $newStatus = $_POST['status'] ?? '';

        $allowedTransitions = [
            'Registered' => ['In Progress'],
            'In Progress' => ['Reviewed', 'Rejected'],
            'Reviewed' => ['Approved', 'Rejected'],
            'Approved' => ['COA Released'],
        ];

        $batch = Batch::find($id);
        if (!$batch) {
            session_flash('error', 'Batch not found.');
            redirect('/batches');
        }

        $currentStatus = $batch['status'];
        if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            session_flash('error', "Invalid status transition from {$currentStatus} to {$newStatus}.");
            redirect('/batches/' . $id);
        }

        $stmt = $db->prepare("UPDATE batches SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        Audit::log('Batch Status Changed', 'batches', $id, ['status' => $currentStatus], ['status' => $newStatus]);
        session_flash('success', "Batch status changed to {$newStatus}.");
        redirect('/batches/' . $id);
    }

    public function edit(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $batch = Batch::findWithRelations($id);
        if (!$batch) { session_flash('error', 'Batch not found.'); redirect('/batches'); }
        $products = Product::active();
        $analysts = User::analysts();
        return $this->render('batches.edit', [
            'batch' => $batch, 'products' => $products, 'analysts' => $analysts,
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE batches SET batch_number=?, product_id=?, batch_size=?, manufacture_date=?, expiry_date=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([
                $_POST['batch_number'], $_POST['product_id'] ?: null, $_POST['batch_size'] ?: null,
                $_POST['manufacture_date'] ?: null, $_POST['expiry_date'] ?: null,
                $_POST['notes'] ?: null, $id,
            ]);
        Audit::log('Batch Updated', 'batches', $id);
        session_flash('success', 'Batch updated successfully.');
        redirect('/batches/' . $id);
    }

    public function addSample(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $batch = Batch::find($id);
        if (!$batch) { session_flash('error', 'Batch not found.'); redirect('/batches'); }
        $db = \App\Helpers\Database::connect();
        $db->beginTransaction();
        try {
            $sampleCode = Sample::generateCode();
            $sStmt = $db->prepare("
                INSERT INTO samples (sample_code, product_id, batch_number, batch_size, batch_id,
                    manufacture_date, expiry_date, received_date, priority, status, registered_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_DATE, 'Normal', 'Registered', ?) RETURNING id
            ");
            $sStmt->execute([
                $sampleCode, $batch['product_id'], $batch['batch_number'], $batch['batch_size'],
                $id, $batch['manufacture_date'], $batch['expiry_date'], Auth::id(),
            ]);
            $sampleId = (int)$sStmt->fetchColumn();
            $productTests = ProductTest::findByProduct($batch['product_id']);
            if (!empty($productTests)) {
                $ptStmt = $db->prepare("INSERT INTO sample_tests (sample_id, test_id, assigned_to, status) VALUES (?, ?, ?, 'Pending')");
                foreach ($productTests as $pt) {
                    $ptStmt->execute([$sampleId, $pt['test_id'], null]);
                }
            }
            $db->commit();
            Audit::log('Sample Added to Batch', 'samples', $sampleId, null, ['sample_code' => $sampleCode, 'batch_id' => $id]);
            session_flash('success', "Sample {$sampleCode} added to batch.");
        } catch (\Exception $e) {
            $db->rollBack();
            session_flash('error', 'Error adding sample: ' . $e->getMessage());
        }
        redirect('/batches/' . $id);
    }

    public function addTests(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $batch = Batch::find($id);
        if (!$batch) { session_flash('error', 'Batch not found.'); redirect('/batches'); }
        $testIds = $_POST['test_ids'] ?? [];
        if (empty($testIds)) { session_flash('error', 'No tests selected.'); redirect('/batches/' . $id); }
        $db = \App\Helpers\Database::connect();
        $db->beginTransaction();
        try {
            $samples = $db->prepare("SELECT id FROM samples WHERE batch_id = ?");
            $samples->execute([$id]);
            $sampleRows = $samples->fetchAll(\PDO::FETCH_ASSOC);
            $ptStmt = $db->prepare("INSERT INTO sample_tests (sample_id, test_id, assigned_to, status) VALUES (?, ?, ?, 'Pending')");
            $count = 0;
            foreach ($sampleRows as $s) {
                foreach ($testIds as $testId) {
                    $ptStmt->execute([$s['id'], $testId, null]);
                    $count++;
                }
            }
            $db->commit();
            Audit::log('Tests Added to Batch', 'batches', $id, null, ['test_count' => count($testIds)]);
            session_flash('success', "{$count} test assignments added across " . count($sampleRows) . " samples.");
        } catch (\Exception $e) {
            $db->rollBack();
            session_flash('error', 'Error adding tests: ' . $e->getMessage());
        }
        redirect('/batches/' . $id);
    }

    public function retest(int $sampleTestId): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();
        $stStmt = $db->prepare("SELECT st.*, s.batch_id FROM sample_tests st JOIN samples s ON st.sample_id = s.id WHERE st.id = ?");
        $stStmt->execute([$sampleTestId]);
        $st = $stStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$st) { session_flash('error', 'Sample test not found.'); redirect('/batches'); }
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM results WHERE sample_test_id = ?")->execute([$sampleTestId]);
            $db->prepare("UPDATE sample_tests SET status = 'Pending', completed_at = NULL, assigned_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$sampleTestId]);
            $db->commit();
            Audit::log('Test Sent for Retest', 'sample_tests', $sampleTestId);
            session_flash('success', 'Test reset to Pending for re-analysis.');
        } catch (\Exception $e) {
            $db->rollBack();
            session_flash('error', 'Error resetting test: ' . $e->getMessage());
        }
        redirect('/batches/' . ($st['batch_id'] ?? 0));
    }

    public function removeTest(int $sampleTestId): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();
        $stStmt = $db->prepare("SELECT st.*, s.batch_id FROM sample_tests st JOIN samples s ON st.sample_id = s.id WHERE st.id = ?");
        $stStmt->execute([$sampleTestId]);
        $st = $stStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$st) { session_flash('error', 'Sample test not found.'); redirect('/batches'); }
        $db->prepare("DELETE FROM sample_tests WHERE id = ?")->execute([$sampleTestId]);
        Audit::log('Test Removed from Batch', 'sample_tests', $sampleTestId);
        session_flash('success', 'Test removed from sample.');
        redirect('/batches/' . ($st['batch_id'] ?? 0));
    }
}
