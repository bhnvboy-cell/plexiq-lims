<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\Database;
use App\Models\Component;
use App\Models\SpecificationLimit;
use App\Services\MasterData\SpecificationService;
use App\Services\MasterData\ProductTestPlanService;
use PDO;

class CommercialMasterDataTest extends TestCase
{
    private SpecificationService $specService;
    private ProductTestPlanService $planService;
    private int $testProductId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->specService = new SpecificationService($this->db);
        $this->planService = new ProductTestPlanService($this->db, $this->specService);

        // Fetch or create a test product
        $stmt = $this->db->query("SELECT id FROM products ORDER BY id ASC LIMIT 1");
        $this->testProductId = (int)$stmt->fetchColumn();
        if ($this->testProductId <= 0) {
            $ins = $this->db->query("
                INSERT INTO products (product_code, product_name, category)
                VALUES ('TEST-PROD-999', 'Test Pharma Compound', 'Pharmaceutical')
                RETURNING id
            ");
            $this->testProductId = (int)$ins->fetchColumn();
        }
    }

    public function testComponentFormatReportableValueLOQ(): void
    {
        $comp = [
            'data_type' => 'numeric',
            'unit' => 'mg/L',
            'decimal_places' => 3,
            'rounding_method' => 'half_even',
            'loq' => 0.05,
            'uloq' => 100.0
        ];

        // Below LOQ (0.03 < 0.05)
        $res = Component::formatReportableValue($comp, 0.03);
        $this->assertTrue($res['is_loq']);
        $this->assertFalse($res['is_uloq']);
        $this->assertEquals('<0.05', $res['reportable_value']);
        $this->assertEquals('<LOQ', $res['flag']);
        $this->assertEquals(0.03, $res['raw_value']);

        // Above ULOQ (150 > 100)
        $resUloq = Component::formatReportableValue($comp, 150.0);
        $this->assertTrue($resUloq['is_uloq']);
        $this->assertEquals('>100', $resUloq['reportable_value']);

        // Normal in-range value with ASTM E29 rounding
        $resNormal = Component::formatReportableValue($comp, 12.3456);
        $this->assertFalse($resNormal['is_loq']);
        $this->assertFalse($resNormal['is_uloq']);
        $this->assertEquals('12.346', $resNormal['reportable_value']);
    }

    public function testSpecificationLimitEvaluation(): void
    {
        $limit = [
            'limit_type' => 'range',
            'min_limit' => 98.0,
            'max_limit' => 102.0,
            'warn_min' => 98.5,
            'warn_max' => 101.5
        ];

        // Within spec & normal
        $evalNormal = SpecificationLimit::evaluate($limit, 100.0);
        $this->assertTrue($evalNormal['is_within_spec']);
        $this->assertFalse($evalNormal['is_warn']);

        // Within spec but inside warning band (98.2 < 98.5)
        $evalWarn = SpecificationLimit::evaluate($limit, 98.2);
        $this->assertTrue($evalWarn['is_within_spec']);
        $this->assertTrue($evalWarn['is_warn']);

        // Out of Specification (102.5 > 102.0)
        $evalOos = SpecificationLimit::evaluate($limit, 102.5);
        $this->assertFalse($evalOos['is_within_spec']);
        $this->assertNotNull($evalOos['reason']);

        // LOQ evaluation: <LOQ meets range or max spec limit
        $limitMax = ['limit_type' => 'max', 'max_limit' => 0.1];
        $evalLoq = SpecificationLimit::evaluate($limitMax, 0.02, true);
        $this->assertTrue($evalLoq['is_within_spec']);
    }

    public function testCreateSpecificationDraftVersionAndImmutability(): void
    {
        $uniqueCode = 'SPEC-TEST-' . bin2hex(random_bytes(4));

        // 1. Create specification (starts with Draft Version 1)
        $specId = $this->specService->createSpecification([
            'spec_code' => $uniqueCode,
            'product_id' => $this->testProductId,
            'grade' => 'Ph. Eur.'
        ], 1);

        $this->assertGreaterThan(0, $specId);

        // Fetch created draft version
        $vStmt = $this->db->prepare("SELECT id, version_number, status FROM specification_versions WHERE specification_id = ?");
        $vStmt->execute([$specId]);
        $version = $vStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(1, (int)$version['version_number']);
        $this->assertEquals('Draft', $version['status']);
        $versionId = (int)$version['id'];

        // 2. Fetch a component to add as limit
        $compStmt = $this->db->query("SELECT id FROM components ORDER BY id ASC LIMIT 1");
        $componentId = (int)$compStmt->fetchColumn();
        if ($componentId <= 0) {
            $insComp = $this->db->query("
                INSERT INTO components (component_code, component_name, unit, data_type)
                VALUES ('COMP-ASSAY', 'Assay Quantitative', '%', 'numeric')
                RETURNING id
            ");
            $componentId = (int)$insComp->fetchColumn();
        }

        // Add limit to draft
        $limitId = $this->specService->addOrUpdateLimit($versionId, $componentId, [
            'limit_type' => 'range',
            'min_limit' => 98.0,
            'max_limit' => 102.0,
            'target_value' => 100.0,
            'display_specification' => '98.0 - 102.0 %'
        ], 1);

        $this->assertGreaterThan(0, $limitId);

        // 3. E-Sign & Approve version
        $approveRes = $this->specService->approveVersion($versionId, 1, 'Validation test specification sign-off');
        $this->assertTrue($approveRes['success']);
        $this->assertEquals('Approved', $approveRes['status']);
        $this->assertNotEmpty($approveRes['signature_hash']);

        // 4. Test 21 CFR Part 11 Immutability Trigger: Database must reject deleting or altering approved version
        $this->expectException(\PDOException::class);
        $this->db->exec("DELETE FROM specification_versions WHERE id = {$versionId}");
    }

    public function testActiveSpecificationAndProductTestPlanResolution(): void
    {
        // Ensure at least one analysis is assigned to this product for 'Finished Good'
        $analysisStmt = $this->db->query("SELECT id FROM analyses ORDER BY id ASC LIMIT 1");
        $analysisId = (int)$analysisStmt->fetchColumn();
        $this->planService->assignAnalysisToStage($this->testProductId, 'Finished Good', $analysisId, true, 1);

        // 1. Resolve plan for product & stage
        $resolved = $this->planService->resolveAnalysesAndLimitsForSample($this->testProductId, 'Finished Good');

        $this->assertIsArray($resolved);
        $this->assertEquals($this->testProductId, $resolved['product_id']);
        $this->assertEquals('Finished Good', $resolved['stage']);
        $this->assertNotEmpty($resolved['analyses']);

        // Check that components inside the analyses contain frozen specification limits
        $firstAnalysis = $resolved['analyses'][0];
        $this->assertArrayHasKey('components', $firstAnalysis);
        if (!empty($firstAnalysis['components'])) {
            $firstComp = $firstAnalysis['components'][0];
            $this->assertArrayHasKey('display_specification', $firstComp);
            $this->assertArrayHasKey('rounding_method', $firstComp);
        }
    }
}
