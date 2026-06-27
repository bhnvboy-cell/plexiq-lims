<?php

namespace App\Models;

use App\BaseModel;

class TrainingAssignment extends BaseModel
{
    protected static string $table = 'training_assignments';

    public static function getUserAssignments(int $userId): array
    {
        return static::where('user_id', $userId);
    }

    public static function getOverdue(int $days = 0): array
    {
        $db = \App\Helpers\Database::connect();
        $cutoff = $days > 0
            ? date('Y-m-d H:i:s', strtotime("-{$days} days"))
            : date('Y-m-d H:i:s');

        $stmt = $db->prepare("
            SELECT * FROM " . static::$table . "
            WHERE due_date IS NOT NULL
              AND due_date < ?
              AND completed_at IS NULL
            ORDER BY due_date ASC
        ");
        $stmt->execute([$cutoff]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
