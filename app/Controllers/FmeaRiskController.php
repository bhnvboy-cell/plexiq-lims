<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Audit;
use PDO;

class FmeaRiskController extends BaseController
{
    public function index(): string
    {
        Auth::requirePermission('quality.manage');

        $db = Database::connect();
        $assessments = $db->query(
            "SELECT f.*, u.full_name as assessed_by_name
             FROM fmea_risk_assessments f
             LEFT JOIN users u ON f.assessed_by = u.id
             ORDER BY f.rpn DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('quality.fmea.index', [
            'assessments' => $assessments
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('quality.manage');

        $entityType = $_POST['entity_type'] ?? 'method';
        $entityId = (int)($_POST['entity_id'] ?? 1);
        $step = trim((string)($_POST['process_step'] ?? ''));
        $mode = trim((string)($_POST['failure_mode'] ?? ''));
        $effect = trim((string)($_POST['failure_effect'] ?? ''));
        $cause = trim((string)($_POST['failure_cause'] ?? ''));
        $s = max(1, min(10, (int)($_POST['severity'] ?? 5)));
        $o = max(1, min(10, (int)($_POST['occurrence'] ?? 5)));
        $d = max(1, min(10, (int)($_POST['detection'] ?? 5)));
        $mitigation = trim((string)($_POST['mitigation_plan'] ?? ''));

        if (empty($mode) || empty($effect) || empty($cause)) {
            $this->redirectWithError('/fmea', 'Failure mode, effect, and root cause are required.');
            return;
        }

        $db = Database::connect();
        $userId = Auth::id() ?? 1;

        $stmt = $db->prepare(
            "INSERT INTO fmea_risk_assessments (
                entity_type, entity_id, process_step, failure_mode, failure_effect, failure_cause,
                severity, occurrence, detection, mitigation_plan, assessed_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id, rpn"
        );
        $stmt->execute([
            $entityType,
            $entityId,
            $step,
            $mode,
            $effect,
            $cause,
            $s,
            $o,
            $d,
            $mitigation,
            $userId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        Audit::log(
            'Create FMEA Assessment',
            'fmea_risk_assessments',
            (int)$row['id'],
            null,
            ['rpn' => $row['rpn'], 'severity' => $s, 'occurrence' => $o, 'detection' => $d],
            "Created FMEA assessment for {$entityType} #{$entityId} (RPN: {$row['rpn']})"
        );

        if ($this->isJsonRequest()) {
            $this->json(['success' => true, 'id' => $row['id'], 'rpn' => $row['rpn']]);
            return;
        }

        $_SESSION['flash_success'] = "FMEA Assessment recorded with Initial RPN = {$row['rpn']}.";
        $this->redirect('/fmea');
    }

    private function isJsonRequest(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
            || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }

    private function redirectWithError(string $url, string $msg): void
    {
        if ($this->isJsonRequest()) {
            $this->json(['error' => $msg], 400);
            return;
        }
        $_SESSION['flash_error'] = $msg;
        $this->redirect($url);
    }
}
