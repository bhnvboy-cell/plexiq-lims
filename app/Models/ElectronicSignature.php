<?php

namespace App\Models;

use App\BaseModel;

class ElectronicSignature extends BaseModel
{
    protected static string $table = 'electronic_signatures';

    public static function verifySignature(int $userId, string $actionType, string $entityType, int $entityId): bool
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM " . static::$table . "
            WHERE user_id = ?
              AND action_type = ?
              AND entity_type = ?
              AND entity_id = ?
        ");
        $stmt->execute([$userId, $actionType, $entityType, $entityId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
