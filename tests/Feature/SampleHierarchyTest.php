<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\Database;
use App\Services\SampleHierarchyService;
use Tests\TestCase;
use PDO;

class SampleHierarchyTest extends TestCase
{
    private SampleHierarchyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SampleHierarchyService();
    }

    public function test_can_create_aliquots_from_parent_sample(): void
    {
        $db = Database::connect();

        $parentCode = 'TEST-PARENT-' . uniqid();
        $stmt = $db->prepare(
            "INSERT INTO samples (sample_code, received_date, status, priority, hierarchy_type)
             VALUES (?, CURRENT_DATE, 'Registered', 'Normal', 'primary')
             RETURNING id"
        );
        $stmt->execute([$parentCode]);
        $parentId = (int)$stmt->fetchColumn();

        // 2. Generate 3 aliquots
        $aliquots = $this->service->createAliquots($parentId, 3, '10 mL');

        $this->assertCount(3, $aliquots);
        $this->assertEquals("{$parentCode}-A01", $aliquots[0]['sample_code']);
        $this->assertEquals("{$parentCode}-A02", $aliquots[1]['sample_code']);
        $this->assertEquals("{$parentCode}-A03", $aliquots[2]['sample_code']);

        // Verify database records
        $stmtCheck = $db->prepare("SELECT * FROM samples WHERE parent_sample_id = ? ORDER BY aliquot_index ASC");
        $stmtCheck->execute([$parentId]);
        $rows = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(3, $rows);
        $this->assertEquals('aliquot', $rows[0]['hierarchy_type']);
        $this->assertEquals(1, $rows[0]['aliquot_index']);
        $this->assertStringContainsString("{$parentId}/{$rows[0]['id']}", $rows[0]['genealogy_path']);
    }

    public function test_can_create_composite_sample(): void
    {
        $db = Database::connect();

        $c1 = 'COMP-SRC-1-' . uniqid();
        $c2 = 'COMP-SRC-2-' . uniqid();
        $s1 = (int)$db->query("INSERT INTO samples (sample_code, received_date, status) VALUES ('{$c1}', CURRENT_DATE, 'Registered') RETURNING id")->fetchColumn();
        $s2 = (int)$db->query("INSERT INTO samples (sample_code, received_date, status) VALUES ('{$c2}', CURRENT_DATE, 'Registered') RETURNING id")->fetchColumn();

        $compCode = 'COMP-LOT-' . uniqid();
        $comp = $this->service->createComposite([$s1, $s2], $compCode);

        $this->assertEquals($compCode, $comp['sample_code']);
        $this->assertEquals('composite', $comp['hierarchy_type']);
        $this->assertEquals([$s1, $s2], $comp['components']);
    }

    public function test_can_fetch_genealogy_tree(): void
    {
        $db = Database::connect();

        $treeCode = 'TREE-PARENT-' . uniqid();
        $parentId = (int)$db->query("INSERT INTO samples (sample_code, received_date, status) VALUES ('{$treeCode}', CURRENT_DATE, 'Registered') RETURNING id")->fetchColumn();
        $this->service->createAliquots($parentId, 2);

        $tree = $this->service->getGenealogyTree($parentId);

        $this->assertEquals($treeCode, $tree['sample']['sample_code']);
        $this->assertNull($tree['parent']);
        $this->assertCount(2, $tree['children']);
    }
}
