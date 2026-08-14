<?php

namespace App\Jobs;

use App\Helpers\Audit;
use App\Helpers\Database;

/**
 * Async instrument file import. Payload:
 *   instrument_id:  int
 *   file_path:      string (absolute path to the file to parse)
 *   original_name:  string (for dedupe/audit)
 *   requested_by:   int (user id to notify on completion)
 */
class ImportInstrumentFile extends Job
{
    public string $queue = 'imports';

    public function handle(array $payload): void
    {
        $instrumentId = (int)($payload['instrument_id'] ?? 0);
        $filePath = (string)($payload['file_path'] ?? '');
        $originalName = (string)($payload['original_name'] ?? basename($filePath));
        $userId = (int)($payload['requested_by'] ?? 1);

        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM instruments WHERE id = ?');
        $stmt->execute([$instrumentId]);
        $instrument = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$instrument) {
            Audit::log('Instrument Import Failed', 'instruments', $instrumentId, null, ['error' => 'Instrument not found']);
            return;
        }
        if (!is_file($filePath)) {
            Audit::log('Instrument Import Failed', 'instruments', $instrumentId, null, ['error' => "File missing: {$filePath}"]);
            return;
        }

        $service = new \App\Services\InstrumentImportService();
        try {
            $parsed = $service->parseFile($filePath, $instrument['interface_type']);
            $stats = $service->importMappedResults($instrumentId, $parsed, $userId, $originalName);

            if (($stats['rows'] ?? 0) > 0) {
                \notify($userId, 'instrument_import', 'Instrument Import Completed',
                    "{$originalName}: {$stats['written']} parameter result(s) written" .
                    ($stats['oos'] > 0 ? ", {$stats['oos']} OOS flagged" : '') .
                    ($stats['unmapped'] > 0 ? ", {$stats['unmapped']} unmapped" : ''),
                    '/instruments/results');
            } else {
                \notify($userId, 'instrument_import', 'Instrument Import: No Data',
                    "{$originalName} contained no importable rows.", '/instruments/results');
            }
        } catch (\Exception $e) {
            Audit::log('Instrument Import Failed', 'instruments', $instrumentId, null, [
                'file' => $originalName,
                'error' => $e->getMessage(),
            ]);
            \notify($userId, 'instrument_import', 'Instrument Import Failed',
                "{$originalName}: " . $e->getMessage(), '/instruments/results');
        }
    }
}
