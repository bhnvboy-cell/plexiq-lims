<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Database;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Services\Clinical\FhirService;
use App\Services\Clinical\Hl7MessageService;

class ClinicalController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = Database::connect();

        $fhirResources = $db->query("
            SELECT fr.id, fr.resource_type, fr.resource_id, fr.fhir_version, fr.created_at,
                   s.sample_code, COALESCE(p.product_name, s.sample_nature, 'Clinical Specimen') as sample_name
            FROM fhir_resources fr
            LEFT JOIN samples s ON fr.sample_id = s.id
            LEFT JOIN products p ON s.product_id = p.id
            ORDER BY fr.id DESC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $samples = $db->query("
            SELECT s.id, s.sample_code, COALESCE(p.product_name, s.sample_nature, 'Clinical Specimen') as sample_name, s.status, s.created_at
            FROM samples s
            LEFT JOIN products p ON s.product_id = p.id
            ORDER BY s.id DESC
            LIMIT 20
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('clinical.index', [
            'fhirResources' => $fhirResources,
            'samples' => $samples
        ]);
    }

    public function fhirObservation(int $sampleTestId): string
    {
        Auth::requireAuth();
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT st.*, t.test_name, t.test_code as loinc_code, un.unit_code as unit,
                   s.sample_code, COALESCE(p.product_name, s.sample_nature, 'Clinical Specimen') as sample_name,
                   ua.full_name AS analyst_name,
                   r.result_value, r.result_text
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            JOIN samples s ON st.sample_id = s.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN units un ON t.unit_id = un.id
            LEFT JOIN users ua ON st.assigned_to = ua.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.deleted_at IS NULL
            WHERE st.id = ?
        ");
        $stmt->execute([$sampleTestId]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$data) {
            return $this->json(['error' => 'Sample test not found'], 404);
        }

        $service = new FhirService();
        $observation = $service->createObservation($data, true);

        return $this->json($observation);
    }

    public function fhirDiagnosticReport(int $sampleId): string
    {
        Auth::requireAuth();
        $db = Database::connect();

        $stmt = $db->prepare("SELECT * FROM samples WHERE id = ?");
        $stmt->execute([$sampleId]);
        $sample = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$sample) {
            return $this->json(['error' => 'Sample not found'], 404);
        }

        $testStmt = $db->prepare("
            SELECT st.*, t.test_name, t.test_code as loinc_code, un.unit_code as unit,
                   ua.full_name AS analyst_name,
                   r.result_value, r.result_text
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN units un ON t.unit_id = un.id
            LEFT JOIN users ua ON st.assigned_to = ua.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.deleted_at IS NULL
            WHERE st.sample_id = ?
        ");
        $testStmt->execute([$sampleId]);
        $tests = $testStmt->fetchAll(\PDO::FETCH_ASSOC);

        $fhirService = new FhirService();
        $observations = [];
        foreach ($tests as $t) {
            $t['sample_code'] = $sample['sample_code'];
            $t['sample_name'] = $sample['sample_nature'] ?? 'Clinical Specimen';
            $observations[] = $fhirService->createObservation($t, true);
        }

        $report = $fhirService->createDiagnosticReport($sample, $observations, true);
        $bundle = $fhirService->createBundle($report, $observations);

        Audit::log('FHIR R4 Diagnostic Bundle Generated', 'samples', $sampleId, null, [
            'total_observations' => count($observations)
        ]);

        return $this->json($bundle);
    }

    public function hl7Export(int $sampleId): string
    {
        Auth::requireAuth();
        $db = Database::connect();

        $stmt = $db->prepare("SELECT * FROM samples WHERE id = ?");
        $stmt->execute([$sampleId]);
        $sample = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$sample) {
            return $this->json(['error' => 'Sample not found'], 404);
        }

        $testStmt = $db->prepare("
            SELECT st.*, t.test_name, t.test_code as loinc_code, un.unit_code as unit,
                   r.result_value, r.result_text
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN units un ON t.unit_id = un.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.deleted_at IS NULL
            WHERE st.sample_id = ?
        ");
        $testStmt->execute([$sampleId]);
        $tests = $testStmt->fetchAll(\PDO::FETCH_ASSOC);

        $hl7Service = new Hl7MessageService();
        $hl7Message = $hl7Service->generateOruR01($sample, $tests, [
            'patient_id' => 'PAT-' . $sampleId,
            'first_name' => 'Clinical',
            'last_name' => 'Subject'
        ]);

        Audit::log('HL7 v2.5.1 ORU^R01 Exported', 'samples', $sampleId);

        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="HL7_ORU_' . $sample['sample_code'] . '.hl7"');
        }

        return $hl7Message;
    }
}
