<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\SapSyncLog;

class SapController extends BaseController
{
    public function index(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();

        $config = [];
        $stmt = $db->query("SELECT * FROM sap_config");
        foreach ($stmt->fetchAll() as $row) {
            $config[$row['config_key']] = $row;
        }

        $logsResult = \App\Helpers\Pagination::run($db, "
            SELECT * FROM sap_sync_logs
        ", "
            SELECT COUNT(*) FROM sap_sync_logs
        ", [], 50, 'created_at DESC');

        return $this->render('sap.index', [
            'config' => $config,
            'logs' => $logsResult['items'],
            'paginator' => $logsResult,
        ]);
    }

    public function updateConfig(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("UPDATE sap_config SET config_value = ?, updated_at = CURRENT_TIMESTAMP WHERE config_key = ?");

        $keys = ['sap_hana_host', 'sap_hana_port', 'sap_hana_username', 'sap_hana_password',
                 'sap_odata_url', 'sap_sync_enabled', 'sap_sync_interval_minutes'];

        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $stmt->execute([$_POST[$key], $key]);
            }
        }

        Audit::log('SAP Config Updated', 'sap_config');
        session_flash('success', 'SAP HANA configuration updated.');
        redirect('/sap');
    }

    public function syncPush(string $type): void
    {
        Auth::requireRole('Admin');
        $syncService = new \App\Services\SapSyncService();
        try {
            $result = $syncService->pushToSap($type);
            session_flash($result['success'] ? 'success' : 'error', $result['message']);
        } catch (\Exception $e) {
            session_flash('error', 'Sync error: ' . $e->getMessage());
        }
        redirect('/sap');
    }

    public function syncPull(string $type): void
    {
        Auth::requireRole('Admin');
        $syncService = new \App\Services\SapSyncService();
        try {
            $result = $syncService->pullFromSap($type);
            session_flash($result['success'] ? 'success' : 'error', $result['message']);
        } catch (\Exception $e) {
            session_flash('error', 'Sync error: ' . $e->getMessage());
        }
        redirect('/sap');
    }

    public function syncAllPush(): void
    {
        Auth::requireRole('Admin');
        $syncService = new \App\Services\SapSyncService();
        $results = [];
        foreach (['sample', 'result', 'coa'] as $type) {
            $results[] = $syncService->pushToSap($type);
        }
        $success = !empty(array_filter($results, fn($r) => $r['success']));
        session_flash($success ? 'success' : 'error', 'Bulk push sync completed.');
        redirect('/sap');
    }

    public function syncAllPull(): void
    {
        Auth::requireRole('Admin');
        $syncService = new \App\Services\SapSyncService();
        $results = [];
        foreach (['customer', 'product', 'specification'] as $type) {
            $results[] = $syncService->pullFromSap($type);
        }
        $success = !empty(array_filter($results, fn($r) => $r['success']));
        session_flash($success ? 'success' : 'error', 'Bulk pull sync completed.');
        redirect('/sap');
    }

    public function syncStatus(): string
    {
        $syncService = new \App\Services\SapSyncService();
        return $this->json($syncService->getStatus());
    }
}
