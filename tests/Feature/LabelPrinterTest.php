<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\Database;
use App\Services\LabelPrinterService;
use Tests\TestCase;

class LabelPrinterTest extends TestCase
{
    private LabelPrinterService $printer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->printer = new LabelPrinterService();
    }

    public function test_renders_zpl_stream_with_sample_placeholders(): void
    {
        $db = Database::connect();

        $code = 'SMP-ZPL-' . uniqid();
        $sId = (int)$db->query(
            "INSERT INTO samples (sample_code, batch_number, received_date, status)
             VALUES ('{$code}', 'BATCH-XYZ-123', '2026-11-25', 'Registered')
             RETURNING id"
        )->fetchColumn();

        $label = $this->printer->renderSampleLabel($sId);

        $this->assertEquals('zpl', $label['format']);
        $this->assertEquals('text/plain', $label['mime_type']);
        $this->assertStringContainsString($code, $label['payload']);
        $this->assertStringContainsString('BATCH-XYZ-123', $label['payload']);
        $this->assertStringStartsWith('^XA', $label['payload']);
        $this->assertStringEndsWith("^XZ\n", $label['payload'] . "\n");
    }
}
