<?php

namespace App\Models;

use App\BaseModel;

class SpcParameter extends BaseModel
{
    protected static string $table = 'spc_parameters';
    protected static string $primaryKey = 'id';

    public static function allActive(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM spc_parameters WHERE is_active = TRUE ORDER BY category, parameter_code");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function dashboardStats(): array
    {
        $db = \App\Helpers\Database::connect();
        $stats = [];
        $stats['total_parameters'] = (int)$db->query("SELECT COUNT(*) FROM spc_parameters WHERE is_active = TRUE")->fetchColumn();
        $stats['total_readings'] = (int)$db->query("SELECT COUNT(*) FROM spc_readings")->fetchColumn();
        $stats['categories'] = $db->query("SELECT DISTINCT category FROM spc_parameters WHERE is_active = TRUE ORDER BY category")->fetchAll(\PDO::FETCH_COLUMN);
        return $stats;
    }
}
