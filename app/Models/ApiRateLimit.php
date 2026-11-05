<?php

namespace App\Models;

use App\BaseModel;

class ApiRateLimit extends BaseModel
{
    protected static string $table = 'api_rate_limits';

    public static function checkAndIncrement(int $tokenId, string $endpoint = '*', int $limit = 60): bool
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT id, requests_count, window_start
            FROM " . static::$table . "
            WHERE api_token_id = ?
            ORDER BY window_start DESC
            LIMIT 1
        ");
        $stmt->execute([$tokenId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $now = time();
        if ($row && ($now - strtotime($row['window_start'])) < 60) {
            if ((int)$row['requests_count'] >= $limit) {
                return false;
            }
            $upd = $db->prepare("UPDATE " . static::$table . " SET requests_count = requests_count + 1 WHERE id = ?");
            $upd->execute([$row['id']]);
            return true;
        }

        $ins = $db->prepare("INSERT INTO " . static::$table . " (api_token_id, requests_count, window_start) VALUES (?, 1, CURRENT_TIMESTAMP)");
        $ins->execute([$tokenId]);
        return true;
    }
}
