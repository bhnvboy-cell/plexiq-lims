<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CalculationEngine;
use PHPUnit\Framework\TestCase;

class CalculationEngineTest extends TestCase
{
    private CalculationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new CalculationEngine();
    }

    public function test_basic_arithmetic_evaluates_correctly(): void
    {
        $res = $this->engine->evaluate("10 + 5 * 2");
        $this->assertEquals(20.0, $res);

        $res2 = $this->engine->evaluate("(10 + 5) * 2");
        $this->assertEquals(30.0, $res2);

        $res3 = $this->engine->evaluate("2 ^ 3 + 4 / 2");
        $this->assertEquals(10.0, $res3);
    }

    public function test_variable_substitution_works(): void
    {
        $formula = "(Gross - Tare) * 100 / SampleWeight";
        $vars = [
            'Gross' => 15.452,
            'Tare' => 10.231,
            'SampleWeight' => 5.221
        ];

        $res = $this->engine->evaluate($formula, $vars);
        $expected = (15.452 - 10.231) * 100 / 5.221;
        $this->assertEqualsWithDelta($expected, $res, 0.0001);
    }

    public function test_math_functions_evaluate_correctly(): void
    {
        $res = $this->engine->evaluate("sqrt(16) + abs(-5)");
        $this->assertEquals(9.0, $res);

        $res2 = $this->engine->evaluate("max(10, 25) + min(4, 8)");
        $this->assertEquals(29.0, $res2);
    }

    public function test_division_by_zero_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Division by zero");
        $this->engine->evaluate("10 / (5 - 5)");
    }

    public function test_illegal_tokens_throw_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->engine->evaluate("system('calc')");
    }

    public function test_astm_e29_sig_figs_rounding(): void
    {
        // 12.345 rounded to 4 sig figs: 12.35 (standard)
        $this->assertEquals(12.34, CalculationEngine::roundSigFigs(12.345, 4, 'half_even')); // Banker's: 4 is even -> 12.34
        $this->assertEquals(12.36, CalculationEngine::roundSigFigs(12.355, 4, 'half_even')); // Banker's: 5 is odd -> rounds to 6 (even)

        $this->assertEquals(12.35, CalculationEngine::roundSigFigs(12.345, 4, 'half_up'));
        $this->assertEquals(12.34, CalculationEngine::roundSigFigs(12.349, 4, 'truncate'));
    }

    public function test_descriptive_statistics(): void
    {
        $vals = [10.0, 10.2, 9.8, 10.1, 9.9];
        $stats = CalculationEngine::statistics($vals);

        $this->assertEquals(10.0, $stats['mean']);
        $this->assertEqualsWithDelta(0.158, $stats['stdev'], 0.001);
        $this->assertEqualsWithDelta(1.58, $stats['rsd'], 0.01);
        $this->assertEquals(9.8, $stats['min']);
        $this->assertEquals(10.2, $stats['max']);
        $this->assertEquals(5, $stats['n']);
    }

    public function test_dixon_q_test_identifies_outlier(): void
    {
        // Outlier 15.0 with normal cluster [10.0, 10.1, 10.2, 9.9]
        $reps = [10.0, 10.1, 10.2, 9.9, 15.0];
        $q = CalculationEngine::dixonQTest($reps);

        $this->assertEquals('outlier_rejected', $q['status']);
        $this->assertContains(15.0, $q['outliers']);
        $this->assertNotContains(15.0, $q['replicates']);
    }
}
