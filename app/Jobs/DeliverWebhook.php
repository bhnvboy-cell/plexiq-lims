<?php

namespace App\Jobs;

use App\Helpers\Audit;

/**
 * Async webhook delivery. Payload:
 *   webhook_id: int
 *   event:      string
 *   data:       array
 */
class DeliverWebhook extends Job
{
    public string $queue = 'webhooks';

    public function handle(array $payload): void
    {
        $webhookId = (int)($payload['webhook_id'] ?? 0);
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare('SELECT * FROM api_webhooks WHERE id = ? AND is_active = TRUE');
        $stmt->execute([$webhookId]);
        $webhook = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$webhook) {
            return;
        }

        $body = json_encode(['event' => $event, 'data' => $data, 'sent_at' => date('c')], JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'X-PlexiQ-Event: ' . $event,
        ];
        $secret = $webhook['secret_key'] ?? '';
        if ($secret !== '') {
            $headers[] = 'X-PlexiQ-Signature: sha256=' . hash_hmac('sha256', $body, $secret);
        }

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        $responseBody = curl_exec($ch);
        $responseCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $db->prepare(
            'INSERT INTO webhook_logs (webhook_id, event, payload, response_code, response_body) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $webhookId,
            $event,
            $body,
            $responseCode,
            $curlError !== '' ? $curlError : ($responseBody !== false ? substr((string)$responseBody, 0, 2000) : ''),
        ]);

        $db->prepare(
            'UPDATE api_webhooks SET last_triggered_at = CURRENT_TIMESTAMP,
                failure_count = failure_count + CASE WHEN ? BETWEEN 200 AND 299 THEN 0 ELSE 1 END,
                updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$responseCode, $webhookId]);

        Audit::log('Webhook Delivered', 'api_webhooks', $webhookId, null, [
            'event' => $event,
            'status' => $responseCode,
        ]);
    }
}
