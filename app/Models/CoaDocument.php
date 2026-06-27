<?php

namespace App\Models;

use App\BaseModel;

class CoaDocument extends BaseModel
{
    protected static string $table = 'coa_documents';
    protected static string $primaryKey = 'id';

    public static function generateNumber(): string
    {
        return 'COA-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    public static function findBySample(int $sampleId): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT cd.*, u.full_name AS generated_by_name, ru.full_name AS released_by_name
            FROM coa_documents cd
            LEFT JOIN users u ON cd.generated_by = u.id
            LEFT JOIN users ru ON cd.released_by = ru.id
            WHERE cd.sample_id = ? AND cd.is_active = TRUE
            ORDER BY cd.generated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$sampleId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
