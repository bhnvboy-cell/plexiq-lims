<?php

namespace App\Models;

use App\BaseModel;

class EnvMonitoringReading extends BaseModel
{
    protected static string $table = 'environmental_readings';

    public static function getLatestReadings(int $pointId, int $limit = 20): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT * FROM " . static::$table . "
            WHERE point_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$pointId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getAlerts(string $since): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT r.* FROM " . static::$table . " r
            WHERE r.is_alert = TRUE
              AND r.created_at >= ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$since]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
