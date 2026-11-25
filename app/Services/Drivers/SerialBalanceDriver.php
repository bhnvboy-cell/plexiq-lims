<?php

declare(strict_types=1);

namespace App\Services\Drivers;

use App\Contracts\InstrumentDriverInterface;

/**
 * Analytical Balance RS-232 / Serial Parser
 * Supports Mettler Toledo (MT-SICS protocol), Sartorius (SBI), and Ohaus continuous streams.
 */
class SerialBalanceDriver implements InstrumentDriverInterface
{
    public function getName(): string
    {
        return 'serial_balance';
    }

    public function getSupportedProtocols(): array
    {
        return ['mt_sics', 'sartorius_sbi', 'ohaus_serial', 'rs232_balance'];
    }

    public function parseRawData(string $rawContent, array $metadata = []): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rawContent));
        $sampleCode = $metadata['sample_code'] ?? 'SAMPLE-BALANCE';
        $testCode = $metadata['test_code'] ?? 'WEIGHT';

        $lastValidWeight = 0.0;
        $unit = 'g';
        $stable = true;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Mettler Toledo SICS format: "S S     12.3456 g" or "S D     12.3456 g" (D=Dynamic/unstable)
            if (preg_match('/^S\s+([SD])\s+([+-]?\d+(?:\.\d+)?)\s*([a-zA-Z]+)?/', $line, $matches)) {
                $stable = $matches[1] === 'S';
                $lastValidWeight = (float)$matches[2];
                $unit = $matches[3] ?? 'g';
            }
            // Sartorius format: "N  +  12.3456 g" or "Stat: + 12.3456 g"
            elseif (preg_match('/([+-]?\s*\d+\.\d+)\s*([a-zA-Z]+)/', $line, $matches)) {
                $cleanedNum = str_replace(' ', '', $matches[1]);
                $lastValidWeight = (float)$cleanedNum;
                $unit = $matches[2] ?? 'g';
                $stable = !str_contains(strtolower($line), 'd');
            }
        }

        return [
            [
                'sample_code' => $sampleCode,
                'test_code' => $testCode,
                'value' => $lastValidWeight,
                'unit' => $unit,
                'status' => $stable ? 'Stable' : 'Unstable'
            ]
        ];
    }

    public function generateWorklist(array $sequenceEntries): string
    {
        // Analytical balances do not have autosamplers, but return weighing tare instructions
        $out = ["# Weighing Sequence Instructions"];
        foreach ($sequenceEntries as $entry) {
            $out[] = "SAMPLE: " . ($entry['sample_code'] ?? 'UNKNOWN') . " | TARGET_QTY: " . ($entry['target_qty'] ?? '1.0 g');
        }
        return implode("\r\n", $out) . "\r\n";
    }
}
