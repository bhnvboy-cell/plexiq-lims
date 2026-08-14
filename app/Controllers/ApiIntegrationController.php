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
        $stmt = $db->prepare("
            SELECT w.*,
                (SELECT wl.response_code FROM webhook_logs wl WHERE wl.webhook_id = w.id ORDER BY wl.id DESC LIMIT 1) AS last_response_code
            FROM api_webhooks w
            WHERE w.created_by = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([Auth::id()]);
        $webhooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('api.webhooks', ['webhooks' => $webhooks]);
    }

    public function createWebhook(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $eventsRaw = $_POST['events'] ?? '[]';
        if (is_array($eventsRaw)) {
            $eventsJson = json_encode($eventsRaw);
        } elseif (is_string($eventsRaw) && trim($eventsRaw) !== '' && $eventsRaw !== '[]') {
            $eventsJson = json_encode(array_values(array_filter(array_map('trim', explode(',', $eventsRaw)))));
        } else {
            $eventsJson = '[]';
        }
        $db->prepare("INSERT INTO api_webhooks (created_by, name, url, events, secret_key, is_active) VALUES (?, ?, ?, ?, ?, ?)")->execute([
            Auth::id(),
            $_POST['name'] ?? 'Webhook',
            $_POST['url'],
            $eventsJson,
            $_POST['secret'] ?? bin2hex(random_bytes(16)),
            !empty($_POST['is_active']),
        ]);
        $webhookId = $db->lastInsertId();
        Audit::log('Webhook Created', 'api_webhooks', $webhookId);
        session_flash('success', 'Webhook created.');
        $this->redirect('/api-management/webhooks');
    }

    public function toggleWebhook(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT is_active FROM api_webhooks WHERE id = ? AND created_by = ?");
        $stmt->execute([$id, Auth::id()]);
        $wh = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($wh) {
            $db->prepare("UPDATE api_webhooks SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([!$wh['is_active'], $id]);
            Audit::log('Webhook Toggled', 'api_webhooks', $id);
        }
        $this->redirect('/api-management/webhooks');
    }

    public function editWebhookJson(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT id, name, url, events, secret_key, is_active FROM api_webhooks WHERE id = ? AND created_by = ?");
        $stmt->execute([$id, Auth::id()]);
        $wh = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$wh) return $this->json(['error' => 'Not found'], 404);
        $wh['secret'] = $wh['secret_key'];
        return $this->json($wh);
    }

    public function updateWebhook(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE api_webhooks SET url = ?, events = ?, secret_key = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND created_by = ?")->execute([
            $_POST['url'],
            is_array($_POST['events'] ?? []) ? json_encode($_POST['events']) : ($_POST['events'] ?? '[]'),
            $_POST['secret'] ?? '',
            !empty($_POST['is_active']),
            $id, Auth::id(),
        ]);
        Audit::log('Webhook Updated', 'api_webhooks', $id);
        session_flash('success', 'Webhook updated.');
        $this->redirect('/api-management/webhooks');
    }

    public function deleteWebhook(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM api_webhooks WHERE id = ? AND created_by = ?")->execute([$id, Auth::id()]);
        Audit::log('Webhook Deleted', 'api_webhooks', $id);
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
