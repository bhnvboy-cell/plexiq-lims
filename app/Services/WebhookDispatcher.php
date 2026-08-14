<?php

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\JobQueue;

/**
 * Fires registered webhooks for an event. Delivery is queued
 * and processed asynchronously by bin/worker.php.
 */
class WebhookDispatcher
{
    public static function dispatch(string $event, array $data = []): int
    {
        try {
            $stmt = Database::connect()->prepare(
                "SELECT id, events FROM api_webhooks WHERE is_active = TRUE"
            );
            $stmt->execute();
            $queued = 0;
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $webhook) {
                $events = json_decode($webhook['events'] ?? '[]', true);
                if (is_array($events) && (in_array($event, $events, true) || in_array('*', $events, true))) {
                    JobQueue::dispatch(\App\Jobs\DeliverWebhook::class, [
                        'webhook_id' => (int)$webhook['id'],
                        'event' => $event,
                        'data' => $data,
                    ], 'webhooks');
                    $queued++;
                }
            }
            return $queued;
        } catch (\Throwable $e) {
            error_log('WebhookDispatcher error: ' . $e->getMessage());
            return 0;
        }
    }
}
