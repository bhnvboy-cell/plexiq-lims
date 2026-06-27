<?php
namespace App\Models;

use App\BaseModel;

class ProductTest extends BaseModel
{
    protected static string $table = 'products_tests';
    protected static string $primaryKey = 'id';

    public static function findByProduct(int $productId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT pt.*, t.test_code, t.test_name, t.spec_limit_text AS global_spec,
                   m.method_name, m.method_code,
                   u.unit_code, u.unit_name
            FROM products_tests pt
            JOIN tests t ON pt.test_id = t.id
            LEFT JOIN methods m ON t.method_id = m.id
            LEFT JOIN units u ON t.unit_id = u.id
            WHERE pt.product_id = ? AND pt.is_active = TRUE AND t.is_active = TRUE
            ORDER BY pt.sort_order, t.test_code
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function withProductAndTest(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("
            SELECT pt.*, p.product_code, p.product_name, p.category,
                   t.test_code, t.test_name,
                   t.min_spec_limit AS global_min, t.max_spec_limit AS global_max,
                   COALESCE(pt.min_spec_limit, t.min_spec_limit) AS effective_min,
                   COALESCE(pt.max_spec_limit, t.max_spec_limit) AS effective_max,
                   COALESCE(pt.spec_limit_text, t.spec_limit_text) AS effective_spec
            FROM products_tests pt
            JOIN products p ON pt.product_id = p.id
            JOIN tests t ON pt.test_id = t.id
            ORDER BY p.category, p.product_name, pt.sort_order, t.test_code
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
