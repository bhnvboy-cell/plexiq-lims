<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class ApiIntegrationController extends BaseController
{
    public function tokens(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM api_tokens WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([Auth::id()]);
        $tokens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('api.tokens', ['tokens' => $tokens]);
    }

    public function createToken(): void
    {
        Auth::requireAuth();
        $token = bin2hex(random_bytes(32));
        $masked = substr($token, 0, 8) . '...' . substr($token, -8);
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO api_tokens (user_id, token, masked_token, name, expires_at) VALUES (?, ?, ?, ?, ?)")->execute([
            Auth::id(),
            password_hash($token, PASSWORD_DEFAULT),
            $masked,
            $_POST['name'] ?? 'API Token',
            $_POST['expires_at'] ?: null,
        ]);
        Audit::log('API Token Created', 'api_tokens', null, null, ['name' => $_POST['name'] ?? 'API Token']);
        session_flash('success', 'Token created: ' . $token);
        $this->redirect('/api-management/tokens');
    }

    public function revokeToken(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE api_tokens SET is_revoked = TRUE, revoked_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?")->execute([$id, Auth::id()]);
        Audit::log('API Token Revoked', 'api_tokens', $id);
        session_flash('success', 'Token revoked.');
        $this->redirect('/api-management/tokens');
    }

    public function webhooks(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM webhooks WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([Auth::id()]);
        $webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('api.webhooks', ['webhooks' => $webhooks]);
    }

    public function createWebhook(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO webhooks (user_id, name, url, events, secret, is_active) VALUES (?, ?, ?, ?, ?, ?)")->execute([
            Auth::id(),
            $_POST['name'],
            $_POST['url'],
            $_POST['events'] ?? '[]',
            $_POST['secret'] ?? bin2hex(random_bytes(16)),
            !empty($_POST['is_active']),
        ]);
        $webhookId = $db->lastInsertId();
        Audit::log('Webhook Created', 'webhooks', $webhookId);
        session_flash('success', 'Webhook created.');
        $this->redirect('/api-management/webhooks');
    }

    public function toggleWebhook(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT is_active FROM webhooks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, Auth::id()]);
        $wh = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($wh) {
            $db->prepare("UPDATE webhooks SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([!$wh['is_active'], $id]);
            Audit::log('Webhook Toggled', 'webhooks', $id);
        }
        $this->redirect('/api-management/webhooks');
    }

    public function editWebhookJson(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM webhooks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, Auth::id()]);
        $wh = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$wh) return $this->json(['error' => 'Not found'], 404);
        return $this->json($wh);
    }

    public function updateWebhook(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE webhooks SET url = ?, events = ?, secret = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?")->execute([
            $_POST['url'],
            is_array($_POST['events'] ?? []) ? json_encode($_POST['events']) : ($_POST['events'] ?? '[]'),
            $_POST['secret'] ?? '',
            !empty($_POST['is_active']),
            $id, Auth::id(),
        ]);
        Audit::log('Webhook Updated', 'webhooks', $id);
        session_flash('success', 'Webhook updated.');
        $this->redirect('/api-management/webhooks');
    }

    public function deleteWebhook(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM webhooks WHERE id = ? AND user_id = ?")->execute([$id, Auth::id()]);
        Audit::log('Webhook Deleted', 'webhooks', $id);
        session_flash('success', 'Webhook deleted.');
        $this->redirect('/api-management/webhooks');
    }

    public function webhookLogs(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM webhook_logs WHERE webhook_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$id]);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('api.webhook-logs', ['logs' => $logs, 'webhookId' => $id]);
    }
}
