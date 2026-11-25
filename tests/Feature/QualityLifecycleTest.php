<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\Database;
use Tests\TestCase;
use PDO;

class QualityLifecycleTest extends TestCase
{
    public function test_change_control_and_pre_approval_lifecycle(): void
    {
        $db = Database::connect();

        $changeNum = 'CC-TEST-' . uniqid();
        $stmt = $db->prepare(
            "INSERT INTO change_controls (change_number, title, change_type, reason_for_change, impact_assessment, status)
             VALUES (?, 'Validation of New Dissolution Apparatus', 'Equipment', 'Replaces worn drive belt', 'No impact on method qualification', 'Submitted')
             RETURNING id"
        );
        $stmt->execute([$changeNum]);
        $id = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(0, $id);

        // Pre-approve
        $db->prepare("UPDATE change_controls SET status = 'Pre-Approved', pre_approved_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);

        $status = $db->query("SELECT status FROM change_controls WHERE id = {$id}")->fetchColumn();
        $this->assertEquals('Pre-Approved', $status);
    }

    public function test_fmea_rpn_calculation(): void
    {
        $db = Database::connect();

        // Severity = 8, Occurrence = 5, Detection = 4 => RPN = 8 * 5 * 4 = 160
        $stmt = $db->prepare(
            "INSERT INTO fmea_risk_assessments (
                entity_type, entity_id, process_step, failure_mode, failure_effect, failure_cause,
                severity, occurrence, detection
            ) VALUES ('method', 1, 'Sample Weighing', 'Static electricity interference', 'Incorrect sample weight', 'Low ambient humidity', 8, 5, 4)
             RETURNING id, rpn"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(160, (int)$row['rpn']);
    }
}
