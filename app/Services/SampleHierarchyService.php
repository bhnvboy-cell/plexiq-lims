<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Audit;
use App\Helpers\Auth;
use PDO;

class SampleHierarchyService
{
    /**
     * Create N aliquots from a parent sample.
     *
     * @param int $parentSampleId
     * @param int $count Number of aliquots to generate (1 to 99)
     * @param string|null $volumeOrWeight e.g. "5 mL"
     * @param array<int> $copyTestIds List of test IDs to assign to each aliquot (empty = all parent tests)
     * @return array<array<string, mixed>> Created aliquot samples
     */
    public function createAliquots(
        int $parentSampleId,
        int $count,
        ?string $volumeOrWeight = null,
        array $copyTestIds = []
    ): array {
        if ($count < 1 || $count > 99) {
            throw new \InvalidArgumentException("Aliquot count must be between 1 and 99.");
        }

        $db = Database::connect();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT * FROM samples WHERE id = ? FOR UPDATE');
            $stmt->execute([$parentSampleId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                throw new \InvalidArgumentException("Parent sample #{$parentSampleId} not found.");
            }

            // Determine starting aliquot index
            $stmtMax = $db->prepare(
                "SELECT COALESCE(MAX(aliquot_index), 0) FROM samples WHERE parent_sample_id = ? AND hierarchy_type = 'aliquot'"
            );
            $stmtMax->execute([$parentSampleId]);
            $startIndex = (int)$stmtMax->fetchColumn();

            // Fetch parent tests to replicate
            if (empty($copyTestIds)) {
                $stmtTests = $db->prepare('SELECT test_id, assigned_to FROM sample_tests WHERE sample_id = ?');
                $stmtTests->execute([$parentSampleId]);
                $testsToAssign = $stmtTests->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $inQuery = implode(',', array_fill(0, count($copyTestIds), '?'));
                $stmtTests = $db->prepare("SELECT id as test_id FROM tests WHERE id IN ({$inQuery})");
                $stmtTests->execute($copyTestIds);
                $testsToAssign = array_map(fn($r) => ['test_id' => $r['test_id'], 'assigned_to' => null], $stmtTests->fetchAll(PDO::FETCH_ASSOC));
            }

            $parentGenealogy = !empty($parent['genealogy_path']) ? $parent['genealogy_path'] : (string)$parentSampleId;
            $aliquots = [];
            $userId = Auth::id() ?? 1;

            for ($i = 1; $i <= $count; $i++) {
                $aliquotIndex = $startIndex + $i;
                $aliquotCode = sprintf("%s-A%02d", $parent['sample_code'], $aliquotIndex);

                // Insert aliquot sample
                $insertStmt = $db->prepare(
                    "INSERT INTO samples (
                        sample_code, customer_id, product_id, batch_number, batch_size,
                        manufacture_date, expiry_date, received_date, target_completion_date,
                        priority, status, assigned_analyst_id, registered_by, notes,
                        parent_sample_id, hierarchy_type, aliquot_index, genealogy_path
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, CURRENT_DATE, ?,
                        ?, 'Registered', ?, ?, ?,
                        ?, 'aliquot', ?, ''
                    ) RETURNING id"
                );

                $notes = "Aliquot #{$aliquotIndex} of {$parent['sample_code']}";
                if ($volumeOrWeight) {
                    $notes .= " (Volume/Qty: {$volumeOrWeight})";
                }

                $insertStmt->execute([
                    $aliquotCode,
                    $parent['customer_id'],
                    $parent['product_id'],
                    $parent['batch_number'],
                    $parent['batch_size'],
                    $parent['manufacture_date'],
                    $parent['expiry_date'],
                    $parent['target_completion_date'],
                    $parent['priority'],
                    $parent['assigned_analyst_id'],
                    $userId,
                    $notes,
                    $parentSampleId,
                    $aliquotIndex
                ]);

                $aliquotId = (int)$insertStmt->fetchColumn();
                $aliquotPath = "{$parentGenealogy}/{$aliquotId}";

                // Update genealogy path
                $db->prepare("UPDATE samples SET genealogy_path = ? WHERE id = ?")
                   ->execute([$aliquotPath, $aliquotId]);

                // Assign tests
                foreach ($testsToAssign as $t) {
                    $db->prepare("INSERT INTO sample_tests (sample_id, test_id, assigned_to, status) VALUES (?, ?, ?, 'Pending')")
                       ->execute([$aliquotId, $t['test_id'], $t['assigned_to']]);
                }

                Audit::log(
                    'Create Aliquot',
                    'samples',
                    $aliquotId,
                    null,
                    ['sample_code' => $aliquotCode, 'parent_id' => $parentSampleId, 'aliquot_index' => $aliquotIndex],
                    "Generated aliquot {$aliquotCode} from parent #{$parentSampleId}"
                );

                $aliquots[] = [
                    'id' => $aliquotId,
                    'sample_code' => $aliquotCode,
                    'parent_id' => $parentSampleId,
                    'aliquot_index' => $aliquotIndex,
                    'genealogy_path' => $aliquotPath
                ];
            }

            $db->commit();
            return $aliquots;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Create a composite sample combining multiple source samples.
     *
     * @param array<int> $sourceSampleIds
     * @param string $compositeCode
     * @param int|null $customerId
     * @param int|null $productId
     * @return array<string, mixed>
     */
    public function createComposite(
        array $sourceSampleIds,
        string $compositeCode,
        ?int $customerId = null,
        ?int $productId = null
    ): array {
        if (count($sourceSampleIds) < 2) {
            throw new \InvalidArgumentException("At least two source samples are required to create a composite.");
        }

        $db = Database::connect();
        $db->beginTransaction();

        try {
            // Verify source samples exist
            $inQuery = implode(',', array_fill(0, count($sourceSampleIds), '?'));
            $stmt = $db->prepare("SELECT id, customer_id, product_id, batch_number FROM samples WHERE id IN ({$inQuery})");
            $stmt->execute($sourceSampleIds);
            $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($sources) !== count($sourceSampleIds)) {
                throw new \InvalidArgumentException("One or more source samples could not be found.");
            }

            $customerId = $customerId ?? $sources[0]['customer_id'];
            $productId = $productId ?? $sources[0]['product_id'];
            $batchNumber = $sources[0]['batch_number'] ?? 'COMPOSITE';
            $userId = Auth::id() ?? 1;

            $insertStmt = $db->prepare(
                "INSERT INTO samples (
                    sample_code, customer_id, product_id, batch_number,
                    received_date, priority, status, registered_by, notes,
                    hierarchy_type, composite_components, genealogy_path
                ) VALUES (
                    ?, ?, ?, ?,
                    CURRENT_DATE, 'Normal', 'Registered', ?, ?,
                    'composite', ?::jsonb, ''
                ) RETURNING id"
            );

            $notes = "Composite sample composed of: " . implode(', ', array_column($sources, 'id'));
            $insertStmt->execute([
                $compositeCode,
                $customerId,
                $productId,
                $batchNumber,
                $userId,
                $notes,
                json_encode($sourceSampleIds)
            ]);

            $compositeId = (int)$insertStmt->fetchColumn();
            $db->prepare("UPDATE samples SET genealogy_path = ? WHERE id = ?")
               ->execute([(string)$compositeId, $compositeId]);

            Audit::log(
                'Create Composite Sample',
                'samples',
                $compositeId,
                null,
                ['sample_code' => $compositeCode, 'components' => $sourceSampleIds],
                "Created composite sample {$compositeCode}"
            );

            $db->commit();
            return [
                'id' => $compositeId,
                'sample_code' => $compositeCode,
                'hierarchy_type' => 'composite',
                'components' => $sourceSampleIds
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieve complete genealogy tree for a given sample.
     *
     * @param int $sampleId
     * @return array{sample: array<string, mixed>, parent: ?array<string, mixed>, children: array<array<string, mixed>>, composite_sources: array<array<string, mixed>>}
     */
    public function getGenealogyTree(int $sampleId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare('SELECT * FROM samples WHERE id = ?');
        $stmt->execute([$sampleId]);
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sample) {
            throw new \InvalidArgumentException("Sample #{$sampleId} not found.");
        }

        $parent = null;
        if (!empty($sample['parent_sample_id'])) {
            $stmtP = $db->prepare('SELECT id, sample_code, hierarchy_type, status FROM samples WHERE id = ?');
            $stmtP->execute([$sample['parent_sample_id']]);
            $parent = $stmtP->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $stmtC = $db->prepare('SELECT id, sample_code, hierarchy_type, aliquot_index, status FROM samples WHERE parent_sample_id = ? ORDER BY aliquot_index ASC, id ASC');
        $stmtC->execute([$sampleId]);
        $children = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        $compositeSources = [];
        if (!empty($sample['composite_components'])) {
            $compIds = json_decode($sample['composite_components'], true);
            if (!empty($compIds) && is_array($compIds)) {
                $inQuery = implode(',', array_fill(0, count($compIds), '?'));
                $stmtComp = $db->prepare("SELECT id, sample_code, hierarchy_type, status FROM samples WHERE id IN ({$inQuery})");
                $stmtComp->execute($compIds);
                $compositeSources = $stmtComp->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return [
            'sample' => $sample,
            'parent' => $parent,
            'children' => $children,
            'composite_sources' => $compositeSources
        ];
    }
}
