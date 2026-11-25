<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\Database;
use App\Services\OotDetectionService;
use Tests\TestCase;
use PDO;

class OotDetectionTest extends TestCase
{
    private OotDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OotDetectionService();
    }

    public function test_detects_nelson_rule_1_outlier_beyond_three_sigma(): void
    {
        $db = Database::connect();

        // 1. Create product & test
        $prodCode = 'PROD-OOT-' . uniqid();
        $pId = (int)$db->query("INSERT INTO products (product_code, product_name) VALUES ('{$prodCode}', 'Test OOT Product') RETURNING id")->fetchColumn();

        $testCode = 'TEST-OOT-' . uniqid();
        $tId = (int)$db->query("INSERT INTO tests (test_code, test_name, min_spec_limit, max_spec_limit) VALUES ('{$testCode}', 'Assay Test', 90, 110) RETURNING id")->fetchColumn();

        // 2. Insert 10 historical results with mean around 100 and sigma around 0.5
        $histValues = [100.1, 99.8, 100.2, 100.0, 99.9, 100.3, 99.7, 100.1, 100.0, 99.9];
        foreach ($histValues as $val) {
            $sId = (int)$db->query("INSERT INTO samples (sample_code, product_id, received_date, status) VALUES ('SMP-HIST-" . uniqid() . "', {$pId}, CURRENT_DATE, 'Approved') RETURNING id")->fetchColumn();
            $stId = (int)$db->query("INSERT INTO sample_tests (sample_id, test_id, status) VALUES ({$sId}, {$tId}, 'Approved') RETURNING id")->fetchColumn();
            $db->query("INSERT INTO results (sample_test_id, result_value) VALUES ({$stId}, {$val})");
        }

        // 3. New test sample with extreme value 105.0 (which is > 3 sigma beyond ~100.0)
        $newSampleId = (int)$db->query("INSERT INTO samples (sample_code, product_id, received_date, status) VALUES ('SMP-NEW-" . uniqid() . "', {$pId}, CURRENT_DATE, 'In Progress') RETURNING id")->fetchColumn();
        $newStId = (int)$db->query("INSERT INTO sample_tests (sample_id, test_id, status) VALUES ({$newSampleId}, {$tId}, 'In Progress') RETURNING id")->fetchColumn();

        $violations = $this->service->evaluateResult($newStId, 105.0);

        $this->assertNotEmpty($violations);
        $this->assertEquals('nelson_1', $violations[0]['rule']);

        // Check oot_incidents record
        $stmtInc = $db->prepare("SELECT * FROM oot_incidents WHERE sample_test_id = ?");
        $stmtInc->execute([$newStId]);
        $inc = $stmtInc->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($inc);
        $this->assertEquals('nelson_1', $inc['rule_type']);
        $this->assertEquals(105.0, (float)$inc['measured_value']);
    }
}
