<?php

/**
 * CLI entry point for scheduled SAP HANA sync
 * Run via: php bin/console sap:sync
 * Or via Windows Scheduled Task:
 *   php C:\LIMS\www\bin\console sap:sync
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

// Initialize session-less environment for CLI
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_start();
}

try {
    $syncService = new \App\Services\SapSyncService();
    $results = $syncService->runScheduledSync();

    echo "[" . date('Y-m-d H:i:s') . "] SAP Sync completed:\n";
    foreach ($results as $key => $result) {
        $status = $result['success'] ? 'OK' : 'FAIL';
        echo "  {$key}: [{$status}] {$result['message']}\n";
    }

    // Log to file
    $logDir = __DIR__ . '/../../storage/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    file_put_contents(
        $logDir . '/sap-sync.log',
        "[" . date('Y-m-d H:i:s') . "] " . json_encode($results) . "\n",
        FILE_APPEND
    );

} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Sync failed: {$e->getMessage()}\n";
    exit(1);
}
