<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Models\AuditLog;

class AuditController extends BaseController
{
    public function index(): string
    {
        Auth::requireRole('Admin');
        $filters = [];

        if (!empty($_GET['action'])) $filters['action'] = $_GET['action'];
        if (!empty($_GET['entity_type'])) $filters['entity_type'] = $_GET['entity_type'];
        if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
        if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];

        $logs = AuditLog::getLogs($filters, 100);
        $total = AuditLog::count($filters);

        $db = \App\Helpers\Database::connect();
        $actions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(\PDO::FETCH_COLUMN);
        $entities = $db->query("SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type")->fetchAll(\PDO::FETCH_COLUMN);

        return $this->render('audit.index', [
            'logs' => $logs,
            'total' => $total,
            'filters' => $filters,
            'actions' => $actions,
            'entities' => $entities,
        ]);
    }
}
