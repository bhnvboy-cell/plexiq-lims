<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class NotebookController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $entries = $db->query("
            SELECT n.*, u.full_name AS created_by_name
            FROM eln_entries n
            LEFT JOIN users u ON n.created_by = u.id
            ORDER BY n.created_at DESC
            LIMIT 100
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('notebooks.index', ['entries' => $entries]);
    }

    public function create(): string
    {
        Auth::requireAuth();
        return $this->render('notebooks.create', ['notebook' => null]);
    }

    public function createEntry(int $notebookId): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM eln_notebooks WHERE id = ?");
        $stmt->execute([$notebookId]);
        $notebook = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$notebook) { session_flash('error', 'Notebook not found.'); $this->redirect('/notebooks'); }
        $projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'Active' ORDER BY project_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('notebooks.entry-form', ['entry' => null, 'notebook' => $notebook, 'projects' => $projects]);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO eln_notebooks (notebook_code, notebook_name, description, category, owner_id) VALUES (?, ?, ?, ?, ?)")->execute([
            $_POST['notebook_code'] ?? 'NB-' . time(),
            $_POST['notebook_name'],
            $_POST['description'] ?? null,
            $_POST['category'] ?? 'General',
            Auth::id(),
        ]);
        $nbId = $db->lastInsertId();
        Audit::log('Notebook Created', 'eln_notebooks', $nbId);
        session_flash('success', 'Notebook created.');
        $this->redirect('/notebooks');
    }

    public function storeEntry(int $notebookId): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM eln_notebooks WHERE id = ?");
        $stmt->execute([$notebookId]);
        if (!$stmt->fetch()) { session_flash('error', 'Notebook not found.'); $this->redirect('/notebooks'); }
        $db->prepare("INSERT INTO eln_entries (entry_code, notebook_id, title, content, entry_type, tags, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([
            'ENT-' . time(),
            $notebookId,
            $_POST['title'],
            $_POST['content'],
            $_POST['entry_type'] ?? 'General',
            $_POST['tags'] ?? null,
            Auth::id(),
        ]);
        $entryId = $db->lastInsertId();
        Audit::log('ELN Entry Created', 'eln_entries', $entryId);
        session_flash('success', 'Entry created.');
        $this->redirect('/notebooks/' . $notebookId);
    }

    public function show(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT n.*, u.full_name AS created_by_name
            FROM eln_entries n
            LEFT JOIN users u ON n.created_by = u.id
            WHERE n.id = ?
        ");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$entry) { session_flash('error', 'Entry not found.'); $this->redirect('/notebooks'); }
        return $this->render('notebooks.show', ['entry' => $entry]);
    }

    public function edit(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM eln_entries WHERE id = ?");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$entry) { session_flash('error', 'Entry not found.'); $this->redirect('/notebooks'); }
        $notebook = null;
        if ($entry['notebook_id']) {
            $nbStmt = $db->prepare("SELECT * FROM eln_notebooks WHERE id = ?");
            $nbStmt->execute([$entry['notebook_id']]);
            $notebook = $nbStmt->fetch(\PDO::FETCH_ASSOC);
        }
        $projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'Active' ORDER BY project_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('notebooks.entry-form', ['entry' => $entry, 'notebook' => $notebook, 'projects' => $projects]);
    }

    public function update(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE eln_entries SET title = ?, content = ?, entry_type = ?, project_id = ?, tags = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['title'],
            $_POST['content'],
            $_POST['entry_type'] ?? 'General',
            $_POST['project_id'] ?: null,
            $_POST['tags'] ?? null,
            $id,
        ]);
        Audit::log('ELN Entry Updated', 'eln_entries', $id);
        session_flash('success', 'Notebook entry updated.');
        $this->redirect('/notebooks');
    }

    public function updateEntry(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE eln_entries SET title = ?, content = ?, entry_type = ?, tags = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['title'],
            $_POST['content'],
            $_POST['entry_type'] ?? 'General',
            $_POST['tags'] ?? null,
            $_POST['status'] ?? 'Draft',
            $id,
        ]);
        $stmt = $db->prepare("SELECT notebook_id FROM eln_entries WHERE id = ?");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(\PDO::FETCH_ASSOC);
        Audit::log('ELN Entry Updated', 'eln_entries', $id);
        session_flash('success', 'Entry updated.');
        $this->redirect($entry ? '/notebooks/' . $entry['notebook_id'] : '/notebooks');
    }

    public function deleteEntry(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM eln_entries WHERE id = ?")->execute([$id]);
        Audit::log('ELN Entry Deleted', 'eln_entries', $id);
        session_flash('success', 'Notebook entry deleted.');
        $this->redirect('/notebooks');
    }
}
