<?php

declare(strict_types=1);

namespace App\Services\Drivers;

use App\Contracts\InstrumentDriverInterface;

/**
 * Chromatography Data System (CDS) Parser & Worklist Generator
 * Compatible with Waters Empower Sample Sets, Thermo Chromeleon Sequences, and Agilent OpenLab exports.
 */
class CdsCsvDriver implements InstrumentDriverInterface
{
    public function getName(): string
    {
        return 'cds_csv';
    }

    public function getSupportedProtocols(): array
    {
        return ['waters_empower_csv', 'chromeleon_sequence_csv', 'agilent_openlab_csv', 'delimited_text'];
    }

    public function parseRawData(string $rawContent, array $metadata = []): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rawContent));
        if (empty($lines)) return [];

        // Detect delimiter (comma, tab, or semicolon)
        $firstLine = $lines[0];
        $delimiter = str_contains($firstLine, "\t") ? "\t" : (str_contains($firstLine, ';') ? ';' : ',');

        $header = str_getcsv(array_shift($lines), $delimiter);
        $headerMap = [];
        foreach ($header as $idx => $col) {
            $cleaned = strtolower(trim(str_replace(['"', "'", ' ', '_', '%'], '', $col)));
            $headerMap[$cleaned] = $idx;
        }

        $results = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $row = str_getcsv($line, $delimiter);
            if (count($row) < 2) continue;

            // Extract Sample Identifier
            $sampleCode = $this->extractField($row, $headerMap, ['samplename', 'sampleid', 'samplecode', 'sample', 'vial']);
            if (empty($sampleCode)) {
                $sampleCode = $metadata['default_sample_code'] ?? 'UNKNOWN';
            }

            // Extract Peak/Analyte Name
            $peakName = $this->extractField($row, $headerMap, ['peakname', 'compound', 'analytename', 'componentname', 'name']);
            if (empty($peakName)) {
                $peakName = $metadata['default_test_code'] ?? 'ASSAY';
            }

            // Extract Quantitative Value (Amount, Concentration, % Area, or Area)
            $amountVal = $this->extractField($row, $headerMap, ['amount', 'concentration', 'result', 'quant', 'potency']);
            $areaVal = $this->extractField($row, $headerMap, ['area', 'peakarea', 'response']);
            $rtVal = $this->extractField($row, $headerMap, ['retentiontime', 'ret.time', 'rt', 'time']);
            $unit = $this->extractField($row, $headerMap, ['units', 'unit']);
            $vial = $this->extractField($row, $headerMap, ['vial', 'vialposition', 'pos']);

            $numericVal = !empty($amountVal) && is_numeric($amountVal)
                ? (float)$amountVal
                : (!empty($areaVal) && is_numeric($areaVal) ? (float)$areaVal : 0.0);

            $results[] = [
                'sample_code' => $sampleCode,
                'test_code' => $peakName,
                'value' => $numericVal,
                'unit' => $unit ?: '%',
                'retention_time' => is_numeric($rtVal) ? (float)$rtVal : null,
                'peak_area' => is_numeric($areaVal) ? (float)$areaVal : null,
                'vial_position' => $vial ?: null,
                'status' => 'Valid'
            ];
        }

        return $results;
    }

    public function generateWorklist(array $sequenceEntries): string
    {
        // Standard Empower / Chromeleon Sequence Header
        $rows = [];
        $rows[] = implode(',', ['Vial', 'SampleName', 'SampleType', 'MethodSet', 'InjVol', 'RunTime_Min']);

        foreach ($sequenceEntries as $idx => $entry) {
            $vial = $entry['position'] ?? ($idx + 1);
            $name = $entry['sample_code'] ?? "Sample_{$vial}";
            $type = $entry['sample_type'] ?? 'Sample';
            $method = $entry['method'] ?? 'QC_Assay_Method';
            $vol = $entry['injection_volume_ul'] ?? 10.0;
            $runtime = 15.0;

            $rows[] = implode(',', [
                '"' . $vial . '"',
                '"' . $name . '"',
                '"' . $type . '"',
                '"' . $method . '"',
                $vol,
                $runtime
            ]);
        }

        return implode("\r\n", $rows) . "\r\n";
    }

    private function extractField(array $row, array $headerMap, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (isset($headerMap[$alias]) && isset($row[$headerMap[$alias]])) {
                $val = trim($row[$headerMap[$alias]]);
                if ($val !== '') return $val;
            }
        }
        return null;
    }
}
