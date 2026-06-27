<?php

namespace App\Models;

use App\BaseModel;

class Manufacturer extends BaseModel
{
    protected static string $table = 'manufacturers';
    protected static string $primaryKey = 'id';

    public static function getDefault(): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM manufacturers WHERE is_active = TRUE ORDER BY id LIMIT 1");
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
