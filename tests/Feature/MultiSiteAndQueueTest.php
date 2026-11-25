<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Helpers\TenantContext;
use App\Services\Ops\QueueService;
use App\Services\Ops\SbomService;
use App\Services\Ops\PartitioningService;

class MultiSiteAndQueueTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::connect();
    }

    public function testTenantContextAndSiteScoping(): void
    {
        // 1. Verify default sites seeded by migration 024
        $stmt = $this->db->query("SELECT * FROM sites WHERE site_code = 'SITE-BOS-01'");
        $boston = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotEmpty($boston);
        $this->assertSame('Boston Central QC Testing Facility', $boston['name']);

        // 2. Set active site context
        TenantContext::setSiteId((int)$boston['id']);
        $this->assertSame((int)$boston['id'], TenantContext::getSiteId());

        // 3. Test query scoping
        $baseSql = "SELECT * FROM samples";
        $scoped = TenantContext::scopeQuery($baseSql, [], 'site_id');
        $this->assertStringContainsString('WHERE site_id = ?', $scoped['sql']);
        $this->assertSame([(int)$boston['id']], $scoped['params']);

        $baseSqlWithWhere = "SELECT * FROM samples WHERE status = 'pending'";
        $scopedWithWhere = TenantContext::scopeQuery($baseSqlWithWhere, ['pending'], 'site_id');
        $this->assertStringContainsString('AND site_id = ?', $scopedWithWhere['sql']);
        $this->assertSame(['pending', (int)$boston['id']], $scopedWithWhere['params']);
    }

    public function testQueueServiceLifecycleAndDeadLetterEscalation(): void
    {
        $queue = new QueueService();
        $testClass = 'App\Jobs\ProcessInstrumentStreamJob';
        $payload = ['vault_id' => 42, 'instrument' => 'HPLC-01'];

        // 1. Push job
        $jobId = $queue->push($testClass, $payload, 'qc_high_priority');
        $this->assertGreaterThan(0, $jobId);

        // 2. Pop and reserve job
        $reserved = $queue->pop('qc_high_priority');
        $this->assertNotNull($reserved);
        $this->assertSame($jobId, (int)$reserved['id']);
        $this->assertSame('processing', $reserved['status']);
        $this->assertSame(1, (int)$reserved['attempts']);
        $this->assertSame(42, $reserved['payload']['vault_id']);

        // 3. Complete job
        $completed = $queue->complete($jobId);
        $this->assertTrue($completed);

        $check = $this->db->query("SELECT status FROM job_queues WHERE id = {$jobId}")->fetchColumn();
        $this->assertSame('completed', $check);

        // 4. Test Failure and Dead Letter Queue (DLQ)
        $failJobId = $queue->push('App\Jobs\FailJob', ['attempt' => 1], 'dlq_test');
        $resFail = $queue->pop('dlq_test');
        $this->assertNotNull($resFail);

        // Fail attempt 1 (should retry with backoff)
        $queue->fail($failJobId, 'Temporary timeout error');
        $st1 = $this->db->query("SELECT status, attempts FROM job_queues WHERE id = {$failJobId}")->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('pending', $st1['status']);

        // Force attempts to max_attempts to trigger DLQ
        $this->db->prepare("UPDATE job_queues SET attempts = max_attempts WHERE id = ?")->execute([$failJobId]);
        $queue->fail($failJobId, 'Fatal unrecoverable error');

        $stDlq = $this->db->query("SELECT status, error_message FROM job_queues WHERE id = {$failJobId}")->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('dead_letter', $stDlq['status']);
        $this->assertSame('Fatal unrecoverable error', $stDlq['error_message']);

        // 5. Stats
        $stats = $queue->stats();
        $this->assertGreaterThan(0, (int)$stats['total_jobs']);
        $this->assertGreaterThan(0, (int)$stats['completed_count']);
        $this->assertGreaterThan(0, (int)$stats['dead_letter_count']);
    }

    public function testSbomCycloneDxGenerationAndSupplyChainAssurance(): void
    {
        $sbomService = new SbomService();

        // 1. Generate CycloneDX 1.5 JSON
        $bom = $sbomService->generateCycloneDx();
        $this->assertSame('CycloneDX', $bom['bomFormat']);
        $this->assertSame('1.5', $bom['specVersion']);
        $this->assertNotEmpty($bom['serialNumber']);
        $this->assertSame('PlexiQ LIMS', $bom['metadata']['component']['name']);
        $this->assertNotEmpty($bom['components']);

        // Check component properties
        $firstComp = $bom['components'][0];
        $this->assertArrayHasKey('name', $firstComp);
        $this->assertArrayHasKey('version', $firstComp);
        $this->assertArrayHasKey('purl', $firstComp);
        $this->assertStringStartsWith('pkg:composer/', $firstComp['purl']);

        // 2. Summary
        $summary = $sbomService->getSecuritySummary();
        $this->assertGreaterThan(0, $summary['total_dependencies']);
        $this->assertSame(0, $summary['vulnerabilities_found']);
        $this->assertStringContainsString('Compliant', $summary['supply_chain_status']);
    }

    public function testPartitioningStrategyPlan(): void
    {
        $partService = new PartitioningService();
        $plan = $partService->getPartitionPlan();

        $this->assertArrayHasKey('strategy', $plan);
        $this->assertArrayHasKey('tables', $plan);
        $this->assertArrayHasKey('retention_policy', $plan);

        $this->assertCount(3, $plan['tables']['audit_logs']);
        $this->assertCount(3, $plan['tables']['results']);
        $this->assertNotEmpty($plan['retention_policy']['hot_storage']);
        $this->assertNotEmpty($plan['retention_policy']['cold_storage']);
    }
}
