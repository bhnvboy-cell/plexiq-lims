<?php

namespace App\Models;

use App\BaseModel;

class Result extends BaseModel
{
    protected static string $table = 'results';
    protected static string $primaryKey = 'id';

    public static function findBySampleTest(int $sampleTestId): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT r.*, u.full_name AS entered_by_name, ru.full_name AS reviewed_by_name, au.full_name AS approved_by_name
            FROM results r
            LEFT JOIN users u ON r.entered_by = u.id
            LEFT JOIN users ru ON r.reviewed_by = ru.id
            LEFT JOIN users au ON r.approved_by = au.id
            WHERE r.sample_test_id = ? AND r.deleted_at IS NULL
            ORDER BY r.revision DESC
            LIMIT 1
        ");
        $stmt->execute([$sampleTestId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function validateAgainstSpec(float $value, float $min, float $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    public static function getRevisions(int $resultId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT rr.*, u.full_name AS changed_by_name
            FROM result_revisions rr
            LEFT JOIN users u ON rr.changed_by = u.id
            WHERE rr.result_id = ?
            ORDER BY rr.revision DESC
        ");
        $stmt->execute([$resultId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function saveRevision(int $resultId, int $revision, ?float $value, ?string $text, int $changedBy, string $reason): void
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            INSERT INTO result_revisions (result_id, revision, result_value, result_text, changed_by, change_reason)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$resultId, $revision, $value, $text, $changedBy, $reason]);
    }
}
