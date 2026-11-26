<?php

namespace App\Models;

use App\BaseModel;

class SampleTest extends BaseModel
{
    protected static string $table = 'sample_tests';
    protected static string $primaryKey = 'id';

    public static function findBySample(int $sampleId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT st.*,
                   COALESCE(a.analysis_code, t.test_code) AS test_code,
                   COALESCE(a.analysis_name, t.test_name) AS test_name,
                   t.min_spec_limit, t.max_spec_limit,
                   COALESCE(r.spec_text_snapshot, t.spec_limit_text,
                       CASE WHEN r.spec_min_snapshot IS NOT NULL OR r.spec_max_snapshot IS NOT NULL
                            THEN CONCAT(COALESCE(r.spec_min_snapshot::text, ''), ' - ', COALESCE(r.spec_max_snapshot::text, ''))
                            ELSE NULL END,
                       CASE WHEN t.min_spec_limit IS NOT NULL OR t.max_spec_limit IS NOT NULL
                            THEN CONCAT(COALESCE(t.min_spec_limit::text, ''), ' - ', COALESCE(t.max_spec_limit::text, ''))
                            ELSE NULL END
                   ) AS spec_limit_text,
                   COALESCE(a.sop_reference, m.method_name) AS method_name,
                   COALESCE(r.unit_snapshot, u.unit_code) AS unit_code,
                   u.unit_name,
                   ua.full_name AS assigned_to_name,
                   r.id AS result_id, r.result_value, r.result_text, r.reportable_value, r.is_within_spec,
                   r.entered_at, r.reviewed_at, r.approved_at,
                   r.entered_by AS result_entered_by, r.reviewed_by AS result_reviewed_by, r.approved_by AS result_approved_by
            FROM sample_tests st
            LEFT JOIN tests t ON st.test_id = t.id
            LEFT JOIN analyses a ON st.analysis_id = a.id
            LEFT JOIN methods m ON t.method_id = m.id
            LEFT JOIN units u ON t.unit_id = u.id
            LEFT JOIN users ua ON st.assigned_to = ua.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.deleted_at IS NULL AND r.revision = (
                SELECT MAX(r2.revision) FROM results r2 WHERE r2.sample_test_id = st.id AND r2.deleted_at IS NULL
            )
            WHERE st.sample_id = ? AND st.deleted_at IS NULL
            ORDER BY COALESCE(a.analysis_code, t.test_code)
        ");
        $stmt->execute([$sampleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $db = \App\Helpers\Database::connect();
        $timestamp = '';
        switch ($status) {
            case 'In Progress': $timestamp = 'assigned_at'; break;
            case 'Completed': $timestamp = 'completed_at'; break;
            case 'Reviewed': $timestamp = 'reviewed_at'; break;
            case 'Approved': $timestamp = 'approved_at'; break;
        }
        $sql = "UPDATE sample_tests SET status = ?";
        if ($timestamp) {
            $sql .= ", {$timestamp} = CURRENT_TIMESTAMP";
        }
        $sql .= ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$status, $id]);
    }

    public static function pendingCount(): int
    {
        $db = \App\Helpers\Database::connect();
        return (int)$db->query("SELECT COUNT(*) FROM sample_tests WHERE status = 'Pending' AND deleted_at IS NULL")->fetchColumn();
    }

    public static function inProgressCount(): int
    {
        $db = \App\Helpers\Database::connect();
        return (int)$db->query("SELECT COUNT(*) FROM sample_tests WHERE status = 'In Progress' AND deleted_at IS NULL")->fetchColumn();
    }
}
