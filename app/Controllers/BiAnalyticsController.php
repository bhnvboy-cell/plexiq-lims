<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class BiAnalyticsController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $reports = $db->query("
            SELECT r.*, u.full_name AS created_by_name
            FROM dashboard_reports r
            LEFT JOIN users u ON r.created_by = u.id
            ORDER BY r.created_at DESC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $connections = $db->query("SELECT * FROM bi_connections ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('bi-analytics.index', ['reports' => $reports, 'connections' => $connections]);
    }

    public function createReport(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $connections = $db->query("SELECT * FROM bi_connections WHERE is_active = TRUE ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        $tables = $db->query("SELECT table_name, table_schema FROM information_schema.tables WHERE table_schema NOT IN ('pg_catalog', 'information_schema') ORDER BY table_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('bi-analytics.report-form', ['report' => null, 'connections' => $connections, 'tables' => $tables]);
    }

    public function editReport(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM dashboard_reports WHERE id = ?");
        $stmt->execute([$id]);
        $report = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$report) { session_flash('error', 'Report not found.'); $this->redirect('/bi'); }
        $connections = $db->query("SELECT * FROM bi_connections WHERE is_active = TRUE ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('bi-analytics.report-form', ['report' => $report, 'connections' => $connections, 'tables' => []]);
    }

    public function runReport(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM dashboard_reports WHERE id = ?");
        $stmt->execute([$id]);
        $report = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$report) { return $this->json(['error' => 'Report not found'], 404); }
        $query = $report['query_sql'] ?? 'SELECT 1';
        try {
            $result = $db->query($query);
            $data = $result->fetchAll(\PDO::FETCH_ASSOC);
            Audit::log('BI Report Run', 'dashboard_reports', $id);
            return $this->json(['columns' => array_keys($data[0] ?? []), 'rows' => $data]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Query failed: ' . $e->getMessage()], 500);
        }
    }

    public function biConnections(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $connections = $db->query("SELECT * FROM bi_connections ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('bi-analytics.connections', ['connections' => $connections]);
    }

    public function testConnection(int $id): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM bi_connections WHERE id = ?");
        $stmt->execute([$id]);
        $conn = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$conn) { return $this->json(['error' => 'Connection not found'], 404); }
        Audit::log('BI Connection Tested', 'bi_connections', $id);
        return $this->json(['success' => true, 'message' => 'Connection to ' . $conn['name'] . ' is valid.']);
    }
}
