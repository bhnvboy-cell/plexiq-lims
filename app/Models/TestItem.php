<?php

namespace App\Models;

use App\BaseModel;

class TestItem extends BaseModel
{
    protected static string $table = 'tests';
    protected static string $primaryKey = 'id';

    public static function allWithDetails(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("
            SELECT t.*, m.method_name, m.method_code, u.unit_code, u.unit_name
            FROM tests t
            LEFT JOIN methods m ON t.method_id = m.id
            LEFT JOIN units u ON t.unit_id = u.id
            ORDER BY t.test_code
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function findWithDetails(int $id): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT t.*, m.method_name, m.method_code, u.unit_code, u.unit_name
            FROM tests t
            LEFT JOIN methods m ON t.method_id = m.id
            LEFT JOIN units u ON t.unit_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function getStatusBadge(string $status): string
    {
        $map = [
            'Pending' => 'secondary',
            'In Progress' => 'info',
            'Completed' => 'success',
            'Reviewed' => 'primary',
            'Approved' => 'success',
            'Rejected' => 'danger',
        ];
        $class = $map[$status] ?? 'secondary';
        return "<span class='badge bg-{$class}'>{$status}</span>";
    }
}
