<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Audit;
use PDO;

class OotDetectionService
{
    /**
     * Evaluate a newly recorded sample test result against historical trends.
     *
     * @param int $sampleTestId
     * @param float $measuredValue
     * @return array<array{rule: string, description: string, mean: float, sigma: float}> Triggered OOT violations
     */
    public function evaluateResult(int $sampleTestId, float $measuredValue): array
    {
        $db = Database::connect();

        // Retrieve test_id and product_id for this sample_test
        $stmt = $db->prepare(
            'SELECT st.test_id, s.product_id, s.id as sample_id, t.test_name
             FROM sample_tests st
             JOIN samples s ON st.sample_id = s.id
             JOIN tests t ON st.test_id = t.id
             WHERE st.id = ?'
        );
        $stmt->execute([$sampleTestId]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            return [];
        }

        $testId = (int)$info['test_id'];
        $productId = (int)($info['product_id'] ?? 0);

        // Fetch recent approved historical results for same test & product (last 20 results)
        $histStmt = $db->prepare(
            "SELECT r.result_value
             FROM results r
             JOIN sample_tests st ON r.sample_test_id = st.id
             JOIN samples s ON st.sample_id = s.id
             WHERE st.test_id = ? AND s.product_id = ? AND st.id != ? AND r.result_value IS NOT NULL
             ORDER BY r.id DESC LIMIT 20"
        );
        $histStmt->execute([$testId, $productId, $sampleTestId]);
        $history = array_map(fn($r) => (float)$r['result_value'], $histStmt->fetchAll(PDO::FETCH_ASSOC));

        // Need at least 5 historical points to compute meaningful SPC trends
        if (count($history) < 5) {
            return [];
        }

        // Descriptive statistics on baseline history
        $stats = CalculationEngine::statistics($history);
        $mean = $stats['mean'];
        $sigma = $stats['stdev'];

        if ($sigma <= 0.0) {
            return [];
        }

        $violations = [];

        // Nelson Rule 1: 1 point beyond 3 sigma from center line
        $diff = abs($measuredValue - $mean);
        if ($diff > (3.0 * $sigma)) {
            $violations[] = [
                'rule' => 'nelson_1',
                'description' => "Value {$measuredValue} is beyond 3-sigma limits (" . round($mean - 3 * $sigma, 3) . " to " . round($mean + 3 * $sigma, 3) . ")",
                'mean' => $mean,
                'sigma' => $sigma
            ];
        }

        // Nelson Rule 2: 9 consecutive points on same side of mean
        $recent = array_slice($history, 0, 8);
        array_unshift($recent, $measuredValue);
        if (count($recent) >= 9) {
            $allAbove = true;
            $allBelow = true;
            foreach ($recent as $val) {
                if ($val < $mean) $allAbove = false;
                if ($val > $mean) $allBelow = false;
            }
            if ($allAbove || $allBelow) {
                $violations[] = [
                    'rule' => 'nelson_2',
                    'description' => "9 consecutive points on the " . ($allAbove ? 'upper' : 'lower') . " side of the mean",
                    'mean' => $mean,
                    'sigma' => $sigma
                ];
            }
        }

        // Nelson Rule 3: 6 consecutive points steadily increasing or decreasing
        $run = array_slice($history, 0, 5);
        array_unshift($run, $measuredValue);
        if (count($run) >= 6) {
            $increasing = true;
            $decreasing = true;
            for ($i = 0; $i < count($run) - 1; $i++) {
                if ($run[$i] <= $run[$i + 1]) $increasing = false;
                if ($run[$i] >= $run[$i + 1]) $decreasing = false;
            }
            if ($increasing || $decreasing) {
                $violations[] = [
                    'rule' => 'nelson_3',
                    'description' => "6 consecutive points steadily " . ($increasing ? 'increasing' : 'decreasing'),
                    'mean' => $mean,
                    'sigma' => $sigma
                ];
            }
        }

        // Record any detected incidents in oot_incidents table
        if (!empty($violations)) {
            foreach ($violations as $v) {
                $insert = $db->prepare(
                    "INSERT INTO oot_incidents (
                        sample_test_id, rule_type, measured_value, expected_mean, sigma,
                        status, investigation_notes
                    ) VALUES (?, ?, ?, ?, ?, 'Open', ?)"
                );
                $insert->execute([
                    $sampleTestId,
                    $v['rule'],
                    $measuredValue,
                    $v['mean'],
                    $v['sigma'],
                    $v['description']
                ]);

                Audit::log(
                    'OOT Incident Flagged',
                    'oot_incidents',
                    $sampleTestId,
                    null,
                    ['rule' => $v['rule'], 'value' => $measuredValue, 'mean' => $v['mean']],
                    "OOT detected for test #{$sampleTestId}: {$v['description']}"
                );
            }
        }

        return $violations;
    }
}
