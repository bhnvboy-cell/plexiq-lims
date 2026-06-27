<?php

namespace App\Models;

use App\BaseModel;

class OosInvestigation extends BaseModel
{
    protected static string $table = 'oos_investigations';
    protected static string $primaryKey = 'id';

    public static function forOos(int $oosId): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM oos_investigations WHERE oos_id = ? LIMIT 1");
        $stmt->execute([$oosId]);
        return $stmt->fetch() ?: null;
    }

    public static function upsert(int $oosId, array $data): void
    {
        $db = \App\Helpers\Database::connect();
        $existing = self::forOos($oosId);
        if ($existing) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            self::update($existing['id'], $data);
        } else {
            $data['oos_id'] = $oosId;
            self::create($data);
        }
    }
}
