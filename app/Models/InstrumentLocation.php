<?php

namespace App\Models;

use App\BaseModel;

class InstrumentLocation extends BaseModel
{
    protected static string $table = 'instrument_locations';
    protected static string $primaryKey = 'id';

    public static function allActive(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM instrument_locations WHERE is_active = TRUE ORDER BY location_code");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
