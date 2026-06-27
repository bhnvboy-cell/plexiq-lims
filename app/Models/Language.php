<?php

namespace App\Models;

use App\BaseModel;

class Language extends BaseModel
{
    protected static string $table = 'languages';

    public static function getActive(): array
    {
        return static::where('is_active', 1);
    }

    public static function getDefault(): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM " . static::$table . " WHERE is_default = 1 LIMIT 1");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
