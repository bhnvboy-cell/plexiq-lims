<?php

namespace App\Models;

use App\BaseModel;

class Payment extends BaseModel
{
    protected static string $table = 'payments';

    public static function getTotalPaid(int $invoiceId): float
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM " . static::$table . " WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        return (float)$stmt->fetchColumn();
    }
}
