<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Drivers\CdsCsvDriver;
use PHPUnit\Framework\TestCase;

class CdsDriverTest extends TestCase
{
    private CdsCsvDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new CdsCsvDriver();
    }

    public function test_parses_empower_cds_csv_export(): void
    {
        $csv = "SampleName,Vial,PeakName,RetentionTime,Area,Amount,Units\r\n"
             . "LOT-991-A,1,Paracetamol,4.25,1542000,99.8,%\r\n"
             . "LOT-991-A,1,4-Aminophenol,6.10,3200,0.02,%\r\n"
             . "LOT-992-B,2,Paracetamol,4.26,1539000,99.6,%\r\n";

        $results = $this->driver->parseRawData($csv);

        $this->assertCount(3, $results);
        $this->assertEquals('LOT-991-A', $results[0]['sample_code']);
        $this->assertEquals('Paracetamol', $results[0]['test_code']);
        $this->assertEquals(99.8, $results[0]['value']);
        $this->assertEquals(4.25, $results[0]['retention_time']);
        $this->assertEquals(1542000, $results[0]['peak_area']);
        $this->assertEquals('1', $results[0]['vial_position']);
    }

    public function test_generates_empower_sample_set_worklist(): void
    {
        $entries = [
            ['position' => 1, 'sample_code' => 'BLANK', 'sample_type' => 'Blank'],
            ['position' => 2, 'sample_code' => 'SMP-QC-01', 'sample_type' => 'Sample', 'injection_volume_ul' => 20.0],
        ];

        $worklist = $this->driver->generateWorklist($entries);

        $this->assertStringContainsString('Vial,SampleName,SampleType,MethodSet,InjVol', $worklist);
        $this->assertStringContainsString('"1","BLANK","Blank"', $worklist);
        $this->assertStringContainsString('"2","SMP-QC-01","Sample"', $worklist);
    }
}
