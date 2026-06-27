<?php

namespace App\Models;

use App\BaseModel;

class SapSyncLog extends BaseModel
{
    protected static string $table = 'sap_sync_logs';
    protected static string $primaryKey = 'id';

    public static function pendingRetries(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("
            SELECT * FROM sap_sync_logs
            WHERE status = 'Failed' AND retry_count < max_retries
            AND (next_retry_at IS NULL OR next_retry_at <= CURRENT_TIMESTAMP)
            ORDER BY created_at ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function recent(int $limit = 50): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM sap_sync_logs ORDER BY created_at DESC LIMIT {$limit}");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
