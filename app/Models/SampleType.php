<?php

namespace App\Models;

use App\BaseModel;

class SampleType extends BaseModel
{
    protected static string $table = 'sample_types';
    protected static string $primaryKey = 'id';

    public static function active(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM sample_types WHERE is_active = TRUE ORDER BY type_name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
