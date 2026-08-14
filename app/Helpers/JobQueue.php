<?php

namespace App\Helpers;

class JobQueue
{
    public static function dispatch(string $job, array $payload = [], ?string $queue = null): int
    {
        $queue = $queue ?: (string)config('queue.default_queue', 'default');
        $db = Database::connect();
        $stmt = $db->prepare(
            'INSERT INTO jobs (queue, job, payload, max_attempts) VALUES (?, ?, ?, ?) RETURNING id'
        );
        $stmt->execute([$queue, $job, json_encode($payload), (int)config('queue.max_attempts', 3)]);
        return (int)$stmt->fetchColumn();
    }

    public static function countPending(?string $queue = null): int
    {
        $db = Database::connect();
        if ($queue) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM jobs WHERE status = 'pending' AND queue = ? AND available_at <= CURRENT_TIMESTAMP"
            );
            $stmt->execute([$queue]);
        } else {
            $stmt = $db->query(
                "SELECT COUNT(*) FROM jobs WHERE status = 'pending' AND available_at <= CURRENT_TIMESTAMP"
            );
        }
        return (int)$stmt->fetchColumn();
    }

    public static function countFailed(): int
    {
        return (int)Database::connect()->query("SELECT COUNT(*) FROM jobs WHERE status = 'failed'")->fetchColumn();
    }
}
