<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UncertaintyBudgetService;
use PHPUnit\Framework\TestCase;

class UncertaintyBudgetTest extends TestCase
{
    private UncertaintyBudgetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UncertaintyBudgetService();
    }

    public function test_calculates_gum_combined_and_expanded_uncertainty(): void
    {
        $components = [
            [
                'name' => 'Analytical Balance Precision',
                'type' => 'Type_B',
                'distribution' => 'Rectangular', // divisor sqrt(3) ~= 1.732
                'value' => 0.05,
                'sensitivity' => 1.0,
                'df' => 50
            ],
            [
                'name' => 'Flask Volume Tolerance',
                'type' => 'Type_B',
                'distribution' => 'Triangular', // divisor sqrt(6) ~= 2.449
                'value' => 0.10,
                'sensitivity' => 1.0,
                'df' => 50
            ]
        ];

        $res = $this->service->calculateBudget($components, 2.0);

        $u1 = 0.05 / sqrt(3.0);
        $u2 = 0.10 / sqrt(6.0);
        $expectedUc = sqrt(pow($u1, 2) + pow($u2, 2));
        $expectedU = 2.0 * $expectedUc;

        $this->assertEqualsWithDelta($expectedUc, $res['combined_uncertainty'], 0.0001);
        $this->assertEqualsWithDelta($expectedU, $res['expanded_uncertainty'], 0.0001);
        $this->assertEquals(2.0, $res['coverage_factor_k']);
        $this->assertCount(2, $res['components']);
    }
}
