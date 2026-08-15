<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Models\QcControlLot;
use App\Models\QcControlResult;

class QcController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $lots = QcControlLot::allActive();
        $stats = QcControlLot::dashboardStats();
        return $this->render('qc.index', [
            'lots' => $lots,
            'stats' => $stats,
        ]);
    }

    public function create(): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            INSERT INTO qc_control_lots
                (lot_number, description, manufacturer, material_type, target_mean, target_sd, unit, expiry_date, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $expiry = $_POST['expiry_date'] ?: null;
        $stmt->execute([
            trim($_POST['lot_number']),
            $_POST['description'] ?: null,
            $_POST['manufacturer'] ?: null,
            $_POST['material_type'] ?: null,
            $_POST['target_mean'] !== '' ? $_POST['target_mean'] : null,
            $_POST['target_sd'] !== '' ? $_POST['target_sd'] : null,
            $_POST['unit'] ?: null,
            $expiry ? date('Y-m-d H:i:s', strtotime($expiry)) : null,
            isset($_POST['is_active']) ? 'TRUE' : 'FALSE',
            Auth::id(),
        ]);
        session_flash('success', 'QC control lot created.');
        redirect('/qc');
    }

    public function detail(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $lot = QcControlLot::find($id);
        if (!$lot) {
            session_flash('error', 'QC control lot not found.');
            redirect('/qc');
        }
        $results = QcControlResult::findByLot($id);
        $stats = QcControlResult::parameterStats($id);
        $targetMean = $lot['target_mean'] !== null ? (float)$lot['target_mean'] : null;
        $targetSd = $lot['target_sd'] !== null ? (float)$lot['target_sd'] : null;
        $westgard = QcControlResult::westgard($id, $targetMean, $targetSd);

        $db = \App\Helpers\Database::connect();
        $instruments = $db->query("SELECT id, instrument_name FROM instruments WHERE is_active = TRUE ORDER BY instrument_name")->fetchAll(\PDO::FETCH_ASSOC);
        $tests = $db->query("SELECT id, test_name FROM tests WHERE is_active = TRUE ORDER BY test_name")->fetchAll(\PDO::FETCH_ASSOC);
        $parameters = $db->query("SELECT id, parameter_name FROM analysis_parameters WHERE is_active = TRUE ORDER BY parameter_name")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('qc.detail', [
            'lot' => $lot,
            'results' => $results,
            'stats' => $stats,
            'westgard' => $westgard,
            'instruments' => $instruments,
            'tests' => $tests,
            'parameters' => $parameters,
        ]);
    }

    public function storeResult(int $lotId): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            INSERT INTO qc_control_results
                (control_lot_id, parameter_id, test_id, instrument_id, result_value, entered_by, entered_at, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $enteredAt = $_POST['entered_at'] ?: date('Y-m-d H:i:s');
        $stmt->execute([
            $lotId,
            $_POST['parameter_id'] !== '' ? $_POST['parameter_id'] : null,
            $_POST['test_id'] !== '' ? $_POST['test_id'] : null,
            $_POST['instrument_id'] !== '' ? $_POST['instrument_id'] : null,
            $_POST['result_value'],
            Auth::id(),
            date('Y-m-d H:i:s', strtotime($enteredAt)),
            $_POST['notes'] ?: null,
        ]);
        session_flash('success', 'QC result recorded.');
        redirect('/qc/' . $lotId);
    }

    public function assess(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $lot = QcControlLot::find($id);
        if (!$lot) {
            return $this->json(['error' => 'QC control lot not found.'], 404);
        }
        $targetMean = $lot['target_mean'] !== null ? (float)$lot['target_mean'] : null;
        $targetSd = $lot['target_sd'] !== null ? (float)$lot['target_sd'] : null;
        return $this->json(QcControlResult::westgard($id, $targetMean, $targetSd));
    }

    public function delete(int $id): void
    {
        Auth::requireAnyRole(['Admin']);
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM qc_control_results WHERE control_lot_id = ?")->execute([$id]);
        QcControlLot::delete($id);
        session_flash('success', 'QC control lot deleted.');
        redirect('/qc');
    }
}
