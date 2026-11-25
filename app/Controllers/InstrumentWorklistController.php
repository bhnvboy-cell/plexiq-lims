<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Audit;
use App\Services\InstrumentDriverFactory;
use PDO;

class InstrumentWorklistController extends BaseController
{
    public function index(): void
    {
        Auth::requirePermission('master.instruments');

        $db = Database::connect();
        $worklists = $db->query(
            "SELECT w.*, i.instrument_name, i.instrument_code, u.full_name as creator_name
             FROM instrument_worklists w
             JOIN instruments i ON w.instrument_id = i.id
             LEFT JOIN users u ON w.created_by = u.id
             ORDER BY w.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $instruments = $db->query("SELECT id, instrument_code, instrument_name FROM instruments WHERE is_active = TRUE ORDER BY instrument_name")->fetchAll(PDO::FETCH_ASSOC);

        $this->view('instruments/worklists/index', [
            'worklists' => $worklists,
            'instruments' => $instruments
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('master.instruments');

        $instrumentId = (int)($_POST['instrument_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $format = $_POST['worklist_format'] ?? 'csv';
        $rawEntries = $_POST['entries'] ?? [];

        if (is_string($rawEntries)) {
            $rawEntries = json_decode($rawEntries, true) ?: [];
        }

        if ($instrumentId <= 0 || empty($name)) {
            $this->redirectWithError('/instruments/worklists', 'Instrument and sequence name are required.');
            return;
        }

        $db = Database::connect();
        $userId = Auth::id() ?? 1;
        $seqCode = 'SEQ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        // Format worklist content using driver
        $factory = new InstrumentDriverFactory();
        $driver = $format === 'astm' ? $factory->getDriver('astm_e1394') : $factory->getDriver('cds_csv');
        $exportedContent = $driver->generateWorklist($rawEntries);

        $stmt = $db->prepare(
            "INSERT INTO instrument_worklists (
                instrument_id, name, sequence_code, worklist_format, status, entries, exported_content, created_by
            ) VALUES (?, ?, ?, ?, 'Draft', ?::jsonb, ?, ?) RETURNING id"
        );
        $stmt->execute([
            $instrumentId,
            $name,
            $seqCode,
            $format,
            json_encode($rawEntries),
            $exportedContent,
            $userId
        ]);
        $id = (int)$stmt->fetchColumn();

        Audit::log('Create Instrument Worklist', 'instrument_worklists', $id, null, ['sequence_code' => $seqCode, 'format' => $format], "Generated autosampler sequence {$seqCode}");

        if ($this->isJsonRequest()) {
            $this->json(['success' => true, 'id' => $id, 'sequence_code' => $seqCode]);
            return;
        }

        $_SESSION['flash_success'] = "Sequence {$seqCode} generated with " . count($rawEntries) . " vial(s).";
        $this->redirect('/instruments/worklists');
    }

    public function export(int $id): void
    {
        Auth::requirePermission('master.instruments');

        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM instrument_worklists WHERE id = ?');
        $stmt->execute([$id]);
        $w = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$w) {
            $this->redirectWithError('/instruments/worklists', 'Worklist not found.');
            return;
        }

        // Mark as exported
        $db->prepare("UPDATE instrument_worklists SET status = 'Exported', exported_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);

        $ext = $w['worklist_format'] === 'astm' ? 'astm' : 'csv';
        $filename = "{$w['sequence_code']}.{$ext}";

        header('Content-Type: text/plain');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        echo $w['exported_content'];
        exit;
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
