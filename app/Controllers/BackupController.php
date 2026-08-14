<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Services\BackupService;

class BackupController extends BaseController
{
    public function index(): string
    {
        Auth::requireRole('Admin');
        $service = new BackupService();
        return $this->render('backups.index', [
            'backups' => $service->list(),
            'settings' => $service->settings(),
            'runs' => $service->recentRuns(15),
            'backupDir' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $service->dir()),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole('Admin');
        set_time_limit(0);
        try {
            $meta = (new BackupService())->create('manual');
            Audit::log('Backup Created', 'backup_runs', null, null, ['file' => $meta['file'], 'size' => $meta['size']]);
            session_flash('success', 'Backup created: ' . $meta['file'] . ' (' . $this->humanSize($meta['size']) . ').');
        } catch (\Throwable $e) {
            Audit::log('Backup Failed', 'backup_runs', null, null, ['error' => $e->getMessage()]);
            session_flash('error', 'Backup failed: ' . $e->getMessage());
        }
        redirect('/backups');
    }

    public function download(string $fileName): void
    {
        Auth::requireRole('Admin');
        $service = new BackupService();
        $file = $service->resolve($fileName);
        if ($file === null || !is_file($file)) {
            http_response_code(404);
            echo view('errors.404');
            return;
        }
        Audit::log('Backup Downloaded', 'backup_runs', null, null, ['file' => $fileName]);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        header('X-Content-Type-Options: nosniff');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($file);
        exit;
    }

    public function confirmRestore(string $fileName): string
    {
        Auth::requireRole('Admin');
        $service = new BackupService();
        $file = $service->resolve($fileName);
        if ($file === null || !is_file($file)) {
            session_flash('error', 'Backup file not found.');
            redirect('/backups');
        }
        $meta = $service->list();
        $backup = null;
        foreach ($meta as $b) {
            if ($b['file'] === $fileName) {
                $backup = $b;
                break;
            }
        }
        return $this->render('backups.restore', ['backup' => $backup, 'fileName' => $fileName]);
    }

    public function restore(string $fileName): void
    {
        Auth::requireRole('Admin');
        set_time_limit(0);
        if (($_POST['confirm'] ?? '') !== 'RESTORE') {
            session_flash('error', 'Type RESTORE to confirm the restore.');
            redirect('/backups/restore/' . rawurlencode($fileName));
        }
        try {
            $result = (new BackupService())->restore($fileName);
            Audit::log('Backup Restored', 'backup_runs', null, null, ['file' => $fileName, 'duration_ms' => $result['duration_ms']]);
            session_flash('success', 'Backup restored successfully from ' . $fileName . ' (took ' . round($result['duration_ms'] / 1000, 1) . 's).');
        } catch (\Throwable $e) {
            Audit::log('Restore Failed', 'backup_runs', null, null, ['file' => $fileName, 'error' => $e->getMessage()]);
            session_flash('error', 'Restore failed: ' . $e->getMessage());
        }
        redirect('/backups');
    }

    public function delete(string $fileName): void
    {
        Auth::requireRole('Admin');
        $service = new BackupService();
        if ($service->delete($fileName)) {
            Audit::log('Backup Deleted', 'backup_runs', null, null, ['file' => $fileName]);
            session_flash('success', 'Backup deleted.');
        } else {
            session_flash('error', 'Backup file not found.');
        }
        redirect('/backups');
    }

    public function updateSettings(): void
    {
        Auth::requireRole('Admin');
        $values = [
            'retention_count' => max(1, (int)($_POST['retention_count'] ?? 10)),
            'pg_dump_path' => trim((string)($_POST['pg_dump_path'] ?? '')),
            'psql_path' => trim((string)($_POST['psql_path'] ?? '')),
        ];
        (new BackupService())->saveSettings($values);
        Audit::log('Backup Settings Updated', 'backup_settings');
        session_flash('success', 'Backup settings saved.');
        redirect('/backups');
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float)$bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 1) . ' ' . $units[$i];
    }
}
