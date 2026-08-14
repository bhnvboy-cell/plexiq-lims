<?php

namespace App\Helpers;

/**
 * Database-backed session handler (multi-instance ready).
 * Enable with SESSION_DRIVER=database in .env.
 */
class SessionHandler implements \SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = Database::connect()->prepare(
                'SELECT payload FROM sessions WHERE id = ? AND last_activity > ?'
            );
            $stmt->execute([$id, time() - (int)config('session.lifetime', 7200)]);
            return (string)($stmt->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $db = Database::connect();
            $stmt = $db->prepare(
                'INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON CONFLICT (id) DO UPDATE SET
                    user_id = EXCLUDED.user_id,
                    payload = EXCLUDED.payload,
                    last_activity = EXCLUDED.last_activity'
            );
            $stmt->execute([$id, $userId, $ip, $userAgent, $data, time()]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            Database::connect()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function gc(int $max_lifetime): int|false
    {
        try {
            return Database::connect()
                ->prepare('DELETE FROM sessions WHERE last_activity < ?')
                ->execute([time() - $max_lifetime]) ? 1 : 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
