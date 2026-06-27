<?php

namespace App\Models;

use App\BaseModel;

class SpcReading extends BaseModel
{
    protected static string $table = 'spc_readings';
    protected static string $primaryKey = 'id';

    public static function findByParameter(int $parameterId, int $limit = 100): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT r.*, u.full_name AS entered_by_name
            FROM spc_readings r
            LEFT JOIN users u ON r.entered_by = u.id
            WHERE r.parameter_id = ?
            ORDER BY r.reading_date DESC
            LIMIT ?
        ");
        $stmt->execute([$parameterId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function parameterStats(int $parameterId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as n,
                ROUND(AVG(value)::numeric, 4) as mean,
                ROUND(STDDEV(value)::numeric, 4) as stddev,
                MIN(value) as min_val,
                MAX(value) as max_val
            FROM spc_readings
            WHERE parameter_id = ?
        ");
        $stmt->execute([$parameterId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function calculateCp(int $parameterId): ?array
    {
        $db = \App\Helpers\Database::connect();
        $param = SpcParameter::find($parameterId);
        if (!$param || $param['spec_min'] === null || $param['spec_max'] === null) return null;

        $stats = self::parameterStats($parameterId);
        if (!$stats || $stats['n'] < 2) return null;

        $mean = (float)$stats['mean'];
        $stddev = (float)$stats['stddev'];
        $usl = (float)$param['spec_max'];
        $lsl = (float)$param['spec_min'];
        $target = $param['spec_target'] !== null ? (float)$param['spec_target'] : null;

        $cp = $stddev > 0 ? ($usl - $lsl) / (6 * $stddev) : null;
        $cpu = $stddev > 0 ? ($usl - $mean) / (3 * $stddev) : null;
        $cpl = $stddev > 0 ? ($mean - $lsl) / (3 * $stddev) : null;
        $cpk = $cpu !== null && $cpl !== null ? min($cpu, $cpl) : null;

        $cp_upper = $target !== null ? ($usl - $target) / (3 * $stddev) : null;
        $cp_lower = $target !== null ? ($target - $lsl) / (3 * $stddev) : null;
        $cpm = ($cp_upper !== null && $cp_lower !== null) ? min($cp_upper, $cp_lower) : null;

        return [
            'n' => (int)$stats['n'],
            'mean' => $mean,
            'stddev' => $stddev,
            'min' => (float)$stats['min_val'],
            'max' => (float)$stats['max_val'],
            'usl' => $usl,
            'lsl' => $lsl,
            'target' => $target,
            'cp' => $cp !== null ? round($cp, 4) : null,
            'cpk' => $cpk !== null ? round($cpk, 4) : null,
            'cpu' => $cpu !== null ? round($cpu, 4) : null,
            'cpl' => $cpl !== null ? round($cpl, 4) : null,
            'cpm' => $cpm !== null ? round($cpm, 4) : null,
        ];
    }
}
