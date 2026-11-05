<?php

namespace App\Helpers;

class Audit
{
    private static function canonicalJson($val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $val;
            }
            $val = $decoded;
        }
        $sortKeys = function (&$item) use (&$sortKeys) {
            if (is_array($item)) {
                if (array_keys($item) !== range(0, count($item) - 1)) {
                    ksort($item);
                }
                foreach ($item as &$sub) {
                    $sortKeys($sub);
                }
            }
        };
        $sortKeys($val);
        return json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function log(string $action, string $entityType, ?int $entityId = null, $oldValue = null, $newValue = null): void
    {
        try {
            $db = Database::connect();
            $oldJson = $oldValue !== null && $oldValue !== '' ? self::canonicalJson($oldValue) : null;
            $newJson = $newValue !== null && $newValue !== '' ? self::canonicalJson($newValue) : null;
            $userId = Auth::id() ?? (isset($_REQUEST['_api_user_id']) ? (int)$_REQUEST['_api_user_id'] : null);

            $prevHash = self::lastChainHash($db);
            $createdAt = $db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
            $at = date('c', strtotime($createdAt));

            $rowPayload = json_encode([
                'user' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old' => $oldJson,
                'new' => $newJson,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'at' => $at,
                'prev' => $prevHash,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $chainHash = hash('sha256', $rowPayload);

            $stmt = $db->prepare(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent, prev_hash, hash_chain, created_at)
                 VALUES (?, ?, ?, ?, ?::jsonb, ?::jsonb, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityId,
                $oldJson,
                $newJson,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $prevHash,
                $chainHash,
                $createdAt,
            ]);
        } catch (\Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }

    private static function lastChainHash(\PDO $db): ?string
    {
        try {
            return $db->query('SELECT hash_chain FROM audit_logs ORDER BY id DESC LIMIT 1')->fetchColumn() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function verifyChain(): array
    {
        $db = Database::connect();
        $rows = $db->query('SELECT * FROM audit_logs ORDER BY id ASC')->fetchAll(\PDO::FETCH_ASSOC);
        $issues = [];
        $prev = null;
        foreach ($rows as $row) {
            if ($row['hash_chain'] === null) {
                // Legacy row written before hash-chaining was enabled.
                $prev = null;
                continue;
            }
            $at = date('c', strtotime($row['created_at']));
            $rowPayload = json_encode([
                'user' => $row['user_id'] !== null ? (int)$row['user_id'] : null,
                'action' => $row['action'],
                'entity_type' => $row['entity_type'],
                'entity_id' => $row['entity_id'] !== null ? (int)$row['entity_id'] : null,
                'old' => self::canonicalJson($row['old_value']),
                'new' => self::canonicalJson($row['new_value']),
                'ip' => $row['ip_address'],
                'ua' => $row['user_agent'],
                'at' => $at,
                'prev' => $prev,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $computed = hash('sha256', $rowPayload);
            if ($computed !== $row['hash_chain']) {
                $issues[] = ['id' => $row['id'], 'reason' => 'hash mismatch', 'stored' => $row['hash_chain'], 'computed' => $computed];
            }
            if ($row['prev_hash'] !== $prev) {
                $issues[] = ['id' => $row['id'], 'reason' => 'chain break', 'expected_prev' => $prev, 'stored_prev' => $row['prev_hash']];
            }
            $prev = $row['hash_chain'];
        }
        return $issues;
    }

    public static function getLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $db = Database::connect();
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'a.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'a.entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT a.*, u.username, u.full_name
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id
                {$whereClause}
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function count(array $filters = []): int
    {
        $db = Database::connect();
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $db->prepare("SELECT COUNT(*) FROM audit_logs {$whereClause}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
