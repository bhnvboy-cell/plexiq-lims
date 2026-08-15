<?php

namespace App\Models;

use App\BaseModel;

class ChainOfCustody extends BaseModel
{
    protected static string $table = 'chain_of_custody';
    protected static string $primaryKey = 'id';

    public static function findBySample(int $sampleId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT c.*, s.sample_code,
                   tf.full_name AS transferred_by_name,
                   rc.full_name AS received_by_name
            FROM chain_of_custody c
            JOIN samples s ON c.sample_id = s.id
            LEFT JOIN users tf ON c.transferred_by = tf.id
            LEFT JOIN users rc ON c.received_by = rc.id
            WHERE c.sample_id = ?
            ORDER BY c.transferred_at ASC, c.id ASC
        ");
        $stmt->execute([$sampleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function recent(int $limit = 50): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("
            SELECT c.*, s.sample_code,
                   tf.full_name AS transferred_by_name,
                   rc.full_name AS received_by_name
            FROM chain_of_custody c
            JOIN samples s ON c.sample_id = s.id
            LEFT JOIN users tf ON c.transferred_by = tf.id
            LEFT JOIN users rc ON c.received_by = rc.id
            ORDER BY c.transferred_at DESC
            LIMIT {$limit}
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function dashboardStats(): array
    {
        $db = \App\Helpers\Database::connect();
        $stats = [];
        $stats['total_transfers'] = (int)$db->query("SELECT COUNT(*) FROM chain_of_custody")->fetchColumn();
        $stats['pending_receipt'] = (int)$db->query("SELECT COUNT(*) FROM chain_of_custody WHERE received_at IS NULL")->fetchColumn();
        $stats['sealed'] = (int)$db->query("SELECT COUNT(*) FROM chain_of_custody WHERE sealed = TRUE")->fetchColumn();
        return $stats;
    }
}
