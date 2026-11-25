<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\Database;
use App\Services\TrainingGatingService;
use Tests\TestCase;

class TrainingGatingTest extends TestCase
{
    private TrainingGatingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrainingGatingService();
    }

    public function test_analyst_training_qualification_workflow(): void
    {
        $db = Database::connect();

        // 1. Create a dummy test method
        $tCode = 'TG-TEST-' . uniqid();
        $tId = (int)$db->query(
            "INSERT INTO tests (test_code, test_name) VALUES ('{$tCode}', 'High Precision Assay') RETURNING id"
        )->fetchColumn();

        // 2. Dummy user
        $uName = 'analyst_' . uniqid();
        $uId = (int)$db->query(
            "INSERT INTO users (username, email, password_hash, full_name, role_id)
             VALUES ('{$uName}', '{$uName}@test.com', 'hash', 'Test Analyst', 2)
             RETURNING id"
        )->fetchColumn();

        // Initially no qualification
        $db->query("INSERT INTO training_qualifications (user_id, qualification_type, entity_id, status) VALUES ({$uId}, 'test_method', {$tId}, 'In Training')");

        $this->assertFalse($this->service->isQualified($uId, 'test_method', $tId));

        // Certify user
        $this->service->certify($uId, 'test_method', $tId, date('Y-m-d', strtotime('+1 year')));

        $this->assertTrue($this->service->isQualified($uId, 'test_method', $tId));
    }
}
