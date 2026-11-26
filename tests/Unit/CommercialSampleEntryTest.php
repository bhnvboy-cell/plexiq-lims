<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\Database;
use App\Models\Sample;
use App\Models\SampleTemplate;
use App\Services\MasterData\SpecificationService;
use App\Services\MasterData\ProductTestPlanService;
use App\Services\Samples\CommercialSampleService;
use PDO;

class CommercialSampleEntryTest extends TestCase
{
    private CommercialSampleService $sampleService;
    private SpecificationService $specService;
    private ProductTestPlanService $planService;
    private int $testProductId;
    private int $testSpecId;
    private int $testAnalysisId;
    private int $testCompId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->specService = new SpecificationService($this->db);
        $this->planService = new ProductTestPlanService($this->db, $this->specService);
        $this->sampleService = new CommercialSampleService($this->db, $this->specService, $this->planService);

        // 1. Get or create a unique product
        $pStmt = $this->db->prepare("SELECT id FROM products WHERE product_code = ?");
        $pStmt->execute(['TEST-PHARMA-ENTRY']);
        $this->testProductId = (int)$pStmt->fetchColumn();
        if ($this->testProductId <= 0) {
            $insP = $this->db->prepare("
                INSERT INTO products (product_code, product_name, category, is_active)
                VALUES ('TEST-PHARMA-ENTRY', 'Paracetamol 500mg GxP Tablet', 'Finished Product', TRUE)
                RETURNING id
            ");
            $insP->execute();
            $this->testProductId = (int)$insP->fetchColumn();
        }

        // 2. Create Analysis & Component
        $aStmt = $this->db->prepare("SELECT id FROM analyses WHERE analysis_code = ?");
        $aStmt->execute(['AN-ENTRY-HPLC']);
        $this->testAnalysisId = (int)$aStmt->fetchColumn();
        if ($this->testAnalysisId <= 0) {
            $insA = $this->db->prepare("
                INSERT INTO analyses (analysis_code, analysis_name, method_id, sop_reference, is_active)
                VALUES ('AN-ENTRY-HPLC', 'HPLC Assay for Active Ingredient', NULL, 'SOP-QC-HPLC-099', TRUE)
                RETURNING id
            ");
            $insA->execute();
            $this->testAnalysisId = (int)$insA->fetchColumn();

            $insC = $this->db->prepare("
                INSERT INTO components (analysis_id, component_code, component_name, data_type, unit, decimal_places, rounding_method, loq, uloq, is_active)
                VALUES (?, 'CMP-ASSAY', 'Assay % of Label Claim', 'numeric', '%', 2, 'half_even', 10.0, 150.0, TRUE)
                RETURNING id
            ");
            $insC->execute([$this->testAnalysisId]);
            $this->testCompId = (int)$insC->fetchColumn();
        } else {
            $cStmt = $this->db->prepare("SELECT id FROM components WHERE analysis_id = ? LIMIT 1");
            $cStmt->execute([$this->testAnalysisId]);
            $this->testCompId = (int)$cStmt->fetchColumn();
        }

        // 3. Create Approved Specification & Version 1 (Effective 2026-01-01 to 2026-12-31)
        $spStmt = $this->db->prepare("SELECT id FROM specifications WHERE spec_code = ?");
        $spStmt->execute(['SPEC-ENTRY-001']);
        $this->testSpecId = (int)$spStmt->fetchColumn();
        if ($this->testSpecId <= 0) {
            $this->testSpecId = $this->specService->createSpecification([
                'spec_code' => 'SPEC-ENTRY-001',
                'product_id' => $this->testProductId,
                'grade' => 'USP/NF',
                'customer_id' => null,
                'description' => 'Release specification for sample entry tests'
            ], 1);
        }

        // Fetch or create v1
        $vStmt = $this->db->prepare("SELECT id, status FROM specification_versions WHERE specification_id = ? AND version_number = 1");
        $vStmt->execute([$this->testSpecId]);
        $vRow = $vStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vRow) {
            $v1Id = $this->specService->createVersion($this->testSpecId, [
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'change_control_ref' => 'CC-ENTRY-001',
                'change_summary' => 'Initial spec definition'
            ], 1);
        } else {
            $v1Id = (int)$vRow['id'];
        }

        // Set to Draft temporarily if needed to ensure limit is registered, then approve
        $this->db->prepare("UPDATE specification_versions SET status = 'Draft', effective_from = '2026-01-01', effective_to = '2026-12-31' WHERE id = ?")->execute([$v1Id]);
        $this->specService->addOrUpdateLimit($v1Id, $this->testCompId, [
            'limit_type' => 'range',
            'min_limit' => 98.0,
            'max_limit' => 102.0,
            'display_specification' => '98.0 - 102.0 %'
        ]);
        $this->specService->approveVersion($v1Id, 1, 'Approver User', 'Initial release approval');

        // 4. Ensure Test Plan links this analysis to Finished Good stage
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

    public function testSampleRegistrationAutoAttachesApprovedSpecAndTestPlan(): void
    {
        $code = 'SMP-TEST-' . bin2hex(random_bytes(4));
        $res = $this->sampleService->registerSample([
            'sample_code' => $code,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2026-06-15',
            'receipt_check_status' => 'Passed',
            'receipt_check_notes' => 'Seals intact and verified at 22°C',
            'batch_number' => 'LOT-2026-001',
            'priority' => 'High'
        ], 1);

        $this->assertNotEmpty($res['sample_id']);
        $this->assertEquals($code, $res['sample_code']);
        $this->assertGreaterThanOrEqual(1, $res['total_analyses']);
        $this->assertEquals(1, $res['spec_version_number']);

        // Verify DB Sample row
        $stmt = $this->db->prepare("SELECT * FROM samples WHERE id = ?");
        $stmt->execute([$res['sample_id']]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('Finished Good', $s['sample_stage']);
        $this->assertEquals('Passed', $s['receipt_check_status']);
        $this->assertNotNull($s['spec_version_id']);

        // Verify sample_tests
        $stStmt = $this->db->prepare("SELECT * FROM sample_tests WHERE sample_id = ? AND analysis_id = ?");
        $stStmt->execute([$res['sample_id'], $this->testAnalysisId]);
        $st = $stStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($st);
        $this->assertEquals('Pending', $st['status']);
        $this->assertTrue((bool)$st['is_mandatory']);

        // Verify results snapshot
        $rStmt = $this->db->prepare("SELECT * FROM results WHERE sample_test_id = ? AND component_id = ?");
        $rStmt->execute([$st['id'], $this->testCompId]);
        $r = $rStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($r);
        $this->assertEquals(98.0, (float)$r['spec_min_snapshot']);
        $this->assertEquals(102.0, (float)$r['spec_max_snapshot']);
        $this->assertEquals('%', $r['unit_snapshot']);
        $this->assertEquals(10.0, (float)$r['loq_snapshot']);
    }

    public function testSpecSnapshotImmutabilityAcrossVersions(): void
    {
        // 1. Create sample under Spec v1 (limit 98.0 - 102.0)
        $code1 = 'SMP-V1-' . bin2hex(random_bytes(3));
        $res1 = $this->sampleService->registerSample([
            'sample_code' => $code1,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2026-03-01'
        ], 1);

        $stStmt1 = $this->db->prepare("SELECT id FROM sample_tests WHERE sample_id = ? AND analysis_id = ?");
        $stStmt1->execute([$res1['sample_id'], $this->testAnalysisId]);
        $stId1 = (int)$stStmt1->fetchColumn();

        $rStmt1 = $this->db->prepare("SELECT spec_min_snapshot, spec_max_snapshot FROM results WHERE sample_test_id = ? AND component_id = ?");
        $rStmt1->execute([$stId1, $this->testCompId]);
        $snap1 = $rStmt1->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(98.0, (float)$snap1['spec_min_snapshot']);
        $this->assertEquals(102.0, (float)$snap1['spec_max_snapshot']);

        // 2. Create Spec v2 with tighter limits effective from 2027-01-01
        $v2Stmt = $this->db->prepare("SELECT id FROM specification_versions WHERE specification_id = ? AND version_number = 2");
        $v2Stmt->execute([$this->testSpecId]);
        $v2Id = (int)$v2Stmt->fetchColumn();

        if ($v2Id <= 0) {
            $v2Id = $this->specService->createDraftVersion($this->testSpecId, [
                'effective_from' => '2027-01-01',
                'effective_to' => '2028-12-31',
                'notes' => 'Tighter monograph limits for 2027'
            ], null, 1);
        } else {
            $this->db->prepare("UPDATE specification_versions SET status = 'Draft', effective_from = '2027-01-01', effective_to = '2028-12-31' WHERE id = ?")->execute([$v2Id]);
        }

        $this->specService->addOrUpdateLimit($v2Id, $this->testCompId, [
            'limit_type' => 'range',
            'min_limit' => 99.0,
            'max_limit' => 101.0,
            'display_specification' => '99.0 - 101.0 %'
        ]);
        $this->specService->approveVersion($v2Id, 1, 'Quality Director', 'Approved tight limits');

        // 3. Register sample under 2027 effective date (should resolve v2)
        $code2 = 'SMP-V2-' . bin2hex(random_bytes(3));
        $res2 = $this->sampleService->registerSample([
            'sample_code' => $code2,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2027-02-15'
        ], 1);

        $this->assertEquals(2, $res2['spec_version_number']);

        $stStmt2 = $this->db->prepare("SELECT id FROM sample_tests WHERE sample_id = ? AND analysis_id = ?");
        $stStmt2->execute([$res2['sample_id'], $this->testAnalysisId]);
        $stId2 = (int)$stStmt2->fetchColumn();

        $rStmt2 = $this->db->prepare("SELECT spec_min_snapshot, spec_max_snapshot FROM results WHERE sample_test_id = ? AND component_id = ?");
        $rStmt2->execute([$stId2, $this->testCompId]);
        $snap2 = $rStmt2->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(99.0, (float)$snap2['spec_min_snapshot']);
        $this->assertEquals(101.0, (float)$snap2['spec_max_snapshot']);

        // 4. CRITICAL COMMERCIAL GxP CHECK: Historical sample 1 snapshot must remain UNCHANGED
        $rStmt1->execute([$stId1, $this->testCompId]);
        $snap1After = $rStmt1->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(98.0, (float)$snap1After['spec_min_snapshot'], 'Historical sample snapshot must never mutate when new spec version is approved');
        $this->assertEquals(102.0, (float)$snap1After['spec_max_snapshot']);
    }

    public function testAdHocTestAdditionRequiresJustificationReason(): void
    {
        $code = 'SMP-ADHOC-' . bin2hex(random_bytes(3));
        $res = $this->sampleService->registerSample([
            'sample_code' => $code,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2026-05-01'
        ], 1);

        // Missing reason must throw InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->sampleService->addSampleTest($res['sample_id'], $this->testAnalysisId, '', 1);
    }

    public function testAdHocTestAdditionWithReasonSucceedsAndAudits(): void
    {
        $code = 'SMP-ADHOC-OK-' . bin2hex(random_bytes(3));
        $res = $this->sampleService->registerSample([
            'sample_code' => $code,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2026-05-01'
        ], 1);

        $justification = 'Investigative ad-hoc re-run requested by QA Lead';
        $testId = $this->sampleService->addSampleTest($res['sample_id'], $this->testAnalysisId, $justification, 1);

        $this->assertGreaterThan(0, $testId);

        $stmt = $this->db->prepare("SELECT * FROM sample_tests WHERE id = ?");
        $stmt->execute([$testId]);
        $st = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals($justification, $st['added_reason']);
        $this->assertFalse((bool)$st['is_mandatory']);

        // Check audit entry
        $aStmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'sample_tests' AND entity_id = ?");
        $aStmt->execute([$testId]);
        $this->assertGreaterThan(0, (int)$aStmt->fetchColumn());
    }

    public function testRemoveTestRequiresReasonAndPerformsSoftDelete(): void
    {
        $code = 'SMP-RM-' . bin2hex(random_bytes(3));
        $res = $this->sampleService->registerSample([
            'sample_code' => $code,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2026-05-01'
        ], 1);

        $stStmt = $this->db->prepare("SELECT id FROM sample_tests WHERE sample_id = ? LIMIT 1");
        $stStmt->execute([$res['sample_id']]);
        $testId = (int)$stStmt->fetchColumn();

        // 1. Calling without reason throws exception
        try {
            $this->sampleService->removeSampleTest($testId, '', 1);
            $this->fail('Expected exception for empty reason on test removal');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('mandatory reason', $e->getMessage());
        }

        // 2. Calling with valid reason succeeds
        $removeReason = 'Instrument breakdown; test canceled per Protocol SOP-05';
        $ok = $this->sampleService->removeSampleTest($testId, $removeReason, 1);
        $this->assertTrue($ok);

        // Verify soft-deleted
        $chk = $this->db->prepare("SELECT status, removed_reason, deleted_at FROM sample_tests WHERE id = ?");
        $chk->execute([$testId]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($row['deleted_at'], 'Test must not be physically deleted; deleted_at must be populated');
        $this->assertEquals($removeReason, $row['removed_reason']);
    }

    public function testTransitionQualityGuards(): void
    {
        // 1. Create sample with Failed receipt check
        $code = 'SMP-GUARD-' . bin2hex(random_bytes(3));
        $res = $this->sampleService->registerSample([
            'sample_code' => $code,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2026-05-01',
            'receipt_check_status' => 'Failed_Quarantine',
            'receipt_check_notes' => 'Container broken during transport'
        ], 1);

        // Transition from Registered to In Progress must be BLOCKED
        try {
            $this->sampleService->transitionStatus($res['sample_id'], 'In Progress', 1);
            $this->fail('Expected receipt check failure to block transition');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('failed receipt inspection', $e->getMessage());
        }

        // 2. Update receipt check to Passed
        $this->db->prepare("UPDATE samples SET receipt_check_status = 'Passed' WHERE id = ?")->execute([$res['sample_id']]);

        // Transition to In Progress should now succeed
        $trans1 = $this->sampleService->transitionStatus($res['sample_id'], 'In Progress', 1);
        $this->assertTrue($trans1['success']);
        $this->assertEquals('In Progress', $trans1['new_status']);

        // 3. Transition to Reviewed while required tests are pending must be BLOCKED
        try {
            $this->sampleService->transitionStatus($res['sample_id'], 'Reviewed', 1);
            $this->fail('Expected review transition to be blocked when mandatory tests are pending');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('mandatory test(s) are incomplete', $e->getMessage());
        }
    }
}
