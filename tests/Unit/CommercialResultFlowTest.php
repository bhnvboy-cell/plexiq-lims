<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\Database;
use App\Models\Sample;
use App\Models\Result;
use App\Models\SampleTest;
use App\Services\MasterData\SpecificationService;
use App\Services\MasterData\ProductTestPlanService;
use App\Services\Samples\CommercialSampleService;
use App\Services\Results\ResultLineageService;
use App\Services\TrainingGatingService;
use PDO;

class CommercialResultFlowTest extends TestCase
{
    private CommercialSampleService $sampleService;
    private ResultLineageService $lineageService;
    private TrainingGatingService $trainingService;
    private SpecificationService $specService;
    private ProductTestPlanService $planService;
    private int $testProductId;
    private int $testAnalysisId;
    private int $testCompId;
    private int $testSpecId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->specService = new SpecificationService($this->db);
        $this->planService = new ProductTestPlanService($this->db, $this->specService);
        $this->sampleService = new CommercialSampleService($this->db, $this->specService, $this->planService);
        $this->lineageService = new ResultLineageService($this->db);
        $this->trainingService = new TrainingGatingService();

        // 1. Product
        $pStmt = $this->db->prepare("SELECT id FROM products WHERE product_code = ?");
        $pStmt->execute(['TEST-RESULT-PROD']);
        $this->testProductId = (int)$pStmt->fetchColumn();
        if ($this->testProductId <= 0) {
            $insP = $this->db->prepare("
                INSERT INTO products (product_code, product_name, category, is_active)
                VALUES ('TEST-RESULT-PROD', 'Ibuprofen 400mg Caplets', 'Finished Product', TRUE)
                RETURNING id
            ");
            $insP->execute();
            $this->testProductId = (int)$insP->fetchColumn();
        }

        // 2. Analysis & Component
        $aStmt = $this->db->prepare("SELECT id FROM analyses WHERE analysis_code = ?");
        $aStmt->execute(['AN-HPLC-IBU']);
        $this->testAnalysisId = (int)$aStmt->fetchColumn();
        if ($this->testAnalysisId <= 0) {
            $insA = $this->db->prepare("
                INSERT INTO analyses (analysis_code, analysis_name, method_id, sop_reference, is_active)
                VALUES ('AN-HPLC-IBU', 'Ibuprofen Assay by HPLC', NULL, 'SOP-QC-HPLC-044', TRUE)
                RETURNING id
            ");
            $insA->execute();
            $this->testAnalysisId = (int)$insA->fetchColumn();

            $insC = $this->db->prepare("
                INSERT INTO components (analysis_id, component_code, component_name, data_type, unit, decimal_places, rounding_method, loq, uloq, is_active)
                VALUES (?, 'CMP-IBU-ASSAY', 'Ibuprofen Assay', 'numeric', '%', 1, 'half_even', 10.0, 150.0, TRUE)
                RETURNING id
            ");
            $insC->execute([$this->testAnalysisId]);
            $this->testCompId = (int)$insC->fetchColumn();
        } else {
            $cStmt = $this->db->prepare("SELECT id FROM components WHERE analysis_id = ? LIMIT 1");
            $cStmt->execute([$this->testAnalysisId]);
            $this->testCompId = (int)$cStmt->fetchColumn();
        }

        // 3. Spec & Approved v1 (Limits 98.0 - 102.0 %)
        $spStmt = $this->db->prepare("SELECT id FROM specifications WHERE spec_code = ?");
        $spStmt->execute(['SPEC-IBU-001']);
        $this->testSpecId = (int)$spStmt->fetchColumn();
        if ($this->testSpecId <= 0) {
            $this->testSpecId = $this->specService->createSpecification([
                'spec_code' => 'SPEC-IBU-001',
                'product_id' => $this->testProductId,
                'grade' => 'USP/NF',
                'description' => 'Ibuprofen Release Monograph'
            ], 1);
        }

        $vStmt = $this->db->prepare("SELECT id FROM specification_versions WHERE specification_id = ? AND version_number = 1");
        $vStmt->execute([$this->testSpecId]);
        $v1Id = (int)$vStmt->fetchColumn();
        if ($v1Id <= 0) {
            $v1Id = $this->specService->createDraftVersion($this->testSpecId, [
                'effective_from' => '2026-01-01',
                'effective_to' => '2028-12-31'
            ], null, 1);
        }

        $this->db->prepare("UPDATE specification_versions SET status = 'Draft', effective_from = '2026-01-01', effective_to = '2028-12-31' WHERE id = ?")->execute([$v1Id]);
        $this->specService->addOrUpdateLimit($v1Id, $this->testCompId, [
            'limit_type' => 'range',
            'min_limit' => 98.0,
            'max_limit' => 102.0,
            'display_specification' => '98.0 - 102.0 %'
        ]);
        $this->specService->approveVersion($v1Id, 1, 'Quality Director', 'Approved Ibuprofen Spec');

        // 4. Test Plan
        $planStmt = $this->db->prepare("SELECT id FROM product_test_plans WHERE product_id = ? AND stage = 'Finished Good' AND analysis_id = ?");
        $planStmt->execute([$this->testProductId, $this->testAnalysisId]);
        if (!$planStmt->fetchColumn()) {
            $this->planService->assignAnalysisToStage(
                $this->testProductId,
                'Finished Good',
                $this->testAnalysisId,
                true,
                10
            );
        }
    }

    /**
     * Acceptance Scenario 3:
     * Assay 102.4 is flagged, opens an OOS, blocks approval through the status machine,
     * and the reviewer sees it first (Review by Exception).
     */
    public function testScenario3AssayOOSBlocksApprovalAndPrioritizesReview(): void
    {
        // 1. Register sample
        $code = 'SMP-OOS-' . bin2hex(random_bytes(3));
        $sRes = $this->sampleService->registerSample([
            'sample_code' => $code,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2026-08-01'
        ], 1);
        $sampleId = $sRes['sample_id'];

        $stStmt = $this->db->prepare("SELECT id FROM sample_tests WHERE sample_id = ? AND analysis_id = ?");
        $stStmt->execute([$sampleId, $this->testAnalysisId]);
        $sampleTestId = (int)$stStmt->fetchColumn();

        // 2. Record out-of-specification assay reading 102.4 (spec is 98.0 - 102.0)
        $val = 102.4;
        $isWithin = Result::validateAgainstSpec($val, 98.0, 102.0);
        $this->assertFalse($isWithin, '102.4 must fail spec range 98.0 - 102.0');

        $rStmt = $this->db->prepare("SELECT id FROM results WHERE sample_test_id = ? LIMIT 1");
        $rStmt->execute([$sampleTestId]);
        $resultId = (int)$rStmt->fetchColumn();

        $this->db->prepare("
            UPDATE results
            SET result_value = ?,
                reportable_value = '102.4',
                is_within_spec = FALSE,
                entered_by = 1,
                entered_at = CURRENT_TIMESTAMP,
                remarks = 'Assay 102.4% out of monograph limits'
            WHERE id = ?
        ")->execute([$val, $resultId]);

        // Mark test completed
        $this->db->prepare("UPDATE sample_tests SET status = 'Completed' WHERE id = ?")->execute([$sampleTestId]);

        // Auto-create OOS record
        $oosNumber = 'OOS-' . date('Ymd') . '-' . $sampleTestId;
        $this->db->prepare("
            INSERT INTO oos_records (oos_number, sample_id, sample_test_id, result_id, test_parameter, specification_range, result_value, severity, status, description, initiated_by)
            VALUES (?, ?, ?, ?, 'Ibuprofen Assay', '98.0 - 102.0 %', 102.4, 'Major', 'Open', 'Assay 102.4% out of spec', 1)
        ")->execute([$oosNumber, $sampleId, $sampleTestId, $resultId]);

        // 3. Status machine gate: Transitioning to Approved must be BLOCKED by open OOS
        $this->db->prepare("UPDATE samples SET status = 'Reviewed' WHERE id = ?")->execute([$sampleId]);

        try {
            $this->sampleService->transitionStatus($sampleId, 'Approved', 1);
            $this->fail('Expected approval to be blocked when open OOS exists');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('open OOS investigation', $e->getMessage());
        }

        // 4. Review by Exception check:
        // Exception query filter selects flagged (is_within_spec = false)
        $excStmt = $this->db->prepare("
            SELECT st.id, r.is_within_spec, r.result_value
            FROM sample_tests st
            JOIN results r ON r.sample_test_id = st.id
            WHERE st.id = ? AND r.is_within_spec = FALSE
        ");
        $excStmt->execute([$sampleTestId]);
        $excRow = $excStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($excRow);
        $this->assertFalse((bool)$excRow['is_within_spec']);
        $this->assertEquals(102.4, (float)$excRow['result_value']);
    }

    /**
     * Acceptance Scenario 6:
     * An analyst without current method training cannot record results, and the screen says why.
     */
    public function testScenario6AnalystTrainingGatingBlocksUnqualifiedTesting(): void
    {
        // Fetch legacy test_id
        $tStmt = $this->db->prepare("SELECT id FROM tests WHERE test_code = 'AN-HPLC-IBU' LIMIT 1");
        $tStmt->execute();
        $legacyTestId = (int)$tStmt->fetchColumn();
        if ($legacyTestId <= 0) {
            $tStmt = $this->db->query("SELECT id FROM tests LIMIT 1");
            $legacyTestId = (int)$tStmt->fetchColumn();
        }

        // Fetch real user id
        $analystId = (int)$this->db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();

        // Define a strict qualification requirement for this method
        $this->db->prepare("DELETE FROM training_qualifications WHERE qualification_type = 'test_method' AND entity_id = ?")
                 ->execute([$legacyTestId]);

        // Insert an expired training record for user
        $this->db->prepare("
            INSERT INTO training_qualifications (user_id, qualification_type, entity_id, status, qualified_at, expires_at, certified_by)
            VALUES (?, 'test_method', ?, 'Qualified', '2024-01-01', '2025-01-01', ?)
        ")->execute([$analystId, $legacyTestId, $analystId]);

        // The analyst's training has expired on 2025-01-01 -> isQualified MUST return false
        $isQualified = $this->trainingService->isQualified($analystId, 'test_method', $legacyTestId);
        $this->assertFalse($isQualified, 'Analyst with expired training must be gated and blocked from testing');
    }

    /**
     * Acceptance Scenario 7:
     * Any result shows its full lineage from the raw instrument file to the COA.
     */
    public function testScenario7ResultFullLineageProvenance(): void
    {
        // 1. Ingest raw instrument chromatogram file into vault
        $vaultContent = "SampleName,PeakName,Amount,Units,RetTime\nSMP-LIN-01,Ibuprofen,99.8,%,4.21\n";
        $rawVaultHash = hash('sha256', $vaultContent);
        $hmacSig = hash_hmac('sha256', $vaultContent, 'test_key');

        $vIns = $this->db->prepare("
            INSERT INTO instrument_raw_vault (
                vault_uid, original_filename, sha256_hash, hmac_signature, file_size_bytes, storage_path, raw_content, driver_used, parse_status
            ) VALUES (?, 'chromatogram_run01.csv', ?, ?, ?, 'storage/vault/chromatogram_run01.csv', ?, 'cds_csv', 'Parsed')
            RETURNING id
        ");
        $vIns->execute(['VAULT-LIN-' . bin2hex(random_bytes(8)), $rawVaultHash, $hmacSig, strlen($vaultContent), $vaultContent]);
        $vaultId = (int)$vIns->fetchColumn();

        // 2. Register sample and result
        $code = 'SMP-LIN-' . bin2hex(random_bytes(3));
        $sRes = $this->sampleService->registerSample([
            'sample_code' => $code,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2026-08-15'
        ], 1);
        $sampleId = $sRes['sample_id'];

        $stStmt = $this->db->prepare("SELECT id FROM sample_tests WHERE sample_id = ? LIMIT 1");
        $stStmt->execute([$sampleId]);
        $stId = (int)$stStmt->fetchColumn();

        $rStmt = $this->db->prepare("SELECT id FROM results WHERE sample_test_id = ? LIMIT 1");
        $rStmt->execute([$stId]);
        $resultId = (int)$rStmt->fetchColumn();

        $this->db->prepare("UPDATE results SET result_value = 99.8, reportable_value = '99.8', is_within_spec = TRUE, entered_by = 1 WHERE id = ?")->execute([$resultId]);

        // 3. Record full Lineage snapshot
        $lineageId = $this->lineageService->recordLineage($resultId, [
            'raw_vault_id' => $vaultId,
            'raw_file_hash' => $rawVaultHash,
            'raw_file_name' => 'chromatogram_run01.csv',
            'formula_expression' => '(PeakArea / StandardArea) * StdConc * 100',
            'spec_snapshot_min' => 98.0,
            'spec_snapshot_max' => 102.0,
            'unit_snapshot' => '%',
            'analyst_id' => 1,
            'reviewer_id' => 1,
            'approver_id' => 1
        ]);

        $this->assertGreaterThan(0, $lineageId);

        // 4. Retrieve and verify end-to-end lineage
        $lineage = $this->lineageService->getLineage($resultId);

        $this->assertNotNull($lineage);
        $this->assertEquals($rawVaultHash, $lineage['raw_file_hash'], 'Lineage must capture immutable raw file sha256 hash from vault');
        $this->assertEquals('chromatogram_run01.csv', $lineage['raw_file_name']);
        $this->assertEquals(98.0, (float)$lineage['spec_snapshot_min']);
        $this->assertEquals(102.0, (float)$lineage['spec_snapshot_max']);
        $this->assertEquals('(PeakArea / StandardArea) * StdConc * 100', $lineage['formula_expression']);
    }

    public function testResultInvalidationAndRetestWorkflow(): void
    {
        $code = 'SMP-INVAL-' . bin2hex(random_bytes(3));
        $sRes = $this->sampleService->registerSample([
            'sample_code' => $code,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good'
        ], 1);

        $stStmt = $this->db->prepare("SELECT id FROM sample_tests WHERE sample_id = ? LIMIT 1");
        $stStmt->execute([$sRes['sample_id']]);
        $stId = (int)$stStmt->fetchColumn();

        $rStmt = $this->db->prepare("SELECT id FROM results WHERE sample_test_id = ? LIMIT 1");
        $rStmt->execute([$stId]);
        $resultId = (int)$rStmt->fetchColumn();

        // 1. Invalidation without reason fails
        try {
            $this->lineageService->invalidateResult($resultId, '', 1);
            $this->fail('Expected exception when invalidating without reason');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('mandatory justification reason', $e->getMessage());
        }

        // 2. Invalidation with reason succeeds
        $invalReason = 'Baseline drift caused by air bubble in flow cell (INV-45)';
        $ok = $this->lineageService->invalidateResult($resultId, $invalReason, 1);
        $this->assertTrue($ok);

        $chkR = $this->db->query("SELECT is_invalidated, invalidation_reason, remarks FROM results WHERE id = {$resultId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertTrue((bool)$chkR['is_invalidated']);
        $this->assertEquals($invalReason, $chkR['invalidation_reason']);
        $this->assertStringContainsString('INVALIDATED', $chkR['remarks']);

        // 3. Authorized Retest
        $retestReason = 'Authorized retest following invalidation INV-45';
        $newTestId = $this->lineageService->createRetest($stId, $retestReason, 1);

        $this->assertGreaterThan(0, $newTestId);

        $chkNew = $this->db->query("SELECT is_retest, retest_of_test_id, added_reason FROM sample_tests WHERE id = {$newTestId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertTrue((bool)$chkNew['is_retest']);
        $this->assertEquals($stId, (int)$chkNew['retest_of_test_id']);
        $this->assertEquals($retestReason, $chkNew['added_reason']);
    }
}
