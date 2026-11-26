<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Audit;
use PDO;

class OotIncidentController extends BaseController
{
    public function index(): string
    {
        Auth::requirePermission('results.review');

        $db = Database::connect();
        $incidents = $db->query(
            "SELECT o.*, st.test_id, t.test_name, s.sample_code, s.batch_number, p.product_name
             FROM oot_incidents o
             JOIN sample_tests st ON o.sample_test_id = st.id
             JOIN tests t ON st.test_id = t.id
             JOIN samples s ON st.sample_id = s.id
             LEFT JOIN products p ON s.product_id = p.id
             ORDER BY o.flagged_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('oot.index', [
            'incidents' => $incidents
        ]);
    }

    public function review(int $id): void
    {
        Auth::requirePermission('results.review');

        $status = $_POST['status'] ?? 'Under Review';
        $notes = trim((string)($_POST['investigation_notes'] ?? ''));

        if (!in_array($status, ['Open', 'Under Review', 'Closed', 'Escalated_To_CAPA'], true)) {
            $this->redirectWithError('/oot', 'Invalid incident status.');
            return;
        }

        $db = Database::connect();
        $stmt = $db->prepare('UPDATE oot_incidents SET status = ?, investigation_notes = ? WHERE id = ?');
        $stmt->execute([$status, $notes, $id]);

        Audit::log(
            'Review OOT Incident',
            'oot_incidents',
            $id,
            null,
            ['status' => $status, 'notes' => $notes],
            "Updated OOT incident #{$id} status to {$status}"
        );

        if ($this->isJsonRequest()) {
            $this->json(['success' => true, 'id' => $id, 'status' => $status]);
            return;
        }

        $_SESSION['flash_success'] = "OOT Incident #{$id} updated.";
        $this->redirect('/oot');
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
