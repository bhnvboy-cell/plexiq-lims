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
        $devCode = $_POST['deviation_number'] ?? $_POST['deviation_code'] ?? 'DEV-' . time();
        $productId = ($_POST['product_id'] ?? '') ?: null;
        $sourceId = ($_POST['source_id'] ?? '') ?: null;
        try {
            $db->prepare("INSERT INTO deviations (deviation_code, title, description, deviation_type, severity, product_id, source, source_id, root_cause, immediate_action, corrective_action, preventive_action, reported_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                $devCode,
                $_POST['title'],
                $_POST['description'],
                $_POST['deviation_type'] ?? 'Process',
                $_POST['severity'] ?? 'Minor',
                $productId,
                $_POST['source'] ?? 'Internal',
                $sourceId,
                $_POST['root_cause'] ?? null,
                $_POST['immediate_action'] ?? null,
                $_POST['corrective_action'] ?? null,
                $_POST['preventive_action'] ?? null,
                Auth::id(),
                'Open',
            ]);
        } catch (\Throwable) {
            $db->prepare("INSERT INTO deviations (deviation_code, title, description, deviation_type, severity, product_id, source, root_cause, corrective_action, preventive_action, reported_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                $devCode,
                $_POST['title'],
                $_POST['description'],
                $_POST['deviation_type'] ?? 'Process',
                $_POST['severity'] ?? 'Minor',
                $productId,
                $_POST['source'] ?? 'Internal',
                $_POST['root_cause'] ?? null,
                $_POST['corrective_action'] ?? $_POST['immediate_action'] ?? null,
                $_POST['preventive_action'] ?? null,
                Auth::id(),
                'Open',
            ]);
        }
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
        $productId = ($_POST['product_id'] ?? '') ?: null;
        $sourceId = ($_POST['source_id'] ?? '') ?: null;
        try {
            $db->prepare("UPDATE deviations SET title = ?, description = ?, deviation_type = ?, severity = ?, product_id = ?, source = ?, source_id = ?, impact_assessment = ?, root_cause = ?, immediate_action = ?, corrective_action = ?, preventive_action = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
                $_POST['title'],
                $_POST['description'],
                $_POST['deviation_type'] ?? 'Process',
                $_POST['severity'] ?? 'Minor',
                $productId,
                $_POST['source'] ?? 'Internal',
                $sourceId,
                $_POST['impact_assessment'] ?? null,
                $_POST['root_cause'] ?? null,
                $_POST['immediate_action'] ?? null,
                $_POST['corrective_action'] ?? null,
                $_POST['preventive_action'] ?? null,
                $_POST['status'] ?? 'Open',
                $id,
            ]);
        } catch (\Throwable) {
            $db->prepare("UPDATE deviations SET title = ?, description = ?, deviation_type = ?, severity = ?, product_id = ?, source = ?, source_id = ?, impact_assessment = ?, root_cause = ?, corrective_action = ?, preventive_action = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
                $_POST['title'],
                $_POST['description'],
                $_POST['deviation_type'] ?? 'Process',
                $_POST['severity'] ?? 'Minor',
                $productId,
                $_POST['source'] ?? 'Internal',
                $sourceId,
                $_POST['impact_assessment'] ?? null,
                $_POST['root_cause'] ?? null,
                $_POST['corrective_action'] ?? $_POST['immediate_action'] ?? null,
                $_POST['preventive_action'] ?? null,
                $_POST['status'] ?? 'Open',
                $id,
            ]);
        }
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
            ($_POST['assigned_to'] ?? '') ?: null,
            ($_POST['due_date'] ?? '') ?: null,
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
        $status = $_POST['status'] ?? 'Pending';
        $isCompleted = $status === 'Completed' ? 't' : 'f';
        try {
            $db->prepare("UPDATE deviation_actions SET status = ?, completed_date = CASE WHEN ?::boolean THEN CURRENT_DATE ELSE completed_date END, completed_by = CASE WHEN ?::boolean THEN ? ELSE completed_by END, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
                $status,
                $isCompleted,
                $isCompleted,
                Auth::id(),
                $actionId,
            ]);
        } catch (\Throwable) {
            $db->prepare("UPDATE deviation_actions SET status = ?, completed_date = CASE WHEN ?::boolean THEN CURRENT_DATE ELSE completed_date END, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
                $status,
                $isCompleted,
                $actionId,
            ]);
        }
        Audit::log('Deviation Action Status Updated', 'deviation_actions', $actionId);
        session_flash('success', 'Action status updated.');
        $this->back();
    }

    public function close(int $id): void
    {
        $this->closeDeviation($id);
    }

    public function closeDeviation(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        try {
            $db->prepare("UPDATE deviations SET status = 'Closed', closed_date = CURRENT_DATE, closed_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([Auth::id(), $id]);
        } catch (\Throwable) {
            $db->prepare("UPDATE deviations SET status = 'Closed', closed_date = CURRENT_DATE, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        }
        Audit::log('Deviation Closed', 'deviations', $id);
        session_flash('success', 'Deviation closed.');
        $this->redirect('/deviations');
    }
}
