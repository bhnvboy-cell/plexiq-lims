<?php

declare(strict_types=1);

namespace App\Services\Drivers;

use App\Contracts\InstrumentDriverInterface;

/**
 * ASTM E1381 / E1394 Standard Specification for Transferring Information
 * between Clinical Instruments and Computer Systems.
 */
class AstmDriver implements InstrumentDriverInterface
{
    public function getName(): string
    {
        return 'astm_e1394';
    }

    public function getSupportedProtocols(): array
    {
        return ['astm_e1381', 'astm_e1394', 'lis2_a2'];
    }

    public function parseRawData(string $rawContent, array $metadata = []): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rawContent));
        $results = [];
        $currentSample = 'UNKNOWN';

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Strip frame sequence number (e.g. "1H|\^&..." or "2O|...")
            if (strlen($line) > 1 && is_numeric($line[0]) && ctype_alpha($line[1])) {
                $line = substr($line, 1);
            }

            $fields = explode('|', $line);
            $recordType = strtoupper($fields[0] ?? '');

            switch ($recordType) {
                case 'O': // Order record
                    // Field 2 or 3 holds Sample ID / Specimen ID
                    $currentSample = !empty($fields[2]) ? trim($fields[2]) : ($fields[3] ?? 'UNKNOWN');
                    // Handle sub-delimiters like "^^^SMP-001"
                    if (str_contains($currentSample, '^')) {
                        $parts = array_filter(explode('^', $currentSample));
                        $currentSample = end($parts) ?: $currentSample;
                    }
                    break;

                case 'R': // Result record
                    // Field 1: sequence
                    // Field 2: Universal Test ID (e.g. "^^^GLUCOSE")
                    // Field 3: Data or Measurement Value
                    // Field 4: Units
                    // Field 5: Reference Ranges
                    // Field 6: Abnormal Flags (N = Normal, H = High, L = Low)
                    $testIdField = $fields[2] ?? '';
                    $testCode = $testIdField;
                    if (str_contains($testIdField, '^')) {
                        $sub = array_filter(explode('^', $testIdField));
                        $testCode = reset($sub) ?: end($sub) ?: $testIdField;
                    }

                    $rawVal = trim($fields[3] ?? '');
                    $unit = $fields[4] ?? null;
                    $flag = $fields[6] ?? 'N';

                    $numericVal = is_numeric($rawVal) ? (float)$rawVal : $rawVal;

                    $results[] = [
                        'sample_code' => $currentSample,
                        'test_code' => $testCode,
                        'value' => $numericVal,
                        'unit' => $unit,
                        'status' => $flag === 'N' ? 'Valid' : ($flag === 'H' ? 'High' : ($flag === 'L' ? 'Low' : 'Flagged'))
                    ];
                    break;
            }
        }

        return $results;
    }

    public function generateWorklist(array $sequenceEntries): string
    {
        $timestamp = date('YmdHis');
        $out = [];
        $out[] = "H|\\^&|||PlexiQ-LIMS|||||||P|1394-97|{$timestamp}";

        $orderSeq = 1;
        foreach ($sequenceEntries as $entry) {
            $sampleCode = $entry['sample_code'] ?? "SMP-{$orderSeq}";
            $pos = $entry['position'] ?? $orderSeq;
            $type = $entry['sample_type'] ?? 'Sample';
            $method = $entry['method'] ?? 'GENERAL';

            $out[] = "P|{$orderSeq}||||||||||||||||||||";
            $out[] = "O|{$orderSeq}|{$sampleCode}|{$pos}^^{$type}|^^^{$method}|||||||A||||||||||||||Q";
            $orderSeq++;
        }

        $out[] = "L|1|N";
        return implode("\r\n", $out) . "\r\n";
    }
}
