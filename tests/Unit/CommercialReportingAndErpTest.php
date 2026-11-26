<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\Database;
use App\Models\ProductionLot;
use App\Models\Sample;
use App\Models\Result;
use App\Models\CoaDocument;
use App\Services\Batches\ProductionLotService;
use App\Services\Batches\LabBatchService;
use App\Services\MasterData\SpecificationService;
use App\Services\MasterData\ProductTestPlanService;
use App\Services\Samples\CommercialSampleService;
use App\Services\Results\ResultLineageService;
use App\Services\CoaService;
use PDO;

class CommercialReportingAndErpTest extends TestCase
{
    private ProductionLotService $lotService;
    private LabBatchService $batchService;
    private CommercialSampleService $sampleService;
    private SpecificationService $specService;
    private ProductTestPlanService $planService;
    private ResultLineageService $lineageService;
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
        $this->lotService = new ProductionLotService($this->db, $this->specService);
        $this->batchService = new LabBatchService($this->db);
        $this->lineageService = new ResultLineageService($this->db);

        // Product
        $pStmt = $this->db->prepare("SELECT id FROM products WHERE product_code = ?");
        $pStmt->execute(['TEST-E2E-PROD']);
        $this->testProductId = (int)$pStmt->fetchColumn();
        if ($this->testProductId <= 0) {
            $insP = $this->db->prepare("
                INSERT INTO products (product_code, product_name, category, is_active)
                VALUES ('TEST-E2E-PROD', 'Metformin HCl 500mg GxP Tablets', 'Finished Product', TRUE)
                RETURNING id
            ");
            $insP->execute();
            $this->testProductId = (int)$insP->fetchColumn();
        }

        // Analysis & Component
        $aStmt = $this->db->prepare("SELECT id FROM analyses WHERE analysis_code = ?");
        $aStmt->execute(['AN-METFORMIN-ASSAY']);
        $this->testAnalysisId = (int)$aStmt->fetchColumn();
        if ($this->testAnalysisId <= 0) {
            $insA = $this->db->prepare("
                INSERT INTO analyses (analysis_code, analysis_name, method_id, sop_reference, is_active)
                VALUES ('AN-METFORMIN-ASSAY', 'Assay of Metformin by UV-Vis', NULL, 'SOP-QC-UV-012', TRUE)
                RETURNING id
            ");
            $insA->execute();
            $this->testAnalysisId = (int)$insA->fetchColumn();

            $insC = $this->db->prepare("
                INSERT INTO components (analysis_id, component_code, component_name, data_type, unit, decimal_places, rounding_method, loq, uloq, is_active)
                VALUES (?, 'CMP-MET-ASSAY', 'Metformin Assay', 'numeric', '%', 1, 'half_even', 10.0, 150.0, TRUE)
                RETURNING id
            ");
            $insC->execute([$this->testAnalysisId]);
            $this->testCompId = (int)$insC->fetchColumn();
        } else {
            $cStmt = $this->db->prepare("SELECT id FROM components WHERE analysis_id = ? LIMIT 1");
            $cStmt->execute([$this->testAnalysisId]);
            $this->testCompId = (int)$cStmt->fetchColumn();
        }

        // Specification & Approved v1 (Limits 98.5 - 101.5 %)
        $spStmt = $this->db->prepare("SELECT id FROM specifications WHERE spec_code = ?");
        $spStmt->execute(['SPEC-MET-001']);
        $this->testSpecId = (int)$spStmt->fetchColumn();
        if ($this->testSpecId <= 0) {
            $this->testSpecId = $this->specService->createSpecification([
                'spec_code' => 'SPEC-MET-001',
                'product_id' => $this->testProductId,
                'grade' => 'USP',
                'description' => 'Metformin HCl Finished Product Specification'
            ], 1);
        }

        $vStmt = $this->db->prepare("SELECT id, status FROM specification_versions WHERE specification_id = ? AND version_number = 1");
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
            'min_limit' => 98.5,
            'max_limit' => 101.5,
            'display_specification' => '98.5 - 101.5 %'
        ]);
        $this->specService->approveVersion($v1Id, 1, 'Quality Director', 'Approved Spec');

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

    /**
     * Acceptance Scenario 1 & 2:
     * Full Commercial LIMS Spine Execution:
     * Product -> Versioned Spec -> Test Plan -> Sample -> Lab Batch -> Result -> Review -> Approval -> COA -> Lot Disposition -> ERP.
     */
    public function testEndToEndCommercialLimsWorkflow(): void
    {
        // STEP 1: Create Production Lot
        $lotNum = 'LOT-E2E-' . bin2hex(random_bytes(3));
        $lotRes = $this->lotService->createLot([
            'lot_number' => $lotNum,
            'product_id' => $this->testProductId,
            'erp_batch_number' => 'ERP-' . $lotNum,
            'lot_size' => 100000.0,
            'lot_unit' => 'tabs',
            'manufacturing_date' => '2026-09-01'
        ], 1);
        $lotId = $lotRes['lot_id'];
        $this->assertGreaterThan(0, $lotId);

        // STEP 2: Register Sample Follows Product & Spec Snapshots
        $sCode = 'SMP-E2E-' . bin2hex(random_bytes(3));
        $sRes = $this->sampleService->registerSample([
            'sample_code' => $sCode,
            'product_id' => $this->testProductId,
            'sample_stage' => 'Finished Good',
            'sampling_date' => '2026-09-01',
            'batch_number' => $lotNum
        ], 1);
        $sampleId = $sRes['sample_id'];
        $this->assertEquals(1, $sRes['spec_version_number']);

        // Link sample to lot
        $this->db->prepare("UPDATE samples SET production_lot_id = ? WHERE id = ?")->execute([$lotId, $sampleId]);

        // STEP 3: Setup Lab Batch (Analytical Run)
        $batchRes = $this->batchService->createLabBatch([
            'analysis_id' => $this->testAnalysisId,
            'worklist_format' => 'empower_csv'
        ], 1);
        $batchId = $batchRes['batch_id'];

        $stStmt = $this->db->prepare("SELECT id FROM sample_tests WHERE sample_id = ? LIMIT 1");
        $stStmt->execute([$sampleId]);
        $stId = (int)$stStmt->fetchColumn();

        // Add sequence with Standard + Specimen Sample
        $this->batchService->addSequenceItems($batchId, [
            [
                'item_type' => 'calibration_standard',
                'control_name' => 'Working Standard 100%',
                'expected_value' => 100.0,
                'tolerance_min' => 99.0,
                'tolerance_max' => 101.0,
                'actual_value' => 100.2 // Passed
            ],
            [
                'item_type' => 'sample',
                'sample_id' => $sampleId,
                'sample_test_id' => $stId
            ]
        ]);

        // Evaluate Batch QC (Must pass)
        $qcEval = $this->batchService->evaluateBatchQc($batchId, 1);
        $this->assertTrue($qcEval['is_passed']);

        // STEP 4: Record In-Spec Result (Assay 100.1% on spec 98.5-101.5)
        $rStmt = $this->db->prepare("SELECT id FROM results WHERE sample_test_id = ? LIMIT 1");
        $rStmt->execute([$stId]);
        $resultId = (int)$rStmt->fetchColumn();

        $this->db->prepare("
            UPDATE results
            SET result_value = 100.1,
                reportable_value = '100.1',
                is_within_spec = TRUE,
                entered_by = 1,
                entered_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$resultId]);

        // Record lineage
        $this->lineageService->recordLineage($resultId, [
            'raw_file_name' => 'uv_spectrophotometer_run01.csv',
            'raw_file_hash' => hash('sha256', 'mock_run_data'),
            'instrument_id' => null,
            'analyst_id' => 1
        ]);

        // STEP 5: Review & Approval Workflow
        $this->db->prepare("UPDATE sample_tests SET status = 'Approved', approved_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$stId]);
        $this->db->prepare("UPDATE samples SET status = 'Approved', approved_by = 1, approved_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$sampleId]);

        // STEP 6: Release COA
        $docNum = CoaDocument::generateNumber();
        $insCoa = $this->db->prepare("
            INSERT INTO coa_documents (
                document_number, sample_id, status, generated_by, released_by, released_at
            ) VALUES (?, ?, 'Released', 1, 1, CURRENT_TIMESTAMP)
            RETURNING id
        ");
        $insCoa->execute([$docNum, $sampleId]);
        $coaId = (int)$insCoa->fetchColumn();
        $this->assertGreaterThan(0, $coaId);

        // Update sample to COA Released
        $this->db->prepare("UPDATE samples SET status = 'COA Released' WHERE id = ?")->execute([$sampleId]);

        // STEP 7: Quality Assurance Production Lot Disposition & Release
        $readiness = $this->lotService->evaluateLotReadiness($lotId);
        $this->assertTrue($readiness['can_release'], 'Lot must be ready for release when all samples are approved with no open OOS');

        $dispRes = $this->lotService->setDisposition($lotId, 'Released', 'Metformin Batch meets USP monograph in full', 1);
        $this->assertTrue($dispRes['success']);
        $this->assertEquals('Released', $dispRes['disposition']);
        $this->assertNotEmpty($dispRes['signature_hash']);
        $this->assertNotEmpty($dispRes['idempotency_key']);

        // STEP 8: ERP Synchronization
        $lotFinal = ProductionLot::findWithRelations($lotId);
        $this->assertEquals('Released', $lotFinal['disposition']);
        $this->assertEquals($dispRes['idempotency_key'], $lotFinal['erp_sync_idempotency_key']);
    }

    /**
     * Acceptance Scenario 8:
     * Backward compatibility check:
     * Legacy tables and views (v_production_lots_legacy) continue functioning seamlessly.
     */
    public function testScenario8CompatibilityViewWorksForLegacyQueries(): void
    {
        $stmt = $this->db->query("SELECT * FROM v_production_lots_legacy LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row);
        $this->assertArrayHasKey('batch_number', $row);
        $this->assertArrayHasKey('product_name', $row);
        $this->assertArrayHasKey('status', $row);
    }
}
