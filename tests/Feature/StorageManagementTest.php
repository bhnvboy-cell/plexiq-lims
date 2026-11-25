<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\Database;
use App\Services\StorageManagementService;
use Tests\TestCase;
use PDO;

class StorageManagementTest extends TestCase
{
    private StorageManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StorageManagementService();
    }

    public function test_can_check_in_and_check_out_sample_with_coordinates(): void
    {
        $db = Database::connect();

        // 1. Create a dummy storage box
        $locCode = 'BOX-TEST-' . uniqid();
        $stmtBox = $db->prepare(
            "INSERT INTO storage_locations (code, name, location_type, capacity_rows, capacity_cols, temperature_nominal)
             VALUES (?, 'Test Freezer Box 8x12', 'box', 8, 12, -80.0)
             RETURNING id"
        );
        $stmtBox->execute([$locCode]);
        $boxId = (int)$stmtBox->fetchColumn();

        // 2. Create a test sample
        $sampleCode = 'SAMPLE-STOR-' . uniqid();
        $sId = (int)$db->query("INSERT INTO samples (sample_code, received_date, status) VALUES ('{$sampleCode}', CURRENT_DATE, 'Registered') RETURNING id")->fetchColumn();

        // 3. Check in sample to Row 2, Col 4 => "B4"
        $assignment = $this->service->checkIn($sId, $boxId, 2, 4);

        $this->assertEquals('B4', $assignment['position_label']);
        $this->assertEquals('Stored', $assignment['status']);

        // 4. Collision check: placing another sample in same slot must fail
        $sampleCode2 = 'SAMPLE-COL-' . uniqid();
        $sId2 = (int)$db->query("INSERT INTO samples (sample_code, received_date, status) VALUES ('{$sampleCode2}', CURRENT_DATE, 'Registered') RETURNING id")->fetchColumn();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("already occupied");
        $this->service->checkIn($sId2, $boxId, 2, 4);
    }

    public function test_can_generate_box_matrix_and_occupancy(): void
    {
        $db = Database::connect();

        $locCode = 'BOX-MTX-' . uniqid();
        $stmtBox = $db->prepare(
            "INSERT INTO storage_locations (code, name, location_type, capacity_rows, capacity_cols, temperature_nominal)
             VALUES (?, 'Test 9x9 Cryobox', 'box', 9, 9, -20.0)
             RETURNING id"
        );
        $stmtBox->execute([$locCode]);
        $boxId = (int)$stmtBox->fetchColumn();

        $matrixData = $this->service->getBoxMatrix($boxId);

        $this->assertEquals(81, $matrixData['stats']['total_slots']);
        $this->assertEquals(0, $matrixData['stats']['occupied_slots']);
        $this->assertEquals(81, $matrixData['stats']['free_slots']);
        $this->assertCount(9, $matrixData['matrix']);
    }
}
