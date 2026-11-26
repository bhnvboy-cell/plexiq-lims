<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Helpers\Database;
use App\Helpers\Audit;
use App\Models\ProductTestPlan;
use PDO;

class ProductTestPlanService
{
    private PDO $db;
    private SpecificationService $specService;

    public function __construct(?PDO $db = null, ?SpecificationService $specService = null)
    {
        $this->db = $db ?? Database::connect();
        $this->specService = $specService ?? new SpecificationService($this->db);
    }

    public function getPlanForProductAndStage(int $productId, string $stage): array
    {
        $stmt = $this->db->prepare("
            SELECT ptp.*, a.analysis_code, a.analysis_name, a.sop_reference,
                   a.expected_duration_minutes, a.default_replicate_count,
                   m.method_code, m.method_name
            FROM product_test_plans ptp
            JOIN analyses a ON ptp.analysis_id = a.id
            LEFT JOIN methods m ON a.method_id = m.id
            WHERE ptp.product_id = ? AND ptp.stage = ?
            ORDER BY ptp.sort_order ASC, a.analysis_code ASC
        ");
        $stmt->execute([$productId, $stage]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignAnalysisToStage(int $productId, string $stage, int $analysisId, bool $isMandatory = true, int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO product_test_plans (product_id, stage, analysis_id, is_mandatory, sort_order)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT (product_id, stage, analysis_id) DO UPDATE SET
                is_mandatory = EXCLUDED.is_mandatory,
                sort_order = EXCLUDED.sort_order
            RETURNING id
        ");
        $stmt->execute([$productId, $stage, $analysisId, $isMandatory ? 't' : 'f', $sortOrder]);
        $id = (int)$stmt->fetchColumn();

        Audit::log('Product Test Plan Analysis Assigned', 'product_test_plans', $id, null, [
            'product_id' => $productId,
            'stage' => $stage,
            'analysis_id' => $analysisId
        ]);

        return $id;
    }

    public function removeAnalysisFromStage(int $productId, string $stage, int $analysisId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM product_test_plans WHERE product_id = ? AND stage = ? AND analysis_id = ?");
        $stmt->execute([$productId, $stage, $analysisId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Automatic Commercial LIMS Resolution:
     * Resolves all applicable analyses and their component specification limits
     * for a given product and stage based on the currently approved specification version.
     *
     * @param int $productId
     * @param string $stage e.g. 'Finished Good', 'Raw Material', 'Stability', 'In-Process'
     * @param string|null $effectiveDate
     * @return array{
     *   specification: ?array,
     *   spec_version: ?array,
     *   analyses: array,
     *   total_tests: int,
     *   total_components: int
     * }
     */
    public function resolveAnalysesAndLimitsForSample(int $productId, string $stage, ?string $effectiveDate = null): array
    {
        // 1. Resolve active approved specification version for the product
        $activeSpecVersion = $this->specService->getActiveSpecification($productId, $effectiveDate);

        // 2. Fetch configured product test plan for this stage
        $plannedAnalyses = $this->getPlanForProductAndStage($productId, $stage);

        // If no planned analyses found for this specific stage, fallback to 'Finished Good' or all product analyses
        if (empty($plannedAnalyses)) {
            $plannedAnalyses = $this->getPlanForProductAndStage($productId, 'Finished Good');
        }

        // Index spec limits by component_id for fast retrieval
        $specLimitsByComponent = [];
        if ($activeSpecVersion && !empty($activeSpecVersion['limits'])) {
            foreach ($activeSpecVersion['limits'] as $lim) {
                $specLimitsByComponent[(int)$lim['component_id']] = $lim;
            }
        }

        $resolvedAnalyses = [];
        $totalComponents = 0;

        foreach ($plannedAnalyses as $plan) {
            $analysisId = (int)$plan['analysis_id'];

            // Fetch components defined under this analysis
            $cStmt = $this->db->prepare("
                SELECT c.*, f.name as formula_name, f.expression as formula_expression
                FROM components c
                LEFT JOIN calculation_formulas f ON c.formula_id = f.id
                WHERE c.analysis_id = ? AND c.is_active = TRUE
                ORDER BY c.component_code ASC
            ");
            $cStmt->execute([$analysisId]);
            $components = $cStmt->fetchAll(PDO::FETCH_ASSOC);

            $resolvedComponents = [];
            foreach ($components as $comp) {
                $compId = (int)$comp['id'];
                $limit = $specLimitsByComponent[$compId] ?? null;

                $resolvedComponents[] = [
                    'component_id' => $compId,
                    'component_code' => $comp['component_code'],
                    'component_name' => $comp['component_name'],
                    'unit' => $comp['unit'],
                    'data_type' => $comp['data_type'],
                    'decimal_places' => $comp['decimal_places'],
                    'rounding_method' => $comp['rounding_method'],
                    'significant_figures' => $comp['significant_figures'],
                    'loq' => $comp['loq'],
                    'uloq' => $comp['uloq'],
                    'formula_id' => $comp['formula_id'],
                    // Frozen limit snapshot
                    'has_spec_limit' => $limit !== null,
                    'spec_version_id' => $activeSpecVersion['id'] ?? null,
                    'spec_code' => $activeSpecVersion['spec_code'] ?? null,
                    'spec_version_number' => $activeSpecVersion['version_number'] ?? null,
                    'limit_type' => $limit['limit_type'] ?? 'range',
                    'min_limit' => $limit['min_limit'] ?? null,
                    'max_limit' => $limit['max_limit'] ?? null,
                    'target_value' => $limit['target_value'] ?? null,
                    'text_match_expected' => $limit['text_match_expected'] ?? null,
                    'warn_min' => $limit['warn_min'] ?? null,
                    'warn_max' => $limit['warn_max'] ?? null,
                    'display_specification' => $limit['display_specification'] ?? 'As Found',
                    'oot_rule' => $limit['oot_rule'] ?? null,
                ];
                $totalComponents++;
            }

            $resolvedAnalyses[] = [
                'analysis_id' => $analysisId,
                'analysis_code' => $plan['analysis_code'],
                'analysis_name' => $plan['analysis_name'],
                'method_code' => $plan['method_code'] ?? null,
                'method_name' => $plan['method_name'] ?? null,
                'sop_reference' => $plan['sop_reference'],
                'expected_duration_minutes' => $plan['expected_duration_minutes'],
                'default_replicate_count' => $plan['default_replicate_count'],
                'is_mandatory' => (bool)$plan['is_mandatory'],
                'stage' => $stage,
                'components' => $resolvedComponents
            ];
        }

        return [
            'product_id' => $productId,
            'stage' => $stage,
            'effective_date' => $effectiveDate ?: date('Y-m-d'),
            'spec_version' => $activeSpecVersion,
            'analyses' => $resolvedAnalyses,
            'total_analyses' => count($resolvedAnalyses),
            'total_components' => $totalComponents
        ];
    }
}
