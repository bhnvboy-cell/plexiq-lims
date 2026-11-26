<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use PDO;

class ProductTestPlan extends BaseModel
{
    protected static string $table = 'product_test_plans';

    public static function findByProduct(int $productId): array
    {
        $db = static::getPdo();
        $stmt = $db->prepare("
            SELECT ptp.*, a.analysis_code, a.analysis_name, a.sop_reference, a.expected_duration_minutes,
                   p.product_code, p.product_name
            FROM product_test_plans ptp
            JOIN analyses a ON ptp.analysis_id = a.id
            JOIN products p ON ptp.product_id = p.id
            WHERE ptp.product_id = ?
            ORDER BY ptp.stage ASC, ptp.sort_order ASC, a.analysis_code ASC
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
