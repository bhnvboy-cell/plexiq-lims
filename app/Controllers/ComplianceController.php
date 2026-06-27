<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class ComplianceController extends BaseController
{
    public function index(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stats = [
            'total_users' => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'data_retention_count' => (int)$db->query("SELECT COUNT(*) FROM data_retention_policies")->fetchColumn(),
            'consent_records' => (int)$db->query("SELECT COUNT(*) FROM consent_logs")->fetchColumn(),
            'privacy_logs_count' => (int)$db->query("SELECT COUNT(*) FROM privacy_logs")->fetchColumn(),
            'export_requests' => (int)$db->query("SELECT COUNT(*) FROM data_export_requests WHERE status = 'Pending'")->fetchColumn(),
        ];
        return $this->render('compliance.index', ['stats' => $stats]);
    }

    public function dataRetention(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $policies = $db->query("SELECT * FROM data_retention_policies ORDER BY entity_type")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('compliance.data-retention', ['policies' => $policies]);
    }

    public function privacyLogs(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $logs = $db->query("
            SELECT pl.*, u.full_name AS user_name
            FROM privacy_logs pl
            LEFT JOIN users u ON pl.user_id = u.id
            ORDER BY pl.created_at DESC
            LIMIT 200
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('compliance.privacy-logs', ['logs' => $logs]);
    }

    public function consentLogs(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $logs = $db->query("
            SELECT cl.*, u.full_name AS user_name
            FROM consent_logs cl
            LEFT JOIN users u ON cl.user_id = u.id
            ORDER BY cl.created_at DESC
            LIMIT 200
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('compliance.consent-logs', ['logs' => $logs]);
    }

    public function deleteRetention(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM data_retention_policies WHERE id = ?")->execute([$id]);
        Audit::log('Data Retention Policy Deleted', 'data_retention_policies', $id);
        session_flash('success', 'Retention policy deleted.');
        $this->redirect('/compliance');
    }

    public function exportData(int $userId): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) { session_flash('error', 'User not found.'); $this->redirect('/compliance'); }
        $tables = ['audit_logs', 'electronic_signatures', 'notifications', 'dashboard_filters', 'dashboard_widgets'];
        $export = ['user' => $user];
        foreach ($tables as $table) {
            $tStmt = $db->prepare("SELECT * FROM {$table} WHERE user_id = ? OR created_by = ?");
            $tStmt->execute([$userId, $userId]);
            $export[$table] = $tStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $db->prepare("INSERT INTO data_export_requests (user_id, requested_by, status, exported_at) VALUES (?, ?, 'Completed', CURRENT_TIMESTAMP)")->execute([$userId, Auth::id()]);
        Audit::log('GDPR Data Export', 'data_export_requests', null, null, ['target_user_id' => $userId]);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="user_data_export_' . $userId . '_' . date('Y-m-d') . '.json"');
        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function anonymize(int $userId): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) { session_flash('error', 'User not found.'); $this->redirect('/compliance'); }
        $anon = 'anonymized_' . $userId . '_' . time();
        $db->prepare("UPDATE users SET full_name = ?, email = ?, username = ?, is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $anon, $anon . '@anonymized.local', $anon, $userId,
        ]);
        Audit::log('User Anonymized (GDPR)', 'users', $userId);
        session_flash('success', 'User data has been anonymized.');
        $this->redirect('/compliance');
    }
}
