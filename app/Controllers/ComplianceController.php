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
        $retentionPolicies = $db->query("SELECT * FROM data_retention_policies ORDER BY entity_type")->fetchAll(\PDO::FETCH_ASSOC);
        $consentLogs = $db->query("
            SELECT cl.*, u.full_name AS user_name
            FROM consent_logs cl
            LEFT JOIN users u ON cl.user_id = u.id
            ORDER BY cl.created_at DESC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $privacyLogs = $db->query("
            SELECT pl.*, u.full_name AS user_name
            FROM privacy_logs pl
            LEFT JOIN users u ON pl.user_id = u.id
            ORDER BY pl.created_at DESC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $retentionTotal = count($retentionPolicies);
        $retentionActive = 0;
        $expiringSoon = 0;
        foreach ($retentionPolicies as $rp) {
            if (!empty($rp['is_active'])) { $retentionActive++; }
            if ((int)($rp['retention_days'] ?? 0) <= 90) { $expiringSoon++; }
        }
        $consentTotal = count($consentLogs);
        $lastAuditDate = $db->query("SELECT MAX(created_at) FROM audit_logs")->fetchColumn();
        $compliance = [
            'gdpr' => [
                'status' => $retentionTotal > 0 && $consentTotal > 0 ? 'Compliant' : 'Needs Review',
                'score' => $retentionTotal > 0 || $consentTotal > 0 ? min(100, ($retentionActive + $consentTotal) * 10) : 0,
                'details' => ($retentionActive > 0 ? $retentionActive . ' active retention policies' : 'No retention policies') . ' · ' . $consentTotal . ' consent records',
            ],
            'hipaa' => [
                'status' => $totalUsers > 0 && count($privacyLogs) > 0 ? 'Compliant' : 'Needs Review',
                'score' => $totalUsers > 0 ? min(100, count($privacyLogs) * 5) : 0,
                'details' => count($privacyLogs) . ' privacy access events logged',
            ],
        ];
        return $this->render('compliance.index', [
            'retentionPolicies' => $retentionPolicies,
            'consentLogs' => $consentLogs,
            'privacyLogs' => $privacyLogs,
            'dataRetentionStats' => ['total' => $retentionTotal, 'compliant' => $retentionActive, 'expiring_soon' => $expiringSoon],
            'consentStats' => ['total' => $consentTotal, 'pending' => 0],
            'lastAuditDate' => $lastAuditDate,
            'compliance' => $compliance,
        ]);
    }

    public function storeRetention(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO data_retention_policies (entity_type, retention_days, action_on_expiry, is_active) VALUES (?, ?, ?, ?)")->execute([
            $_POST['entity_type'],
            $_POST['retention_days'],
            $_POST['action_on_expiry'] ?? 'Archive',
            !empty($_POST['is_active']),
        ]);
        Audit::log('Data Retention Policy Created', 'data_retention_policies', $db->lastInsertId());
        session_flash('success', 'Retention policy added.');
        $this->redirect('/compliance');
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
