<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\SampleTest;
use App\Models\Result;
use App\Models\TestItem;

class TestResultController extends BaseController
{
    public function pendingResults(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();

        $stmt = $db->query("
            SELECT st.*, s.sample_code, t.test_name, t.test_code, t.min_spec_limit, t.max_spec_limit,
                   c.customer_name, p.product_name, u.full_name AS assigned_to_name
            FROM sample_tests st
            JOIN samples s ON st.sample_id = s.id
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON st.assigned_to = u.id
            WHERE st.status IN ('Pending', 'In Progress') AND st.deleted_at IS NULL AND s.deleted_at IS NULL
            ORDER BY s.priority DESC, s.created_at ASC
        ");

        return $this->render('tests.pending', ['tests' => $stmt->fetchAll()]);
    }

    public function enterResult(int $sampleTestId): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();

        $stmt = $db->prepare("
            SELECT st.*, s.sample_code, s.batch_number, s.batch_id, s.id AS sample_id, t.test_name, t.test_code,
                   t.min_spec_limit, t.max_spec_limit, t.spec_limit_text, u.unit_code, u.unit_name,
                   m.method_name
            FROM sample_tests st
            JOIN samples s ON st.sample_id = s.id
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN units u ON t.unit_id = u.id
            LEFT JOIN methods m ON t.method_id = m.id
            WHERE st.id = ? AND st.deleted_at IS NULL
        ");
        $stmt->execute([$sampleTestId]);
        $test = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$test) {
            session_flash('error', 'Test assignment not found.');
            redirect('/tests/pending');
        }

        // Find next pending test in same batch for Save & Next
        $nextTest = null;
        if (!empty($test['sample_id'])) {
            $nStmt = $db->prepare("
                SELECT st.id FROM sample_tests st
                WHERE st.sample_id = ? AND st.status = 'Pending'
                ORDER BY st.id LIMIT 1
            ");
            $nStmt->execute([$test['sample_id']]);
            $nextRow = $nStmt->fetch(\PDO::FETCH_ASSOC);
            if ($nextRow && (int)$nextRow['id'] !== $sampleTestId) {
                $nextTest = $nextRow['id'];
            }
        }

        $existingResult = Result::findBySampleTest($sampleTestId);
        $revisions = [];
        if ($existingResult) {
            $revisions = Result::getRevisions($existingResult['id']);
        }

        return $this->render('tests.result-entry', [
            'test' => $test,
            'result' => $existingResult,
            'revisions' => $revisions,
            'nextTestId' => $nextTest,
        ]);
    }

    public function saveResult(int $sampleTestId): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();

        $value = $_POST['result_value'] ?? null;
        $text = $_POST['result_text'] ?? null;
        $remarks = $_POST['remarks'] ?? null;
        $uncertainty = $_POST['uncertainty'] !== '' ? $_POST['uncertainty'] : null;
        $kFactor = $_POST['k_factor'] !== '' ? $_POST['k_factor'] : null;
        $confidenceInterval = $_POST['confidence_interval'] ?: null;
        $replicateCount = $_POST['replicate_count'] !== '' ? (int)$_POST['replicate_count'] : 1;

        // Get test specs
        $stmt = $db->prepare("
            SELECT t.*, st.status FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            WHERE st.id = ? AND st.deleted_at IS NULL
        ");
        $stmt->execute([$sampleTestId]);
        $spec = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$spec) {
            session_flash('error', 'Test assignment not found.');
            redirect('/tests/pending');
        }

        // Auto-validate
        $isWithinSpec = null;
        if ($value !== null && $value !== '' && $spec['min_spec_limit'] !== null && $spec['max_spec_limit'] !== null) {
            $isWithinSpec = Result::validateAgainstSpec((float)$value, (float)$spec['min_spec_limit'], (float)$spec['max_spec_limit']);
        }

        $db->beginTransaction();
        try {
            // Check existing result for revision logic
            $existing = Result::findBySampleTest($sampleTestId);
            $revision = 1;
            $resultId = null;

            if ($existing) {
                $revision = $existing['revision'] + 1;
                $resultId = $existing['id'];

                // Save old revision
                Result::saveRevision(
                    $resultId, $revision - 1,
                    $existing['result_value'], $existing['result_text'],
                    Auth::id(), 'Auto-saved before revision'
                );

                // Update existing result
                $stmt = $db->prepare("
                    UPDATE results SET result_value = ?, result_text = ?, is_within_spec = ?,
                        entered_by = ?, entered_at = CURRENT_TIMESTAMP, remarks = ?,
                        uncertainty = ?, k_factor = ?, confidence_interval = ?, replicate_count = ?,
                        revision = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$value, $text, $isWithinSpec, Auth::id(), $remarks, $uncertainty, $kFactor, $confidenceInterval, $replicateCount, $revision, $resultId]);
            } else {
                // Create new result
                $stmt = $db->prepare("
                    INSERT INTO results (sample_test_id, result_value, result_text, is_within_spec, entered_by, remarks,
                        uncertainty, k_factor, confidence_interval, replicate_count, revision)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
                ");
                $stmt->execute([$sampleTestId, $value, $text, $isWithinSpec, Auth::id(), $remarks, $uncertainty, $kFactor, $confidenceInterval, $replicateCount, $revision]);
                $resultId = (int)$stmt->fetchColumn();
            }

            // Update sample_test status
            SampleTest::updateStatus($sampleTestId, 'Completed');

            // Update sample status if needed
            $sampleStmt = $db->prepare("
                UPDATE samples SET status = 'In Progress', updated_at = CURRENT_TIMESTAMP
                WHERE id = (SELECT sample_id FROM sample_tests WHERE id = ?) AND status = 'Registered'
            ");
            $sampleStmt->execute([$sampleTestId]);

            $db->commit();

            Audit::log('Result Entered', 'results', $resultId, null, [
                'sample_test_id' => $sampleTestId,
                'value' => $value,
                'is_within_spec' => $isWithinSpec,
            ]);

            session_flash('success', 'Result saved successfully.');
        } catch (\Exception $e) {
            $db->rollBack();
            session_flash('error', 'Error saving result: ' . $e->getMessage());
        }

        $nextId = $_POST['_next_test_id'] ?? null;
        if ($nextId) {
            redirect('/tests/' . (int)$nextId . '/result');
        }
        redirect('/tests/pending');
    }

    public function reviewResults(): string
    {
        Auth::requireAnyRole(['Admin', 'Reviewer']);
        $db = \App\Helpers\Database::connect();

        $stmt = $db->query("
            SELECT st.*, s.sample_code, t.test_name, t.test_code, t.min_spec_limit, t.max_spec_limit,
                   r.result_value, r.result_text, r.is_within_spec, r.id AS result_id,
                   r.uncertainty, r.k_factor, r.confidence_interval, r.replicate_count,
                   u.full_name AS entered_by_name, ue.full_name AS assigned_to_name,
                   c.customer_name, p.product_name
            FROM sample_tests st
            JOIN samples s ON st.sample_id = s.id
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.revision = (
                SELECT MAX(r2.revision) FROM results r2 WHERE r2.sample_test_id = st.id
            )
            LEFT JOIN users u ON r.entered_by = u.id
            LEFT JOIN users ue ON st.assigned_to = ue.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            WHERE st.status = 'Completed' AND st.deleted_at IS NULL AND s.deleted_at IS NULL
            ORDER BY s.priority DESC, r.entered_at ASC
        ");

        return $this->render('tests.review', ['tests' => $stmt->fetchAll()]);
    }

    public function approveResult(int $sampleTestId): void
    {
        Auth::requireAnyRole(['Admin', 'Reviewer']);
        $db = \App\Helpers\Database::connect();
        $action = $_POST['action'] ?? 'reject';
        $remarks = $_POST['remarks'] ?? '';

        if ($action === 'approve') {
            SampleTest::updateStatus($sampleTestId, 'Reviewed');

            // Update result
            $result = Result::findBySampleTest($sampleTestId);
            if ($result) {
                $stmt = $db->prepare("UPDATE results SET reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([Auth::id(), $result['id']]);
            }

            // Check if all tests are reviewed
            $stmt = $db->prepare("SELECT sample_id FROM sample_tests WHERE id = ?");
            $stmt->execute([$sampleTestId]);
            $sampleId = $stmt->fetchColumn();
            if ($sampleId) {
                $pending = $db->prepare("SELECT COUNT(*) FROM sample_tests WHERE sample_id = ? AND status NOT IN ('Reviewed', 'Approved')");
                $pending->execute([$sampleId]);
                if ((int)$pending->fetchColumn() === 0) {
                    $db->prepare("UPDATE samples SET status = 'Reviewed', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([Auth::id(), $sampleId]);
                    Audit::log('Sample Reviewed (all tests)', 'samples', (int)$sampleId);
                }
            }

            Audit::log('Result Reviewed', 'results', $result['id'] ?? null);
            session_flash('success', 'Result reviewed and approved.');
        } else {
            SampleTest::updateStatus($sampleTestId, 'In Progress');
            Audit::log('Result Rejected', 'results');
            session_flash('warning', 'Result rejected. Analyst needs to re-enter.');
        }

        redirect('/tests/review');
    }

    public function finalApproval(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();

        $stmt = $db->query("
            SELECT st.*, s.sample_code, t.test_name, t.test_code, t.min_spec_limit, t.max_spec_limit,
                   r.result_value, r.result_text, r.is_within_spec, r.id AS result_id,
                   r.uncertainty, r.k_factor, r.confidence_interval, r.replicate_count,
                   u.full_name AS entered_by_name, ru.full_name AS reviewed_by_name,
                   c.customer_name, p.product_name
            FROM sample_tests st
            JOIN samples s ON st.sample_id = s.id
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.revision = (
                SELECT MAX(r2.revision) FROM results r2 WHERE r2.sample_test_id = st.id
            )
            LEFT JOIN users u ON r.entered_by = u.id
            LEFT JOIN users ru ON r.reviewed_by = ru.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            WHERE st.status = 'Reviewed' AND st.deleted_at IS NULL AND s.deleted_at IS NULL
            ORDER BY s.priority DESC, r.reviewed_at ASC
        ");

        return $this->render('tests.final-approval', ['tests' => $stmt->fetchAll()]);
    }

    public function finalApproveResult(int $sampleTestId): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $action = $_POST['action'] ?? 'reject';

        if ($action === 'approve') {
            SampleTest::updateStatus($sampleTestId, 'Approved');

            $result = Result::findBySampleTest($sampleTestId);
            if ($result) {
                $stmt = $db->prepare("UPDATE results SET approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([Auth::id(), $result['id']]);
            }

            // Update sample to Approved if all tests approved
            $stmt = $db->prepare("
                UPDATE samples SET status = 'Approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = (SELECT sample_id FROM sample_tests WHERE id = ?)
                AND NOT EXISTS (
                    SELECT 1 FROM sample_tests st2
                    WHERE st2.sample_id = samples.id AND st2.status NOT IN ('Approved', 'Reviewed')
                )
                AND status != 'Approved'
            ");
            $stmt->execute([Auth::id(), $sampleTestId]);

            Audit::log('Result Final Approved', 'results', $result['id'] ?? null);
            session_flash('success', 'Result approved.');
        } else {
            SampleTest::updateStatus($sampleTestId, 'Completed');
            session_flash('warning', 'Result sent back for revision.');
        }

        redirect('/tests/final-approval');
    }
}
