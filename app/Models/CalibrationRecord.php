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
            WHERE next_due_date IS NOT NULL
              AND next_due_date < CURDATE()
              AND (completed_date IS NULL OR completed_date < next_due_date)
            ORDER BY next_due_date ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getUpcomingCalibrations(int $days = 30): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT * FROM " . static::$table . "
            WHERE next_due_date IS NOT NULL
              AND next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY next_due_date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
