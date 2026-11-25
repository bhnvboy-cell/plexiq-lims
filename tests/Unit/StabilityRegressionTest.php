<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StabilityRegressionService;
use PHPUnit\Framework\TestCase;

class StabilityRegressionTest extends TestCase
{
    private StabilityRegressionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StabilityRegressionService();
    }

    public function test_computes_linear_regression_and_predicts_shelf_life(): void
    {
        // Degrading API Assay: starts at 100%, degrades approx 0.25% per month
        $data = [
            ['month' => 0, 'value' => 100.0],
            ['month' => 3, 'value' => 99.2],
            ['month' => 6, 'value' => 98.6],
            ['month' => 9, 'value' => 97.7],
            ['month' => 12, 'value' => 97.0],
            ['month' => 18, 'value' => 95.5],
            ['month' => 24, 'value' => 94.0],
        ];

        // Lower spec limit = 90.0%
        $res = $this->service->evaluateStability($data, 90.0, null);

        $this->assertLessThan(0.0, $res['slope']); // Degrading slope
        $this->assertGreaterThan(0.95, $res['r_squared']); // Strong fit
        $this->assertNotNull($res['predicted_shelf_life_months']);
        $this->assertGreaterThan(24.0, $res['predicted_shelf_life_months']);
        $this->assertStringContainsString('Lower Spec Limit', $res['governing_spec']);
    }

    public function test_fails_with_fewer_than_three_points(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->evaluateStability([['month' => 0, 'value' => 100.0], ['month' => 3, 'value' => 99.0]]);
    }
}
