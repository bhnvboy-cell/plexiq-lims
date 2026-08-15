<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\OosRecord;
use App\Models\OosInvestigation;

class OosController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $records = $db->query("
            SELECT o.*, u1.full_name AS initiator_name, u2.full_name AS assigned_name
            FROM oos_records o
            LEFT JOIN users u1 ON o.initiated_by = u1.id
            LEFT JOIN users u2 ON o.assigned_to = u2.id
            WHERE o.deleted_at IS NULL
            ORDER BY o.created_at DESC
        ")->fetchAll();
        return $this->render('oos.index', ['records' => $records]);
    }

    public function show(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $record = OosRecord::withDetails($id);
        if (!$record) { session_flash('error', 'OOS record not found.'); redirect('/oos'); }
        $users = \App\Models\User::all();
        return $this->render('oos.show', ['record' => $record, 'users' => $users]);
    }

    public function create(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $samples = \App\Models\Sample::all();
        $users = \App\Models\User::all();
        return $this->render('oos.form', ['record' => null, 'samples' => $samples, 'users' => $users]);
    }

    public function store(): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $result = OosRecord::create([
            'oos_number' => $_POST['oos_number'],
            'sample_id' => $_POST['sample_id'] ?: null,
            'sample_test_id' => $_POST['sample_test_id'] ?? null,
            'result_id' => $_POST['result_id'] ?? null,
            'test_parameter' => $_POST['test_parameter'] ?: null,
            'specification_range' => $_POST['specification_range'] ?: null,
            'result_value' => isset($_POST['result_value']) && $_POST['result_value'] !== '' ? (float)$_POST['result_value'] : null,
            'result_text' => $_POST['result_text'] ?? null,
            'unit' => $_POST['unit'] ?: null,
            'description' => $_POST['description'],
            'severity' => $_POST['severity'] ?? 'Minor',
            'initiated_by' => Auth::id(),
            'assigned_to' => $_POST['assigned_to'] ?: null,
        ]);
        $id = $result['id'] ?? null;
        Audit::log('OOS Record Created', 'oos_records', $id);
        session_flash('success', 'OOS record created.');
        redirect('/oos/' . $id);
    }

    public function edit(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $record = OosRecord::find($id);
        if (!$record) { session_flash('error', 'OOS record not found.'); redirect('/oos'); }
        $samples = \App\Models\Sample::all();
        $users = \App\Models\User::all();
        return $this->render('oos.form', ['record' => $record, 'samples' => $samples, 'users' => $users]);
    }

    public function update(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        OosRecord::update($id, [
            'oos_number' => $_POST['oos_number'],
            'sample_id' => $_POST['sample_id'] ?: null,
            'test_parameter' => $_POST['test_parameter'] ?: null,
            'specification_range' => $_POST['specification_range'] ?: null,
            'result_value' => $_POST['result_value'] !== '' ? (float)$_POST['result_value'] : null,
            'result_text' => $_POST['result_text'] ?: null,
            'unit' => $_POST['unit'] ?: null,
            'description' => $_POST['description'],
            'severity' => $_POST['severity'] ?? 'Minor',
            'assigned_to' => $_POST['assigned_to'] ?: null,
        ]);
        Audit::log('OOS Record Updated', 'oos_records', $id);
        session_flash('success', 'OOS record updated.');
        redirect('/oos/' . $id);
    }

    public function investigate(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer']);
        OosInvestigation::upsert($id, [
            'root_cause' => $_POST['root_cause'] ?: null,
            'immediate_action' => $_POST['immediate_action'] ?: null,
            'corrective_action' => $_POST['corrective_action'] ?: null,
            'preventive_action' => $_POST['preventive_action'] ?: null,
            'investigation_notes' => $_POST['investigation_notes'] ?: null,
            'investigated_by' => Auth::id(),
            'investigated_at' => date('Y-m-d H:i:s'),
        ]);
        OosRecord::update($id, ['status' => 'Under Investigation']);
        Audit::log('OOS Investigation Updated', 'oos_records', $id);
        session_flash('success', 'Investigation saved.');
        redirect('/oos/' . $id);
    }

    public function review(int $id): void
    {
        Auth::requireRole('Reviewer');
        $inv = OosInvestigation::forOos($id);
        if ($inv) {
            OosInvestigation::update($inv['id'], [
                'reviewed_by' => Auth::id(),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_notes' => $_POST['review_notes'] ?: null,
            ]);
        }
        Audit::log('OOS Investigation Reviewed', 'oos_records', $id);
        session_flash('success', 'Review submitted.');
        redirect('/oos/' . $id);
    }

    public function close(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Reviewer']);
        OosRecord::update($id, [
            'status' => 'Closed',
            'disposition' => $_POST['disposition'],
            'disposition_notes' => $_POST['disposition_notes'] ?: null,
            'closed_by' => Auth::id(),
            'closed_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('OOS Record Closed', 'oos_records', $id, null, [
            'disposition' => $_POST['disposition'],
        ]);
        session_flash('success', 'OOS record closed.');
        redirect('/oos/' . $id);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('Admin');
        OosRecord::softDelete($id);
        Audit::log('OOS Record Deleted', 'oos_records', $id);
        session_flash('success', 'OOS record deleted.');
        redirect('/oos');
    }
}
