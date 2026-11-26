<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Audit;
use PDO;

class ChangeControlController extends BaseController
{
    public function index(): string
    {
        Auth::requirePermission('quality.manage');

        $db = Database::connect();
        $changes = $db->query(
            "SELECT cc.*, u.full_name as proposed_by_name, p.full_name as pre_approved_by_name
             FROM change_controls cc
             LEFT JOIN users u ON cc.proposed_by = u.id
             LEFT JOIN users p ON cc.pre_approved_by = p.id
             ORDER BY cc.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('quality.change-controls.index', [
            'changes' => $changes
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('quality.manage');

        $title = trim((string)($_POST['title'] ?? ''));
        $changeType = $_POST['change_type'] ?? 'Method';
        $reason = trim((string)($_POST['reason_for_change'] ?? ''));
        $impact = trim((string)($_POST['impact_assessment'] ?? ''));

        if (empty($title) || empty($reason) || empty($impact)) {
            $this->redirectWithError('/change-controls', 'Title, reason for change, and impact assessment are mandatory.');
            return;
        }

        $db = Database::connect();
        $userId = Auth::id() ?? 1;
        $changeNumber = 'CC-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));

        $stmt = $db->prepare(
            "INSERT INTO change_controls (
                change_number, title, change_type, reason_for_change, impact_assessment, proposed_by, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'Submitted') RETURNING id"
        );
        $stmt->execute([
            $changeNumber,
            $title,
            $changeType,
            $reason,
            $impact,
            $userId
        ]);
        $id = (int)$stmt->fetchColumn();

        Audit::log(
            'Create Change Control',
            'change_controls',
            $id,
            null,
            ['change_number' => $changeNumber, 'title' => $title, 'type' => $changeType],
            "Proposed change control {$changeNumber}: {$title}"
        );

        if ($this->isJsonRequest()) {
            $this->json(['success' => true, 'id' => $id, 'change_number' => $changeNumber]);
            return;
        }

        $_SESSION['flash_success'] = "Change Control {$changeNumber} submitted.";
        $this->redirect('/change-controls');
    }

    public function preApprove(int $id): void
    {
        Auth::requirePermission('quality.manage');

        $db = Database::connect();
        $userId = Auth::id() ?? 1;

        $stmt = $db->prepare("UPDATE change_controls SET status = 'Pre-Approved', pre_approved_by = ?, pre_approved_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$userId, $id]);

        Audit::log('Pre-Approve Change Control', 'change_controls', $id, null, ['status' => 'Pre-Approved'], "Pre-approved change control #{$id}");

        $_SESSION['flash_success'] = "Change Control #{$id} pre-approved.";
        $this->redirect('/change-controls');
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
