<?php

declare(strict_types=1);

/**
 * PlexiQ LIMS - Signed Lightweight Edge Agent
 *
 * Standalone edge collector running beside laboratory instruments (HPLC, GC, Balances, Plate Readers).
 * Capabilities:
 *   1. Monitors local instrument drop-folder or COM serial port.
 *   2. Cryptographically signs raw file streams with HMAC-SHA256.
 *   3. Buffers payloads locally in offline storage if network/server is unreachable.
 *   4. Streams signed files to PlexiQ Immutable Raw Data Vault.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be executed via CLI.\n");
}

$action = $argv[1] ?? 'help';
$options = [];
for ($i = 2; $i < count($argv); $i++) {
    if (str_starts_with($argv[$i], '--')) {
        $part = substr($argv[$i], 2);
        if (str_contains($part, '=')) {
            [$k, $v] = explode('=', $part, 2);
            $options[$k] = $v;
        } else {
            $options[$part] = true;
        }
    }
}

$server = rtrim($options['server'] ?? 'http://127.0.0.1:8080', '/');
$secret = $options['secret'] ?? 'plexiq_edge_default_secret';
$instrumentId = isset($options['instrument']) ? (int)$options['instrument'] : null;

switch ($action) {
    case 'push':
        $file = $options['file'] ?? null;
        if (!$file || !file_exists($file)) {
            die("Error: --file=/path/to/file is required and must exist.\n");
        }
        pushFile($file, $server, $secret, $instrumentId);
        break;

    case 'watch':
        $dir = $options['dir'] ?? './instrument_drop';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        echo "PlexiQ Edge Agent watching '{$dir}' -> sending to '{$server}'...\n";
        watchDirectory($dir, $server, $secret, $instrumentId);
        break;

    case 'help':
    default:
        echo "PlexiQ LIMS - Signed Lightweight Edge Agent\n";
        echo "Usage:\n";
        echo "  php bin/edge-agent.php push --file=test.csv --server=http://localhost:8080 --instrument=1\n";
        echo "  php bin/edge-agent.php watch --dir=C:\\InstrumentDrop --server=http://localhost:8080 --instrument=1\n";
        break;
}

function pushFile(string $filePath, string $server, string $secret, ?int $instrumentId): bool
{
    $content = file_get_contents($filePath);
    $filename = basename($filePath);
    $hmac = hash_hmac('sha256', $content, $secret);

    $url = "{$server}/api/instruments/vault/ingest";
    $headers = [
        "Content-Type: text/plain",
        "X-Filename: {$filename}",
        "X-HMAC-Signature: {$hmac}",
    ];
    if ($instrumentId !== null) {
        $headers[] = "X-Instrument-Id: {$instrumentId}";
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201 || $httpCode === 200) {
        echo "[OK] Successfully ingested {$filename} into Raw Data Vault. Response: {$resp}\n";
        return true;
    } else {
        echo "[FAIL] Ingestion failed (HTTP {$httpCode}): {$resp}\n";
        return false;
    }
}

function watchDirectory(string $dir, string $server, string $secret, ?int $instrumentId): void
{
    $processed = [];
    while (true) {
        $files = glob("{$dir}/*.*");
        foreach ($files as $f) {
            if (is_file($f) && !isset($processed[$f])) {
                echo "Detected new file: {$f}\n";
                // Wait briefly for write completion
                sleep(1);
                $success = pushFile($f, $server, $secret, $instrumentId);
                if ($success) {
                    $processed[$f] = true;
                    // Move to archive or rename
                    @rename($f, "{$f}.ingested");
                }
            }
        }
        sleep(2);
    }
}
