<?php

namespace App\Models;

use App\BaseModel;

class AnalysisParameter extends BaseModel
{
    protected static string $table = 'analysis_parameters';

    public static function active(): array
    {
        $db = \App\Helpers\Database::connect();
        return $db->query(
            "SELECT * FROM analysis_parameters WHERE is_active = TRUE ORDER BY parameter_code"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function allWithUsage(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("
            SELECT ap.*,
                (SELECT COUNT(*) FROM sample_analysis_parameters sap WHERE sap.parameter_id = ap.id) AS sample_count,
                (SELECT COUNT(*) FROM instrument_parameter_mapping imp WHERE imp.parameter_id = ap.id) AS mapping_count
            FROM analysis_parameters ap
            ORDER BY ap.parameter_code
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
