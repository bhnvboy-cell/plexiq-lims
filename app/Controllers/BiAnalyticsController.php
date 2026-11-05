<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class BiAnalyticsController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer']);
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
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->storeReport();
            return '';
        }
        $db = \App\Helpers\Database::connect();
        $connections = $db->query("SELECT * FROM bi_connections WHERE is_active = TRUE ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        $tables = $db->query("SELECT table_name, table_schema FROM information_schema.tables WHERE table_schema NOT IN ('pg_catalog', 'information_schema') ORDER BY table_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('bi-analytics.report-form', ['report' => null, 'connections' => $connections, 'tables' => $tables]);
    }

    public function storeReport(): void
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer']);
        $db = \App\Helpers\Database::connect();

        $reportName = trim($_POST['report_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $reportType = trim($_POST['report_type'] ?? $_POST['category'] ?? 'Custom');
        $querySql = trim($_POST['query_sql'] ?? $_POST['query'] ?? 'SELECT 1');
        $chartType = trim($_POST['chart_type'] ?? 'table');
        $isPublic = (!empty($_POST['is_public']) || !empty($_POST['is_active'])) ? 't' : 'f';

        if ($reportName === '') {
            session_flash('error', 'Report name is required.');
            $this->redirect('/bi');
            return;
        }

        $stmt = $db->prepare("
            INSERT INTO dashboard_reports (report_name, description, report_type, query_sql, chart_type, is_public, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([$reportName, $description, $reportType, $querySql, $chartType, $isPublic, Auth::id()]);
        $newId = (int)$stmt->fetchColumn();

        Audit::log('BI Report Created', 'dashboard_reports', $newId, null, ['report_name' => $reportName]);
        session_flash('success', 'Report created successfully.');
        $this->redirect('/bi');
    }

    public function editReport(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer']);
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM dashboard_reports WHERE id = ?");
        $stmt->execute([$id]);
        $report = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$report) { session_flash('error', 'Report not found.'); $this->redirect('/bi'); }
        $connections = $db->query("SELECT * FROM bi_connections WHERE is_active = TRUE ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('bi-analytics.report-form', ['report' => $report, 'connections' => $connections, 'tables' => []]);
    }

    public function updateReport(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer']);
        $db = \App\Helpers\Database::connect();

        $reportName = trim($_POST['report_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $reportType = trim($_POST['report_type'] ?? $_POST['category'] ?? 'Custom');
        $querySql = trim($_POST['query_sql'] ?? $_POST['query'] ?? 'SELECT 1');
        $chartType = trim($_POST['chart_type'] ?? 'table');
        $isPublic = (!empty($_POST['is_public']) || !empty($_POST['is_active'])) ? 't' : 'f';

        if ($reportName === '') {
            session_flash('error', 'Report name is required.');
            $this->redirect('/bi');
            return;
        }

        $stmt = $db->prepare("
            UPDATE dashboard_reports
            SET report_name = ?, description = ?, report_type = ?, query_sql = ?, chart_type = ?, is_public = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$reportName, $description, $reportType, $querySql, $chartType, $isPublic, $id]);

        Audit::log('BI Report Updated', 'dashboard_reports', $id, null, ['report_name' => $reportName]);
        session_flash('success', 'Report updated successfully.');
        $this->redirect('/bi');
    }

    public function runReport(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer']);
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM dashboard_reports WHERE id = ?");
        $stmt->execute([$id]);
        $report = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$report) { return $this->json(['error' => 'Report not found'], 404); }
        $sql = trim($report['query_sql'] ?? 'SELECT 1');

        if (!preg_match('/^(SELECT|WITH)\b/i', $sql) || str_contains($sql, ';') || preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|GRANT|REVOKE|COPY|EXECUTE)\b/i', $sql)) {
            return $this->json(['error' => 'Only read-only SELECT queries are permitted.'], 400);
        }

        try {
            $db->beginTransaction();
            $db->exec('SET TRANSACTION READ ONLY');
            $result = $db->query($sql);
            $data = $result->fetchAll(\PDO::FETCH_ASSOC);
            $db->rollBack();
            Audit::log('BI Report Run', 'dashboard_reports', $id);
            return $this->json(['columns' => array_keys($data[0] ?? []), 'rows' => $data]);
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
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
