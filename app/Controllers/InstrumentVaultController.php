<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Services\InstrumentVaultService;
use PDO;

class InstrumentVaultController extends BaseController
{
    private InstrumentVaultService $vaultService;

    public function __construct()
    {
        $this->vaultService = new InstrumentVaultService();
    }

    public function index(): void
    {
        Auth::requirePermission('master.instruments');

        $db = Database::connect();
        $records = $db->query(
            "SELECT v.id, v.vault_uid, v.original_filename, v.sha256_hash, v.file_size_bytes,
                    v.driver_used, v.parse_status, v.received_at, i.instrument_name, i.instrument_code
             FROM instrument_raw_vault v
             LEFT JOIN instruments i ON v.instrument_id = i.id
             ORDER BY v.received_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->view('instruments/vault/index', [
            'records' => $records
        ]);
    }

    public function show(int $id): void
    {
        Auth::requirePermission('master.instruments');

        $db = Database::connect();
        $stmt = $db->prepare('SELECT v.*, i.instrument_name, i.instrument_code FROM instrument_raw_vault v LEFT JOIN instruments i ON v.instrument_id = i.id WHERE v.id = ?');
        $stmt->execute([$id]);
        $vault = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vault) {
            $this->redirectWithError('/instruments/vault', 'Vault record not found.');
            return;
        }

        $parsed = json_decode($vault['parsed_results'] ?? '[]', true);

        $this->view('instruments/vault/show', [
            'vault' => $vault,
            'parsedResults' => $parsed
        ]);
    }

    public function verify(int $id): void
    {
        Auth::requirePermission('master.instruments');

        try {
            $res = $this->vaultService->verifyVaultIntegrity($id);

            if ($this->isJsonRequest()) {
                $this->json(['success' => true, 'verification' => $res]);
                return;
            }

            if ($res['valid']) {
                $_SESSION['flash_success'] = "SHA-256 Checksum verified intact: {$res['stored_hash']}";
            } else {
                $_SESSION['flash_error'] = "SECURITY ALERT: Hash mismatch! Stored: {$res['stored_hash']} vs Computed: {$res['computed_hash']}";
            }

            $this->redirect("/instruments/vault/{$id}");
        } catch (\Throwable $e) {
            $this->redirectWithError('/instruments/vault', $e->getMessage());
        }
    }

    /**
     * API Ingestion endpoint for Edge Agent.
     */
    public function ingestApi(): void
    {
        $rawContent = file_get_contents('php://input');
        if (empty($rawContent)) {
            $rawContent = $_POST['raw_content'] ?? '';
        }

        $filename = $_SERVER['HTTP_X_FILENAME'] ?? ($_POST['filename'] ?? 'instrument_output.csv');
        $instrumentId = isset($_SERVER['HTTP_X_INSTRUMENT_ID']) ? (int)$_SERVER['HTTP_X_INSTRUMENT_ID'] : (isset($_POST['instrument_id']) ? (int)$_POST['instrument_id'] : null);
        $agentHmac = $_SERVER['HTTP_X_HMAC_SIGNATURE'] ?? ($_POST['hmac_signature'] ?? null);
        $driver = $_SERVER['HTTP_X_DRIVER'] ?? ($_POST['driver'] ?? null);

        if (empty($rawContent)) {
            $this->json(['error' => 'Empty payload stream received.'], 400);
            return;
        }

        try {
            $res = $this->vaultService->ingest(
                $rawContent,
                $filename,
                $instrumentId,
                $agentHmac,
                null,
                $driver
            );

            $this->json([
                'success' => true,
                'vault_uid' => $res['vault_uid'],
                'sha256' => $res['hash'],
                'parsed_count' => $res['parsed_count'],
                'matched_count' => $res['matched_count'],
                'status' => $res['status']
            ], 201);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    private function isJsonRequest(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
            || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }

    private function redirectWithError(string $url, string $msg): void
    {
        if ($this->isJsonRequest()) {
            $this->json(['error' => $msg], 400);
            return;
        }
        $_SESSION['flash_error'] = $msg;
        $this->redirect($url);
    }
}
