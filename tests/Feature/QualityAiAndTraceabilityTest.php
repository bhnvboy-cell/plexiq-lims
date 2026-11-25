<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Services\Ai\QualityAiAdvisorService;
use App\Services\Validation\TraceabilityService;
use App\Controllers\BenchController;

class QualityAiAndTraceabilityTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::connect();
        $_SESSION = [
            'user_id' => 1,
            'role' => 'QA Manager',
            'username' => 'qa_advisor_lead'
        ];
    }

    public function testQualityAiIshikawaAnd5WhysAnalysis(): void
    {
        $advisor = new QualityAiAdvisorService();

        // 1. Machine category trigger (HPLC column pressure)
        $analysisMachine = $advisor->analyzeIncident('oos', 101, [
            'description' => 'HPLC column backpressure exceeded 400 bar and baseline drift observed',
            'test_name' => 'Assay HPLC'
        ]);
        $this->assertSame('machine', $analysisMachine['ishikawa_category']);
        $this->assertCount(5, $analysisMachine['five_whys_chain']);
        $this->assertNotEmpty($analysisMachine['recommended_preventive_actions']);
        $this->assertTrue($analysisMachine['ai_drafted']);
        $this->assertSame('pending', $analysisMachine['review_status']);

        // 2. Material category trigger (reagent lot issue)
        $analysisMaterial = $advisor->analyzeIncident('deviation', 102, [
            'description' => 'Contaminant peak found in mobile phase organic solvent bottle',
            'test_name' => 'Related Substances'
        ]);
        $this->assertSame('material', $analysisMaterial['ishikawa_category']);

        // 3. Human sign-off on suggestion (21 CFR Part 11)
        $reviewed = $advisor->reviewSuggestion($analysisMachine['id'], 1, 'accepted', 'Guard column replaced and verified.');
        $this->assertSame('accepted', $reviewed['review_status']);
        $this->assertSame(1, $reviewed['reviewed_by']);
    }

    public function testValidationRequirementsTraceabilityMatrix(): void
    {
        $traceService = new TraceabilityService();

        // 1. Seed baseline GAMP 5 requirements
        $count = $traceService->seedRequirements();
        $this->assertGreaterThan(5, $count);

        // 2. Generate matrix and verify completeness
        $rtm = $traceService->generateTraceabilityMatrix();
        $this->assertGreaterThan(0, $rtm['total_requirements']);
        $this->assertSame(100.0, $rtm['coverage_percentage']);
        $this->assertArrayHasKey('URS', $rtm['requirements_by_type']);
        $this->assertArrayHasKey('FS', $rtm['requirements_by_type']);
        $this->assertArrayHasKey('DS', $rtm['requirements_by_type']);
        $this->assertNotEmpty($rtm['matrix']);
    }

    public function testBenchControllerDirectResultEntry(): void
    {
        // Find or prepare an existing sample_test
        $st = $this->db->query("
            SELECT st.id, t.min_spec_limit, t.max_spec_limit
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            WHERE t.min_spec_limit IS NOT NULL AND t.max_spec_limit IS NOT NULL
            LIMIT 1
        ")->fetch(\PDO::FETCH_ASSOC);

        if (!$st) {
            $this->markTestSkipped('No sample_test with min/max limits found for bench test.');
        }

        $testId = (int)$st['id'];
        $min = (float)$st['min_spec_limit'];
        $max = (float)$st['max_spec_limit'];
        $passVal = round(($min + $max) / 2, 2);

        // Simulate POST to bench save-result with in-spec value
        $_POST['sample_test_id'] = $testId;
        $_POST['value'] = (string)$passVal;

        $controller = new BenchController();
        $resJson = $controller->saveResult();
        $res = json_decode($resJson, true);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['is_within_spec']);
        $this->assertSame('Completed', $res['status']);

        // Test OOS value (greater than max)
        $_POST['value'] = (string)($max + 50.0);
        $oosJson = $controller->saveResult();
        $oosRes = json_decode($oosJson, true);

        $this->assertTrue($oosRes['success']);
        $this->assertFalse($oosRes['is_within_spec']);
        $this->assertSame('Rejected', $oosRes['status']);
    }
}
