<?php

namespace App\Services\Instruments;

class CsvParser implements InstrumentParserInterface
{
    private string $delimiter = ',';

    public function __construct(?string $delimiter = null)
    {
        if ($delimiter !== null) $this->delimiter = $delimiter;
    }

    public function supportedFormats(): array
    {
        return ['csv', 'tsv'];
    }

    public function parse(string $rawData): array
    {
        $results = [];
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawData));
        $lines = array_filter($lines, fn($l) => trim($l) !== '');
        $lines = array_values($lines);

        if (empty($lines)) return $results;

        // Detect delimiter: try comma, tab, semicolon
        if ($this->delimiter === ',') {
            $detect = $lines[0];
            $commaCount = substr_count($detect, ',');
            $tabCount = substr_count($detect, "\t");
            $semiCount = substr_count($detect, ';');
            if ($tabCount > $commaCount && $tabCount > $semiCount) $this->delimiter = "\t";
            elseif ($semiCount > $commaCount && $semiCount > $tabCount) $this->delimiter = ';';
        }

        // Parse header row
        $header = str_getcsv($lines[0], $this->delimiter);
        $header = array_map(fn($h) => trim(trim($h), '"\' '), $header);
        $headerLower = array_map('strtolower', $header);

        // Known column mappings (case-insensitive)
        $columnMap = [
            'sample_id' => 'sample_code', 'sampleid' => 'sample_code',
            'sample_code' => 'sample_code', 'samplecode' => 'sample_code',
            'sample' => 'sample_code', 'specimen' => 'sample_code',
            'test_id' => 'test_code', 'testid' => 'test_code',
            'test_code' => 'test_code', 'testcode' => 'test_code',
            'test' => 'test_code', 'parameter' => 'test_code',
            'test_name' => 'test_name', 'testname' => 'test_name',
            'analyte' => 'test_name', 'parameter_name' => 'test_name',
            'result' => 'result_value', 'value' => 'result_value',
            'result_value' => 'result_value', 'reading' => 'result_value',
            'result_text' => 'result_text', 'remark' => 'result_text',
            'remarks' => 'result_text', 'status' => 'result_text',
            'unit' => 'unit', 'uom' => 'unit', 'units' => 'unit',
            'method' => 'method', 'method_name' => 'method',
            'timestamp' => 'timestamp', 'date' => 'timestamp',
            'date_time' => 'timestamp', 'datetime' => 'timestamp',
        ];

        // Map header indices to normalized fields
        $fieldIndices = [];
        foreach ($headerLower as $i => $h) {
            $normalized = $columnMap[$h] ?? null;
            if ($normalized) {
                $fieldIndices[$normalized] = $i;
            }
        }

        // Parse data rows
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i], $this->delimiter);
            if (count($row) < 2) continue;

            $entry = [
                'sample_code' => $fieldIndices['sample_code'] !== null ? trim($row[$fieldIndices['sample_code']] ?? '') : null,
                'test_code' => $fieldIndices['test_code'] !== null ? trim($row[$fieldIndices['test_code']] ?? '') : null,
                'test_name' => $fieldIndices['test_name'] !== null ? trim($row[$fieldIndices['test_name']] ?? '') : null,
                'result_value' => $fieldIndices['result_value'] !== null ? trim($row[$fieldIndices['result_value']] ?? '') : null,
                'result_text' => $fieldIndices['result_text'] !== null ? trim($row[$fieldIndices['result_text']] ?? '') : null,
                'unit' => $fieldIndices['unit'] !== null ? trim($row[$fieldIndices['unit']] ?? '') : null,
                'method' => $fieldIndices['method'] !== null ? trim($row[$fieldIndices['method']] ?? '') : null,
                'timestamp' => $fieldIndices['timestamp'] !== null ? trim($row[$fieldIndices['timestamp']] ?? '') : null,
                'raw' => $lines[$i],
            ];

            // Fallback: map positionally if no header match
            if (!$entry['sample_code'] && !$entry['test_code'] && !$entry['result_value']) {
                if (isset($row[0])) $entry['sample_code'] = trim($row[0]);
                if (isset($row[1])) $entry['test_code'] = trim($row[1]);
                if (isset($row[2])) $entry['result_value'] = trim($row[2]);
            }

            if ($entry['sample_code'] || $entry['test_code'] || $entry['result_value'] !== null) {
                $results[] = $entry;
            }
        }

        return $results;
    }
}
