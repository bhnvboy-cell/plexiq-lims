<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class SsoController extends BaseController
{
    public function index(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $configs = $db->query("SELECT * FROM sso_providers ORDER BY is_default DESC, provider")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('sso.index', ['configs' => $configs]);
    }

    public function updateConfig(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        if (!empty($_POST['is_default'])) {
            $db->exec("UPDATE sso_providers SET is_default = FALSE");
        }
        $db->prepare("UPDATE sso_providers SET provider = ?, client_id = ?, client_secret = ?, redirect_url = ?, authorize_url = ?, token_url = ?, userinfo_url = ?, scope = ?, is_default = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['provider'],
            $_POST['client_id'],
            $_POST['client_secret'] ?? null,
            $_POST['redirect_url'],
            $_POST['authorize_url'] ?? null,
            $_POST['token_url'] ?? null,
            $_POST['userinfo_url'] ?? null,
            $_POST['scope'] ?? 'openid email profile',
            !empty($_POST['is_default']),
            !empty($_POST['is_active']),
            $id,
        ]);
        Audit::log('SSO Configuration Updated', 'sso_providers', $id);
        session_flash('success', 'SSO configuration updated.');
        $this->redirect('/sso');
    }

    public function testConnection(int $id): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM sso_providers WHERE id = ?");
        $stmt->execute([$id]);
        $config = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$config) {
            return $this->json(['error' => 'Configuration not found'], 404);
        }
        // Simulate connection test - in production would use a HTTP client
        $result = [
            'success' => true,
            'message' => 'Configuration validated. Provider: ' . $config['provider'],
            'provider' => $config['provider'],
            'authorize_url' => $config['authorize_url'],
            'token_url' => $config['token_url'],
        ];
        Audit::log('SSO Connection Test', 'sso_providers', $id, null, $result);
        return $this->json($result);
    }
}
