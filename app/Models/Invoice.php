<?php

namespace App\Models;

use App\BaseModel;

class Invoice extends BaseModel
{
    protected static string $table = 'invoices';

    public static function findByCustomer(int $customerId): array
    {
        return static::where('customer_id', $customerId);
    }

    public static function findByStatus(string $status): array
    {
        return static::where('status', $status);
    }

    public static function getTotalsByMonth(int $year, int $month): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total), 0) as total,
                   COALESCE(SUM(paid), 0) as paid,
                   COALESCE(SUM(balance), 0) as balance
            FROM " . static::$table . "
            WHERE EXTRACT(YEAR FROM issued_at) = ?
              AND EXTRACT(MONTH FROM issued_at) = ?
        ");
        $stmt->execute([$year, $month]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
