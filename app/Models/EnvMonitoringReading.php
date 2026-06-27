<?php

namespace App\Models;

use App\BaseModel;

class EnvMonitoringReading extends BaseModel
{
    protected static string $table = 'env_monitoring_readings';

    public static function getLatestReadings(int $pointId, int $limit = 20): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT * FROM " . static::$table . "
            WHERE monitoring_point_id = ?
            ORDER BY recorded_at DESC
            LIMIT ?
        ");
        $stmt->execute([$pointId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getAlerts(string $since): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT * FROM " . static::$table . "
            WHERE is_alert = 1
              AND recorded_at >= ?
            ORDER BY recorded_at DESC
        ");
        $stmt->execute([$since]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
