<?php

namespace App\Services\Ops;

use App\Helpers\Database;

class QueueService
{
    /**
     * Push a new job onto the queue
     *
     * @param string $jobClass Job class name or task handler
     * @param array<string, mixed> $payload
     * @param string $queue
     * @param int $delaySeconds
     * @return int Job ID
     */
    public function push(string $jobClass, array $payload = [], string $queue = 'default', int $delaySeconds = 0): int
    {
        $db = Database::connect();
        $availableAt = date('Y-m-d H:i:s', time() + $delaySeconds);

        $stmt = $db->prepare("
            INSERT INTO job_queues (queue_name, job_class, payload, attempts, max_attempts, status, available_at)
            VALUES (?, ?, ?, 0, 3, 'pending', ?)
            RETURNING id
        ");
        $stmt->execute([
            $queue,
            $jobClass,
            json_encode($payload, JSON_UNESCAPED_SLASHES),
            $availableAt
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Reserve and fetch the next available job from the queue
     *
     * @param string $queue
     * @return array<string, mixed>|null
     */
    public function pop(string $queue = 'default'): ?array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE job_queues
            SET status = 'processing',
                reserved_at = CURRENT_TIMESTAMP,
                attempts = attempts + 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = (
                SELECT id
                FROM job_queues
                WHERE queue_name = ?
                  AND status = 'pending'
                  AND available_at <= CURRENT_TIMESTAMP
                ORDER BY id ASC
                FOR UPDATE SKIP LOCKED
                LIMIT 1
            )
            RETURNING *
        ");
        $stmt->execute([$queue]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if (is_string($row['payload'])) {
            $row['payload'] = json_decode($row['payload'], true) ?? [];
        }

        return $row;
    }

    /**
     * Mark a job as successfully completed
     *
     * @param int $jobId
     * @return bool
     */
    public function complete(int $jobId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            UPDATE job_queues
            SET status = 'completed', updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$jobId]);
    }

    /**
     * Fail a job, moving it to retry with exponential backoff or Dead-Letter Queue (DLQ)
     *
     * @param int $jobId
     * @param string $errorMessage
     * @return bool
     */
    public function fail(int $jobId, string $errorMessage): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT attempts, max_attempts FROM job_queues WHERE id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$job) {
            return false;
        }

        $attempts = (int)$job['attempts'];
        $maxAttempts = (int)$job['max_attempts'];

        if ($attempts >= $maxAttempts) {
            // Escalate to Dead-Letter Queue
            $upd = $db->prepare("
                UPDATE job_queues
                SET status = 'dead_letter', error_message = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            return $upd->execute([$errorMessage, $jobId]);
        }

        // Exponential backoff: 2^attempts * 10 seconds
        $delay = pow(2, $attempts) * 10;
        $nextAvailable = date('Y-m-d H:i:s', time() + $delay);

        $upd = $db->prepare("
            UPDATE job_queues
            SET status = 'pending',
                error_message = ?,
                available_at = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $upd->execute([$errorMessage, $nextAvailable, $jobId]);
    }

    /**
     * Get queue operational metrics
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $db = Database::connect();
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_jobs,
                COUNT(*) FILTER (WHERE status = 'pending') as pending_count,
                COUNT(*) FILTER (WHERE status = 'processing') as processing_count,
                COUNT(*) FILTER (WHERE status = 'completed') as completed_count,
                COUNT(*) FILTER (WHERE status = 'dead_letter') as dead_letter_count
            FROM job_queues
        ");
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_jobs' => 0,
            'pending_count' => 0,
            'processing_count' => 0,
            'completed_count' => 0,
            'dead_letter_count' => 0
        ];
    }
}
