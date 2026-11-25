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
                   COALESCE(pt.min_spec_limit, t.min_spec_limit) AS min_spec_limit,
                   COALESCE(pt.max_spec_limit, t.max_spec_limit) AS max_spec_limit,
                   COALESCE(pt.spec_limit_text, t.spec_limit_text) AS spec_limit_text,
                   u.unit_code, u.unit_name,
                   m.method_name
            FROM sample_tests st
            JOIN samples s ON st.sample_id = s.id
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN products_tests pt ON pt.product_id = s.product_id AND pt.test_id = st.test_id
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

        $instruments = $db->query("SELECT id, instrument_code, instrument_name, next_calibration_date FROM instruments WHERE is_active = TRUE ORDER BY instrument_name")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('tests.result-entry', [
            'test' => $test,
            'result' => $existingResult,
            'revisions' => $revisions,
            'nextTestId' => $nextTest,
            'instruments' => $instruments,
        ]);
    }

    public function saveResult(int $sampleTestId): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();

        $value = $_POST['result_value'] ?? null;
        $text = $_POST['result_text'] ?? null;
        $remarks = $_POST['remarks'] ?? null;
        $uncertainty = ($u = $_POST['uncertainty'] ?? '') !== '' ? $u : null;
        $kFactor = ($kf = $_POST['k_factor'] ?? '') !== '' ? $kf : null;
        $confidenceInterval = ($ci = $_POST['confidence_interval'] ?? '') !== '' ? $ci : null;
        $replicateCount = ($rc = $_POST['replicate_count'] ?? '') !== '' ? (int)$rc : 1;
        $instrumentId = ($inst = $_POST['instrument_id'] ?? '') !== '' ? (int)$inst : null;

        // Commercial LIMS: Statistical Replicate Calculation Engine (Mean, SD, %RSD)
        $replicatesRaw = trim($_POST['replicates_raw'] ?? '');
        if ($replicatesRaw !== '') {
            $parts = array_filter(array_map('trim', preg_split('/[,;\s]+/', $replicatesRaw)), 'is_numeric');
            if (count($parts) >= 2) {
                $nums = array_map('floatval', $parts);
                $replicateCount = count($nums);
                $mean = array_sum($nums) / $replicateCount;
                $sumSq = 0;
                foreach ($nums as $n) {
                    $sumSq += pow($n - $mean, 2);
                }
                $sd = sqrt($sumSq / ($replicateCount - 1));
                $rsd = ($mean != 0) ? abs(($sd / $mean) * 100) : 0;
                $uncertainty = round($sd * ($kFactor ? (float)$kFactor : 2.0), 4);
                if ($value === null || $value === '') {
                    $value = round($mean, 4);
                }
                $statRemark = sprintf("Replicates (n=%d): [%s] | Mean=%.4f | SD=%.4f | %%RSD=%.2f%%", $replicateCount, implode(', ', $nums), $mean, $sd, $rsd);
                $remarks = $remarks ? ($remarks . " | " . $statRemark) : $statRemark;
            }
        }

        // Commercial LIMS: ISO 17025 Instrument Calibration Gating
        if ($instrumentId) {
            $instStmt = $db->prepare("SELECT instrument_name, next_calibration_date FROM instruments WHERE id = ?");
            $instStmt->execute([$instrumentId]);
            $instRow = $instStmt->fetch(\PDO::FETCH_ASSOC);
            if ($instRow && !empty($instRow['next_calibration_date'])) {
                if (strtotime($instRow['next_calibration_date']) < strtotime(date('Y-m-d'))) {
                    session_flash('error', "Compliance Gate: Instrument '{$instRow['instrument_name']}' has an expired calibration (due: {$instRow['next_calibration_date']}). Cannot be used for testing.");
                    redirect('/tests/' . $sampleTestId . '/result');
                    return;
                }
            }
        }

        // Phase 3: Training Gating - Verify Analyst Qualification on Test Method & Instrument
        $trainingService = new \App\Services\TrainingGatingService();
        $analystId = Auth::id() ?? 1;
        $testIdStmt = $db->prepare("SELECT test_id FROM sample_tests WHERE id = ?");
        $testIdStmt->execute([$sampleTestId]);
        $targetTestId = (int)$testIdStmt->fetchColumn();

        if ($targetTestId && !$trainingService->isQualified($analystId, 'test_method', $targetTestId)) {
            session_flash('error', "Compliance Gate: Analyst is not certified/qualified to execute test method #{$targetTestId}. Complete required training protocol before testing.");
            redirect('/tests/' . $sampleTestId . '/result');
            return;
        }

        if ($instrumentId && !$trainingService->isQualified($analystId, 'instrument', $instrumentId)) {
            session_flash('error', "Compliance Gate: Analyst is not certified/qualified to operate instrument #{$instrumentId}.");
            redirect('/tests/' . $sampleTestId . '/result');
            return;
        }

        // Get test specs
        $stmt = $db->prepare("
            SELECT t.*, st.status, st.sample_id,
                   COALESCE(pt.min_spec_limit, t.min_spec_limit) AS min_spec_limit,
                   COALESCE(pt.max_spec_limit, t.max_spec_limit) AS max_spec_limit,
                   COALESCE(pt.spec_limit_text, t.spec_limit_text) AS spec_limit_text
            FROM sample_tests st
            JOIN samples s ON st.sample_id = s.id
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN products_tests pt ON pt.product_id = s.product_id AND pt.test_id = st.test_id
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
        $isWithinSpecParam = $isWithinSpec === null ? null : ($isWithinSpec ? 't' : 'f');

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
                        instrument_id = ?, revision = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$value, $text, $isWithinSpecParam, Auth::id(), $remarks, $uncertainty, $kFactor, $confidenceInterval, $replicateCount, $instrumentId, $revision, $resultId]);
            } else {
                // Create new result
                $stmt = $db->prepare("
                    INSERT INTO results (sample_test_id, result_value, result_text, is_within_spec, entered_by, remarks,
                        uncertainty, k_factor, confidence_interval, replicate_count, instrument_id, revision)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
                ");
                $stmt->execute([$sampleTestId, $value, $text, $isWithinSpecParam, Auth::id(), $remarks, $uncertainty, $kFactor, $confidenceInterval, $replicateCount, $instrumentId, $revision]);
                $resultId = (int)$stmt->fetchColumn();
            }

            // Update sample_test status
            SampleTest::updateStatus($sampleTestId, 'Completed');

            // Auto-create OOS record when result is out of specification
            if ($isWithinSpec === false) {
                $oosCheck = $db->prepare("SELECT id FROM oos_records WHERE sample_test_id = ? AND deleted_at IS NULL LIMIT 1");
                $oosCheck->execute([$sampleTestId]);
                if (!$oosCheck->fetch()) {
                    $oosNumber = 'OOS-' . date('Ymd') . '-' . $sampleTestId . '-' . random_int(100, 999);
                    $specRange = $spec['spec_limit_text'] ?: (($spec['min_spec_limit'] ?? '') . ' - ' . ($spec['max_spec_limit'] ?? ''));
                    $oosStmt = $db->prepare("
                        INSERT INTO oos_records (oos_number, sample_id, sample_test_id, result_id, test_parameter, specification_range, result_value, result_text, description, severity, status, initiated_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Major', 'Open', ?)
                    ");
                    $oosStmt->execute([
                        $oosNumber,
                        $spec['sample_id'] ?? null,
                        $sampleTestId,
                        $resultId,
                        $spec['test_name'] ?? null,
                        $specRange,
                        ($value !== null && $value !== '') ? (float)$value : null,
                        $text,
                        'Auto-generated OOS from out-of-specification result entry',
                        Auth::id(),
                    ]);
                }
            }

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

            // Phase 2: Statistical Out-of-Trend (OOT) Detection
            $ootMsg = '';
            if ($value !== null && $value !== '') {
                try {
                    $ootService = new \App\Services\OotDetectionService();
                    $violations = $ootService->evaluateResult($sampleTestId, (float)$value);
                    if (!empty($violations)) {
                        $ootMsg = ' [OOT Warning: ' . count($violations) . ' statistical trend rule(s) triggered]';
                    }
                } catch (\Throwable $ootEx) {
                    error_log('OOT evaluation error: ' . $ootEx->getMessage());
                }
            }

            session_flash('success', 'Result saved successfully.' . $ootMsg);
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

        $exceptionsOnly = !empty($_GET['exceptions_only']);
        $exceptionFilter = $exceptionsOnly
            ? " AND (r.is_within_spec = FALSE OR r.revision > 1 OR EXISTS (SELECT 1 FROM oot_incidents oi WHERE oi.sample_test_id = st.id AND oi.status = 'Open'))"
            : "";

        $stmt = $db->query("
            SELECT st.*, s.sample_code, t.test_name, t.test_code,
                   COALESCE(pt.min_spec_limit, t.min_spec_limit) AS min_spec_limit,
                   COALESCE(pt.max_spec_limit, t.max_spec_limit) AS max_spec_limit,
                   r.result_value, r.result_text, r.is_within_spec, r.id AS result_id,
                   r.uncertainty, r.k_factor, r.confidence_interval, r.replicate_count, r.revision,
                   u.full_name AS entered_by_name, ue.full_name AS assigned_to_name,
                   c.customer_name, p.product_name,
                   (SELECT COUNT(*) FROM oot_incidents oi WHERE oi.sample_test_id = st.id AND oi.status = 'Open') AS oot_count
            FROM sample_tests st
            JOIN samples s ON st.sample_id = s.id
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN products_tests pt ON pt.product_id = s.product_id AND pt.test_id = st.test_id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.deleted_at IS NULL AND r.revision = (
                SELECT MAX(r2.revision) FROM results r2 WHERE r2.sample_test_id = st.id AND r2.deleted_at IS NULL
            )
            LEFT JOIN users u ON r.entered_by = u.id
            LEFT JOIN users ue ON st.assigned_to = ue.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            WHERE st.status = 'Completed' AND st.deleted_at IS NULL AND s.deleted_at IS NULL {$exceptionFilter}
            ORDER BY s.priority DESC, r.entered_at ASC
        ");

        return $this->render('tests.review', [
            'tests' => $stmt->fetchAll(),
            'exceptionsOnly' => $exceptionsOnly
        ]);
    }

    public function approveResult(int $sampleTestId): void
    {
        Auth::requireAnyRole(['Admin', 'Reviewer']);
        $db = \App\Helpers\Database::connect();
        $action = $_POST['action'] ?? 'reject';
        $remarks = $_POST['remarks'] ?? '';

        $result = Result::findBySampleTest($sampleTestId);

        // Compliance Gate: Segregation of Duties (4-Eyes Principle)
        if ($action === 'approve' && $result && !Auth::verifySegregationOfDuties('review', $result)) {
            session_flash('error', 'Segregation of duties violation: An analyst cannot review their own test result (4-eyes principle).');
            redirect('/tests/review');
            return;
        }

        if ($action === 'approve') {
            SampleTest::updateStatus($sampleTestId, 'Reviewed');

            // Update result
            if ($result) {
                $stmt = $db->prepare("UPDATE results SET reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([Auth::id(), $result['id']]);
            }

            // Check if all tests are reviewed
            $stmt = $db->prepare("SELECT sample_id FROM sample_tests WHERE id = ?");
            $stmt->execute([$sampleTestId]);
            $sampleId = $stmt->fetchColumn();
            if ($sampleId) {
                $pending = $db->prepare("SELECT COUNT(*) FROM sample_tests WHERE sample_id = ? AND deleted_at IS NULL AND status NOT IN ('Reviewed', 'Approved')");
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
            SELECT st.*, s.sample_code, t.test_name, t.test_code,
                   COALESCE(pt.min_spec_limit, t.min_spec_limit) AS min_spec_limit,
                   COALESCE(pt.max_spec_limit, t.max_spec_limit) AS max_spec_limit,
                   r.result_value, r.result_text, r.is_within_spec, r.id AS result_id,
                   r.uncertainty, r.k_factor, r.confidence_interval, r.replicate_count,
                   u.full_name AS entered_by_name, ru.full_name AS reviewed_by_name,
                   c.customer_name, p.product_name
            FROM sample_tests st
            JOIN samples s ON st.sample_id = s.id
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN products_tests pt ON pt.product_id = s.product_id AND pt.test_id = st.test_id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.deleted_at IS NULL AND r.revision = (
                SELECT MAX(r2.revision) FROM results r2 WHERE r2.sample_test_id = st.id AND r2.deleted_at IS NULL
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

        $result = Result::findBySampleTest($sampleTestId);

        // Compliance Gate: Segregation of Duties (4-Eyes Principle)
        if ($action === 'approve' && $result && !Auth::verifySegregationOfDuties('approve', $result)) {
            session_flash('error', 'Segregation of duties violation: Approver cannot be the same user who entered or reviewed this result.');
            redirect('/tests/final-approval');
            return;
        }

        if ($action === 'approve') {
            SampleTest::updateStatus($sampleTestId, 'Approved');

            if ($result) {
                $stmt = $db->prepare("UPDATE results SET approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([Auth::id(), $result['id']]);
            }

            // Check if all tests are approved AND no open OOS records exist before promoting Sample to Approved
            $stmtSample = $db->prepare("SELECT sample_id FROM sample_tests WHERE id = ?");
            $stmtSample->execute([$sampleTestId]);
            $sampleId = $stmtSample->fetchColumn();

            if ($sampleId) {
                $checkOos = $db->prepare("SELECT COUNT(*) FROM oos_records WHERE sample_id = ? AND status != 'Closed' AND deleted_at IS NULL");
                $checkOos->execute([$sampleId]);
                $openOos = (int)$checkOos->fetchColumn();

                if ($openOos === 0) {
                    $stmt = $db->prepare("
                        UPDATE samples SET status = 'Approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                        AND NOT EXISTS (
                            SELECT 1 FROM sample_tests st2
                            WHERE st2.sample_id = samples.id AND st2.deleted_at IS NULL AND st2.status != 'Approved'
                        )
                        AND status != 'Approved'
                    ");
                    $stmt->execute([Auth::id(), $sampleId]);
                }
            }

            Audit::log('Result Final Approved', 'results', $result['id'] ?? null);
            session_flash('success', 'Result approved.');
        } else {
            SampleTest::updateStatus($sampleTestId, 'Completed');
            session_flash('warning', 'Result sent back for revision.');
        }

        redirect('/tests/final-approval');
    }
}
