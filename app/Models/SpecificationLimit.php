<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use PDO;

class SpecificationLimit extends BaseModel
{
    protected static string $table = 'specification_limits';

    /**
     * Evaluate a result against this specification limit.
     *
     * @param array $limit Specification limit definition
     * @param float|string|null $value Value to evaluate
     * @param bool $isLoq Whether the value is marked <LOQ
     * @return array{is_within_spec: bool, is_warn: bool, reason: ?string}
     */
    public static function evaluate(array $limit, $value, bool $isLoq = false): array
    {
        if ($value === null || $value === '') {
            return ['is_within_spec' => true, 'is_warn' => false, 'reason' => null];
        }

        $type = $limit['limit_type'] ?? 'range';

        // Commercial LIMS Rule: If value is <LOQ and upper limit exists, <LOQ is within specification
        if ($isLoq) {
            if ($type === 'max' || $type === 'range') {
                return ['is_within_spec' => true, 'is_warn' => false, 'reason' => 'Result <LOQ meets specification.'];
            }
            if ($type === 'min') {
                return ['is_within_spec' => false, 'is_warn' => false, 'reason' => 'Result <LOQ fails minimum specification threshold.'];
            }
        }

        if ($type === 'text_match' || $type === 'pass_fail') {
            $expected = trim(strtolower((string)($limit['text_match_expected'] ?? 'pass')));
            $actual = trim(strtolower((string)$value));
            $pass = ($actual === $expected);
            return [
                'is_within_spec' => $pass,
                'is_warn' => false,
                'reason' => $pass ? null : "Expected '{$limit['text_match_expected']}', got '{$value}'"
            ];
        }

        // Numeric evaluation
        $num = (float)$value;
        $min = $limit['min_limit'] !== null ? (float)$limit['min_limit'] : null;
        $max = $limit['max_limit'] !== null ? (float)$limit['max_limit'] : null;
        $warnMin = $limit['warn_min'] !== null ? (float)$limit['warn_min'] : null;
        $warnMax = $limit['warn_max'] !== null ? (float)$limit['warn_max'] : null;

        $withinSpec = true;
        $reason = null;

        if ($type === 'range' && $min !== null && $max !== null) {
            if ($num < $min || $num > $max) {
                $withinSpec = false;
                $reason = "Result {$num} outside spec range [{$min} - {$max}]";
            }
        } elseif ($type === 'min' && $min !== null) {
            if ($num < $min) {
                $withinSpec = false;
                $reason = "Result {$num} below minimum limit {$min}";
            }
        } elseif ($type === 'max' && $max !== null) {
            if ($num > $max) {
                $withinSpec = false;
                $reason = "Result {$num} exceeds maximum limit {$max}";
            }
        }

        // Check warning limits (if within spec)
        $isWarn = false;
        if ($withinSpec) {
            if ($warnMin !== null && $num < $warnMin) {
                $isWarn = true;
                $reason = "Result {$num} entered warning band (< {$warnMin})";
            } elseif ($warnMax !== null && $num > $warnMax) {
                $isWarn = true;
                $reason = "Result {$num} entered warning band (> {$warnMax})";
            }
        }

        return [
            'is_within_spec' => $withinSpec,
            'is_warn' => $isWarn,
            'reason' => $reason
        ];
    }
}
