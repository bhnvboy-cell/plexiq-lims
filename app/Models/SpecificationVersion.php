<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use PDO;

class SpecificationVersion extends BaseModel
{
    protected static string $table = 'specification_versions';

    public static function findWithLimits(int $versionId): ?array
    {
        $db = static::getPdo();
        $stmt = $db->prepare("
            SELECT sv.*, s.spec_code, s.product_id, s.grade, p.product_code, p.product_name,
                   u.full_name as approved_by_name
            FROM specification_versions sv
            JOIN specifications s ON sv.specification_id = s.id
            JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON sv.approved_by = u.id
            WHERE sv.id = ?
        ");
        $stmt->execute([$versionId]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$version) {
            return null;
        }

        $lStmt = $db->prepare("
            SELECT sl.*, c.component_code, c.component_name, c.unit, c.data_type,
                   c.decimal_places, c.rounding_method, c.significant_figures, c.loq, c.uloq,
                   a.analysis_code, a.analysis_name
            FROM specification_limits sl
            JOIN components c ON sl.component_id = c.id
            LEFT JOIN analyses a ON c.analysis_id = a.id
            WHERE sl.spec_version_id = ?
            ORDER BY sl.sort_order ASC, c.component_code ASC
        ");
        $lStmt->execute([$versionId]);
        $version['limits'] = $lStmt->fetchAll(PDO::FETCH_ASSOC);

        return $version;
    }
}
