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
            SELECT COALESCE(SUM(i.total_amount), 0) AS total,
                   COALESCE(SUM(p.paid_amount), 0) AS paid,
                   COALESCE(SUM(i.total_amount - COALESCE(p.paid_amount, 0)), 0) AS balance
            FROM " . static::$table . " i
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) AS paid_amount
                FROM payments
                GROUP BY invoice_id
            ) p ON p.invoice_id = i.id
            WHERE EXTRACT(YEAR FROM i.invoice_date) = ?
              AND EXTRACT(MONTH FROM i.invoice_date) = ?
        ");
        $stmt->execute([$year, $month]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
