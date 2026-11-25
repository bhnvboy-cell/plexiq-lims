<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Audit;
use App\Helpers\Auth;
use PDO;

class UncertaintyBudgetService
{
    /**
     * Calculate combined and expanded measurement uncertainty according to ISO/IEC Guide 98-3 (GUM).
     *
     * @param array<array{name: string, type: string, distribution: string, value: float, sensitivity?: float, df?: int}> $components
     * @param float $coverageFactorK Coverage factor (typically 2.0 for ~95.45% confidence)
     * @return array{
     *   components: array<array<string, mixed>>,
     *   combined_uncertainty: float,
     *   expanded_uncertainty: float,
     *   coverage_factor_k: float,
     *   effective_degrees_of_freedom: float
     * }
     */
    public function calculateBudget(array $components, float $coverageFactorK = 2.0): array
    {
        $processed = [];
        $sumSquares = 0.0;
        $welchSatterthwaiteNumerator = 0.0;
        $welchSatterthwaiteDenominator = 0.0;

        foreach ($components as $c) {
            $name = $c['name'] ?? 'Component';
            $type = $c['type'] ?? 'Type_B';
            $dist = $c['distribution'] ?? 'Normal';
            $val = (float)($c['value'] ?? 0.0);
            $sens = (float)($c['sensitivity'] ?? 1.0);
            $df = (int)($c['df'] ?? 100);

            // Divisor based on probability distribution
            $divisor = match (strtolower($dist)) {
                'rectangular' => sqrt(3.0),
                'triangular' => sqrt(6.0),
                'u-shaped', 'u_shaped' => sqrt(2.0),
                default => 2.0, // Normal k=2 default
            };

            $stdUncertainty = $val / $divisor;
            $contribution = $sens * $stdUncertainty;
            $sumSquares += pow($contribution, 2);

            // For Welch-Satterthwaite effective degrees of freedom
            if ($df > 0) {
                $welchSatterthwaiteDenominator += pow($contribution, 4) / $df;
            }

            $processed[] = [
                'source_name' => $name,
                'component_type' => $type,
                'distribution' => ucfirst($dist),
                'value' => $val,
                'divisor' => round($divisor, 4),
                'standard_uncertainty' => round($stdUncertainty, 6),
                'sensitivity_coefficient' => $sens,
                'contribution' => round($contribution, 6),
                'degrees_of_freedom' => $df,
            ];
        }

        $combined = sqrt($sumSquares);
        $expanded = $coverageFactorK * $combined;

        $effDf = 100.0;
        if ($welchSatterthwaiteDenominator > 0.0) {
            $effDf = pow($combined, 4) / $welchSatterthwaiteDenominator;
        }

        return [
            'components' => $processed,
            'combined_uncertainty' => round($combined, 6),
            'expanded_uncertainty' => round($expanded, 6),
            'coverage_factor_k' => $coverageFactorK,
            'effective_degrees_of_freedom' => round($effDf, 1),
        ];
    }

    /**
     * Save an uncertainty budget for a laboratory test.
     *
     * @param int $testId
     * @param string $name
     * @param string $unit
     * @param array<array<string, mixed>> $rawComponents
     * @param float $k
     * @return int
     */
    public function saveBudget(
        int $testId,
        string $name,
        string $unit,
        array $rawComponents,
        float $k = 2.0
    ): int {
        $calc = $this->calculateBudget($rawComponents, $k);
        $db = Database::connect();
        $db->beginTransaction();

        try {
            $userId = Auth::id() ?? 1;

            $stmtB = $db->prepare(
                "INSERT INTO uncertainty_budgets (
                    test_id, name, unit, coverage_factor_k, combined_uncertainty, expanded_uncertainty, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id"
            );
            $stmtB->execute([
                $testId,
                $name,
                $unit,
                $k,
                $calc['combined_uncertainty'],
                $calc['expanded_uncertainty'],
                $userId
            ]);
            $budgetId = (int)$stmtB->fetchColumn();

            $stmtC = $db->prepare(
                "INSERT INTO uncertainty_components (
                    budget_id, source_name, component_type, distribution, value, divisor,
                    standard_uncertainty, sensitivity_coefficient, degrees_of_freedom
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($calc['components'] as $c) {
                $stmtC->execute([
                    $budgetId,
                    $c['source_name'],
                    $c['component_type'],
                    $c['distribution'],
                    $c['value'],
                    $c['divisor'],
                    $c['standard_uncertainty'],
                    $c['sensitivity_coefficient'],
                    $c['degrees_of_freedom']
                ]);
            }

            Audit::log(
                'Save Measurement Uncertainty Budget',
                'uncertainty_budgets',
                $budgetId,
                null,
                ['test_id' => $testId, 'name' => $name, 'expanded_u' => $calc['expanded_uncertainty']],
                "Created ISO 17025 Uncertainty Budget {$name} for test #{$testId}"
            );

            $db->commit();
            return $budgetId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
