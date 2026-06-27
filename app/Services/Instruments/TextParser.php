<?php

namespace App\Services\Instruments;

class TextParser implements InstrumentParserInterface
{
    private array $patterns;

    public function __construct(?array $patterns = null)
    {
        $this->patterns = $patterns ?? [
            'sample_code' => ['/Sample\s*[:#]?\s*(\S+)/i', '/SampleID\s*[:=]\s*(\S+)/i', '/SAMPLE\s*(\S+)/i'],
            'test_code' => ['/Test\s*[:#]?\s*(\S+)/i', '/TestID\s*[:=]\s*(\S+)/i', '/Parameter\s*[:=]\s*(\S+)/i'],
            'test_name' => ['/Analyte\s*[:=]\s*(.+)/i', '/TestName\s*[:=]\s*(.+)/i', '/ParameterName\s*[:=]\s*(.+)/i'],
            'result_value' => ['/Result\s*[:=]\s*([\d.]+)/i', '/Value\s*[:=]\s*([\d.]+)/i', '/Reading\s*[:=]\s*([\d.]+)/i'],
            'result_text' => ['/Remark\s*[:=]\s*(.+)/i', '/Status\s*[:=]\s*(.+)/i'],
            'unit' => ['/Unit\s*[:=]\s*(\S+)/i', '/UOM\s*[:=]\s*(\S+)/i'],
            'method' => ['/Method\s*[:=]\s*(.+)/i'],
        ];
    }

    public function supportedFormats(): array
    {
        return ['txt', 'text', 'dat', 'prn'];
    }

    public function parse(string $rawData): array
    {
        $results = [];
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawData));

        $current = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $matched = false;
            foreach ($this->patterns as $field => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $line, $m)) {
                        $current[$field] = trim($m[1]);
                        $matched = true;
                        break 2;
                    }
                }
            }

            // Line with a numeric value but no label = standalone result
            if (!$matched && preg_match('/^([\d.]+)\s*$/', $line, $m)) {
                $current['result_value'] = $m[1];
            }

            // Detect record separator (blank line or known separator)
            if (preg_match('/^[-=]{5,}$/', $line) || preg_match('/^End|^----/', $line)) {
                if (!empty($current)) {
                    $current['raw'] = implode("\n", $current['_lines'] ?? []);
                    unset($current['_lines']);
                    $results[] = $current;
                    $current = [];
                }
                continue;
            }

            if (!isset($current['_lines'])) $current['_lines'] = [];
            $current['_lines'][] = $line;
        }

        // Last record
        if (!empty($current) && (isset($current['sample_code']) || isset($current['test_code']) || isset($current['result_value']))) {
            $current['raw'] = implode("\n", $current['_lines'] ?? []);
            unset($current['_lines']);
            $results[] = $current;
        }

        return $results;
    }

    public function setPatterns(array $patterns): void
    {
        $this->patterns = $patterns;
    }
}
