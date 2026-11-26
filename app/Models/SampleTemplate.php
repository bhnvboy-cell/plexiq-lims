<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use PDO;

class SampleTemplate extends BaseModel
{
    protected static string $table = 'sample_templates';

    public static function active(): array
    {
        $db = static::getPdo();
        $stmt = $db->query("
            SELECT st.*, t.name as sample_type_name
            FROM sample_templates st
            LEFT JOIN sample_types t ON st.sample_type_id = t.id
            WHERE st.is_active = TRUE
            ORDER BY st.template_name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
