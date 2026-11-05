<?php

namespace App\Models;

use App\BaseModel;

class CalibrationRecord extends BaseModel
{
    protected static string $table = 'calibration_records';

    public static function getOverdueCalibrations(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("
            SELECT * FROM " . static::$table . "
            WHERE COALESCE(next_due_date, next_calibration_date, due_date) IS NOT NULL
              AND COALESCE(next_due_date, next_calibration_date, due_date) < CURRENT_DATE
              AND (calibration_date IS NULL OR calibration_date < COALESCE(next_due_date, next_calibration_date, due_date))
            ORDER BY COALESCE(next_due_date, next_calibration_date, due_date) ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getUpcomingCalibrations(int $days = 30): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT * FROM " . static::$table . "
            WHERE COALESCE(next_due_date, next_calibration_date, due_date) IS NOT NULL
              AND COALESCE(next_due_date, next_calibration_date, due_date) BETWEEN CURRENT_DATE AND CURRENT_DATE + (? || ' days')::interval
            ORDER BY COALESCE(next_due_date, next_calibration_date, due_date) ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
