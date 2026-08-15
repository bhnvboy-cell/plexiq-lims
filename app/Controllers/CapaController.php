<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\CapaRecord;

class CapaController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $records = CapaRecord::getAllWithDetails();
        return $this->render('capa.index', ['records' => $records]);
    }

    public function show(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $record = CapaRecord::withAssignees($id);
        if (!$record) { session_flash('error', 'CAPA record not found.'); redirect('/capa'); }
        return $this->render('capa.show', ['record' => $record]);
    }

    public function create(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $users = \App\Models\User::all();
        return $this->render('capa.form', ['record' => null, 'users' => $users]);
    }

    public function store(): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $result = CapaRecord::create([
            'capa_number' => $_POST['capa_number'],
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'source_type' => $_POST['source_type'] ?: null,
            'source_reference_id' => $_POST['source_reference_id'] ?? null,
            'source_reference_type' => $_POST['source_reference_type'] ?? null,
            'root_cause' => $_POST['root_cause'] ?: null,
            'corrective_action_plan' => $_POST['corrective_action_plan'] ?: null,
            'preventive_action_plan' => $_POST['preventive_action_plan'] ?: null,
            'effectiveness_check' => $_POST['effectiveness_check'] ?: null,
            'priority' => $_POST['priority'] ?? 'Medium',
            'due_date' => $_POST['due_date'] ?: null,
            'assigned_to' => $_POST['assigned_to'] ?: null,
            'created_by' => Auth::id(),
        ]);
        $id = $result['id'] ?? null;
        Audit::log('CAPA Record Created', 'capa_records', $id);
        session_flash('success', 'CAPA record created.');
        redirect('/capa/' . $id);
    }

    public function edit(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $record = CapaRecord::find($id);
        if (!$record) { session_flash('error', 'CAPA record not found.'); redirect('/capa'); }
        $users = \App\Models\User::all();
        return $this->render('capa.form', ['record' => $record, 'users' => $users]);
    }

    public function update(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $data = [
            'capa_number' => $_POST['capa_number'],
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'source_type' => $_POST['source_type'] ?: null,
            'root_cause' => $_POST['root_cause'] ?: null,
            'corrective_action_plan' => $_POST['corrective_action_plan'] ?: null,
            'preventive_action_plan' => $_POST['preventive_action_plan'] ?: null,
            'effectiveness_check' => $_POST['effectiveness_check'] ?: null,
            'priority' => $_POST['priority'] ?? 'Medium',
            'due_date' => $_POST['due_date'] ?: null,
            'assigned_to' => $_POST['assigned_to'] ?: null,
        ];
        if (Auth::user()['role_name'] === 'Admin') {
            $data['source_reference_id'] = $_POST['source_reference_id'] ?: null;
            $data['source_reference_type'] = $_POST['source_reference_type'] ?: null;
        }
        CapaRecord::update($id, $data);
        Audit::log('CAPA Record Updated', 'capa_records', $id);
        session_flash('success', 'CAPA record updated.');
        redirect('/capa/' . $id);
    }

    public function updateStatus(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Reviewer']);
        $status = $_POST['status'];
        $data = ['status' => $status];
        if ($status === 'Completed' || $status === 'Closed') {
            $data['completed_date'] = date('Y-m-d');
        }
        if ($status === 'Closed') {
            $data['closed_by'] = Auth::id();
            $data['closed_at'] = date('Y-m-d H:i:s');
        }
        if ($_POST['effectiveness_results']) {
            $data['effectiveness_results'] = $_POST['effectiveness_results'];
        }
        if (Auth::user()['role_name'] === 'Reviewer' || $status === 'Under Review') {
            $data['reviewed_by'] = Auth::id();
        }
        CapaRecord::update($id, $data);
        Audit::log('CAPA Status Changed', 'capa_records', $id, null, ['status' => $status]);
        session_flash('success', "CAPA status changed to {$status}.");
        redirect('/capa/' . $id);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('Admin');
        CapaRecord::softDelete($id);
        Audit::log('CAPA Record Deleted', 'capa_records', $id);
        session_flash('success', 'CAPA record deleted.');
        redirect('/capa');
    }
}
