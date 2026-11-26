<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use PDO;

class ProductionLot extends BaseModel
{
    protected static string $table = 'production_lots';
    protected static string $primaryKey = 'id';

    public static function allWithRelations(array $filters = []): array
    {
        $db = \App\Helpers\Database::connect();
        $where = ['pl.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['disposition'])) {
            $where[] = 'pl.disposition = ?';
            $params[] = $filters['disposition'];
        }
        if (!empty($filters['product_id'])) {
            $where[] = 'pl.product_id = ?';
            $params[] = (int)$filters['product_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(pl.lot_number ILIKE ? OR pl.erp_batch_number ILIKE ? OR p.product_name ILIKE ? OR p.product_code ILIKE ?)';
            $s = '%' . trim($filters['search']) . '%';
            $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT pl.*,
                   p.product_code, p.product_name, p.category AS product_category,
                   sv.version_number AS spec_version_number,
                   sp.spec_code, sp.grade AS spec_grade,
                   u.full_name AS disposition_by_name,
                   (SELECT COUNT(*) FROM samples s WHERE s.production_lot_id = pl.id AND s.deleted_at IS NULL) AS sample_count,
                   (SELECT COUNT(*) FROM samples s WHERE s.production_lot_id = pl.id AND s.status = 'Approved' AND s.deleted_at IS NULL) AS approved_sample_count,
                   (SELECT COUNT(*) FROM oos_records oos JOIN samples s2 ON oos.sample_id = s2.id WHERE s2.production_lot_id = pl.id AND oos.status NOT IN ('Closed', 'Resolved', 'Cancelled')) AS open_oos_count
            FROM production_lots pl
            JOIN products p ON pl.product_id = p.id
            LEFT JOIN specification_versions sv ON pl.spec_version_id = sv.id
            LEFT JOIN specifications sp ON sv.specification_id = sp.id
            LEFT JOIN users u ON pl.disposition_by = u.id
            {$whereClause}
            ORDER BY pl.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findWithRelations(int $id): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT pl.*,
                   p.product_code, p.product_name, p.category AS product_category,
                   sv.version_number AS spec_version_number,
                   sp.spec_code, sp.grade AS spec_grade,
                   u.full_name AS disposition_by_name,
                   c.full_name AS created_by_name
            FROM production_lots pl
            JOIN products p ON pl.product_id = p.id
            LEFT JOIN specification_versions sv ON pl.spec_version_id = sv.id
            LEFT JOIN specifications sp ON sv.specification_id = sp.id
            LEFT JOIN users u ON pl.disposition_by = u.id
            LEFT JOIN users c ON pl.created_by = c.id
            WHERE pl.id = ? AND pl.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $lot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lot) return null;

        // Fetch associated samples
        $sStmt = $db->prepare("
            SELECT s.*, u.full_name AS analyst_name,
                   (SELECT COUNT(*) FROM sample_tests st WHERE st.sample_id = s.id AND st.deleted_at IS NULL) AS total_tests,
                   (SELECT COUNT(*) FROM sample_tests st WHERE st.sample_id = s.id AND st.status = 'Completed' AND st.deleted_at IS NULL) AS completed_tests
            FROM samples s
            LEFT JOIN users u ON s.assigned_analyst_id = u.id
            WHERE s.production_lot_id = ? AND s.deleted_at IS NULL
            ORDER BY s.created_at ASC
        ");
        $sStmt->execute([$id]);
        $lot['samples'] = $sStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch OOS records
        $oosStmt = $db->prepare("
            SELECT oos.*, s.sample_code, oos.test_parameter, oos.specification_range
            FROM oos_records oos
            JOIN samples s ON oos.sample_id = s.id
            WHERE s.production_lot_id = ?
            ORDER BY oos.created_at DESC
        ");
        $oosStmt->execute([$id]);
        $lot['oos_records'] = $oosStmt->fetchAll(PDO::FETCH_ASSOC);

        return $lot;
    }
}
