<?php

namespace App\Models;

use App\BaseModel;

class CapaRecord extends BaseModel
{
    protected static string $table = 'capa_records';
    protected static string $primaryKey = 'id';

    public static function withAssignees(int $id): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT c.*,
                u1.full_name AS assigned_name,
                u2.full_name AS created_name,
                u3.full_name AS reviewed_name
            FROM capa_records c
            LEFT JOIN users u1 ON c.assigned_to = u1.id
            LEFT JOIN users u2 ON c.created_by = u2.id
            LEFT JOIN users u3 ON c.reviewed_by = u3.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getAllWithDetails(): array
    {
        $db = \App\Helpers\Database::connect();
        return $db->query("
            SELECT c.*, u1.full_name AS assigned_name, u2.full_name AS created_name
            FROM capa_records c
            LEFT JOIN users u1 ON c.assigned_to = u1.id
            LEFT JOIN users u2 ON c.created_by = u2.id
            ORDER BY c.created_at DESC
        ")->fetchAll();
    }
}
