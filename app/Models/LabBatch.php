<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use PDO;

class LabBatch extends BaseModel
{
    protected static string $table = 'lab_batches';
    protected static string $primaryKey = 'id';

    public static function allWithRelations(array $filters = []): array
    {
        $db = \App\Helpers\Database::connect();
        $where = ['lb.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'lb.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['instrument_id'])) {
            $where[] = 'lb.instrument_id = ?';
            $params[] = (int)$filters['instrument_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(lb.batch_code ILIKE ? OR a.analysis_code ILIKE ? OR i.instrument_name ILIKE ?)';
            $s = '%' . trim($filters['search']) . '%';
            $params[] = $s; $params[] = $s; $params[] = $s;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT lb.*,
                   a.analysis_code, a.analysis_name, a.sop_reference,
                   i.instrument_name, i.instrument_code,
                   u.full_name AS analyst_name,
                   c.full_name AS created_by_name,
                   (SELECT COUNT(*) FROM lab_batch_items lbi WHERE lbi.lab_batch_id = lb.id) AS total_items,
                   (SELECT COUNT(*) FROM lab_batch_items lbi WHERE lbi.lab_batch_id = lb.id AND lbi.item_type = 'sample') AS sample_count,
                   (SELECT COUNT(*) FROM lab_batch_items lbi WHERE lbi.lab_batch_id = lb.id AND lbi.item_type != 'sample') AS control_count
            FROM lab_batches lb
            LEFT JOIN analyses a ON lb.analysis_id = a.id
            LEFT JOIN instruments i ON lb.instrument_id = i.id
            LEFT JOIN users u ON lb.analyst_id = u.id
            LEFT JOIN users c ON lb.created_by = c.id
            {$whereClause}
            ORDER BY lb.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findWithRelations(int $id): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT lb.*,
                   a.analysis_code, a.analysis_name, a.sop_reference,
                   i.instrument_name, i.instrument_code,
                   m.method_name,
                   u.full_name AS analyst_name,
                   c.full_name AS created_by_name
            FROM lab_batches lb
            LEFT JOIN analyses a ON lb.analysis_id = a.id
            LEFT JOIN instruments i ON lb.instrument_id = i.id
            LEFT JOIN methods m ON lb.method_id = m.id
            LEFT JOIN users u ON lb.analyst_id = u.id
            LEFT JOIN users c ON lb.created_by = c.id
            WHERE lb.id = ? AND lb.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$batch) return null;

        $itemsStmt = $db->prepare("
            SELECT lbi.*,
                   s.sample_code, s.sample_stage, p.product_name,
                   st.status AS test_status,
                   r.result_value, r.reportable_value, r.is_within_spec
            FROM lab_batch_items lbi
            LEFT JOIN samples s ON lbi.sample_id = s.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN sample_tests st ON lbi.sample_test_id = st.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.deleted_at IS NULL
            WHERE lbi.lab_batch_id = ?
            ORDER BY lbi.sequence_index ASC
        ");
        $itemsStmt->execute([$id]);
        $batch['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        return $batch;
    }
}
