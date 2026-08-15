<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Models\SpcParameter;
use App\Models\SpcReading;

class SpcController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $params = SpcParameter::allActive();
        $stats = SpcParameter::dashboardStats();
        return $this->render('spc.index', [
            'params' => $params,
            'stats' => $stats,
        ]);
    }

    public function detail(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $param = SpcParameter::find($id);
        if (!$param) {
            session_flash('error', 'SPC parameter not found.');
            redirect('/spc');
        }
        $readings = SpcReading::findByParameter($id);
        $stats = SpcReading::parameterStats($id);
        $cpk = SpcReading::calculateCp($id);
        $valuesChronological = array_reverse(array_column($readings, 'value'));
        $violations = SpcReading::nelsonRules($valuesChronological);
        return $this->render('spc.detail', [
            'param' => $param,
            'readings' => $readings,
            'stats' => $stats,
            'cpk' => $cpk,
            'violations' => $violations,
        ]);
    }

    public function calculate(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $result = SpcReading::calculateCp($id);
        if (!$result) {
            return $this->json(['error' => 'Insufficient data or missing spec limits.'], 400);
        }
        return $this->json($result);
    }

    public function storeReading(int $paramId): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("INSERT INTO spc_readings (parameter_id, batch_id, reading_date, value, entered_by, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $paramId,
            $_POST['batch_id'] ?: null,
            $_POST['reading_date'] ?? date('Y-m-d H:i:s'),
            $_POST['value'],
            \App\Helpers\Auth::id(),
            $_POST['notes'] ?: null,
        ]);
        session_flash('success', 'Reading recorded successfully.');
        redirect('/spc/' . $paramId);
    }
}
