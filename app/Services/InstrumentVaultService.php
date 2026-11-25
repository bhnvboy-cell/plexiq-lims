<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Audit;
use PDO;

class InstrumentVaultService
{
    private InstrumentDriverFactory $driverFactory;
    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->driverFactory = new InstrumentDriverFactory();
        $this->storageDir = $storageDir ?? (dirname(__DIR__, 2) . '/storage/vault');
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Ingest raw instrument output into the immutable Raw Data Vault.
     *
     * @param string $rawContent Raw file/stream data
     * @param string $filename Original file name
     * @param int|null $instrumentId
     * @param string|null $agentHmac
     * @param string|null $agentSecret
     * @param string|null $driverName Explicit driver name or null to auto-detect
     * @return array{vault_uid: string, hash: string, parsed_count: int, status: string}
     */
    public function ingest(
        string $rawContent,
        string $filename,
        ?int $instrumentId = null,
        ?string $agentHmac = null,
        ?string $agentSecret = null,
        ?string $driverName = null
    ): array {
        $db = Database::connect();

        $sha256 = hash('sha256', $rawContent);
        $vaultUid = 'VAULT-' . bin2hex(random_bytes(16));
        $fileSize = strlen($rawContent);

        // Verify HMAC if agent secret is configured
        $secret = $agentSecret ?? ($_ENV['EDGE_AGENT_SECRET'] ?? 'plexiq_edge_default_secret');
        $computedHmac = hash_hmac('sha256', $rawContent, $secret);

        if ($agentHmac !== null && !hash_equals($computedHmac, $agentHmac)) {
            throw new \SecurityException("HMAC signature verification failed. Untrusted edge agent payload.");
        }

        // Save raw file to vault directory
        $safeFileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
        $storagePath = $this->storageDir . '/' . date('Ymd_His') . '_' . $safeFileName;
        file_put_contents($storagePath, $rawContent);

        // Select and invoke driver
        $driver = $driverName ? $this->driverFactory->getDriver($driverName) : $this->driverFactory->detectDriver($rawContent, $filename);
        $parsedResults = [];
        $parseStatus = 'Parsed';
        $parseError = null;

        try {
            $parsedResults = $driver->parseRawData($rawContent, [
                'instrument_id' => $instrumentId,
                'filename' => $filename,
            ]);
        } catch (\Throwable $e) {
            $parseStatus = 'Parse_Error';
            $parseError = $e->getMessage();
        }

        // Insert into immutable instrument_raw_vault
        $stmt = $db->prepare(
            "INSERT INTO instrument_raw_vault (
                instrument_id, vault_uid, original_filename, sha256_hash, hmac_signature,
                file_size_bytes, storage_path, raw_content, driver_used,
                parsed_results, parse_status, parse_error
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?, ?) RETURNING id"
        );
        $stmt->execute([
            $instrumentId,
            $vaultUid,
            $filename,
            $sha256,
            $computedHmac,
            $fileSize,
            $storagePath,
            $rawContent,
            $driver->getName(),
            json_encode($parsedResults),
            $parseStatus,
            $parseError
        ]);
        $vaultId = (int)$stmt->fetchColumn();

        // Auto-match parsed results to pending sample tests in database
        $matchedCount = 0;
        if (!empty($parsedResults) && $parseStatus === 'Parsed') {
            $matchedCount = $this->linkParsedResults($parsedResults, $instrumentId);
        }

        Audit::log(
            'Raw Data Vault Ingestion',
            'instrument_raw_vault',
            $vaultId,
            null,
            ['uid' => $vaultUid, 'hash' => $sha256, 'records' => count($parsedResults), 'matched' => $matchedCount],
            "Ingested raw instrument stream from {$filename} (SHA-256: {$sha256})"
        );

        return [
            'vault_uid' => $vaultUid,
            'vault_id' => $vaultId,
            'hash' => $sha256,
            'parsed_count' => count($parsedResults),
            'matched_count' => $matchedCount,
            'driver' => $driver->getName(),
            'status' => $parseStatus
        ];
    }

    /**
     * Tamper-evidence check: verifies stored file hash against computed SHA-256.
     *
     * @param int $vaultId
     * @return array{status: string, stored_hash: string, computed_hash: string, valid: bool}
     */
    public function verifyVaultIntegrity(int $vaultId): array
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT id, sha256_hash, raw_content, storage_path FROM instrument_raw_vault WHERE id = ?');
        $stmt->execute([$vaultId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \InvalidArgumentException("Raw data vault record #{$vaultId} not found.");
        }

        $computed = hash('sha256', (string)$row['raw_content']);
        $valid = hash_equals($row['sha256_hash'], $computed);

        if (!$valid) {
            // Update parse_status to Tampered
            $db->prepare("UPDATE instrument_raw_vault SET parse_status = 'Tampered' WHERE id = ?")->execute([$vaultId]);
        }

        return [
            'status' => $valid ? 'Verified_Intact' : 'Tampered',
            'stored_hash' => $row['sha256_hash'],
            'computed_hash' => $computed,
            'valid' => $valid
        ];
    }

    /**
     * Auto-link parsed instrument data to matching pending sample_tests.
     */
    private function linkParsedResults(array $parsedResults, ?int $instrumentId): int
    {
        $db = Database::connect();
        $matched = 0;

        foreach ($parsedResults as $res) {
            $sampleCode = $res['sample_code'] ?? '';
            $testCode = $res['test_code'] ?? '';
            $val = $res['value'] ?? null;

            if (empty($sampleCode) || empty($testCode) || $val === null) continue;

            // Find matching pending sample_test
            $stmt = $db->prepare(
                "SELECT st.id as sample_test_id, st.status
                 FROM sample_tests st
                 JOIN samples s ON st.sample_id = s.id
                 JOIN tests t ON st.test_id = t.id
                 WHERE (s.sample_code = ? OR s.sample_code ILIKE ?)
                   AND (t.test_code = ? OR t.test_name ILIKE ?)
                   AND st.status IN ('Pending', 'In Progress')
                 ORDER BY st.id ASC LIMIT 1"
            );
            $stmt->execute([$sampleCode, "%{$sampleCode}%", $testCode, "%{$testCode}%"]);
            $stRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($stRow) {
                $stId = (int)$stRow['sample_test_id'];
                $floatVal = is_numeric($val) ? (float)$val : null;

                // Insert into results table
                $insertR = $db->prepare(
                    "INSERT INTO results (sample_test_id, result_value, result_text, remarks, entered_at)
                     VALUES (?, ?, ?, 'Automated Instrument Vault Integration', CURRENT_TIMESTAMP)"
                );
                $insertR->execute([
                    $stId,
                    $floatVal,
                    is_string($val) ? $val : (string)$val
                ]);

                // Update sample_test status to Completed
                $db->prepare("UPDATE sample_tests SET status = 'Completed' WHERE id = ?")->execute([$stId]);
                $matched++;
            }
        }

        return $matched;
    }
}
