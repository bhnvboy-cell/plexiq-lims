<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Drivers\SerialBalanceDriver;
use PHPUnit\Framework\TestCase;

class SerialBalanceDriverTest extends TestCase
{
    private SerialBalanceDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new SerialBalanceDriver();
    }

    public function test_parses_mettler_toledo_sics_format(): void
    {
        $raw = "S S     15.4321 g\r\n";
        $res = $this->driver->parseRawData($raw, ['sample_code' => 'SMP-WT-1']);

        $this->assertCount(1, $res);
        $this->assertEquals(15.4321, $res[0]['value']);
        $this->assertEquals('g', $res[0]['unit']);
        $this->assertEquals('Stable', $res[0]['status']);
    }

    public function test_parses_sartorius_format(): void
    {
        $raw = "N  +  2.5000 g\r\n";
        $res = $this->driver->parseRawData($raw, ['sample_code' => 'SMP-WT-2']);

        $this->assertCount(1, $res);
        $this->assertEquals(2.5, $res[0]['value']);
        $this->assertEquals('g', $res[0]['unit']);
    }
}
