<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Audit;
use App\Helpers\Auth;
use PDO;

class StorageManagementService
{
    /**
     * Check in a sample to a specific storage location coordinate (e.g., Row 1, Col 4 => "A4").
     *
     * @param int $sampleId
     * @param int $locationId
     * @param int $row
     * @param int $col
     * @return array<string, mixed>
     */
    public function checkIn(int $sampleId, int $locationId, int $row, int $col): array
    {
        $db = Database::connect();
        $db->beginTransaction();

        try {
            // Verify location exists and is a container/box
            $stmtLoc = $db->prepare('SELECT * FROM storage_locations WHERE id = ? FOR UPDATE');
            $stmtLoc->execute([$locationId]);
            $loc = $stmtLoc->fetch(PDO::FETCH_ASSOC);

            if (!$loc) {
                throw new \InvalidArgumentException("Storage location #{$locationId} not found.");
            }

            if ($row < 1 || $row > (int)$loc['capacity_rows'] || $col < 1 || $col > (int)$loc['capacity_cols']) {
                throw new \InvalidArgumentException(
                    "Coordinates [{$row}, {$col}] exceed location bounds ({$loc['capacity_rows']}x{$loc['capacity_cols']})."
                );
            }

            // Check if slot is already occupied
            $stmtOccupied = $db->prepare(
                "SELECT id, sample_id FROM sample_storage_assignments 
                 WHERE storage_location_id = ? AND row_coord = ? AND col_coord = ? AND status = 'Stored' 
                 LIMIT 1"
            );
            $stmtOccupied->execute([$locationId, $row, $col]);
            if ($existing = $stmtOccupied->fetch(PDO::FETCH_ASSOC)) {
                throw new \InvalidArgumentException(
                    "Slot [Row {$row}, Col {$col}] is already occupied by Sample #{$existing['sample_id']}."
                );
            }

            // Calculate standard alphanumeric label (Row 1 -> 'A', Row 2 -> 'B', etc.)
            $rowChar = chr(64 + $row);
            $posLabel = "{$rowChar}{$col}";

            $userId = Auth::id() ?? 1;

            // Check out any previous active storage assignment for this sample
            $stmtOld = $db->prepare("UPDATE sample_storage_assignments SET status = 'Transferred', checked_out_at = CURRENT_TIMESTAMP WHERE sample_id = ? AND status = 'Stored'");
            $stmtOld->execute([$sampleId]);

            $stmtInsert = $db->prepare(
                "INSERT INTO sample_storage_assignments (
                    sample_id, storage_location_id, row_coord, col_coord,
                    position_label, checked_in_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'Stored') RETURNING id"
            );
            $stmtInsert->execute([$sampleId, $locationId, $row, $col, $posLabel, $userId]);
            $assignmentId = (int)$stmtInsert->fetchColumn();

            Audit::log(
                'Sample Storage Check-In',
                'sample_storage_assignments',
                $assignmentId,
                null,
                ['sample_id' => $sampleId, 'location_id' => $locationId, 'position' => $posLabel],
                "Sample #{$sampleId} checked into {$loc['name']} at slot {$posLabel}"
            );

            $db->commit();
            return [
                'assignment_id' => $assignmentId,
                'sample_id' => $sampleId,
                'location_id' => $locationId,
                'location_name' => $loc['name'],
                'position_label' => $posLabel,
                'status' => 'Stored'
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Check out a sample from storage.
     *
     * @param int $sampleId
     * @param string $reason
     * @return bool
     */
    public function checkOut(int $sampleId, string $reason): bool
    {
        $db = Database::connect();
        $userId = Auth::id() ?? 1;

        $stmt = $db->prepare(
            "UPDATE sample_storage_assignments
             SET status = 'Checked Out', checked_out_at = CURRENT_TIMESTAMP, checked_out_by = ?, checkout_reason = ?
             WHERE sample_id = ? AND status = 'Stored' RETURNING id, storage_location_id, position_label"
        );
        $stmt->execute([$userId, $reason, $sampleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \InvalidArgumentException("No active storage assignment found for Sample #{$sampleId}.");
        }

        Audit::log(
            'Sample Storage Check-Out',
            'sample_storage_assignments',
            (int)$row['id'],
            ['status' => 'Stored'],
            ['status' => 'Checked Out', 'reason' => $reason],
            "Checked out sample #{$sampleId} from {$row['position_label']}: {$reason}"
        );

        return true;
    }

    /**
     * Get complete 2D grid matrix of a storage box with sample details.
     *
     * @param int $locationId
     * @return array{location: array<string, mixed>, matrix: array<int, array<int, ?array<string, mixed>>>, stats: array<string, int>}
     */
    public function getBoxMatrix(int $locationId): array
    {
        $db = Database::connect();

        $stmtLoc = $db->prepare('SELECT * FROM storage_locations WHERE id = ?');
        $stmtLoc->execute([$locationId]);
        $loc = $stmtLoc->fetch(PDO::FETCH_ASSOC);

        if (!$loc) {
            throw new \InvalidArgumentException("Storage location #{$locationId} not found.");
        }

        $rows = (int)($loc['capacity_rows'] ?: 8);
        $cols = (int)($loc['capacity_cols'] ?: 12);

        $stmtAssigned = $db->prepare(
            "SELECT a.id as assignment_id, a.row_coord, a.col_coord, a.position_label, a.checked_in_at,
                    s.id as sample_id, s.sample_code, s.status as sample_status, s.priority,
                    p.product_name
             FROM sample_storage_assignments a
             JOIN samples s ON a.sample_id = s.id
             LEFT JOIN products p ON s.product_id = p.id
             WHERE a.storage_location_id = ? AND a.status = 'Stored'"
        );
        $stmtAssigned->execute([$locationId]);
        $items = $stmtAssigned->fetchAll(PDO::FETCH_ASSOC);

        $lookup = [];
        foreach ($items as $item) {
            $lookup[$item['row_coord']][$item['col_coord']] = $item;
        }

        $matrix = [];
        $occupied = 0;

        for ($r = 1; $r <= $rows; $r++) {
            $matrix[$r] = [];
            for ($c = 1; $c <= $cols; $c++) {
                if (isset($lookup[$r][$c])) {
                    $matrix[$r][$c] = $lookup[$r][$c];
                    $occupied++;
                } else {
                    $rowChar = chr(64 + $r);
                    $matrix[$r][$c] = [
                        'empty' => true,
                        'row_coord' => $r,
                        'col_coord' => $c,
                        'position_label' => "{$rowChar}{$c}"
                    ];
                }
            }
        }

        $totalSlots = $rows * $cols;
        return [
            'location' => $loc,
            'matrix' => $matrix,
            'stats' => [
                'total_slots' => $totalSlots,
                'occupied_slots' => $occupied,
                'free_slots' => $totalSlots - $occupied,
                'occupancy_pct' => $totalSlots > 0 ? (int)round(($occupied / $totalSlots) * 100) : 0
            ]
        ];
    }
}
