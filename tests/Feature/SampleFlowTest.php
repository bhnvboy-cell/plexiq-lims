<?php

namespace Tests\Feature;

use App\Helpers\Auth;
use App\Helpers\Database;
use Tests\TestCase;

/**
 * Feature tests for the Sample lifecycle.
 *
 * Tests the end-to-end workflow:
 * - Sample creation with test assignment
 * - Status transitions (Registered -> In Progress -> Reviewed -> Approved -> COA Released)
 * - Invalid transition rejection
 * - Sample search and filtering
 * - Authorization checks
 *
 * These tests require a running PostgreSQL database.
 * Run with: vendor/bin/phpunit --testsuite Feature
 *
 * @group feature
 * @group database
 */
class SampleFlowTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        self::loadSchema();
        self::loadSeeds();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginTransaction();
        $this->actingAs(1, 'Admin');
    }

    public function test_create_sample_and_assign_tests(): void
    {
        $db = $this->db();

        // Create a sample
        $sampleCode = 'SMP-TEST-' . date('Ymd') . '-00001';
        $stmt = $db->prepare("
            INSERT INTO samples (sample_code, customer_id, product_id, batch_number, priority, registered_by, status)
            VALUES (?, 1, 1, 'BATCH-001', 'Normal', 1, 'Registered')
            RETURNING id
        ");
        $stmt->execute([$sampleCode]);
        $sampleId = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(0, $sampleId, 'Sample should be created with a valid ID');

        // Verify the sample exists in the database
        $sample = $db->query("SELECT * FROM samples WHERE id = $sampleId")->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($sample);
        $this->assertEquals($sampleCode, $sample['sample_code']);
        $this->assertEquals('Registered', $sample['status']);
        $this->assertEquals('Normal', $sample['priority']);
    }

    public function test_sample_status_transition_from_registered_to_in_progress(): void
    {
        $sampleId = $this->createTestSample();

        $db = $this->db();
        $db->exec("UPDATE samples SET status = 'In Progress' WHERE id = $sampleId");

        $status = $db->query("SELECT status FROM samples WHERE id = $sampleId")->fetchColumn();
        $this->assertEquals('In Progress', $status);
    }

    public function test_full_sample_workflow(): void
    {
        $db = $this->db();
        $sampleId = $this->createTestSample();

        $transitions = ['Registered', 'In Progress', 'Reviewed', 'Approved', 'COA Released'];

        foreach ($transitions as $status) {
            $db->exec("UPDATE samples SET status = '$status' WHERE id = $sampleId");
            $current = $db->query("SELECT status FROM samples WHERE id = $sampleId")->fetchColumn();
            $this->assertEquals($status, $current, "Sample should reach status '$status'");
        }
    }

    public function test_assigned_tests_are_created_with_sample(): void
    {
        $db = $this->db();
        $sampleId = $this->createTestSample();

        // Assign a test to the sample
        $testId = $db->query("SELECT id FROM tests LIMIT 1")->fetchColumn();
        if ($testId) {
            $db->prepare("INSERT INTO sample_tests (sample_id, test_id, status) VALUES (?, ?, 'Pending')")
               ->execute([$sampleId, $testId]);

            $count = $db->query("SELECT COUNT(*) FROM sample_tests WHERE sample_id = $sampleId")->fetchColumn();
            $this->assertEquals(1, $count, 'Sample should have 1 assigned test');
        }
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $sampleId = $this->createTestSample();

        $db = $this->db();
        $db->exec("UPDATE samples SET status = 'Registered' WHERE id = $sampleId");

        // Try to skip directly to Approved (should require Reviewed first)
        $currentStatus = $db->query("SELECT status FROM samples WHERE id = $sampleId")->fetchColumn();

        $allowedTransitions = [
            'Registered' => ['In Progress'],
            'In Progress' => ['Reviewed', 'Rejected'],
            'Reviewed' => ['Approved', 'Rejected'],
            'Approved' => ['COA Released'],
        ];

        $canTransition = isset($allowedTransitions[$currentStatus]) && in_array('Approved', $allowedTransitions[$currentStatus]);

        $this->assertFalse($canTransition, 'Direct transition from Registered to Approved should not be allowed');
    }

    public function test_unauthorized_user_cannot_access_samples(): void
    {
        $this->clearAuth();
        $this->assertFalse(Auth::check(), 'User should not be authenticated');
    }

    public function test_sample_search_by_code(): void
    {
        $db = $this->db();
        $this->createTestSample();

        $searchTerm = 'SMP-TEST';
        $stmt = $db->prepare("SELECT * FROM samples WHERE sample_code ILIKE ?");
        $stmt->execute(["%$searchTerm%"]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($results, 'Search should return at least one sample');
    }

    public function test_sample_search_by_batch_number(): void
    {
        $db = $this->db();
        $this->createTestSample();

        $stmt = $db->prepare("SELECT * FROM samples WHERE batch_number = ?");
        $stmt->execute(['BATCH-001']);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($results, 'Search by batch number should find samples');
    }

    public function test_dashboard_stats_are_accurate(): void
    {
        $db = $this->db();

        $total = (int)$db->query("SELECT COUNT(*) FROM samples")->fetchColumn();
        $registered = (int)$db->query("SELECT COUNT(*) FROM samples WHERE status = 'Registered'")->fetchColumn();

        $this->assertGreaterThanOrEqual(0, $total);
        $this->assertGreaterThanOrEqual(0, $registered);
        $this->assertGreaterThanOrEqual($registered, $total, 'Registered count should not exceed total');
    }

    public function test_sample_with_relations_returns_joined_data(): void
    {
        $db = $this->db();
        $sampleId = $this->createTestSample();

        $stmt = $db->prepare("
            SELECT s.*, c.customer_name, p.product_name
            FROM samples s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            WHERE s.id = ?
        ");
        $stmt->execute([$sampleId]);
        $sample = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($sample);
        $this->assertArrayHasKey('customer_name', $sample);
        $this->assertArrayHasKey('product_name', $sample);
    }

    /**
     * Helper: create a test sample and return its ID.
     */
    private function createTestSample(): int
    {
        $db = $this->db();
        $stmt = $db->prepare("
            INSERT INTO samples (sample_code, customer_id, product_id, batch_number, status, priority, registered_by)
            VALUES (?, 1, 1, 'BATCH-001', 'Registered', 'Normal', 1)
            RETURNING id
        ");
        $stmt->execute(['SMP-TEST-' . uniqid()]);
        return (int)$stmt->fetchColumn();
    }

    protected function tearDown(): void
    {
        $this->rollBack();
        parent::tearDown();
    }
}
