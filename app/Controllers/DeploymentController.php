<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Helpers\Database;

class DeploymentController extends BaseController
{
    public function index(): string
    {
        Auth::requireRole('Admin');
        $db = Database::connect();
        $settings = $db->query("SELECT * FROM deployment_settings ORDER BY category, setting_key")->fetchAll(\PDO::FETCH_ASSOC);
        $grouped = [];
        foreach ($settings as $s) {
            $cat = $s['category'] ?? 'general';
            $grouped[$cat][] = $s;
        }
        return $this->render('deployment.index', ['grouped' => $grouped]);
    }

    public function update(): void
    {
        Auth::requireRole('Admin');
        $db = Database::connect();
        foreach ($_POST['settings'] ?? [] as $key => $value) {
            $stmt = $db->prepare("UPDATE deployment_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        Audit::log('Deployment Settings Updated', 'deployment_settings');
        session_flash('success', 'Deployment settings updated successfully.');
        redirect('/deployment');
    }

    public function toggleMode(): void
    {
        Auth::requireRole('Admin');
        $db = Database::connect();
        $current = $db->query("SELECT setting_value FROM deployment_settings WHERE setting_key = 'cloud_enabled'")->fetchColumn();
        $newValue = $current === 'true' ? 'false' : 'true';
        $db->prepare("UPDATE deployment_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = 'cloud_enabled'")
            ->execute([$newValue]);
        Audit::log('Cloud Mode ' . ($newValue === 'true' ? 'Enabled' : 'Disabled'), 'deployment_settings');
        session_flash('success', 'Cloud mode ' . ($newValue === 'true' ? 'enabled' : 'disabled') . '.');
        redirect('/deployment');
    }
}
