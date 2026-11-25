<?php

declare(strict_types=1);

namespace App\Services;

class StabilityRegressionService
{
    /**
     * Compute ICH Q1E linear regression and predict shelf-life against specification limits.
     *
     * @param array<array{month: float|int, value: float}> $data Timepoint observations e.g. [['month' => 0, 'value' => 100.2], ...]
     * @param float|null $lowerSpecLimit e.g. 90.0% for active pharmaceutical ingredient
     * @param float|null $upperSpecLimit e.g. 0.5% for related substances
     * @return array{
     *   slope: float,
     *   intercept: float,
     *   r_squared: float,
     *   residual_std_error: float,
     *   predicted_shelf_life_months: ?float,
     *   governing_spec: string,
     *   regression_points: array<array<string, float>>
     * }
     */
    public function evaluateStability(
        array $data,
        ?float $lowerSpecLimit = null,
        ?float $upperSpecLimit = null
    ): array {
        $n = count($data);
        if ($n < 3) {
            throw new \InvalidArgumentException("ICH Q1E stability regression requires at least 3 historical timepoints.");
        }

        $x = array_column($data, 'month');
        $y = array_column($data, 'value');

        $xMean = array_sum($x) / $n;
        $yMean = array_sum($y) / $n;

        $ssxx = 0.0;
        $ssyy = 0.0;
        $ssxy = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $xMean;
            $dy = $y[$i] - $yMean;
            $ssxx += $dx * $dx;
            $ssyy += $dy * $dy;
            $ssxy += $dx * $dy;
        }

        if ($ssxx == 0.0) {
            throw new \InvalidArgumentException("All timepoints have the same month value.");
        }

        $slope = $ssxy / $ssxx;
        $intercept = $yMean - ($slope * $xMean);

        // Residual variance & standard error
        $sse = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $yHat = $intercept + $slope * $x[$i];
            $sse += pow($y[$i] - $yHat, 2);
        }

        $df = $n - 2;
        $sYx = ($df > 0) ? sqrt($sse / $df) : 0.0;
        $rSquared = ($ssyy != 0.0) ? pow($ssxy, 2) / ($ssxx * $ssyy) : 0.0;

        // Student's t for two-sided 90% or one-sided 95% (standard ICH Q1E recommendation)
        $tCrit = self::getTCritical($df);

        // Calculate predicted shelf-life (intersection with specification limit)
        $shelfLife = null;
        $governingSpec = 'None';

        // Evaluate months 0 through 120 in steps of 0.5 to find confidence boundary crossover
        $maxMonths = 120;
        $points = [];

        for ($m = 0; $m <= $maxMonths; $m += 1) {
            $yPred = $intercept + $slope * $m;
            $sePred = $sYx * sqrt((1.0 / $n) + (pow($m - $xMean, 2) / $ssxx));
            $ciLower = $yPred - ($tCrit * $sePred);
            $ciUpper = $yPred + ($tCrit * $sePred);

            if ($m <= max($x) + 24) {
                $points[] = [
                    'month' => (float)$m,
                    'predicted' => round($yPred, 4),
                    'ci_lower' => round($ciLower, 4),
                    'ci_upper' => round($ciUpper, 4),
                ];
            }

            // Check shelf-life crossover
            if ($shelfLife === null && $m > 0) {
                // If degrading downwards (negative slope) and lower spec exists
                if ($slope < 0 && $lowerSpecLimit !== null && $ciLower <= $lowerSpecLimit) {
                    $shelfLife = (float)$m;
                    $governingSpec = "Lower Spec Limit ({$lowerSpecLimit})";
                }
                // If increasing upwards (positive slope, e.g. impurity) and upper spec exists
                if ($slope > 0 && $upperSpecLimit !== null && $ciUpper >= $upperSpecLimit) {
                    $shelfLife = (float)$m;
                    $governingSpec = "Upper Spec Limit ({$upperSpecLimit})";
                }
            }
        }

        return [
            'slope' => round($slope, 6),
            'intercept' => round($intercept, 4),
            'r_squared' => round($rSquared, 4),
            'residual_std_error' => round($sYx, 4),
            'predicted_shelf_life_months' => $shelfLife,
            'governing_spec' => $governingSpec,
            'regression_points' => $points
        ];
    }

    /**
     * Approximate Student's t critical value for one-sided 95% (two-sided 90%) confidence level.
     */
    private static function getTCritical(int $df): float
    {
        $tTable = [
            1 => 6.314, 2 => 2.920, 3 => 2.353, 4 => 2.132, 5 => 2.015,
            6 => 1.943, 7 => 1.895, 8 => 1.860, 9 => 1.833, 10 => 1.812,
            12 => 1.782, 15 => 1.753, 20 => 1.725, 25 => 1.708, 30 => 1.697,
            40 => 1.684, 60 => 1.671, 120 => 1.658
        ];

        if (isset($tTable[$df])) {
            return $tTable[$df];
        }

        foreach ($tTable as $deg => $val) {
            if ($df <= $deg) {
                return $val;
            }
        }

        return 1.645; // Asymptote z = 1.645
    }
}
