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
            SELECT st.*, t.test_code, t.test_name, t.min_spec_limit, t.max_spec_limit, t.spec_limit_text,
                   m.method_name, u.unit_code, u.unit_name,
                   ua.full_name AS assigned_to_name,
                   r.id AS result_id, r.result_value, r.result_text, r.is_within_spec,
                   r.entered_at, r.reviewed_at, r.approved_at,
                   r.entered_by AS result_entered_by, r.reviewed_by AS result_reviewed_by, r.approved_by AS result_approved_by
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN methods m ON t.method_id = m.id
            LEFT JOIN units u ON t.unit_id = u.id
            LEFT JOIN users ua ON st.assigned_to = ua.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.revision = (
                SELECT MAX(r2.revision) FROM results r2 WHERE r2.sample_test_id = st.id
            )
            WHERE st.sample_id = ?
            ORDER BY t.test_code
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
        return (int)$db->query("SELECT COUNT(*) FROM sample_tests WHERE status = 'Pending'")->fetchColumn();
    }

    public static function inProgressCount(): int
    {
        $db = \App\Helpers\Database::connect();
        return (int)$db->query("SELECT COUNT(*) FROM sample_tests WHERE status = 'In Progress'")->fetchColumn();
    }
}
