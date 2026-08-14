<?php

namespace App\Models;

use App\BaseModel;

class SampleAnalysisParameter extends BaseModel
{
    protected static string $table = 'sample_analysis_parameters';

    public static function forSample(int $sampleId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT sap.*, ap.parameter_code, ap.parameter_name, ap.category,
                   ap.data_type, ap.decimal_places, ap.spec_min AS base_spec_min,
                   ap.spec_max AS base_spec_max, ap.spec_target AS base_spec_target,
                   en.full_name AS entered_by_name, rv.full_name AS reviewed_by_name,
                   apv.full_name AS approved_by_name
            FROM sample_analysis_parameters sap
            JOIN analysis_parameters ap ON sap.parameter_id = ap.id
            LEFT JOIN users en ON sap.entered_by = en.id
            LEFT JOIN users rv ON sap.reviewed_by = rv.id
            LEFT JOIN users apv ON sap.approved_by = apv.id
            WHERE sap.sample_id = ?
            ORDER BY ap.parameter_code
        ");
        $stmt->execute([$sampleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function findWithDetails(int $id): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT sap.*, ap.parameter_code, ap.parameter_name, ap.category,
                   ap.data_type, ap.decimal_places, s.sample_code
            FROM sample_analysis_parameters sap
            JOIN analysis_parameters ap ON sap.parameter_id = ap.id
            JOIN samples s ON sap.sample_id = s.id
            WHERE sap.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function statusBadge(string $status): string
    {
        $map = [
            'Pending' => 'secondary',
            'In Progress' => 'info',
            'Completed' => 'primary',
            'Reviewed' => 'info',
            'Approved' => 'success',
            'Rejected' => 'danger',
        ];
        $class = $map[$status] ?? 'secondary';
        return "<span class='badge bg-{$class}'>{$status}</span>";
    }
}
