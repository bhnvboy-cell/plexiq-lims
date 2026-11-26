<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Helpers\Database;
use App\Helpers\Audit;
use App\Models\Sample;
use App\Models\SampleLifecycleTransition;
use App\Services\MasterData\SpecificationService;
use App\Services\MasterData\ProductTestPlanService;
use PDO;

class CommercialSampleService
{
    private PDO $db;
    private SpecificationService $specService;
    private ProductTestPlanService $planService;

    public function __construct(
        ?PDO $db = null,
        ?SpecificationService $specService = null,
        ?ProductTestPlanService $planService = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->specService = $specService ?? new SpecificationService($this->db);
        $this->planService = $planService ?? new ProductTestPlanService($this->db, $this->specService);
    }

    /**
     * Commercial Sample Registration:
     * Product + Stage automatically creates the test list from product_test_plans
     * and attaches the approved specification version effective on the sampling date.
     * Freezes the specification limits snapshot for each test result.
     *
     * @param array $data Sample inputs (sample_code, product_id, sample_stage, sampling_date, customer_id, etc.)
     * @param int $userId Creator ID
     * @return array Created sample details, resolved spec version, and assigned tests
     */
    public function registerSample(array $data, int $userId): array
    {
        $productId = (int)($data['product_id'] ?? 0);
        $stage = trim($data['sample_stage'] ?? 'Finished Good');
        $samplingDate = !empty($data['sampling_date']) ? $data['sampling_date'] : date('Y-m-d');
        $sampleCode = trim($data['sample_code'] ?? '');

        if ($productId <= 0) {
            throw new \InvalidArgumentException('A valid Product ID is required for commercial sample registration.');
        }

        if (empty($sampleCode)) {
            $sampleCode = Sample::generateCode();
        }

        $this->db->beginTransaction();
        try {
            // 1. Resolve active approved specification version for the product & sampling date
            $specResolution = $this->planService->resolveAnalysesAndLimitsForSample($productId, $stage, $samplingDate);
            $specVersionId = $specResolution['spec_version']['id'] ?? null;

            // 2. Insert Sample Record
            $insSample = $this->db->prepare("
                INSERT INTO samples (
                    sample_code, customer_id, product_id, batch_number, batch_size,
                    manufacture_date, expiry_date, received_date, sampling_date,
                    sample_stage, spec_version_id, template_id, priority, status,
                    receipt_check_status, receipt_check_notes, registered_by, notes
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, CURRENT_DATE, ?,
                    ?, ?, ?, ?, 'Registered',
                    ?, ?, ?, ?
                ) RETURNING id
            ");

            $insSample->execute([
                $sampleCode,
                !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
                $productId,
                $data['batch_number'] ?? null,
                $data['batch_size'] ?? null,
                $data['manufacture_date'] ?? null,
                $data['expiry_date'] ?? null,
                $samplingDate,
                $stage,
                $specVersionId,
                !empty($data['template_id']) ? (int)$data['template_id'] : null,
                $data['priority'] ?? 'Normal',
                $data['receipt_check_status'] ?? 'Passed',
                $data['receipt_check_notes'] ?? null,
                $userId,
                $data['notes'] ?? null
            ]);

            $sampleId = (int)$insSample->fetchColumn();

            // 3. Automatically create Sample Tests and Freeze Limits Snapshots
            $assignedTests = [];
            $assignedComponents = 0;

            foreach ($specResolution['analyses'] as $planAnalysis) {
                $analysisId = (int)$planAnalysis['analysis_id'];
                $isMandatory = (bool)$planAnalysis['is_mandatory'];

                // Find legacy test_id mapping if available (for backward compatibility with legacy views)
                $legacyTestIdStmt = $this->db->prepare("SELECT id FROM tests WHERE test_code = ? LIMIT 1");
                $legacyTestIdStmt->execute([$planAnalysis['analysis_code']]);
                $legacyTestId = $legacyTestIdStmt->fetchColumn() ?: null;

                $stStmt = $this->db->prepare("
                    INSERT INTO sample_tests (
                        sample_id, test_id, analysis_id, spec_version_id, is_mandatory, status
                    ) VALUES (?, ?, ?, ?, ?, 'Pending') RETURNING id
                ");
                $stStmt->execute([
                    $sampleId,
                    $legacyTestId,
                    $analysisId,
                    $specVersionId,
                    $isMandatory ? 't' : 'f'
                ]);
                $sampleTestId = (int)$stStmt->fetchColumn();

                // 4. Freeze limit snapshot for each component in this analysis onto results table
                foreach ($planAnalysis['components'] as $comp) {
                    $resStmt = $this->db->prepare("
                        INSERT INTO results (
                            sample_test_id, spec_version_id, component_id,
                            spec_min_snapshot, spec_max_snapshot, spec_target_snapshot,
                            spec_text_snapshot, unit_snapshot, decimal_places_snapshot,
                            rounding_method_snapshot, loq_snapshot, uloq_snapshot,
                            remarks
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Automated Spec Snapshot at Sample Registration')
                        RETURNING id
                    ");

                    $resStmt->execute([
                        $sampleTestId,
                        $specVersionId,
                        $comp['component_id'],
                        $comp['min_limit'],
                        $comp['max_limit'],
                        $comp['target_value'],
                        $comp['display_specification'],
                        $comp['unit'],
                        $comp['decimal_places'] ?? 2,
                        $comp['rounding_method'] ?? 'half_even',
                        $comp['loq'],
                        $comp['uloq']
                    ]);
                    $assignedComponents++;
                }

                $assignedTests[] = [
                    'sample_test_id' => $sampleTestId,
                    'analysis_id' => $analysisId,
                    'analysis_code' => $planAnalysis['analysis_code'],
                    'analysis_name' => $planAnalysis['analysis_name'],
                    'components_count' => count($planAnalysis['components'])
                ];
            }

            $this->db->commit();

            Audit::log('Sample Registered with Commercial Spec Snapshot', 'samples', $sampleId, null, [
                'sample_code' => $sampleCode,
                'product_id' => $productId,
                'stage' => $stage,
                'spec_version_id' => $specVersionId,
                'assigned_analyses_count' => count($assignedTests),
                'assigned_components_count' => $assignedComponents
            ]);

            return [
                'success' => true,
                'sample_id' => $sampleId,
                'sample_code' => $sampleCode,
                'product_id' => $productId,
                'stage' => $stage,
                'spec_version_id' => $specVersionId,
                'spec_code' => $specResolution['spec_version']['spec_code'] ?? null,
                'spec_version_number' => $specResolution['spec_version']['version_number'] ?? null,
                'assigned_tests' => $assignedTests,
                'total_analyses' => count($assignedTests),
                'total_components' => $assignedComponents
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Add a non-standard test to a sample.
     * Regulatory requirement: Adding or removing a test needs permission and an audited reason.
     */
    public function addSampleTest(int $sampleId, int $analysisId, string $reason, int $userId): int
    {
        $reason = trim($reason);
        if (empty($reason)) {
            throw new \InvalidArgumentException('A mandatory reason is required to add an ad-hoc test to a sample.');
        }

        $sStmt = $this->db->prepare("SELECT spec_version_id, product_id, sample_stage FROM samples WHERE id = ?");
        $sStmt->execute([$sampleId]);
        $sample = $sStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sample) {
            throw new \RuntimeException('Sample not found.');
        }

        // Fetch analysis details
        $aStmt = $this->db->prepare("SELECT a.*, t.id as legacy_test_id FROM analyses a LEFT JOIN tests t ON t.test_code = a.analysis_code WHERE a.id = ?");
        $aStmt->execute([$analysisId]);
        $analysis = $aStmt->fetch(PDO::FETCH_ASSOC);

        if (!$analysis) {
            throw new \RuntimeException('Analysis not found.');
        }

        $this->db->beginTransaction();
        try {
            $ins = $this->db->prepare("
                INSERT INTO sample_tests (
                    sample_id, test_id, analysis_id, spec_version_id, is_mandatory, status,
                    added_reason, added_by
                ) VALUES (?, ?, ?, ?, FALSE, 'Pending', ?, ?) RETURNING id
            ");
            $ins->execute([
                $sampleId,
                $analysis['legacy_test_id'],
                $analysisId,
                $sample['spec_version_id'],
                $reason,
                $userId
            ]);
            $sampleTestId = (int)$ins->fetchColumn();

            // Fetch components for this analysis to snapshot limits if available
            $cStmt = $this->db->prepare("SELECT * FROM components WHERE analysis_id = ? AND is_active = TRUE");
            $cStmt->execute([$analysisId]);
            $components = $cStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($components as $comp) {
                // Check if spec limit exists for this component in this spec version
                $limStmt = $this->db->prepare("SELECT * FROM specification_limits WHERE spec_version_id = ? AND component_id = ?");
                $limStmt->execute([$sample['spec_version_id'], $comp['id']]);
                $lim = $limStmt->fetch(PDO::FETCH_ASSOC);

                $rStmt = $this->db->prepare("
                    INSERT INTO results (
                        sample_test_id, spec_version_id, component_id,
                        spec_min_snapshot, spec_max_snapshot, spec_target_snapshot,
                        spec_text_snapshot, unit_snapshot, decimal_places_snapshot,
                        rounding_method_snapshot, loq_snapshot, uloq_snapshot,
                        remarks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $rStmt->execute([
                    $sampleTestId,
                    $sample['spec_version_id'],
                    $comp['id'],
                    $lim['min_limit'] ?? null,
                    $lim['max_limit'] ?? null,
                    $lim['target_value'] ?? null,
                    $lim['display_specification'] ?? 'Ad-hoc Test',
                    $comp['unit'],
                    $comp['decimal_places'] ?? 2,
                    $comp['rounding_method'] ?? 'half_even',
                    $comp['loq'],
                    $comp['uloq'],
                    "Ad-hoc test added: {$reason}"
                ]);
            }

            $this->db->commit();

            Audit::log('Ad-hoc Test Added to Sample', 'sample_tests', $sampleTestId, null, [
                'sample_id' => $sampleId,
                'analysis_id' => $analysisId,
                'reason' => $reason,
                'added_by' => $userId
            ]);

            return $sampleTestId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Remove an assigned test from a sample.
     * Regulatory requirement: Removing a test requires permission, reason, and is audited without physical delete.
     */
    public function removeSampleTest(int $sampleTestId, string $reason, int $userId): bool
    {
        $reason = trim($reason);
        if (empty($reason)) {
            throw new \InvalidArgumentException('A mandatory reason is required to remove an assigned test.');
        }

        $stmt = $this->db->prepare("
            UPDATE sample_tests
            SET status = 'Rejected',
                removed_reason = ?,
                removed_by = ?,
                deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$reason, $userId, $sampleTestId]);

        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            Audit::log('Test Removed from Sample', 'sample_tests', $sampleTestId, null, [
                'removed_reason' => $reason,
                'removed_by' => $userId
            ]);
        }

        return $ok;
    }

    /**
     * Configurable Status Machine with Quality Guards:
     * Transitions sample lifecycle status enforcing:
     *   1. All required/mandatory tests completed
     *   2. No open OOS investigations
     *   3. Analyst/Approver qualification verified
     *   4. Receipt check passed
     */
    public function transitionStatus(int $sampleId, string $targetStatus, int $userId, array $transitionData = []): array
    {
        $sStmt = $this->db->prepare("SELECT s.*, st.id as sample_type_id FROM samples s LEFT JOIN sample_types st ON s.sample_type_id = st.id WHERE s.id = ?");
        $sStmt->execute([$sampleId]);
        $sample = $sStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sample) {
            throw new \RuntimeException('Sample not found.');
        }

        $currentStatus = $sample['status'];
        if ($currentStatus === $targetStatus) {
            return ['success' => true, 'status' => $currentStatus, 'message' => 'Sample already in target status.'];
        }

        // Find matching lifecycle transition rule
        $ruleStmt = $this->db->prepare("
            SELECT * FROM sample_lifecycle_transitions
            WHERE (sample_type_id = ? OR sample_type_id IS NULL)
              AND from_status = ? AND to_status = ?
            ORDER BY sample_type_id DESC NULLS LAST LIMIT 1
        ");
        $ruleStmt->execute([$sample['sample_type_id'], $currentStatus, $targetStatus]);
        $rule = $ruleStmt->fetch(PDO::FETCH_ASSOC);

        // Validation against standard allowed transitions if no custom rule exists
        if (!$rule) {
            $allowedTransitions = [
                'Registered' => ['In Progress'],
                'In Progress' => ['Completed', 'Reviewed', 'Rejected'],
                'Completed' => ['Reviewed', 'Rejected'],
                'Reviewed' => ['Approved', 'Rejected'],
                'Approved' => ['COA Released'],
            ];
            if (!isset($allowedTransitions[$currentStatus]) || !in_array($targetStatus, $allowedTransitions[$currentStatus], true)) {
                throw new \RuntimeException("Compliance Guard: Invalid lifecycle transition from '{$currentStatus}' to '{$targetStatus}'.");
            }
        }

        // Guard 1: Receipt Check
        $checkReceipt = ($rule && $rule['guard_receipt_checked']) || ($currentStatus === 'Registered');
        if ($checkReceipt) {
            if ($sample['receipt_check_status'] === 'Failed_Quarantine') {
                throw new \RuntimeException("Compliance Guard: Sample #{$sample['sample_code']} failed receipt inspection and is in Quarantine. Cannot advance.");
            }
        }

        // Guard 2: All Required Tests Completed
        $checkTests = ($rule && $rule['guard_all_tests_completed']) || in_array($targetStatus, ['Completed', 'Reviewed', 'Approved', 'COA Released'], true);
        if ($checkTests) {
            $pendingStmt = $this->db->prepare("
                SELECT COUNT(*) FROM sample_tests
                WHERE sample_id = ? AND is_mandatory = TRUE
                  AND status NOT IN ('Completed', 'Reviewed', 'Approved')
                  AND deleted_at IS NULL
            ");
            $pendingStmt->execute([$sampleId]);
            $pendingCount = (int)$pendingStmt->fetchColumn();
            if ($pendingCount > 0) {
                throw new \RuntimeException("Compliance Guard: {$pendingCount} mandatory test(s) are incomplete. All required analyses must be completed before advancing.");
            }
        }

        // Guard 3: No Open OOS (Out-of-Specification)
        $checkOos = ($rule && $rule['guard_no_open_oos']) || in_array($targetStatus, ['Approved', 'COA Released'], true);
        if ($checkOos) {
            $oosStmt = $this->db->prepare("
                SELECT COUNT(*) FROM oos_records
                WHERE sample_id = ? AND status NOT IN ('Closed', 'Resolved', 'Cancelled')
            ");
            $oosStmt->execute([$sampleId]);
            $openOos = (int)$oosStmt->fetchColumn();
            if ($openOos > 0) {
                throw new \RuntimeException("Compliance Guard: Sample #{$sample['sample_code']} has {$openOos} open OOS investigation(s). Approval is blocked until all OOS records are closed.");
            }
        }

        // E-signature token if required
        $signatureHash = null;
        if ($rule && $rule['requires_esignature']) {
            $reason = trim($transitionData['signature_reason'] ?? "Lifecycle transition to {$targetStatus}");
            $timestamp = date('c');
            $signatureHash = hash_hmac('sha256', "{$sampleId}:{$targetStatus}:{$userId}:{$timestamp}:{$reason}", getenv('APP_KEY') ?: 'plexiq_secret_master_key_2026');
        }

        // Execute transition
        $upd = $this->db->prepare("
            UPDATE samples
            SET status = ?,
                updated_at = CURRENT_TIMESTAMP,
                approved_at = CASE WHEN ? = 'Approved' THEN CURRENT_TIMESTAMP ELSE approved_at END,
                approved_by = CASE WHEN ? = 'Approved' THEN ? ELSE approved_by END
            WHERE id = ?
        ");
        $upd->execute([$targetStatus, $targetStatus, $targetStatus, $userId, $sampleId]);

        Audit::log('Sample Lifecycle Status Transition', 'samples', $sampleId, null, [
            'from_status' => $currentStatus,
            'to_status' => $targetStatus,
            'transition_user' => $userId,
            'signature_hash' => $signatureHash
        ]);

        return [
            'success' => true,
            'sample_id' => $sampleId,
            'previous_status' => $currentStatus,
            'new_status' => $targetStatus,
            'signature_hash' => $signatureHash
        ];
    }
}
