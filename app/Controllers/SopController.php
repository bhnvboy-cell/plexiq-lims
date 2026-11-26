<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Audit;
use PDO;

class SopController extends BaseController
{
    public function index(): string
    {
        Auth::requirePermission('master.methods');

        $db = Database::connect();
        $sops = $db->query(
            "SELECT s.*, u.full_name as author_name,
                    (SELECT COUNT(*) FROM sop_controlled_copies c WHERE c.sop_id = s.id AND c.is_recalled = FALSE) as active_copies
             FROM sops s
             LEFT JOIN users u ON s.author_id = u.id
             ORDER BY s.sop_number"
        )->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('quality.sops.index', [
            'sops' => $sops
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('master.methods');

        $sopNumber = trim((string)($_POST['sop_number'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $version = trim((string)($_POST['version'] ?? '1.0'));
        $effectiveDate = !empty($_POST['effective_date']) ? $_POST['effective_date'] : date('Y-m-d');
        $reviewDueDate = !empty($_POST['review_due_date']) ? $_POST['review_due_date'] : date('Y-m-d', strtotime('+1 year'));

        if (empty($sopNumber) || empty($title)) {
            $this->redirectWithError('/sops', 'SOP Number and Title are required.');
            return;
        }

        $db = Database::connect();
        $userId = Auth::id() ?? 1;

        $stmt = $db->prepare(
            "INSERT INTO sops (sop_number, title, version, effective_date, review_due_date, author_id, status)
             VALUES (?, ?, ?, ?, ?, ?, 'Approved') RETURNING id"
        );
        $stmt->execute([
            $sopNumber,
            $title,
            $version,
            $effectiveDate,
            $reviewDueDate,
            $userId
        ]);
        $id = (int)$stmt->fetchColumn();

        Audit::log('Create SOP', 'sops', $id, null, ['sop_number' => $sopNumber, 'version' => $version], "Registered SOP {$sopNumber} v{$version}");

        if ($this->isJsonRequest()) {
            $this->json(['success' => true, 'id' => $id, 'sop_number' => $sopNumber]);
            return;
        }

        $_SESSION['flash_success'] = "SOP {$sopNumber} created successfully.";
        $this->redirect('/sops');
    }

    public function issueCopy(int $id): void
    {
        Auth::requirePermission('master.methods');

        $userId = (int)($_POST['issued_to'] ?? 0);
        if ($userId <= 0) {
            $this->redirectWithError('/sops', 'Select an analyst to issue controlled copy.');
            return;
        }

        $db = Database::connect();
        $copyNum = (int)$db->query("SELECT COALESCE(MAX(copy_number), 0) + 1 FROM sop_controlled_copies WHERE sop_id = {$id}")->fetchColumn();

        $stmt = $db->prepare(
            "INSERT INTO sop_controlled_copies (sop_id, copy_number, issued_to)
             VALUES (?, ?, ?) RETURNING id"
        );
        $stmt->execute([$id, $copyNum, $userId]);
        $copyId = (int)$stmt->fetchColumn();

        Audit::log('Issue Controlled Copy', 'sop_controlled_copies', $copyId, null, ['sop_id' => $id, 'copy_number' => $copyNum, 'issued_to' => $userId], "Issued controlled copy #{$copyNum} of SOP #{$id} to User #{$userId}");

        $_SESSION['flash_success'] = "Controlled Copy #{$copyNum} issued to analyst.";
        $this->redirect('/sops');
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
