<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Services\Clinical\FhirService;
use App\Services\Clinical\Hl7MessageService;
use App\Controllers\AnalyticsStreamController;

class ClinicalAndStreamingTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::connect();
        $_SESSION = [
            'user_id' => 1,
            'role' => 'Admin',
            'username' => 'admin_tester'
        ];
    }

    public function testFhirObservationGenerationAndStorage(): void
    {
        $st = $this->db->query("SELECT id, sample_id FROM sample_tests LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        $sampleTestId = $st ? (int)$st['id'] : null;
        $sampleId = $st ? (int)$st['sample_id'] : null;

        $service = new FhirService();
        $testData = [
            'sample_test_id' => $sampleTestId,
            'sample_id' => $sampleId,
            'loinc_code' => '14682-9',
            'test_name' => 'High Purity Assay',
            'status' => 'completed',
            'result_value' => '99.85',
            'unit' => '%',
            'min_limit' => 98.0,
            'max_limit' => 102.0,
            'specification_text' => '98.0% - 102.0%'
        ];

        $observation = $service->createObservation($testData, true);

        $this->assertSame('Observation', $observation['resourceType']);
        $this->assertSame('final', $observation['status']);
        $this->assertSame('laboratory', $observation['category'][0]['coding'][0]['code']);
        $this->assertSame('14682-9', $observation['code']['coding'][0]['code']);
        $this->assertSame(99.85, $observation['valueQuantity']['value']);
        $this->assertSame('%', $observation['valueQuantity']['unit']);
        $this->assertNotEmpty($observation['referenceRange']);

        // Check database persistence
        $stmt = $this->db->prepare("SELECT * FROM fhir_resources WHERE resource_id = ?");
        $stmt->execute([$observation['id']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row);
        $this->assertSame('Observation', $row['resource_type']);
        $this->assertSame('R4', $row['fhir_version']);
    }

    public function testFhirDiagnosticReportAndBundle(): void
    {
        $service = new FhirService();

        $obs1 = $service->createObservation([
            'sample_test_id' => 9992,
            'test_name' => 'Assay',
            'result_value' => '100.1',
            'unit' => '%'
        ]);

        $obs2 = $service->createObservation([
            'sample_test_id' => 9993,
            'test_name' => 'pH Level',
            'result_value' => '7.2',
            'unit' => 'pH'
        ]);

        $s = $this->db->query("SELECT id, sample_code FROM samples LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        $sampleId = $s ? (int)$s['id'] : null;
        $sampleCode = $s ? $s['sample_code'] : 'SPL-CLIN-001';

        $sampleData = [
            'sample_id' => $sampleId,
            'sample_code' => $sampleCode,
            'sample_name' => 'Blood Plasma Sample',
            'conclusion' => 'Acceptable for clinical trial dosing.'
        ];

        $report = $service->createDiagnosticReport($sampleData, [$obs1, $obs2], true);
        $this->assertSame('DiagnosticReport', $report['resourceType']);
        $this->assertSame('final', $report['status']);
        $this->assertCount(2, $report['result']);

        $bundle = $service->createBundle($report, [$obs1, $obs2]);
        $this->assertSame('Bundle', $bundle['resourceType']);
        $this->assertSame('collection', $bundle['type']);
        $this->assertSame(3, $bundle['total']); // 1 report + 2 observations
    }

    public function testHl7OruR01GenerationAndParsing(): void
    {
        $hl7Service = new Hl7MessageService();

        $sample = [
            'sample_code' => 'CLIN-ORD-2026-99',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $results = [
            [
                'loinc_code' => '14682-9',
                'test_name' => 'Serum Creatinine',
                'result_value' => '1.05',
                'unit' => 'mg/dL',
                'min_limit' => 0.7,
                'max_limit' => 1.3,
                'status' => 'pass',
                'notes' => 'Normal kidney function indicator.'
            ],
            [
                'loinc_code' => '2345-7',
                'test_name' => 'Glucose Fasting',
                'result_value' => '95',
                'unit' => 'mg/dL',
                'min_limit' => 70,
                'max_limit' => 99,
                'status' => 'pass'
            ]
        ];

        $patient = [
            'patient_id' => 'PAT-887766',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'dob' => '19900515',
            'gender' => 'F'
        ];

        $hl7Raw = $hl7Service->generateOruR01($sample, $results, $patient);

        $this->assertStringContainsString('MSH|^~\\&|PLEXIQ_LIMS', $hl7Raw);
        $this->assertStringContainsString('PID|1|PAT-887766', $hl7Raw);
        $this->assertStringContainsString('OBR|1|CLIN-ORD-2026-99', $hl7Raw);
        $this->assertStringContainsString('OBX|1|NM|14682-9^Serum Creatinine^LN|1|1.05|mg/dL', $hl7Raw);
        $this->assertStringContainsString('OBX|2|NM|2345-7^Glucose Fasting^LN|1|95|mg/dL', $hl7Raw);
        $this->assertStringContainsString('NTE|1|L|Normal kidney function indicator.', $hl7Raw);

        // Parse back
        $parsed = $hl7Service->parseMessage($hl7Raw);
        $this->assertTrue($parsed['success']);
        $this->assertSame('PLEXIQ_LIMS', $parsed['segments']['msh']['sending_app']);
        $this->assertSame('PAT-887766', $parsed['segments']['pid']['patient_id']);
        $this->assertSame('CLIN-ORD-2026-99', $parsed['segments']['obr']['placer_order_number']);
        $this->assertCount(2, $parsed['segments']['obx']);
        $this->assertSame('1.05', $parsed['segments']['obx'][0]['value']);
        $this->assertSame('95', $parsed['segments']['obx'][1]['value']);
    }

    public function testAnalyticsStreamAndBiFeed(): void
    {
        $controller = new AnalyticsStreamController();

        // 1. NDJSON Stream test
        $streamOutput = $controller->stream();
        $this->assertNotEmpty($streamOutput);
        $lines = explode("\n", trim($streamOutput));
        $this->assertGreaterThan(0, count($lines));

        // Validate each line is valid JSON
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            $this->assertIsArray($decoded, "Line must be valid JSON: {$line}");
            $this->assertArrayHasKey('entity', $decoded);
            $this->assertArrayHasKey('timestamp', $decoded);
        }

        // 2. BI Feed test
        $biJson = $controller->biFeed();
        $this->assertNotEmpty($biJson);
        $biData = json_decode($biJson, true);
        $this->assertIsArray($biData);
        $this->assertArrayHasKey('feed_metadata', $biData);
        $this->assertArrayHasKey('kpis', $biData);
        $this->assertArrayHasKey('status_distribution', $biData);
        $this->assertArrayHasKey('turn_around_time', $biData);
    }
}
