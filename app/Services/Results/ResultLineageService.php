<?php

declare(strict_types=1);

namespace App\Services\Results;

use App\Helpers\Database;
use App\Helpers\Audit;
use App\Models\Result;
use App\Models\ResultLineage;
use PDO;

class ResultLineageService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Record end-to-end ALCOA+ data pedigree snapshot for a test result.
     */
    public function recordLineage(int $resultId, array $data = []): int
    {
        $rStmt = $this->db->prepare("
            SELECT r.*, st.sample_id, st.analysis_id, st.test_id, s.product_id
            FROM results r
            JOIN sample_tests st ON r.sample_test_id = st.id
            JOIN samples s ON st.sample_id = s.id
            WHERE r.id = ?
        ");
        $rStmt->execute([$resultId]);
        $res = $rStmt->fetch(PDO::FETCH_ASSOC);

        if (!$res) {
            throw new \RuntimeException('Result not found.');
        }

        $sampleTestId = (int)$res['sample_test_id'];
        $sampleId = (int)$res['sample_id'];

        // Raw instrument vault linkage
        $rawVaultId = !empty($data['raw_vault_id']) ? (int)$data['raw_vault_id'] : null;
        $rawFileHash = $data['raw_file_hash'] ?? null;
        $rawFileName = $data['raw_file_name'] ?? null;

        if ($rawVaultId && empty($rawFileHash)) {
            $vStmt = $this->db->prepare("SELECT sha256_hash, original_filename FROM instrument_raw_vault WHERE id = ?");
            $vStmt->execute([$rawVaultId]);
            $vRow = $vStmt->fetch(PDO::FETCH_ASSOC);
            if ($vRow) {
                $rawFileHash = $vRow['sha256_hash'];
                $rawFileName = $vRow['original_filename'];
            }
        }

        // Calculation formula
        $formulaId = !empty($data['formula_id']) ? (int)$data['formula_id'] : null;
        $formulaExpr = $data['formula_expression'] ?? null;

        // Spec version and limit snapshot
        $specVersionId = $res['spec_version_id'] ?? (!empty($data['spec_version_id']) ? (int)$data['spec_version_id'] : null);
        $specMin = $res['spec_min_snapshot'] ?? (!empty($data['spec_snapshot_min']) ? (float)$data['spec_snapshot_min'] : null);
        $specMax = $res['spec_max_snapshot'] ?? (!empty($data['spec_snapshot_max']) ? (float)$data['spec_snapshot_max'] : null);
        $unit = $res['unit_snapshot'] ?? ($data['unit_snapshot'] ?? null);

        // Lab batch
        $labBatchId = !empty($data['lab_batch_id']) ? (int)$data['lab_batch_id'] : null;
        if (!$labBatchId) {
            $bStmt = $this->db->prepare("SELECT lab_batch_id FROM lab_batch_items WHERE sample_test_id = ? LIMIT 1");
            $bStmt->execute([$sampleTestId]);
            $labBatchId = (int)$bStmt->fetchColumn() ?: null;
        }

        $instrumentId = !empty($data['instrument_id']) ? (int)$data['instrument_id'] : ($res['instrument_id'] ?? null);
        $analystId = !empty($data['analyst_id']) ? (int)$data['analyst_id'] : ($res['entered_by'] ?? null);
        $reviewerId = !empty($data['reviewer_id']) ? (int)$data['reviewer_id'] : ($res['reviewed_by'] ?? null);
        $approverId = !empty($data['approver_id']) ? (int)$data['approver_id'] : ($res['approved_by'] ?? null);

        // COA link if released
        $coaId = !empty($data['coa_id']) ? (int)$data['coa_id'] : null;
        if (!$coaId) {
            $coaStmt = $this->db->prepare("SELECT id FROM coa_documents WHERE sample_id = ? AND is_active = TRUE ORDER BY id DESC LIMIT 1");
            $coaStmt->execute([$sampleId]);
            $coaId = (int)$coaStmt->fetchColumn() ?: null;
        }

        $ins = $this->db->prepare("
            INSERT INTO result_lineage (
                result_id, sample_test_id, sample_id, raw_vault_id, raw_file_hash, raw_file_name,
                formula_id, formula_expression, spec_version_id, spec_snapshot_min, spec_snapshot_max,
                unit_snapshot, instrument_id, analyst_id, lab_batch_id, reviewer_id, approver_id,
                coa_id, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");

        $ins->execute([
            $resultId,
            $sampleTestId,
            $sampleId,
            $rawVaultId,
            $rawFileHash,
            $rawFileName,
            $formulaId,
            $formulaExpr,
            $specVersionId,
            $specMin,
            $specMax,
            $unit,
            $instrumentId,
            $analystId,
            $labBatchId,
            $reviewerId,
            $approverId,
            $coaId,
            $data['notes'] ?? 'Auto-captured GxP ALCOA+ Result Lineage'
        ]);

        return (int)$ins->fetchColumn();
    }

    /**
     * Retrieve complete lineage history for a result.
     */
    public function getLineage(int $resultId): ?array
    {
        return ResultLineage::findByResult($resultId);
    }

    /**
     * Invalidate a result with mandatory justification under 21 CFR Part 11.
     * No physical delete occurs. The result is flagged, reason recorded, and audited.
     */
    public function invalidateResult(int $resultId, string $reason, int $userId): bool
    {
        $reason = trim($reason);
        if (empty($reason)) {
            throw new \InvalidArgumentException('A mandatory justification reason is required to invalidate a result.');
        }

        $rStmt = $this->db->prepare("SELECT * FROM results WHERE id = ?");
        $rStmt->execute([$resultId]);
        $result = $rStmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new \RuntimeException('Result not found.');
        }

        if (!empty($result['approved_at'])) {
            // In commercial LIMS, changing an approved result requires authorized invalidation + retest
            Audit::log('Approved Result Invalidation Requested', 'results', $resultId, null, [
                'reason' => $reason,
                'invalidated_by' => $userId
            ]);
        }

        $this->db->beginTransaction();
        try {
            $upd = $this->db->prepare("
                UPDATE results
                SET is_invalidated = TRUE,
                    invalidation_reason = ?,
                    invalidated_by = ?,
                    invalidated_at = CURRENT_TIMESTAMP,
                    remarks = CONCAT(COALESCE(remarks, ''), ' [INVALIDATED: ', ?::text, ']'),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $upd->execute([$reason, $userId, $reason, $resultId]);

            // Requeue the sample test for re-execution
            $this->db->prepare("
                UPDATE sample_tests
                SET status = 'Pending',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$result['sample_test_id']]);

            $this->db->commit();

            Audit::log('Result Invalidated', 'results', $resultId, [
                'previous_value' => $result['result_value'],
                'previous_status' => 'Active'
            ], [
                'is_invalidated' => true,
                'invalidation_reason' => $reason,
                'invalidated_by' => $userId
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Authorized Retest Workflow:
     * Creates an official retest record linked back to the original test and result.
     */
    public function createRetest(int $sampleTestId, string $retestReason, int $userId): int
    {
        $retestReason = trim($retestReason);
        if (empty($retestReason)) {
            throw new \InvalidArgumentException('A mandatory justification reason is required to authorize a retest.');
        }

        $stStmt = $this->db->prepare("SELECT * FROM sample_tests WHERE id = ?");
        $stStmt->execute([$sampleTestId]);
        $orig = $stStmt->fetch(PDO::FETCH_ASSOC);

        if (!$orig) {
            throw new \RuntimeException('Original sample test not found.');
        }

        $this->db->beginTransaction();
        try {
            // Increment retest count on original
            $this->db->prepare("UPDATE sample_tests SET retest_count = retest_count + 1 WHERE id = ?")->execute([$sampleTestId]);

            // Insert new retest test item
            $ins = $this->db->prepare("
                INSERT INTO sample_tests (
                    sample_id, test_id, analysis_id, spec_version_id, is_mandatory, status,
                    is_retest, retest_of_test_id, added_reason, added_by
                ) VALUES (?, ?, ?, ?, ?, 'Pending', TRUE, ?, ?, ?)
                RETURNING id
            ");
            $ins->execute([
                $orig['sample_id'],
                $orig['test_id'],
                $orig['analysis_id'],
                $orig['spec_version_id'],
                $orig['is_mandatory'],
                $sampleTestId,
                $retestReason,
                $userId
            ]);

            $newTestId = (int)$ins->fetchColumn();

            $this->db->commit();

            Audit::log('Authorized Retest Created', 'sample_tests', $newTestId, null, [
                'original_test_id' => $sampleTestId,
                'sample_id' => $orig['sample_id'],
                'retest_reason' => $retestReason,
                'authorized_by' => $userId
            ]);

            return $newTestId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
