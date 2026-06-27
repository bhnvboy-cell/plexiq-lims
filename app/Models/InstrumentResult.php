<?php

namespace App\Models;

use App\BaseModel;

class InstrumentResult extends BaseModel
{
    protected static string $table = 'instrument_results';
    protected static string $primaryKey = 'id';

    public static function pending(): array
    {
        $db = \App\Helpers\Database::connect();
        return $db->query("
            SELECT ir.*, i.instrument_name, i.instrument_code
            FROM instrument_results ir
            JOIN instruments i ON ir.instrument_id = i.id
            WHERE ir.status = 'Pending'
            ORDER BY ir.created_at DESC
        ")->fetchAll();
    }
}
