<?php

namespace App\Services\Instruments;

class XmlParser implements InstrumentParserInterface
{
    public function supportedFormats(): array
    {
        return ['xml'];
    }

    public function parse(string $rawData): array
    {
        $results = [];
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rawData);

        if ($xml === false) {
            $errors = array_map(fn($e) => trim($e->message), libxml_get_errors());
            throw new \RuntimeException('Invalid XML: ' . implode(', ', $errors));
        }

        // Support common instrument XML schemas
        $namespaces = $xml->getNamespaces(true);
        $ns = $namespaces[''] ?? null;

        // Auto-detect XML structure
        $samples = [];
        if (isset($xml->Sample)) {
            $samples = $xml->Sample;
        } elseif (isset($xml->sample)) {
            $samples = $xml->sample;
        } elseif (isset($xml->Result)) {
            $samples = [$xml];
        } elseif (isset($xml->result)) {
            $samples = [$xml];
        } elseif (isset($xml->Results)) {
            $samples = $xml->Results->Result ?? $xml->Results->result ?? [];
        } elseif (isset($xml->results)) {
            $samples = $xml->results->result ?? $xml->results->Result ?? [];
        } elseif ($xml->children()) {
            // Treat each child as a result entry
            $samples = $xml->children();
        }

        foreach ($samples as $sample) {
            $entry = [
                'sample_code' => self::extractValue($sample, ['SampleID', 'SampleCode', 'sample_id', 'sample_code', 'SampleId']),
                'test_code' => self::extractValue($sample, ['TestID', 'TestCode', 'test_id', 'test_code', 'TestId', 'Parameter']),
                'test_name' => self::extractValue($sample, ['TestName', 'test_name', 'ParameterName', 'Analyte']),
                'result_value' => self::extractValue($sample, ['Result', 'Value', 'result', 'value', 'ResultValue', 'Reading']),
                'result_text' => self::extractValue($sample, ['ResultText', 'result_text', 'Remark', 'remark', 'Status']),
                'unit' => self::extractValue($sample, ['Unit', 'unit', 'UOM']),
                'method' => self::extractValue($sample, ['Method', 'method', 'MethodName']),
                'instrument' => self::extractValue($sample, ['Instrument', 'instrument', 'DeviceId']),
                'timestamp' => self::extractValue($sample, ['Timestamp', 'timestamp', 'DateTime', 'date_time', 'Date']),
                'raw' => $sample->asXML(),
            ];

            if ($entry['sample_code'] || $entry['test_code'] || $entry['result_value'] !== null) {
                $results[] = $entry;
            }
        }

        return $results;
    }

    private static function extractValue(\SimpleXMLElement $element, array $possibleNames): ?string
    {
        foreach ($possibleNames as $name) {
            if (isset($element->{$name})) {
                $val = trim((string)$element->{$name});
                if ($val !== '') return $val;
            }
            // Check attributes
            $attrs = $element->attributes();
            if ($attrs && isset($attrs[$name])) {
                $val = trim((string)$attrs[$name]);
                if ($val !== '') return $val;
            }
        }
        return null;
    }
}
