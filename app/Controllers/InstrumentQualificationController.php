<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Audit;
use PDO;

class InstrumentQualificationController extends BaseController
{
    public function index(): void
    {
        Auth::requirePermission('master.instruments');

        $db = Database::connect();
        $qualifications = $db->query(
            "SELECT iq.*, i.instrument_name, i.instrument_code, u.full_name as executed_by_name
             FROM instrument_qualifications iq
             JOIN instruments i ON iq.instrument_id = i.id
             LEFT JOIN users u ON iq.executed_by = u.id
             ORDER BY iq.execution_date DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $impacts = $db->query(
            "SELECT cia.*, i.instrument_name, i.instrument_code
             FROM calibration_impact_assessments cia
             JOIN instruments i ON cia.instrument_id = i.id
             ORDER BY cia.evaluated_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $instruments = $db->query("SELECT id, instrument_code, instrument_name FROM instruments WHERE is_active = TRUE ORDER BY instrument_name")->fetchAll(PDO::FETCH_ASSOC);

        $this->view('quality/instruments/qualifications', [
            'qualifications' => $qualifications,
            'impacts' => $impacts,
            'instruments' => $instruments
        ]);
    }

    public function storeQualification(): void
    {
        Auth::requirePermission('master.instruments');

        $instrumentId = (int)($_POST['instrument_id'] ?? 0);
        $phase = $_POST['phase'] ?? 'OQ';
        $protocol = trim((string)($_POST['protocol_code'] ?? ''));
        $date = !empty($_POST['execution_date']) ? $_POST['execution_date'] : date('Y-m-d');
        $status = $_POST['status'] ?? 'Passed';

        if ($instrumentId <= 0 || empty($protocol)) {
            $this->redirectWithError('/instruments/qualifications', 'Instrument and Protocol Code are required.');
            return;
        }

        $db = Database::connect();
        $userId = Auth::id() ?? 1;

        $stmt = $db->prepare(
            "INSERT INTO instrument_qualifications (
                instrument_id, phase, protocol_code, executed_by, execution_date, status
            ) VALUES (?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([
            $instrumentId,
            $phase,
            $protocol,
            $userId,
            $date,
            $status
        ]);
        $id = (int)$stmt->fetchColumn();

        Audit::log('Instrument Qualification', 'instrument_qualifications', $id, null, ['instrument_id' => $instrumentId, 'phase' => $phase, 'status' => $status], "Recorded {$phase} qualification for instrument #{$instrumentId}");

        $_SESSION['flash_success'] = "Qualification protocol {$protocol} ({$phase}) recorded.";
        $this->redirect('/instruments/qualifications');
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
