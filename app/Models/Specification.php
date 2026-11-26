<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use PDO;

class Specification extends BaseModel
{
    protected static string $table = 'specifications';

    public static function findByProduct(int $productId): array
    {
        $db = static::getPdo();
        $stmt = $db->prepare("
            SELECT s.*, p.product_code, p.product_name, c.customer_name
            FROM specifications s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.product_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findWithDetails(int $id): ?array
    {
        $db = static::getPdo();
        $stmt = $db->prepare("
            SELECT s.*, p.product_code, p.product_name, p.product_type, c.customer_name
            FROM specifications s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $spec = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$spec) {
            return null;
        }

        // Fetch all versions
        $vStmt = $db->prepare("
            SELECT sv.*, u.full_name as approved_by_name, cc.title as change_control_title
            FROM specification_versions sv
            LEFT JOIN users u ON sv.approved_by = u.id
            LEFT JOIN change_controls cc ON sv.change_control_id = cc.id
            WHERE sv.specification_id = ?
            ORDER BY sv.version_number DESC
        ");
        $vStmt->execute([$id]);
        $spec['versions'] = $vStmt->fetchAll(PDO::FETCH_ASSOC);

        return $spec;
    }
}
