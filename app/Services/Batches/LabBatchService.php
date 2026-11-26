<?php

declare(strict_types=1);

namespace App\Services\Batches;

use App\Helpers\Database;
use App\Helpers\Audit;
use App\Models\LabBatch;
use App\Models\LabBatchItem;
use App\Services\Drivers\CdsCsvDriver;
use PDO;

class LabBatchService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function createLabBatch(array $data, int $userId): array
    {
        $batchCode = trim($data['batch_code'] ?? '');
        if (empty($batchCode)) {
            $batchCode = 'LB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }

        $analysisId = !empty($data['analysis_id']) ? (int)$data['analysis_id'] : null;
        $instrumentId = !empty($data['instrument_id']) ? (int)$data['instrument_id'] : null;
        $analystId = !empty($data['analyst_id']) ? (int)$data['analyst_id'] : $userId;
        $methodId = !empty($data['method_id']) ? (int)$data['method_id'] : null;
        $runDate = $data['run_date'] ?? date('Y-m-d');
        $format = $data['worklist_format'] ?? 'empower_csv';

        $ins = $this->db->prepare("
            INSERT INTO lab_batches (
                batch_code, analysis_id, instrument_id, analyst_id, method_id,
                run_date, status, qc_status, worklist_format, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, 'Draft', 'Pending', ?, ?)
            RETURNING id
        ");

        $ins->execute([
            $batchCode,
            $analysisId,
            $instrumentId,
            $analystId,
            $methodId,
            $runDate,
            $format,
            $userId
        ]);

        $batchId = (int)$ins->fetchColumn();

        Audit::log('Lab Analytical Batch Created', 'lab_batches', $batchId, null, [
            'batch_code' => $batchCode,
            'analysis_id' => $analysisId,
            'instrument_id' => $instrumentId,
            'analyst_id' => $analystId
        ]);

        return [
            'batch_id' => $batchId,
            'batch_code' => $batchCode,
            'status' => 'Draft',
            'qc_status' => 'Pending'
        ];
    }

    /**
     * Add ordered controls and specimen samples to the analytical run sequence.
     */
    public function addSequenceItems(int $batchId, array $items): int
    {
        $added = 0;
        $this->db->beginTransaction();
        try {
            // Get current max sequence index
            $seqStmt = $this->db->prepare("SELECT COALESCE(MAX(sequence_index), 0) FROM lab_batch_items WHERE lab_batch_id = ?");
            $seqStmt->execute([$batchId]);
            $seqIndex = (int)$seqStmt->fetchColumn();

            $ins = $this->db->prepare("
                INSERT INTO lab_batch_items (
                    lab_batch_id, sequence_index, item_type, sample_id, sample_test_id,
                    control_name, vial_position, injection_volume_ul, expected_value,
                    tolerance_min, tolerance_max, actual_value, qc_pass
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $seqIndex++;
                $itemType = trim($item['item_type'] ?? 'sample');
                $sampleId = !empty($item['sample_id']) ? (int)$item['sample_id'] : null;
                $sampleTestId = !empty($item['sample_test_id']) ? (int)$item['sample_test_id'] : null;
                $controlName = $item['control_name'] ?? ($itemType === 'sample' ? null : ucfirst($itemType));
                $vial = $item['vial_position'] ?? (string)$seqIndex;
                $injVol = isset($item['injection_volume_ul']) ? (float)$item['injection_volume_ul'] : 10.0;
                $expVal = isset($item['expected_value']) ? (float)$item['expected_value'] : null;
                $tolMin = isset($item['tolerance_min']) ? (float)$item['tolerance_min'] : null;
                $tolMax = isset($item['tolerance_max']) ? (float)$item['tolerance_max'] : null;
                $actVal = isset($item['actual_value']) ? (float)$item['actual_value'] : null;
                $qcPass = isset($item['qc_pass']) ? (bool)$item['qc_pass'] : null;

                $ins->execute([
                    $batchId,
                    $seqIndex,
                    $itemType,
                    $sampleId,
                    $sampleTestId,
                    $controlName,
                    $vial,
                    $injVol,
                    $expVal,
                    $tolMin,
                    $tolMax,
                    $actVal,
                    $qcPass
                ]);
                $added++;
            }

            // Update batch status to Prepared if was Draft
            $this->db->prepare("UPDATE lab_batches SET status = 'Prepared', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'Draft'")
                     ->execute([$batchId]);

            $this->db->commit();
            return $added;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Compute Batch QC Status from Sequence Controls (Blank, Standard, QC Control).
     *
     * COMMERCIAL LIMS RULE:
     * A failed batch QC flags and invalidates every result in the batch and requeues
     * the tests with a clear mandatory reason and ALCOA+ audit log.
     */
    public function evaluateBatchQc(int $batchId, int $userId): array
    {
        $batch = LabBatch::findWithRelations($batchId);
        if (!$batch) {
            throw new \RuntimeException('Lab analytical batch not found.');
        }

        $items = $batch['items'] ?? [];
        if (empty($items)) {
            throw new \RuntimeException('Cannot evaluate QC on an empty lab batch.');
        }

        $failedControls = [];
        $passedControls = [];
        $evaluatedControlsCount = 0;

        foreach ($items as $item) {
            if ($item['item_type'] === 'sample') {
                continue;
            }

            $evaluatedControlsCount++;
            $actual = isset($item['actual_value']) ? (float)$item['actual_value'] : null;
            $tolMin = isset($item['tolerance_min']) ? (float)$item['tolerance_min'] : null;
            $tolMax = isset($item['tolerance_max']) ? (float)$item['tolerance_max'] : null;
            $type = $item['item_type'];
            $name = $item['control_name'] ?? $type;

            if ($actual === null) {
                $failedControls[] = "Control '{$name}' (#{$item['sequence_index']}) has no recorded actual reading.";
                continue;
            }

            $isPass = true;
            $reason = '';

            if ($type === 'blank') {
                // Blank check: must be below max tolerance / LOQ threshold
                if ($tolMax !== null && $actual > $tolMax) {
                    $isPass = false;
                    $reason = "Blank reading ({$actual}) exceeded maximum baseline limit ({$tolMax}).";
                }
            } else {
                // Calibration standard or QC control tolerance check
                if ($tolMin !== null && $actual < $tolMin) {
                    $isPass = false;
                    $reason = "Control '{$name}' ({$actual}) below minimum limit ({$tolMin}).";
                }
                if ($tolMax !== null && $actual > $tolMax) {
                    $isPass = false;
                    $reason = "Control '{$name}' ({$actual}) above maximum limit ({$tolMax}).";
                }
            }

            // Update item qc_pass in DB
            $this->db->prepare("UPDATE lab_batch_items SET qc_pass = ?, invalidation_reason = ? WHERE id = ?")
                     ->execute([$isPass ? 't' : 'f', $isPass ? null : $reason, $item['id']]);

            if ($isPass) {
                $passedControls[] = "Control '{$name}': PASS ({$actual})";
            } else {
                $failedControls[] = $reason;
            }
        }

        $isBatchQcPassed = empty($failedControls) && $evaluatedControlsCount > 0;
        $batchQcStatus = $isBatchQcPassed ? 'Passed' : 'Failed';
        $batchStatus = $isBatchQcPassed ? 'QC_Passed' : 'QC_Failed';
        $qcNotes = $isBatchQcPassed
            ? "Batch QC Passed: {$evaluatedControlsCount} control(s) verified within statistical tolerances."
            : "Batch QC Failed: " . implode('; ', $failedControls);

        $this->db->beginTransaction();
        try {
            // Update Lab Batch Record
            $updBatch = $this->db->prepare("
                UPDATE lab_batches
                SET status = ?,
                    qc_status = ?,
                    qc_evaluation_notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updBatch->execute([$batchStatus, $batchQcStatus, $qcNotes, $batchId]);

            $requeuedTestsCount = 0;

            // IF QC FAILED: Invalidate all sample results in this run and requeue tests
            if (!$isBatchQcPassed) {
                foreach ($items as $item) {
                    if ($item['item_type'] === 'sample' && !empty($item['sample_test_id'])) {
                        $testId = (int)$item['sample_test_id'];
                        $invalReason = "Analytical Run #{$batch['batch_code']} QC Failed: " . implode('; ', $failedControls);

                        // 1. Mark existing result as invalidated/flagged with reason
                        $invalResult = $this->db->prepare("
                            UPDATE results
                            SET is_within_spec = FALSE,
                                remarks = CONCAT(COALESCE(remarks, ''), ' [INVALIDATED: ', ?::text, ']'),
                                updated_at = CURRENT_TIMESTAMP
                            WHERE sample_test_id = ? AND deleted_at IS NULL
                        ");
                        $invalResult->execute([$invalReason, $testId]);

                        // 2. Requeue sample_tests for re-analysis
                        $requeue = $this->db->prepare("
                            UPDATE sample_tests
                            SET status = 'Pending',
                                updated_at = CURRENT_TIMESTAMP
                            WHERE id = ? AND deleted_at IS NULL
                        ");
                        $requeue->execute([$testId]);

                        $requeuedTestsCount++;

                        Audit::log('Test Requeued Due to Batch QC Failure', 'sample_tests', $testId, null, [
                            'lab_batch_id' => $batchId,
                            'batch_code' => $batch['batch_code'],
                            'reason' => $invalReason
                        ]);
                    }
                }
            }

            $this->db->commit();

            Audit::log('Lab Batch QC Evaluated', 'lab_batches', $batchId, null, [
                'batch_code' => $batch['batch_code'],
                'qc_status' => $batchQcStatus,
                'controls_evaluated' => $evaluatedControlsCount,
                'failed_controls' => count($failedControls),
                'requeued_tests' => $requeuedTestsCount,
                'evaluation_notes' => $qcNotes
            ]);

            return [
                'success' => true,
                'batch_id' => $batchId,
                'batch_code' => $batch['batch_code'],
                'qc_status' => $batchQcStatus,
                'is_passed' => $isBatchQcPassed,
                'evaluated_controls' => $evaluatedControlsCount,
                'failed_controls' => $failedControls,
                'requeued_tests_count' => $requeuedTestsCount,
                'notes' => $qcNotes
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Export instrument sequence worklist in CDS Format (Waters Empower, Thermo Chromeleon, Agilent OpenLab).
     */
    public function generateInstrumentWorklist(int $batchId, string $format = 'empower_csv'): string
    {
        $batch = LabBatch::findWithRelations($batchId);
        if (!$batch) {
            throw new \RuntimeException('Lab analytical batch not found.');
        }

        $items = $batch['items'] ?? [];
        $sequenceEntries = [];

        foreach ($items as $item) {
            $sequenceEntries[] = [
                'position' => $item['vial_position'] ?? (string)$item['sequence_index'],
                'sample_code' => $item['item_type'] === 'sample' ? ($item['sample_code'] ?? "Sample_{$item['sequence_index']}") : ($item['control_name'] ?? $item['item_type']),
                'sample_type' => ucfirst($item['item_type']),
                'method' => $batch['method_name'] ?? $batch['analysis_code'] ?? 'Assay_Method',
                'injection_volume_ul' => (float)($item['injection_volume_ul'] ?? 10.0)
            ];
        }

        $driver = new CdsCsvDriver();
        $content = $driver->generateWorklist($sequenceEntries);

        // Cache exported content on the batch record
        $this->db->prepare("UPDATE lab_batches SET exported_worklist_content = ?, worklist_format = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                 ->execute([$content, $format, $batchId]);

        return $content;
    }
}
