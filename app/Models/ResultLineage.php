<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use PDO;

class ResultLineage extends BaseModel
{
    protected static string $table = 'result_lineage';
    protected static string $primaryKey = 'id';

    public static function findByResult(int $resultId): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT rl.*,
                   r.result_value, r.reportable_value, r.is_within_spec, r.is_invalidated, r.invalidation_reason,
                   s.sample_code, s.sample_stage, p.product_name, p.product_code,
                   COALESCE(a.analysis_code, t.test_code) as analysis_code,
                   COALESCE(a.analysis_name, t.test_name) as analysis_name,
                   i.instrument_name, i.instrument_code,
                   u.full_name as analyst_name,
                   rev.full_name as reviewer_name,
                   app.full_name as approver_name,
                   lb.batch_code as lab_batch_code,
                   rv.original_filename as raw_vault_filename, rv.sha256_hash as raw_vault_hash,
                   sp.spec_code, sv.version_number as spec_version_number,
                   cd.document_number as coa_number
            FROM result_lineage rl
            JOIN results r ON rl.result_id = r.id
            JOIN sample_tests st ON rl.sample_test_id = st.id
            JOIN samples s ON rl.sample_id = s.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN tests t ON st.test_id = t.id
            LEFT JOIN analyses a ON st.analysis_id = a.id
            LEFT JOIN instruments i ON rl.instrument_id = i.id
            LEFT JOIN users u ON rl.analyst_id = u.id
            LEFT JOIN users rev ON rl.reviewer_id = rev.id
            LEFT JOIN users app ON rl.approver_id = app.id
            LEFT JOIN lab_batches lb ON rl.lab_batch_id = lb.id
            LEFT JOIN instrument_raw_vault rv ON rl.raw_vault_id = rv.id
            LEFT JOIN specification_versions sv ON rl.spec_version_id = sv.id
            LEFT JOIN specifications sp ON sv.specification_id = sp.id
            LEFT JOIN coa_documents cd ON rl.coa_id = cd.id
            WHERE rl.result_id = ?
            ORDER BY rl.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$resultId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
