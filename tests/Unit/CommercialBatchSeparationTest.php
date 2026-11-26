<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\Database;
use App\Models\ProductionLot;
use App\Models\LabBatch;
use App\Services\Batches\ProductionLotService;
use App\Services\Batches\LabBatchService;
use App\Services\MasterData\SpecificationService;
use App\Services\MasterData\ProductTestPlanService;
use App\Services\Samples\CommercialSampleService;
use PDO;

class CommercialBatchSeparationTest extends TestCase
{
    private ProductionLotService $lotService;
    private LabBatchService $batchService;
    private CommercialSampleService $sampleService;
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
        $this->lotService = new ProductionLotService($this->db, $this->specService);
        $this->batchService = new LabBatchService($this->db);
        $this->sampleService = new CommercialSampleService($this->db, $this->specService, $this->planService);

        // Product
        $pStmt = $this->db->prepare("SELECT id FROM products WHERE product_code = ?");
        $pStmt->execute(['TEST-BATCH-PROD']);
        $this->testProductId = (int)$pStmt->fetchColumn();
        if ($this->testProductId <= 0) {
            $insP = $this->db->prepare("
                INSERT INTO products (product_code, product_name, category, is_active)
                VALUES ('TEST-BATCH-PROD', 'Amoxicillin Trihydrate 250mg', 'Finished Product', TRUE)
                RETURNING id
            ");
            $insP->execute();
            $this->testProductId = (int)$insP->fetchColumn();
        }

        // Analysis & Component
        $aStmt = $this->db->prepare("SELECT id FROM analyses WHERE analysis_code = ?");
        $aStmt->execute(['AN-HPLC-AMOXI']);
        $this->testAnalysisId = (int)$aStmt->fetchColumn();
        if ($this->testAnalysisId <= 0) {
            $insA = $this->db->prepare("
                INSERT INTO analyses (analysis_code, analysis_name, method_id, sop_reference, is_active)
                VALUES ('AN-HPLC-AMOXI', 'Assay of Amoxicillin by HPLC', NULL, 'SOP-QC-HPLC-021', TRUE)
                RETURNING id
            ");
            $insA->execute();
            $this->testAnalysisId = (int)$insA->fetchColumn();

            $insC = $this->db->prepare("
                INSERT INTO components (analysis_id, component_code, component_name, data_type, unit, decimal_places, rounding_method, loq, uloq, is_active)
                VALUES (?, 'CMP-AMOXI-ASSAY', 'Amoxicillin Assay', 'numeric', '%', 2, 'half_even', 10.0, 150.0, TRUE)
                RETURNING id
            ");
            $insC->execute([$this->testAnalysisId]);
            $this->testCompId = (int)$insC->fetchColumn();
        } else {
            $cStmt = $this->db->prepare("SELECT id FROM components WHERE analysis_id = ? LIMIT 1");
            $cStmt->execute([$this->testAnalysisId]);
            $this->testCompId = (int)$cStmt->fetchColumn();
        }

        // Specification & Approved Version
        $spStmt = $this->db->prepare("SELECT id FROM specifications WHERE spec_code = ?");
        $spStmt->execute(['SPEC-BATCH-001']);
        $this->testSpecId = (int)$spStmt->fetchColumn();
        if ($this->testSpecId <= 0) {
            $this->testSpecId = $this->specService->createSpecification([
                'spec_code' => 'SPEC-BATCH-001',
                'product_id' => $this->testProductId,
                'grade' => 'BP/EP',
                'description' => 'Amoxicillin Batch Test Spec'
            ], 1);
        }

        $vStmt = $this->db->prepare("SELECT id, status FROM specification_versions WHERE specification_id = ? AND version_number = 1");
        $vStmt->execute([$this->testSpecId]);
        $vRow = $vStmt->fetch(PDO::FETCH_ASSOC);

        $v1Id = $vRow ? (int)$vRow['id'] : $this->specService->createDraftVersion($this->testSpecId, [
            'effective_from' => '2026-01-01',
            'effective_to' => '2027-12-31'
        ], null, 1);

        $this->db->prepare("UPDATE specification_versions SET status = 'Draft', effective_from = '2026-01-01', effective_to = '2027-12-31' WHERE id = ?")->execute([$v1Id]);
        $this->specService->addOrUpdateLimit($v1Id, $this->testCompId, [
            'limit_type' => 'range',
            'min_limit' => 95.0,
            'max_limit' => 105.0,
            'display_specification' => '95.0 - 105.0 %'
        ]);
        $this->specService->approveVersion($v1Id, 1, 'Quality Unit', 'Initial Approval');

        // Test Plan
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

    public function testProductionLotCreationAndSpecLinkage(): void
    {
        $lotNum = 'LOT-TEST-' . bin2hex(random_bytes(3));
        $res = $this->lotService->createLot([
            'lot_number' => $lotNum,
            'product_id' => $this->testProductId,
            'erp_batch_number' => 'ERP-' . $lotNum,
            'lot_size' => 1250.5,
            'lot_unit' => 'kg',
            'manufacturing_date' => '2026-07-01'
        ], 1);

        $this->assertGreaterThan(0, $res['lot_id']);
        $this->assertEquals($lotNum, $res['lot_number']);
        $this->assertEquals('Pending', $res['disposition']);

        $lot = ProductionLot::findWithRelations($res['lot_id']);
        $this->assertNotNull($lot);
        $this->assertEquals(1250.5, (float)$lot['lot_size']);
        $this->assertEquals('Pending', $lot['erp_sync_status']);
    }

    /**
     * Acceptance Scenario 5:
     * A lot cannot be released while a required sample is unapproved or an OOS is open;
     * release records an e-signature and posts to the ERP once, even after retries.
     */
    public function testLotReleaseBlockedByUnapprovedSampleOrOpenOOS(): void
    {
        $lotNum = 'LOT-BLOCK-' . bin2hex(random_bytes(3));
        $lotRes = $this->lotService->createLot([
            'lot_number' => $lotNum,
            'product_id' => $this->testProductId,
            'manufacturing_date' => '2026-07-01'
        ], 1);
        $lotId = $lotRes['lot_id'];

        // 1. Initial State: No samples linked -> Release must be BLOCKED
        try {
            $this->lotService->setDisposition($lotId, 'Released', 'Trying to release empty lot', 1);
            $this->fail('Expected release to be blocked on lot with no samples');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('No quality control samples', $e->getMessage());
        }

        // 2. Link a Sample in Registered status (Unapproved)
        $sCode = 'SMP-LOT-' . bin2hex(random_bytes(3));
        $sRes = $this->sampleService->registerSample([
            'sample_code' => $sCode,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2026-07-01'
        ], 1);
        $sampleId = $sRes['sample_id'];

        // Assign sample to lot
        $this->db->prepare("UPDATE samples SET production_lot_id = ? WHERE id = ?")->execute([$lotId, $sampleId]);

        // Release must STILL be BLOCKED because sample is unapproved
        try {
            $this->lotService->setDisposition($lotId, 'Released', 'Trying to release with unapproved sample', 1);
            $this->fail('Expected release to be blocked when sample is not approved');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('sample(s) have not completed testing', $e->getMessage());
        }

        // 3. Mark sample as Approved, but open an OOS record on it
        $this->db->prepare("UPDATE samples SET status = 'Approved' WHERE id = ?")->execute([$sampleId]);

        $oosNum = 'OOS-' . date('Ymd') . '-' . bin2hex(random_bytes(2));
        $insOos = $this->db->prepare("
            INSERT INTO oos_records (oos_number, sample_id, status, test_parameter, description, initiated_by)
            VALUES (?, ?, 'Open', 'Amoxicillin Assay', 'Assay failed at 91.2% (spec 95-105%)', 1)
            RETURNING id
        ");
        $insOos->execute([$oosNum, $sampleId]);
        $oosId = (int)$insOos->fetchColumn();

        // Release must STILL be BLOCKED because an OOS is open
        try {
            $this->lotService->setDisposition($lotId, 'Released', 'Trying to release with open OOS', 1);
            $this->fail('Expected release to be blocked when open OOS exists');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Out-Of-Specification (OOS) investigation(s) remain open', $e->getMessage());
        }

        // 4. Close the OOS record
        $this->db->prepare("UPDATE oos_records SET status = 'Closed', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$oosId]);

        // 5. Release should NOW SUCCEED and capture Part 11 e-signature & ERP sync
        $dispositionReason = 'All monograph tests verified in-spec; OOS-01 closed after lab error confirmed.';
        $dispRes = $this->lotService->setDisposition($lotId, 'Released', $dispositionReason, 1);

        $this->assertTrue($dispRes['success']);
        $this->assertEquals('Released', $dispRes['disposition']);
        $this->assertNotEmpty($dispRes['signature_hash']);
        $this->assertNotEmpty($dispRes['idempotency_key']);

        // Verify database state
        $lotAfter = ProductionLot::findWithRelations($lotId);
        $this->assertEquals('Released', $lotAfter['disposition']);
        $this->assertNotNull($lotAfter['disposition_at']);
        $this->assertNotEmpty($lotAfter['disposition_signature_hash']);
        $this->assertEquals($dispRes['idempotency_key'], $lotAfter['erp_sync_idempotency_key']);

        // 6. Test Idempotent ERP Sync (Calling again uses same idempotency key and does not duplicate)
        $firstKey = $lotAfter['erp_sync_idempotency_key'];
        $syncRes2 = $this->lotService->syncLotToErp($lotId);
        $this->assertEquals($firstKey, $syncRes2['idempotency_key'], 'ERP sync retry must reuse identical idempotency key');
    }

    /**
     * Acceptance Scenario 4:
     * A lab batch with blank, standard and QC control:
     * A failed Westgard / QC rule flags and requeues every result in that batch with a reason.
     */
    public function testLabBatchQcFailureFlagsAndRequeuesResults(): void
    {
        // 1. Create a Lab Analytical Batch
        $batchRes = $this->batchService->createLabBatch([
            'analysis_id' => $this->testAnalysisId,
            'worklist_format' => 'empower_csv'
        ], 1);
        $batchId = $batchRes['batch_id'];

        // 2. Register two test samples
        $s1 = $this->sampleService->registerSample([
            'sample_code' => 'SMP-RUN-01-' . bin2hex(random_bytes(2)),
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good'
        ], 1);
        $s2 = $this->sampleService->registerSample([
            'sample_code' => 'SMP-RUN-02-' . bin2hex(random_bytes(2)),
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good'
        ], 1);

        // Fetch their sample_tests
        $stId1 = (int)$this->db->query("SELECT id FROM sample_tests WHERE sample_id = {$s1['sample_id']} LIMIT 1")->fetchColumn();
        $stId2 = (int)$this->db->query("SELECT id FROM sample_tests WHERE sample_id = {$s2['sample_id']} LIMIT 1")->fetchColumn();

        // Mark them In Progress with preliminary readings
        $this->db->prepare("UPDATE sample_tests SET status = 'In Progress' WHERE id IN (?, ?)")->execute([$stId1, $stId2]);
        $this->db->prepare("UPDATE results SET result_value = 99.5, is_within_spec = TRUE WHERE sample_test_id = ?")->execute([$stId1]);
        $this->db->prepare("UPDATE results SET result_value = 100.2, is_within_spec = TRUE WHERE sample_test_id = ?")->execute([$stId2]);

        // 3. Assemble sequence: Blank, Calibration Standard, Sample 1, Sample 2, QC Control
        $sequence = [
            [
                'item_type' => 'blank',
                'control_name' => 'Methanol Blank',
                'vial_position' => '1',
                'tolerance_max' => 0.05,
                'actual_value' => 0.01 // Blank passed
            ],
            [
                'item_type' => 'calibration_standard',
                'control_name' => 'Cal Standard 100%',
                'vial_position' => '2',
                'expected_value' => 100.0,
                'tolerance_min' => 98.0,
                'tolerance_max' => 102.0,
                'actual_value' => 100.1 // Cal standard passed
            ],
            [
                'item_type' => 'sample',
                'sample_id' => $s1['sample_id'],
                'sample_test_id' => $stId1,
                'vial_position' => '3'
            ],
            [
                'item_type' => 'sample',
                'sample_id' => $s2['sample_id'],
                'sample_test_id' => $stId2,
                'vial_position' => '4'
            ],
            [
                'item_type' => 'qc_control',
                'control_name' => 'Check Standard Mid',
                'vial_position' => '5',
                'expected_value' => 100.0,
                'tolerance_min' => 98.0,
                'tolerance_max' => 102.0,
                'actual_value' => 88.5 // CRITICAL FAILURE: Exceeds tolerance (88.5 < 98.0)
            ]
        ];

        $addedCount = $this->batchService->addSequenceItems($batchId, $sequence);
        $this->assertEquals(5, $addedCount);

        // 4. Execute Batch QC Evaluation
        $evalRes = $this->batchService->evaluateBatchQc($batchId, 1);

        $this->assertFalse($evalRes['is_passed']);
        $this->assertEquals('Failed', $evalRes['qc_status']);
        $this->assertEquals(2, $evalRes['requeued_tests_count']);
        $this->assertNotEmpty($evalRes['failed_controls']);

        // 5. CRITICAL VERIFICATION: Every result in that batch must be invalidated & requeued
        $chkSt1 = $this->db->query("SELECT status FROM sample_tests WHERE id = {$stId1}")->fetch(PDO::FETCH_ASSOC);
        $chkSt2 = $this->db->query("SELECT status FROM sample_tests WHERE id = {$stId2}")->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('Pending', $chkSt1['status'], 'Sample Test 1 must be requeued to Pending');
        $this->assertEquals('Pending', $chkSt2['status'], 'Sample Test 2 must be requeued to Pending');

        $chkR1 = $this->db->query("SELECT is_within_spec, remarks FROM results WHERE sample_test_id = {$stId1}")->fetch(PDO::FETCH_ASSOC);
        $this->assertFalse((bool)$chkR1['is_within_spec']);
        $this->assertStringContainsString('INVALIDATED', $chkR1['remarks']);
        $this->assertStringContainsString('QC Failed', $chkR1['remarks']);
    }

    public function testInstrumentWorklistGeneration(): void
    {
        $batchRes = $this->batchService->createLabBatch([
            'analysis_id' => $this->testAnalysisId,
            'worklist_format' => 'empower_csv'
        ], 1);
        $batchId = $batchRes['batch_id'];

        $this->batchService->addSequenceItems($batchId, [
            [
                'item_type' => 'blank',
                'control_name' => 'Solvent Blank',
                'vial_position' => '1:A,1'
            ],
            [
                'item_type' => 'calibration_standard',
                'control_name' => 'Cal Standard',
                'vial_position' => '1:A,2'
            ]
        ]);

        $csv = $this->batchService->generateInstrumentWorklist($batchId, 'empower_csv');

        $this->assertNotEmpty($csv);
        $this->assertStringContainsString('Vial,SampleName,SampleType,MethodSet,InjVol,RunTime_Min', $csv);
        $this->assertStringContainsString('Solvent Blank', $csv);
        $this->assertStringContainsString('Cal Standard', $csv);
    }
}
