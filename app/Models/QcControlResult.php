<?php

namespace App\Models;

use App\BaseModel;

class QcControlResult extends BaseModel
{
    protected static string $table = 'qc_control_results';
    protected static string $primaryKey = 'id';

    public static function findByLot(int $lotId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT r.*, u.full_name AS entered_by_name, i.instrument_name
            FROM qc_control_results r
            LEFT JOIN users u ON r.entered_by = u.id
            LEFT JOIN instruments i ON r.instrument_id = i.id
            WHERE r.control_lot_id = ?
            ORDER BY r.entered_at ASC, r.id ASC
        ");
        $stmt->execute([$lotId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function parameterStats(int $lotId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as n,
                ROUND(AVG(result_value)::numeric, 6) as mean,
                ROUND(STDDEV(result_value)::numeric, 6) as stddev,
                MIN(result_value) as min_val,
                MAX(result_value) as max_val
            FROM qc_control_results
            WHERE control_lot_id = ?
        ");
        $stmt->execute([$lotId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Westgard multi-rule assessment for a control lot.
     *
     * Returns an array of violations. Requires the lot's target mean and
     * standard deviation (either stored on the lot or estimated from data).
     */
    public static function westgard(int $lotId, ?float $targetMean = null, ?float $targetSd = null): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT result_value FROM qc_control_results WHERE control_lot_id = ? ORDER BY entered_at ASC, id ASC");
        $stmt->execute([$lotId]);
        $values = array_map('floatval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        if (count($values) < 3) {
            return ['status' => 'insufficient', 'n' => count($values), 'violations' => []];
        }

        $mean = $targetMean ?? array_sum($values) / count($values);
        $sd = $targetSd;
        if ($sd === null || $sd <= 0) {
            $variance = 0.0;
            foreach ($values as $v) {
                $variance += ($v - $mean) ** 2;
            }
            $sd = sqrt($variance / max(1, count($values) - 1));
        }
        if ($sd <= 0) {
            return ['status' => 'ok', 'n' => count($values), 'violations' => [], 'mean' => $mean, 'sd' => $sd];
        }

        $z = array_map(fn($v) => ($v - $mean) / $sd, $values);
        $n = count($z);
        $violations = [];

        // 1_3s: one control value exceeding mean ± 3 SD
        for ($i = 0; $i < $n; $i++) {
            if (abs($z[$i]) >= 3) {
                $violations[] = ['rule' => '1_3s', 'label' => '1₃ₛ', 'index' => $i,
                    'text' => 'One control value exceeds the mean by more than 3 SD (random error — reject run).'];
            }
        }

        // 2_2s: two consecutive values exceeding mean ± 2 SD on the same side
        for ($i = 0; $i < $n - 1; $i++) {
            $sameSide = ($z[$i] >= 2 && $z[$i + 1] >= 2) || ($z[$i] <= -2 && $z[$i + 1] <= -2);
            if ($sameSide) {
                $violations[] = ['rule' => '2_2s', 'label' => '2₂ₛ', 'index' => $i + 1,
                    'text' => 'Two consecutive values exceed the mean by more than 2 SD on the same side (systematic error).'];
            }
        }

        // R_4s: one value exceeding +2 SD and another exceeding −2 SD within 4 points (range error)
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n && $j <= $i + 3; $j++) {
                if (($z[$i] >= 2 && $z[$j] <= -2) || ($z[$i] <= -2 && $z[$j] >= 2)) {
                    $violations[] = ['rule' => 'R_4s', 'label' => 'R₄ₛ', 'index' => $j,
                        'text' => 'Range check: values differ by more than 4 SD within the same run (random error).'];
                }
            }
        }

        // 4_1s: four consecutive values exceeding mean ± 1 SD on the same side
        for ($i = 0; $i <= $n - 4; $i++) {
            $side = $z[$i] > 0 ? 1 : -1;
            $ok = true;
            for ($k = $i; $k < $i + 4; $k++) {
                if (!($z[$k] * $side > 1)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $violations[] = ['rule' => '4_1s', 'label' => '4₁ₛ', 'index' => $i + 3,
                    'text' => 'Four consecutive values exceed the mean by more than 1 SD on the same side (systematic error).'];
            }
        }

        // 10x: ten consecutive values on the same side of the mean (trend / shift)
        for ($i = 0; $i <= $n - 10; $i++) {
            $side = $z[$i] > 0 ? 1 : -1;
            $ok = true;
            for ($k = $i; $k < $i + 10; $k++) {
                if (!($z[$k] * $side > 0)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $violations[] = ['rule' => '10x', 'label' => '10ₓ', 'index' => $i + 9,
                    'text' => 'Ten consecutive values on the same side of the mean (drift or shift — systematic error).'];
            }
        }

        // Deduplicate same-rule triggers on the same index
        $seen = [];
        $unique = [];
        foreach ($violations as $v) {
            $key = $v['rule'] . ':' . $v['index'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $v;
            }
        }

        return [
            'status' => count($unique) > 0 ? 'violation' : 'ok',
            'n' => $n,
            'mean' => $mean,
            'sd' => $sd,
            'target_mean' => $targetMean,
            'target_sd' => $targetSd,
            'violations' => $unique,
        ];
    }
}
