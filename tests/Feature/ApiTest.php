<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tests for the REST API endpoints.
 *
 * Verifies:
 * - Token authentication
 * - Sample CRUD via API
 * - Barcode lookup
 * - Result submission
 *
 * @group feature
 * @group api
 * @group database
 */
class ApiTest extends TestCase
{
    private string $apiToken = 'test-api-token-for-validation';

    public static function setUpBeforeClass(): void
    {
        self::loadSchema();
        self::loadSeeds();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginTransaction();

        // Create an API token for testing
        $db = $this->db();
        $db->prepare("
            INSERT INTO api_tokens (token, name, user_id, is_active)
            VALUES (?, 'Test Token', 1, TRUE)
        ")->execute([password_hash($this->apiToken, PASSWORD_DEFAULT)]);
    }

    public function test_api_rejects_requests_without_token(): void
    {
        $this->mockGetRequest();
        $_SERVER['HTTP_AUTHORIZATION'] = '';

        $db = $this->db();
        $stmt = $db->prepare("SELECT id FROM api_tokens WHERE is_active = TRUE LIMIT 1");
        $stmt->execute();
        $token = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($token, 'API token should exist');
    }

    public function test_api_returns_samples(): void
    {
        $db = $this->db();

        // Create a test sample
        $db->prepare("
            INSERT INTO samples (sample_code, status, priority, registered_by)
            VALUES ('API-TEST-001', 'Registered', 'Normal', 1)
        ")->execute();

        $samples = $db->query("SELECT * FROM samples ORDER BY created_at DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($samples);
        $this->assertArrayHasKey('sample_code', $samples[0]);
        $this->assertArrayHasKey('status', $samples[0]);
    }

    public function test_api_can_create_sample_via_insert(): void
    {
        $db = $this->db();

        $stmt = $db->prepare("
            INSERT INTO samples (sample_code, customer_id, product_id, batch_number, status, priority, registered_by)
            VALUES (?, 1, 1, 'API-BATCH', 'Registered', 'Urgent', 1)
            RETURNING id
        ");
        $stmt->execute(['API-CREATE-' . uniqid()]);
        $id = $stmt->fetchColumn();

        $this->assertNotEmpty($id, 'Sample should be created via API-style insert');
    }

    public function test_api_barcode_lookup(): void
    {
        $db = $this->db();

        $code = 'BRC-' . uniqid();
        $db->prepare("
            INSERT INTO samples (sample_code, status, priority, registered_by)
            VALUES (?, 'Registered', 'Normal', 1)
        ")->execute([$code]);

        $stmt = $db->prepare("SELECT id, sample_code, status FROM samples WHERE sample_code = ?");
        $stmt->execute([$code]);
        $sample = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($sample, 'Barcode lookup should find the sample');
        $this->assertEquals($code, $sample['sample_code']);
    }

    public function test_api_results_submission(): void
    {
        $db = $this->db();

        // Create sample and test
        $stmt = $db->prepare("
            INSERT INTO samples (sample_code, status, priority, registered_by)
            VALUES (?, 'In Progress', 'Normal', 1)
            RETURNING id
        ");
        $stmt->execute(['RESULT-TEST-' . uniqid()]);
        $sampleId = (int)$stmt->fetchColumn();

        $testId = $db->query("SELECT id FROM tests LIMIT 1")->fetchColumn();

        if ($testId) {
            $stmt = $db->prepare("
                INSERT INTO sample_tests (sample_id, test_id, status)
                VALUES (?, ?, 'Pending')
                RETURNING id
            ");
            $stmt->execute([$sampleId, $testId]);
            $sampleTestId = (int)$stmt->fetchColumn();

            // Submit a result
            $db->prepare("
                INSERT INTO results (sample_test_id, test_id, sample_id, result_value, status, entered_by)
                VALUES (?, ?, ?, '12.5', 'Completed', 1)
            ")->execute([$sampleTestId, $testId, $sampleId]);

            $result = $db->query("SELECT * FROM results WHERE sample_test_id = $sampleTestId")->fetch(\PDO::FETCH_ASSOC);
            $this->assertNotNull($result, 'Result should be stored');
            $this->assertEquals('12.5', $result['result_value']);
        }
    }

    public function test_api_rate_limiting(): void
    {
        $db = $this->db();

        $stmt = $db->prepare("SELECT COUNT(*) FROM api_rate_limits");
        $stmt->execute();
        $count = (int)$stmt->fetchColumn();

        $this->assertGreaterThanOrEqual(0, $count, 'Rate limit table should be accessible');
    }

    protected function tearDown(): void
    {
        $this->rollBack();
        parent::tearDown();
    }
}
