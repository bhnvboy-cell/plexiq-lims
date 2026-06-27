<?php

namespace App\Models;

use App\BaseModel;

class OosRecord extends BaseModel
{
    protected static string $table = 'oos_records';
    protected static string $primaryKey = 'id';

    public static function withDetails(int $id): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT o.*, u1.full_name AS initiator_name, u2.full_name AS assigned_name
            FROM oos_records o
            LEFT JOIN users u1 ON o.initiated_by = u1.id
            LEFT JOIN users u2 ON o.assigned_to = u2.id
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record) return null;

        $inv = $db->prepare("SELECT * FROM oos_investigations WHERE oos_id = ?");
        $inv->execute([$id]);
        $record['investigation'] = $inv->fetch() ?: null;
        return $record;
    }
}
