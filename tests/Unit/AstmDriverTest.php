<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Drivers\AstmDriver;
use PHPUnit\Framework\TestCase;

class AstmDriverTest extends TestCase
{
    private AstmDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new AstmDriver();
    }

    public function test_parses_astm_e1394_frames_accurately(): void
    {
        $raw = "H|\^&|||Analyzer-1|||||||P|1394-97\r\n"
             . "P|1||||||||||||||||||||\r\n"
             . "O|1|SMP-AST-001|1^^Sample|^^^GLUCOSE|||||||A\r\n"
             . "R|1|^^^GLUCOSE|98.5|mg/dL||N||F\r\n"
             . "R|2|^^^CHOLESTEROL|185.0|mg/dL||N||F\r\n"
             . "L|1|N\r\n";

        $results = $this->driver->parseRawData($raw);

        $this->assertCount(2, $results);
        $this->assertEquals('SMP-AST-001', $results[0]['sample_code']);
        $this->assertEquals('GLUCOSE', $results[0]['test_code']);
        $this->assertEquals(98.5, $results[0]['value']);
        $this->assertEquals('mg/dL', $results[0]['unit']);
        $this->assertEquals('Valid', $results[0]['status']);

        $this->assertEquals('CHOLESTEROL', $results[1]['test_code']);
        $this->assertEquals(185.0, $results[1]['value']);
    }

    public function test_generates_bidirectional_astm_worklist(): void
    {
        $entries = [
            ['position' => 1, 'sample_code' => 'SMP-01', 'sample_type' => 'Sample', 'method' => 'GLU'],
            ['position' => 2, 'sample_code' => 'SMP-02', 'sample_type' => 'Sample', 'method' => 'CHOL'],
        ];

        $worklist = $this->driver->generateWorklist($entries);

        $this->assertStringStartsWith('H|\^&', $worklist);
        $this->assertStringContainsString('O|1|SMP-01|1^^Sample|^^^GLU', $worklist);
        $this->assertStringContainsString('O|2|SMP-02|2^^Sample|^^^CHOL', $worklist);
        $this->assertStringEndsWith("L|1|N\r\n", $worklist);
    }
}
