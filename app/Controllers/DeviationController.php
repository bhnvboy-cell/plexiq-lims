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
        $where = 'WHERE d.deleted_at IS NULL';
        $params = [];
        if ($status) {
            $where .= ' AND d.status = ?';
            $params[] = $status;
        }
        $result = \App\Helpers\Pagination::run($db, "
            SELECT d.*, u.full_name AS reported_by_name, p.product_name
            FROM deviations d
            LEFT JOIN users u ON d.reported_by = u.id
            LEFT JOIN products p ON d.product_id = p.id
            {$where}
        ", "
            SELECT COUNT(*) FROM deviations d {$where}
        ", $params, 20, 'd.created_at DESC');
        return $this->render('deviations.index', ['deviations' => $result['items'], 'paginator' => $result]);
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
        $db->prepare("INSERT INTO deviations (deviation_code, title, description, deviation_type, severity, product_id, source, root_cause, corrective_action, preventive_action, reported_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['deviation_number'] ?? $_POST['deviation_code'] ?? 'DEV-' . time(),
            $_POST['title'],
            $_POST['description'],
            $_POST['deviation_type'] ?? 'Process',
            $_POST['severity'] ?? 'Minor',
            $_POST['product_id'] ?: null,
            $_POST['source'] ?? 'Internal',
            $_POST['root_cause'] ?? null,
            $_POST['immediate_action'] ?? null,
            $_POST['corrective_action'] ?? null,
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
        $db->prepare("UPDATE deviations SET title = ?, description = ?, deviation_type = ?, severity = ?, product_id = ?, source = ?, source_id = ?, impact_assessment = ?, root_cause = ?, corrective_action = ?, preventive_action = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['deviation_type'] ?? 'Process',
            $_POST['severity'] ?? 'Minor',
            $_POST['product_id'] ?: null,
            $_POST['source'] ?? 'Internal',
            $_POST['source_id'] ?? null,
            $_POST['impact_assessment'] ?? null,
            $_POST['root_cause'] ?? null,
            $_POST['immediate_action'] ?? null,
            $_POST['corrective_action'] ?? null,
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
