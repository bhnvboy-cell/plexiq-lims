<?php

namespace App\Models;

use App\BaseModel;

class Product extends BaseModel
{
    protected static string $table = 'products';
    protected static string $primaryKey = 'id';

    public static function active(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM products WHERE is_active = TRUE ORDER BY product_name");
        return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
    }

    public static function findBySapId(string $sapId): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM products WHERE sap_id = ?");
        $stmt->execute([$sapId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
