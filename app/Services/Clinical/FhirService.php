<?php

namespace App\Services\Clinical;

use App\Helpers\Database;

class FhirService
{
    /**
     * Create or format a FHIR R4 Observation resource from a test result
     *
     * @param array<string, mixed> $testData Test and sample attributes
     * @param bool $persist Whether to save into fhir_resources table
     * @return array<string, mixed> FHIR R4 Observation resource
     */
    public function createObservation(array $testData, bool $persist = false): array
    {
        $id = $testData['resource_id'] ?? ('obs-' . ($testData['sample_test_id'] ?? uniqid()));
        $loincCode = $testData['loinc_code'] ?? '14682-9'; // Default e.g. Creatinine or generic lab analyte
        $testName = $testData['test_name'] ?? 'Laboratory Test';
        $status = $this->mapStatusToFhir($testData['status'] ?? 'completed');
        $effectiveDate = $testData['completed_at'] ?? date('c');

        $isNumeric = is_numeric($testData['result_value'] ?? null);
        $valueElement = [];
        if ($isNumeric) {
            $valueElement['valueQuantity'] = [
                'value' => (float)$testData['result_value'],
                'unit' => $testData['unit'] ?? '',
                'system' => 'http://unitsofmeasure.org',
                'code' => $testData['unit'] ?? ''
            ];
        } else {
            $valueElement['valueString'] = (string)($testData['result_value'] ?? '');
        }

        $resource = [
            'resourceType' => 'Observation',
            'id' => $id,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => date('c'),
                'profile' => [
                    'http://hl7.org/fhir/StructureDefinition/Observation'
                ]
            ],
            'status' => $status,
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                            'code' => 'laboratory',
                            'display' => 'Laboratory'
                        ]
                    ]
                ]
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://loinc.org',
                        'code' => $loincCode,
                        'display' => $testName
                    ]
                ],
                'text' => $testName
            ],
            'subject' => [
                'reference' => 'Patient/' . ($testData['patient_id'] ?? 'ANONYMIZED-SUBJECT'),
                'display' => $testData['patient_name'] ?? 'Sample ' . ($testData['sample_code'] ?? 'Unknown')
            ],
            'effectiveDateTime' => $effectiveDate,
            'issued' => date('c'),
            'performer' => [
                [
                    'reference' => 'Practitioner/' . ($testData['analyst_id'] ?? '1'),
                    'display' => $testData['analyst_name'] ?? 'PlexiQ Laboratory Analyst'
                ]
            ]
        ];

        // Merge value element
        $resource = array_merge($resource, $valueElement);

        // Reference range
        if (isset($testData['min_limit']) || isset($testData['max_limit'])) {
            $range = [];
            if (isset($testData['min_limit']) && is_numeric($testData['min_limit'])) {
                $range['low'] = ['value' => (float)$testData['min_limit'], 'unit' => $testData['unit'] ?? ''];
            }
            if (isset($testData['max_limit']) && is_numeric($testData['max_limit'])) {
                $range['high'] = ['value' => (float)$testData['max_limit'], 'unit' => $testData['unit'] ?? ''];
            }
            if (!empty($testData['specification_text'])) {
                $range['text'] = $testData['specification_text'];
            }
            $resource['referenceRange'] = [$range];
        }

        if ($persist) {
            $this->persistResource('Observation', $id, $resource, $testData['sample_id'] ?? null, $testData['sample_test_id'] ?? null);
        }

        return $resource;
    }

    /**
     * Create a FHIR R4 DiagnosticReport resource grouping observations for a sample
     *
     * @param array<string, mixed> $sampleData
     * @param array<int, array<string, mixed>> $observations
     * @param bool $persist
     * @return array<string, mixed>
     */
    public function createDiagnosticReport(array $sampleData, array $observations, bool $persist = false): array
    {
        $id = 'diag-' . ($sampleData['sample_id'] ?? uniqid());
        $status = 'final';

        $resultReferences = [];
        foreach ($observations as $obs) {
            $obsId = $obs['id'] ?? uniqid();
            $resultReferences[] = [
                'reference' => 'Observation/' . $obsId,
                'display' => $obs['code']['text'] ?? 'Observation'
            ];
        }

        $report = [
            'resourceType' => 'DiagnosticReport',
            'id' => $id,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => date('c'),
                'profile' => ['http://hl7.org/fhir/StructureDefinition/DiagnosticReport']
            ],
            'status' => $status,
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0074',
                            'code' => 'LAB',
                            'display' => 'Laboratory'
                        ]
                    ]
                ]
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://loinc.org',
                        'code' => '11502-2',
                        'display' => 'Laboratory report'
                    ]
                ],
                'text' => 'Laboratory Certificate of Analysis Report'
            ],
            'subject' => [
                'reference' => 'Specimen/' . ($sampleData['sample_code'] ?? 'SPEC-01'),
                'display' => 'Sample ' . ($sampleData['sample_code'] ?? '') . ' - ' . ($sampleData['sample_name'] ?? '')
            ],
            'effectiveDateTime' => $sampleData['collection_date'] ?? date('c'),
            'issued' => date('c'),
            'result' => $resultReferences,
            'conclusion' => $sampleData['conclusion'] ?? 'All quality specifications met release criteria.'
        ];

        if ($persist) {
            $this->persistResource('DiagnosticReport', $id, $report, $sampleData['sample_id'] ?? null, null);
        }

        return $report;
    }

    /**
     * Build a FHIR R4 Bundle containing DiagnosticReport and all related Observations
     *
     * @param array<string, mixed> $diagnosticReport
     * @param array<int, array<string, mixed>> $observations
     * @return array<string, mixed>
     */
    public function createBundle(array $diagnosticReport, array $observations): array
    {
        $entries = [];

        // Add DiagnosticReport entry
        $entries[] = [
            'fullUrl' => 'urn:uuid:' . ($diagnosticReport['id'] ?? uniqid()),
            'resource' => $diagnosticReport
        ];

        // Add Observation entries
        foreach ($observations as $obs) {
            $entries[] = [
                'fullUrl' => 'urn:uuid:' . ($obs['id'] ?? uniqid()),
                'resource' => $obs
            ];
        }

        return [
            'resourceType' => 'Bundle',
            'id' => 'bundle-' . uniqid(),
            'type' => 'collection',
            'timestamp' => date('c'),
            'total' => count($entries),
            'entry' => $entries
        ];
    }

    private function persistResource(string $type, string $resourceId, array $payload, ?int $sampleId = null, ?int $sampleTestId = null): void
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO fhir_resources (resource_type, resource_id, sample_id, sample_test_id, payload, fhir_version, updated_at)
            VALUES (?, ?, ?, ?, ?, 'R4', CURRENT_TIMESTAMP)
            ON CONFLICT (resource_id) DO UPDATE SET
                payload = EXCLUDED.payload,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $type,
            $resourceId,
            $sampleId,
            $sampleTestId,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ]);
    }

    private function mapStatusToFhir(string $limsStatus): string
    {
        return match (strtolower($limsStatus)) {
            'pending', 'in_progress', 'draft' => 'preliminary',
            'completed', 'approved', 'pass' => 'final',
            'amended', 'revised' => 'amended',
            'rejected', 'cancelled' => 'cancelled',
            default => 'final'
        };
    }
}
