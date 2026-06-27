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
}
