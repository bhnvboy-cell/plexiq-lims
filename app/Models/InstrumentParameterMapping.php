<?php

namespace App\Models;

use App\BaseModel;

class InstrumentParameterMapping extends BaseModel
{
    protected static string $table = 'instrument_parameter_mapping';

    public static function forInstrument(int $instrumentId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT imp.*, ap.parameter_code, ap.parameter_name, ap.unit AS parameter_unit
            FROM instrument_parameter_mapping imp
            JOIN analysis_parameters ap ON imp.parameter_id = ap.id
            WHERE imp.instrument_id = ?
            ORDER BY imp.source_column
        ");
        $stmt->execute([$instrumentId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function activeForInstrument(int $instrumentId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT imp.*, ap.parameter_code, ap.parameter_name, ap.data_type
            FROM instrument_parameter_mapping imp
            JOIN analysis_parameters ap ON imp.parameter_id = ap.id
            WHERE imp.instrument_id = ? AND imp.is_active = TRUE
            ORDER BY imp.source_column
        ");
        $stmt->execute([$instrumentId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
