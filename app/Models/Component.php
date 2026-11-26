<?php

declare(strict_types=1);

namespace App\Models;

use App\BaseModel;
use App\Services\CalculationEngine;
use PDO;

class Component extends BaseModel
{
    protected static string $table = 'components';

    /**
     * Format a raw numeric value according to component rules (decimals, ASTM E29 rounding, LOQ/ULOQ).
     */
    public static function formatReportableValue(array $component, $rawValue): array
    {
        if ($rawValue === null || $rawValue === '') {
            return [
                'raw_value' => null,
                'reportable_value' => null,
                'display_text' => 'N/A',
                'flag' => null,
                'is_loq' => false,
                'is_uloq' => false
            ];
        }

        $type = $component['data_type'] ?? 'numeric';
        if ($type !== 'numeric') {
            return [
                'raw_value' => $rawValue,
                'reportable_value' => (string)$rawValue,
                'display_text' => (string)$rawValue,
                'flag' => null,
                'is_loq' => false,
                'is_uloq' => false
            ];
        }

        $num = (float)$rawValue;
        $loq = isset($component['loq']) && $component['loq'] !== null ? (float)$component['loq'] : null;
        $uloq = isset($component['uloq']) && $component['uloq'] !== null ? (float)$component['uloq'] : null;

        // Check LOQ condition (<LOQ)
        if ($loq !== null && $num < $loq) {
            return [
                'raw_value' => $num,
                'reportable_value' => "<{$loq}",
                'display_text' => "<{$loq} " . ($component['unit'] ?? ''),
                'flag' => '<LOQ',
                'is_loq' => true,
                'is_uloq' => false
            ];
        }

        // Check ULOQ condition (>ULOQ)
        if ($uloq !== null && $num > $uloq) {
            return [
                'raw_value' => $num,
                'reportable_value' => ">{$uloq}",
                'display_text' => ">{$uloq} " . ($component['unit'] ?? ''),
                'flag' => '>ULOQ',
                'is_loq' => false,
                'is_uloq' => true
            ];
        }

        // Apply Rounding & Decimals (ASTM E29 Banker's rounding)
        $decimals = (int)($component['decimal_places'] ?? 2);
        $roundingMode = $component['rounding_method'] ?? 'half_even';
        $sigFigs = isset($component['significant_figures']) && $component['significant_figures'] > 0
            ? (int)$component['significant_figures']
            : null;

        if ($roundingMode === 'half_up') {
            $rounded = round($num, $decimals, PHP_ROUND_HALF_UP);
        } elseif ($roundingMode === 'truncate') {
            $mult = pow(10, $decimals);
            $rounded = ($num >= 0) ? floor($num * $mult) / $mult : ceil($num * $mult) / $mult;
        } else {
            // ASTM E29 default: Round Half to Even (Banker's rounding)
            $rounded = round($num, $decimals, PHP_ROUND_HALF_EVEN);
        }

        if ($sigFigs !== null) {
            $rounded = CalculationEngine::roundSigFigs($rounded, $sigFigs, $roundingMode);
        }

        $formatted = number_format($rounded, $decimals, '.', '');

        return [
            'raw_value' => $num,
            'reportable_value' => $formatted,
            'display_text' => $formatted . (!empty($component['unit']) ? ' ' . $component['unit'] : ''),
            'flag' => null,
            'is_loq' => false,
            'is_uloq' => false
        ];
    }
}
