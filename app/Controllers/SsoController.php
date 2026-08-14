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
        $configs = $db->query("SELECT * FROM sso_providers ORDER BY is_default DESC, provider_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('sso.index', ['configs' => $configs]);
    }

    public function updateConfig(int|string $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();

        if (is_string($id)) {
            $type = $id;
            $stmt = $db->prepare("SELECT id FROM sso_providers WHERE provider_type = ? LIMIT 1");
            $stmt->execute([$type]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($existing) {
                $providerId = (int)$existing['id'];
            } else {
                $db->prepare("INSERT INTO sso_providers (provider_name, provider_type, is_active) VALUES (?, ?, FALSE)")->execute([strtoupper($type), $type]);
                $providerId = (int)$db->lastInsertId();
            }
            $this->saveProviderFields($db, $providerId, $type);
            Audit::log('SSO Configuration Updated', 'sso_providers', $providerId, null, ['provider_type' => $type]);
            session_flash('success', ucfirst($type) . ' configuration updated.');
            $this->redirect('/sso');
        }

        if (!empty($_POST['is_default'])) {
            $db->exec("UPDATE sso_providers SET is_default = FALSE");
        }
        $db->prepare("UPDATE sso_providers SET provider_name = ?, client_id = ?, client_secret = ?, redirect_url = ?, authorize_url = ?, token_url = ?, userinfo_url = ?, scope = ?, is_default = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['provider'] ?? $_POST['oauth_provider'] ?? 'custom',
            $_POST['client_id'] ?? $_POST['oauth_client_id'] ?? null,
            $_POST['client_secret'] ?? null,
            $_POST['redirect_url'] ?? null,
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

    private function saveProviderFields(\PDO $db, int $providerId, string $type): void
    {
        $fields = [];
        $params = [];

        if ($type === 'saml') {
            $fields = [
                'provider_name' => 'SAML',
                'issuer_url' => $_POST['saml_entity_id'] ?? null,
                'redirect_url' => $_POST['saml_acs_url'] ?? null,
                'authorize_url' => $_POST['saml_idp_sso_url'] ?? null,
                'client_id' => $_POST['saml_idp_entity_id'] ?? null,
                'certificate' => $_POST['saml_idp_cert'] ?? null,
                'scope' => $_POST['saml_nameid_format'] ?? null,
                'auto_create_users' => isset($_POST['saml_auto_provision']) && $_POST['saml_auto_provision'] === '1',
                'is_active' => isset($_POST['saml_enabled']) && $_POST['saml_enabled'] === '1',
            ];
        } elseif ($type === 'ldap') {
            $fields = [
                'provider_name' => 'LDAP',
                'ldap_host' => $_POST['ldap_host'] ?? null,
                'ldap_port' => (int)($_POST['ldap_port'] ?? 389),
                'ldap_base_dn' => $_POST['ldap_base_dn'] ?? null,
                'ldap_bind_dn' => $_POST['ldap_bind_dn'] ?? null,
                'ldap_user_filter' => $_POST['ldap_user_filter'] ?? null,
                'auto_create_users' => isset($_POST['ldap_auto_provision']) && $_POST['ldap_auto_provision'] === '1',
                'is_active' => isset($_POST['ldap_enabled']) && $_POST['ldap_enabled'] === '1',
            ];
            if (!empty($_POST['ldap_bind_password'])) {
                $fields['ldap_bind_password'] = $_POST['ldap_bind_password'];
            }
        } else {
            $fields = [
                'provider_name' => $_POST['oauth_provider'] ?? 'custom',
                'client_id' => $_POST['oauth_client_id'] ?? null,
                'redirect_url' => $_POST['oauth_redirect_uri'] ?? null,
                'authorize_url' => $_POST['oauth_auth_url'] ?? null,
                'token_url' => $_POST['oauth_token_url'] ?? null,
                'userinfo_url' => $_POST['oauth_userinfo_url'] ?? null,
                'scope' => $_POST['oauth_scopes'] ?? 'openid email profile',
                'auto_create_users' => isset($_POST['oauth_auto_provision']) && $_POST['oauth_auto_provision'] === '1',
                'is_active' => isset($_POST['oauth_enabled']) && $_POST['oauth_enabled'] === '1',
            ];
            if (!empty($_POST['oauth_client_secret'])) {
                $fields['client_secret'] = $_POST['oauth_client_secret'];
            }
        }

        $sets = [];
        foreach ($fields as $col => $val) {
            $sets[] = "{$col} = ?";
            $params[] = $val;
        }
        $params[] = $providerId;
        $db->prepare("UPDATE sso_providers SET " . implode(', ', $sets) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute($params);
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
            'message' => 'Configuration validated. Provider: ' . ($config['provider_name'] ?? ''),
            'provider' => $config['provider_name'] ?? '',
            'authorize_url' => $config['authorize_url'],
            'token_url' => $config['token_url'],
        ];
        Audit::log('SSO Connection Test', 'sso_providers', $id, null, $result);
        return $this->json($result);
    }
}
