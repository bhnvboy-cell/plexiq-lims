<?php

namespace App\Models;

use App\BaseModel;

class CoaTemplate extends BaseModel
{
    protected static string $table = 'coa_templates';
    protected static string $primaryKey = 'id';

    public static function getDefault(): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM coa_templates WHERE is_default = TRUE AND is_active = TRUE LIMIT 1");
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
