<?php

namespace App\Services;

use App\Services\Instruments\XmlParser;
use App\Services\Instruments\CsvParser;
use App\Services\Instruments\TextParser;

class InstrumentImportService
{
    public function parseFile(string $filePath, string $format): array
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException("Cannot read file: {$filePath}");
        }
        return $this->parseRaw($raw, $format);
    }

    public function parseRaw(string $rawData, string $format): array
    {
        $parser = match (strtoupper($format)) {
            'XML' => new XmlParser(),
            'CSV' => new CsvParser(),
            'TSV' => new CsvParser("\t"),
            'TEXT', 'TXT' => new TextParser(),
            default => throw new \RuntimeException("Unsupported format: {$format}"),
        };
        return $parser->parse($rawData);
    }

    public function importResults(int $instrumentId, array $parsedEntries, int $userId): array
    {
        $db = \App\Helpers\Database::connect();
        $imported = 0;
        $failed = 0;
        $errors = [];

        $insertStmt = $db->prepare("
            INSERT INTO instrument_results (instrument_id, sample_code, test_code, raw_data, parsed_data, result_value, result_text, unit, status, imported_by)
            VALUES (?, ?, ?, ?, ?::jsonb, ?, ?, ?, 'Pending', ?)
        ");

        foreach ($parsedEntries as $entry) {
            try {
                $value = $entry['result_value'] ?? null;
                if ($value !== null && !is_numeric($value)) {
                    $textResult = $value;
                    $value = null;
                } else {
                    $textResult = $entry['result_text'] ?? null;
                }

                $insertStmt->execute([
                    $instrumentId,
                    $entry['sample_code'] ?? null,
                    $entry['test_code'] ?? null,
                    $entry['raw'] ?? null,
                    json_encode($entry),
                    $value !== null ? (float)$value : null,
                    $textResult,
                    $entry['unit'] ?? null,
                    $userId,
                ]);
                $imported++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
            'total' => $imported + $failed,
        ];
    }

    public function matchToSampleTest(int $instrumentResultId): bool
    {
        $db = \App\Helpers\Database::connect();

        $stmt = $db->prepare("SELECT * FROM instrument_results WHERE id = ?");
        $stmt->execute([$instrumentResultId]);
        $ir = $stmt->fetch();

        if (!$ir || $ir['status'] !== 'Pending') return false;

        // Find matching sample_test by sample_code + test_code
        $matchStmt = $db->prepare("
            SELECT st.id FROM sample_tests st
            JOIN samples s ON st.sample_id = s.id
            JOIN tests t ON st.test_id = t.id
            WHERE s.sample_code = ? AND t.test_code = ? AND st.status IN ('Pending','In Progress')
            LIMIT 1
        ");
        $matchStmt->execute([$ir['sample_code'], $ir['test_code']]);
        $match = $matchStmt->fetch();

        if ($match) {
            $db->prepare("UPDATE instrument_results SET sample_test_id = ?, status = 'Matched' WHERE id = ?")
               ->execute([$match['id'], $instrumentResultId]);

            // Auto-create the result entry
            $value = $ir['result_value'];
            $text = $ir['result_text'];
            $isWithinSpec = null;

            // Check specs
            $specStmt = $db->prepare("SELECT min_spec_limit, max_spec_limit FROM tests t JOIN sample_tests st ON t.id = st.test_id WHERE st.id = ?");
            $specStmt->execute([$match['id']]);
            $spec = $specStmt->fetch();

            if ($spec && $value !== null && $spec['min_spec_limit'] !== null && $spec['max_spec_limit'] !== null) {
                $isWithinSpec = (float)$value >= (float)$spec['min_spec_limit'] && (float)$value <= (float)$spec['max_spec_limit'];
            }

            $insertResult = $db->prepare("
                INSERT INTO results (sample_test_id, result_value, result_text, is_within_spec, entered_by, remarks)
                VALUES (?, ?, ?, ?, ?, 'Auto-imported from instrument')
            ");
            $insertResult->execute([$match['id'], $value, $text, $isWithinSpec, $ir['imported_by']]);

            // Update sample_test status
            $db->prepare("UPDATE sample_tests SET status = 'Completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?")
               ->execute([$match['id']]);

            $db->prepare("UPDATE instrument_results SET status = 'Imported' WHERE id = ?")
               ->execute([$instrumentResultId]);

            \App\Helpers\Audit::log('Instrument Result Imported', 'instrument_results', $instrumentResultId);
            return true;
        }

        return false;
    }

    public function autoMatchAll(): array
    {
        $db = \App\Helpers\Database::connect();
        $pending = $db->query("SELECT id FROM instrument_results WHERE status = 'Pending'")->fetchAll();
        $matched = 0;

        foreach ($pending as $p) {
            if ($this->matchToSampleTest((int)$p['id'])) {
                $matched++;
            }
        }

        return ['matched' => $matched, 'total' => count($pending)];
    }

    /**
     * Column-mapped import pipeline (instrument auto-fetch).
     *
     * Resolves each parsed entry to a sample (by sample_code), then for every
     * active instrument_parameter_mapping present in the entry writes the value
     * into sample_analysis_parameters with spec validation, unit conversion and
     * automatic OOS detection. Also records the raw row in instrument_results.
     */
    public function importMappedResults(int $instrumentId, array $parsedEntries, int $userId, ?string $sourceFile = null): array
    {
        $db = \App\Helpers\Database::connect();
        $mappings = \App\Models\InstrumentParameterMapping::activeForInstrument($instrumentId);
        $parameterService = new AnalysisParameterService();

        $stats = ['rows' => 0, 'written' => 0, 'oos' => 0, 'unmapped' => 0, 'errors' => []];
        $insertIr = $db->prepare("
            INSERT INTO instrument_results
                (instrument_id, sample_test_id, sample_code, test_code, raw_data, parsed_data,
                 result_value, result_text, unit, status, imported_by, source_file)
            VALUES (?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?, 'Imported', ?, ?)
        ");

        foreach ($parsedEntries as $entry) {
            $stats['rows']++;
            $sampleCode = $entry['sample_code'] ?? null;
            if (!$sampleCode) {
                $stats['unmapped']++;
                continue;
            }

            // Resolve sample
            $sampleStmt = $db->prepare("SELECT id, batch_number FROM samples WHERE sample_code = ? LIMIT 1");
            $sampleStmt->execute([$sampleCode]);
            $sample = $sampleStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$sample) {
                $stats['errors'][] = "Sample {$sampleCode} not found";
                $stats['unmapped']++;
                continue;
            }

            $matchedColumns = 0;
            foreach ($mappings as $mapping) {
                $rawValue = $this->entryValue($entry, $mapping['source_column']);
                if ($rawValue === null || $rawValue === '') {
                    continue;
                }
                $matchedColumns++;

                $value = $this->applyConversion($rawValue, $mapping['conversion_factor']);
                $parameter = $this->loadParameter((int)$mapping['parameter_id']);

                $resultText = null;
                $numericValue = null;
                if ($parameter && $parameter['data_type'] === 'numeric') {
                    if (!is_numeric($value)) {
                        $stats['errors'][] = "Non-numeric value '{$rawValue}' for {$mapping['source_column']} (sample {$sampleCode})";
                        $resultText = (string)$value;
                    } else {
                        $numericValue = (float)$value;
                    }
                } else {
                    $resultText = (string)$value;
                }

                $validation = $parameterService->validateValue($parameter, $numericValue);
                $withinSpec = $validation['within_spec'] === null ? null : ($validation['within_spec'] ? 't' : 'f');

                // Upsert into sample_analysis_parameters
                $upsert = $db->prepare("
                    INSERT INTO sample_analysis_parameters
                        (sample_id, parameter_id, spec_min, spec_max, spec_target, unit,
                         result_value, result_text, is_within_spec, status, analyst_notes,
                         entered_by, entered_at, source, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', ?, ?, CURRENT_TIMESTAMP, 'instrument', CURRENT_TIMESTAMP)
                    ON CONFLICT (sample_id, parameter_id) DO UPDATE SET
                        result_value = EXCLUDED.result_value,
                        result_text = EXCLUDED.result_text,
                        is_within_spec = EXCLUDED.is_within_spec,
                        status = 'Completed',
                        entered_by = EXCLUDED.entered_by,
                        entered_at = CURRENT_TIMESTAMP,
                        source = 'instrument',
                        updated_at = CURRENT_TIMESTAMP
                    RETURNING id
                ");
                $upsert->execute([
                    $sample['id'],
                    (int)$mapping['parameter_id'],
                    $parameter['spec_min'] ?? null,
                    $parameter['spec_max'] ?? null,
                    $parameter['spec_target'] ?? null,
                    $mapping['unit'] ?: ($parameter['unit'] ?? null),
                    $numericValue,
                    $resultText,
                    $withinSpec,
                    $validation['within_spec'] === false
                        ? "Auto-imported OOS: " . ($validation['reason'] ?? 'out of spec')
                        : 'Auto-imported from instrument',
                    $userId,
                ]);
                $sapId = (int)$upsert->fetchColumn();
                $stats['written']++;

                // Raw instrument row (dedupe by source_file + row)
                $rawRow = $entry['raw'] ?? null;
                $insertIr->execute([
                    $instrumentId,
                    null,
                    $sampleCode,
                    $mapping['source_column'],
                    $rawRow,
                    json_encode($entry),
                    $numericValue,
                    $resultText,
                    $mapping['unit'] ?: ($parameter['unit'] ?? null),
                    $userId,
                    $sourceFile,
                ]);

                if ($validation['within_spec'] === false) {
                    $stats['oos']++;
                    $sapRow = $db->prepare("
                        SELECT sap.*, ap.parameter_code, ap.parameter_name, ap.specification_text
                        FROM sample_analysis_parameters sap
                        JOIN analysis_parameters ap ON sap.parameter_id = ap.id
                        WHERE sap.id = ?
                    ");
                    $sapRow->execute([$sapId]);
                    $parameterService->createOosRecord($sapRow->fetch(\PDO::FETCH_ASSOC), $validation['reason']);
                }
            }

            if ($matchedColumns === 0) {
                $stats['unmapped']++;
            }
        }

        \App\Helpers\Audit::log('Instrument Results Mapped & Imported', 'instruments', $instrumentId, null, [
            'rows' => $stats['rows'],
            'written' => $stats['written'],
            'oos' => $stats['oos'],
            'source_file' => $sourceFile,
        ]);

        return $stats;
    }

    private function loadParameter(int $parameterId): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM analysis_parameters WHERE id = ?");
        $stmt->execute([$parameterId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Resolve the value for a mapped source column.
     * Checks the raw column map first, then normalized entry keys.
     */
    private function entryValue(array $entry, string $sourceColumn): ?string
    {
        $needle = strtolower(trim($sourceColumn));
        $columns = $entry['columns'] ?? [];
        foreach ($columns as $col => $val) {
            if (strtolower((string)$col) === $needle) {
                return $val === '' ? null : (string)$val;
            }
        }
        foreach ($entry as $key => $val) {
            if ($key === 'columns' || $key === 'raw') continue;
            if (strtolower((string)$key) === $needle) {
                return $val === null || $val === '' ? null : (string)$val;
            }
        }
        return null;
    }

    private function applyConversion($value, $factor): string
    {
        $factor = $factor === null ? 1 : (float)$factor;
        if ($factor == 1) {
            return (string)$value;
        }
        if (is_numeric($value)) {
            return (string)((float)$value * $factor);
        }
        return (string)$value;
    }
}
