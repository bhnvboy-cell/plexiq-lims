<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use PDO;

class Analysis extends BaseModel
{
    protected static string $table = 'analyses';

    public static function activeWithComponents(): array
    {
        $db = static::getPdo();
        $analyses = $db->query("
            SELECT a.*, m.method_code, m.method_name
            FROM analyses a
            LEFT JOIN methods m ON a.method_id = m.id
            WHERE a.is_active = TRUE
            ORDER BY a.analysis_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($analyses as &$a) {
            $stmt = $db->prepare("
                SELECT c.*, f.name as formula_name
                FROM components c
                LEFT JOIN calculation_formulas f ON c.formula_id = f.id
                WHERE c.analysis_id = ? AND c.is_active = TRUE
                ORDER BY c.component_code ASC
            ");
            $stmt->execute([$a['id']]);
            $a['components'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $analyses;
    }
}
