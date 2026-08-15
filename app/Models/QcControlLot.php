<?php

namespace App\Models;

use App\BaseModel;

class QcControlLot extends BaseModel
{
    protected static string $table = 'qc_control_lots';
    protected static string $primaryKey = 'id';

    public static function allActive(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("
            SELECT l.*,
                   (SELECT COUNT(*) FROM qc_control_results r WHERE r.control_lot_id = l.id) AS reading_count,
                   (SELECT MAX(r.entered_at) FROM qc_control_results r WHERE r.control_lot_id = l.id) AS last_result_at
            FROM qc_control_lots l
            ORDER BY l.is_active DESC, l.lot_number
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function dashboardStats(): array
    {
        $db = \App\Helpers\Database::connect();
        $stats = [];
        $stats['active_lots'] = (int)$db->query("SELECT COUNT(*) FROM qc_control_lots WHERE is_active = TRUE")->fetchColumn();
        $stats['total_results'] = (int)$db->query("SELECT COUNT(*) FROM qc_control_results")->fetchColumn();
        $stats['expiring_soon'] = (int)$db->query(
            "SELECT COUNT(*) FROM qc_control_lots WHERE is_active = TRUE AND expiry_date IS NOT NULL AND expiry_date > CURRENT_TIMESTAMP AND expiry_date < CURRENT_TIMESTAMP + INTERVAL '90 days'"
        )->fetchColumn();
        return $stats;
    }

    public static function findByLotNumber(string $lotNumber): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM qc_control_lots WHERE LOWER(lot_number) = LOWER(?)");
        $stmt->execute([$lotNumber]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
