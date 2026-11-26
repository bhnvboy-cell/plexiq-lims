<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use PDO;

class SampleLifecycleTransition extends BaseModel
{
    protected static string $table = 'sample_lifecycle_transitions';

    public static function findAllowedTransitions(?int $sampleTypeId, string $currentStatus): array
    {
        $db = static::getPdo();
        $stmt = $db->prepare("
            SELECT * FROM sample_lifecycle_transitions
            WHERE (sample_type_id = ? OR sample_type_id IS NULL)
              AND from_status = ?
            ORDER BY sample_type_id DESC NULLS LAST
        ");
        $stmt->execute([$sampleTypeId, $currentStatus]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
