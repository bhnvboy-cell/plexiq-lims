<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class DeviationController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $status = $_GET['status'] ?? '';
        $where = '';
        $params = [];
        if ($status) {
            $where = 'WHERE d.status = ?';
            $params[] = $status;
        }
        $deviations = $db->prepare("
            SELECT d.*, u.full_name AS reported_by_name, p.product_name
            FROM deviations d
            LEFT JOIN users u ON d.reported_by = u.id
            LEFT JOIN products p ON d.product_id = p.id
            {$where}
            ORDER BY d.created_at DESC
            LIMIT 100
        ");
        $deviations->execute($params);
        return $this->render('deviations.index', ['deviations' => $deviations->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function create(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $products = $db->query("SELECT id, product_code, product_name FROM products WHERE is_active = TRUE ORDER BY product_name")->fetchAll(\PDO::FETCH_ASSOC);
        $types = ['Process', 'Quality', 'Equipment', 'Documentation', 'Material', 'Environmental', 'Other'];
        $severities = ['Critical', 'Major', 'Minor', 'Observation'];
        return $this->render('deviations.form', [
            'deviation' => null, 'products' => $products, 'types' => $types, 'severities' => $severities,
        ]);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO deviations (deviation_number, title, description, deviation_type, severity, product_id, batch_number, root_cause, immediate_action, reported_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['deviation_number'] ?? 'DEV-' . time(),
            $_POST['title'],
            $_POST['description'],
            $_POST['deviation_type'] ?? 'Process',
            $_POST['severity'] ?? 'Minor',
            $_POST['product_id'] ?: null,
            $_POST['batch_number'] ?? null,
            $_POST['root_cause'] ?? null,
            $_POST['immediate_action'] ?? null,
            Auth::id(),
            'Open',
        ]);
        $devId = $db->lastInsertId();
        Audit::log('Deviation Created', 'deviations', $devId);
        session_flash('success', 'Deviation reported.');
        $this->redirect('/deviations');
    }

    public function show(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT d.*, u.full_name AS reported_by_name, p.product_name
            FROM deviations d
            LEFT JOIN users u ON d.reported_by = u.id
            LEFT JOIN products p ON d.product_id = p.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $deviation = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$deviation) { session_flash('error', 'Deviation not found.'); $this->redirect('/deviations'); }
        $actions = $db->prepare("SELECT da.*, u.full_name AS assigned_to_name FROM deviation_actions da LEFT JOIN users u ON da.assigned_to = u.id WHERE da.deviation_id = ? ORDER BY da.created_at");
        $actions->execute([$id]);
        return $this->render('deviations.show', [
            'deviation' => $deviation,
            'actions' => $actions->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    public function edit(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM deviations WHERE id = ?");
        $stmt->execute([$id]);
        $deviation = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$deviation) { session_flash('error', 'Deviation not found.'); $this->redirect('/deviations'); }
        $products = $db->query("SELECT id, product_code, product_name FROM products WHERE is_active = TRUE ORDER BY product_name")->fetchAll(\PDO::FETCH_ASSOC);
        $types = ['Process', 'Quality', 'Equipment', 'Documentation', 'Material', 'Environmental', 'Other'];
        $severities = ['Critical', 'Major', 'Minor', 'Observation'];
        return $this->render('deviations.form', [
            'deviation' => $deviation, 'products' => $products, 'types' => $types, 'severities' => $severities,
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE deviations SET title = ?, description = ?, deviation_type = ?, severity = ?, product_id = ?, batch_number = ?, root_cause = ?, immediate_action = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['deviation_type'] ?? 'Process',
            $_POST['severity'] ?? 'Minor',
            $_POST['product_id'] ?: null,
            $_POST['batch_number'] ?? null,
            $_POST['root_cause'] ?? null,
            $_POST['immediate_action'] ?? null,
            $_POST['status'] ?? 'Open',
            $id,
        ]);
        Audit::log('Deviation Updated', 'deviations', $id);
        session_flash('success', 'Deviation updated.');
        $this->redirect('/deviations/' . $id);
    }

    public function addAction(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO deviation_actions (deviation_id, action_description, assigned_to, due_date, priority) VALUES (?, ?, ?, ?, ?)")->execute([
            $id,
            $_POST['action_description'],
            $_POST['assigned_to'] ?: null,
            $_POST['due_date'] ?: null,
            $_POST['priority'] ?? 'Medium',
        ]);
        Audit::log('Deviation Action Added', 'deviation_actions', null, null, ['deviation_id' => $id]);
        session_flash('success', 'Action added.');
        $this->redirect('/deviations/' . $id);
    }

    public function updateActionStatus(int $actionId): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE deviation_actions SET status = ?, completed_date = CASE WHEN ? THEN CURRENT_DATE ELSE completed_date END, completed_by = CASE WHEN ? THEN ? ELSE completed_by END, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['status'],
            $_POST['status'] === 'Completed' ? 1 : 0,
            $_POST['status'] === 'Completed' ? 1 : 0,
            Auth::id(),
            $actionId,
        ]);
        Audit::log('Deviation Action Status Updated', 'deviation_actions', $actionId);
        session_flash('success', 'Action status updated.');
        $this->back();
    }

    public function closeDeviation(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE deviations SET status = 'Closed', closed_date = CURRENT_DATE, closed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([Auth::id(), $id]);
        Audit::log('Deviation Closed', 'deviations', $id);
        session_flash('success', 'Deviation closed.');
        $this->redirect('/deviations');
    }
}
