<?php

namespace App\Models;

use App\BaseModel;

class Customer extends BaseModel
{
    protected static string $table = 'customers';
    protected static string $primaryKey = 'id';

    public static function active(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM customers WHERE is_active = TRUE ORDER BY customer_name");
        return $stmt->fetchAll(\PDO::FETCH_CLASS, static::class);
    }

    public static function findBySapId(string $sapId): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM customers WHERE sap_id = ?");
        $stmt->execute([$sapId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
