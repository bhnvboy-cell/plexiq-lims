<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\Project;
use App\Models\Sample;

class ProjectController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $projects = Project::all();
        return $this->render('projects.index', ['projects' => $projects]);
    }

    public function show(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $project = Project::withSamples($id);
        if (!$project) { session_flash('error', 'Project not found.'); redirect('/projects'); }
        return $this->render('projects.show', ['project' => $project]);
    }

    public function create(): string
    {
        Auth::requireRole('Admin');
        $users = \App\Models\User::all();
        return $this->render('projects.form', ['project' => null, 'users' => $users]);
    }

    public function store(): void
    {
        Auth::requireRole('Admin');
        $id = Project::create([
            'project_code' => $_POST['project_code'],
            'project_name' => $_POST['project_name'],
            'description' => $_POST['description'] ?: null,
            'status' => $_POST['status'] ?? 'Active',
            'priority' => $_POST['priority'] ?? 'Medium',
            'start_date' => $_POST['start_date'] ?: null,
            'target_end_date' => $_POST['target_end_date'] ?: null,
            'manager_id' => $_POST['manager_id'] ?: null,
            'created_by' => Auth::id(),
        ]);
        Audit::log('Project Created', 'projects', $id);
        session_flash('success', 'Project created successfully.');
        redirect('/projects/' . $id);
    }

    public function edit(int $id): string
    {
        Auth::requireRole('Admin');
        $project = Project::find($id);
        if (!$project) { session_flash('error', 'Project not found.'); redirect('/projects'); }
        $users = \App\Models\User::all();
        return $this->render('projects.form', ['project' => $project, 'users' => $users]);
    }

    public function update(int $id): void
    {
        Auth::requireRole('Admin');
        $data = [
            'project_code' => $_POST['project_code'],
            'project_name' => $_POST['project_name'],
            'description' => $_POST['description'] ?: null,
            'status' => $_POST['status'] ?? 'Active',
            'priority' => $_POST['priority'] ?? 'Medium',
            'start_date' => $_POST['start_date'] ?: null,
            'target_end_date' => $_POST['target_end_date'] ?: null,
            'actual_end_date' => $_POST['actual_end_date'] ?: null,
            'manager_id' => $_POST['manager_id'] ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        Project::update($id, $data);
        Audit::log('Project Updated', 'projects', $id);
        session_flash('success', 'Project updated.');
        redirect('/projects/' . $id);
    }

    public function addSample(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $sampleId = (int)($_POST['sample_id'] ?? 0);
        $project = Project::find($id);
        $sample = Sample::find($sampleId);
        if (!$project || !$sample) {
            session_flash('error', 'Project or Sample not found.');
            redirect('/projects/' . $id);
        }
        \App\Models\ProjectSample::create([
            'project_id' => $id,
            'sample_id' => $sampleId,
            'notes' => $_POST['notes'] ?: null,
        ]);
        Audit::log('Sample Added to Project', 'projects', $id);
        session_flash('success', 'Sample added to project.');
        redirect('/projects/' . $id);
    }

    public function removeSample(int $projectId, int $sampleId): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM project_samples WHERE project_id = ? AND sample_id = ?")
           ->execute([$projectId, $sampleId]);
        Audit::log('Sample Removed from Project', 'projects', $projectId);
        session_flash('success', 'Sample removed from project.');
        redirect('/projects/' . $projectId);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('Admin');
        Project::delete($id);
        Audit::log('Project Deleted', 'projects', $id);
        session_flash('success', 'Project deleted.');
        redirect('/projects');
    }
}
