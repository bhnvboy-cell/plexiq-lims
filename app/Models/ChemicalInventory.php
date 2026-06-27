<?php

namespace App\Models;

use App\BaseModel;

class ChemicalInventory extends BaseModel
{
    protected static string $table = 'chemical_inventory';
    protected static string $primaryKey = 'id';

    public static function dashboardStats(): array
    {
        $db = \App\Helpers\Database::connect();
        $stats = [];
        $stats['total'] = (int)$db->query("SELECT COUNT(*) FROM chemical_inventory WHERE is_active = TRUE")->fetchColumn();
        $stats['in_stock'] = (int)$db->query("SELECT COUNT(*) FROM chemical_inventory WHERE status = 'In Stock' AND is_active = TRUE")->fetchColumn();
        $stats['low_stock'] = (int)$db->query("SELECT COUNT(*) FROM chemical_inventory WHERE (status = 'Low Stock' OR quantity <= minimum_quantity) AND is_active = TRUE")->fetchColumn();
        $stats['expired'] = (int)$db->query("SELECT COUNT(*) FROM chemical_inventory WHERE (status = 'Expired' OR expiry_date < CURRENT_DATE) AND is_active = TRUE")->fetchColumn();
        return $stats;
    }
}
