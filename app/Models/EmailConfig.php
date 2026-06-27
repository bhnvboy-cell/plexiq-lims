<?php

namespace App\Models;

use App\BaseModel;

class EmailConfig extends BaseModel
{
    protected static string $table = 'email_configurations';
    protected static string $primaryKey = 'id';

    public static function getDefault(): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM email_configurations WHERE is_default = TRUE AND is_active = TRUE LIMIT 1");
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
