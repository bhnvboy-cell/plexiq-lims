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

    /**
     * Apply Nelson (1984) out-of-control rules to a series of readings.
     * Returns a list of violations: [index, rule_number, description].
     *
     * Rule 1: One point beyond 3 sigma from the center line.
     * Rule 2: Nine points in a row on the same side of the center line.
     * Rule 3: Six points in a row steadily increasing or decreasing.
     * Rule 4: Fourteen points in a row alternating up and down.
     * Rule 5: Two of three consecutive points beyond 2 sigma on the same side.
     * Rule 6: Four of five consecutive points beyond 1 sigma on the same side.
     * Rule 7: Fifteen points in a row within 1 sigma (either side).
     * Rule 8: Eight points in a row beyond 1 sigma (either side).
     */
    public static function nelsonRules(array $readings): array
    {
        $values = array_values($readings);
        $n = count($values);
        if ($n < 2) {
            return [];
        }

        $mean = array_sum($values) / $n;
        $sigma = null;
        $squaredDiff = 0.0;
        foreach ($values as $v) {
            $squaredDiff += ($v - $mean) ** 2;
        }
        $sigma = sqrt($squaredDiff / max(1, $n - 1));
        if ($sigma <= 0) {
            $sigma = null;
        }

        $violations = [];
        $z = function (float $v) use ($mean, $sigma): ?float {
            return $sigma !== null ? ($v - $mean) / $sigma : null;
        };

        // Rule 1: one point beyond 3 sigma
        if ($sigma !== null) {
            foreach ($values as $i => $v) {
                if (abs($v - $mean) > 3 * $sigma) {
                    $violations[] = ['index' => $i, 'rule' => 1, 'rule_text' => 'One point beyond 3σ (outside control limits)'];
                }
            }
        }

        // Rule 2: nine points in a row on the same side of the center line
        if ($n >= 9) {
            for ($i = 0; $i <= $n - 9; $i++) {
                $sameSide = true;
                for ($j = $i; $j < $i + 9; $j++) {
                    if (($values[$j] - $mean) * ($values[$i] - $mean) <= 0) {
                        $sameSide = false;
                        break;
                    }
                }
                if ($sameSide) {
                    $violations[] = ['index' => $i + 8, 'rule' => 2, 'rule_text' => 'Nine consecutive points on the same side of the center line'];
                    break;
                }
            }
        }

        // Rule 3: six points in a row steadily increasing or decreasing
        if ($n >= 6) {
            for ($i = 0; $i <= $n - 6; $i++) {
                $inc = true;
                $dec = true;
                for ($j = $i; $j < $i + 5; $j++) {
                    if (!($values[$j + 1] > $values[$j])) {
                        $inc = false;
                    }
                    if (!($values[$j + 1] < $values[$j])) {
                        $dec = false;
                    }
                }
                if ($inc || $dec) {
                    $violations[] = ['index' => $i + 5, 'rule' => 3, 'rule_text' => 'Six points in a row steadily increasing or decreasing'];
                    break;
                }
            }
        }

        // Rule 4: fourteen points in a row alternating up and down
        if ($n >= 14) {
            for ($i = 0; $i <= $n - 14; $i++) {
                $alt = true;
                for ($j = $i + 2; $j < $i + 14; $j++) {
                    $up = $values[$j] > $values[$j - 1];
                    $prevUp = $values[$j - 1] > $values[$j - 2];
                    if ($up === $prevUp) {
                        $alt = false;
                        break;
                    }
                }
                if ($alt) {
                    $violations[] = ['index' => $i + 13, 'rule' => 4, 'rule_text' => 'Fourteen points in a row alternating up and down'];
                    break;
                }
            }
        }

        // Rule 5: two of three consecutive points beyond 2σ on the same side
        if ($sigma !== null && $n >= 3) {
            for ($i = 0; $i <= $n - 3; $i++) {
                $count = 0;
                for ($j = $i; $j < $i + 3; $j++) {
                    if (abs($z($values[$j]) ?? 0) > 2) {
                        $count++;
                    }
                }
                if ($count >= 2) {
                    $violations[] = ['index' => $i + 2, 'rule' => 5, 'rule_text' => 'Two of three consecutive points beyond 2σ on the same side'];
                    break;
                }
            }
        }

        // Rule 6: four of five consecutive points beyond 1σ on the same side
        if ($sigma !== null && $n >= 5) {
            for ($i = 0; $i <= $n - 5; $i++) {
                $count = 0;
                for ($j = $i; $j < $i + 5; $j++) {
                    if (abs($z($values[$j]) ?? 0) > 1) {
                        $count++;
                    }
                }
                if ($count >= 4) {
                    $violations[] = ['index' => $i + 4, 'rule' => 6, 'rule_text' => 'Four of five consecutive points beyond 1σ on the same side'];
                    break;
                }
            }
        }

        // Rule 7: fifteen points in a row within 1σ (either side)
        if ($sigma !== null && $n >= 15) {
            for ($i = 0; $i <= $n - 15; $i++) {
                $within = true;
                for ($j = $i; $j < $i + 15; $j++) {
                    if (abs($z($values[$j]) ?? 99) > 1) {
                        $within = false;
                        break;
                    }
                }
                if ($within) {
                    $violations[] = ['index' => $i + 14, 'rule' => 7, 'rule_text' => 'Fifteen points in a row within 1σ of the center line'];
                    break;
                }
            }
        }

        // Rule 8: eight points in a row beyond 1σ (either side)
        if ($sigma !== null && $n >= 8) {
            for ($i = 0; $i <= $n - 8; $i++) {
                $beyond = true;
                for ($j = $i; $j < $i + 8; $j++) {
                    if (abs($z($values[$j]) ?? 0) <= 1) {
                        $beyond = false;
                        break;
                    }
                }
                if ($beyond) {
                    $violations[] = ['index' => $i + 7, 'rule' => 8, 'rule_text' => 'Eight points in a row beyond 1σ of the center line'];
                    break;
                }
            }
        }

        return $violations;
    }
}
