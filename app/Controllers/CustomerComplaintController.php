<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Audit;
use PDO;

class CustomerComplaintController extends BaseController
{
    public function index(): string
    {
        Auth::requirePermission('quality.manage');

        $db = Database::connect();
        $complaints = $db->query(
            "SELECT cc.*, c.customer_name, s.sample_code, u.full_name as assigned_to_name
             FROM customer_complaints cc
             LEFT JOIN customers c ON cc.customer_id = c.id
             LEFT JOIN samples s ON cc.sample_id = s.id
             LEFT JOIN users u ON cc.assigned_to = u.id
             ORDER BY cc.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $customers = $db->query("SELECT id, customer_code, customer_name FROM customers WHERE is_active = TRUE ORDER BY customer_name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('quality.complaints.index', [
            'complaints' => $complaints,
            'customers' => $customers
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('quality.manage');

        $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $batch = trim((string)($_POST['batch_number'] ?? ''));
        $type = $_POST['complaint_type'] ?? 'OOS_Result';
        $risk = $_POST['risk_level'] ?? 'Medium';
        $desc = trim((string)($_POST['description'] ?? ''));

        if (empty($desc)) {
            $this->redirectWithError('/complaints', 'Complaint description is mandatory.');
            return;
        }

        $db = Database::connect();
        $complaintNumber = 'CMP-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));

        $stmt = $db->prepare(
            "INSERT INTO customer_complaints (
                complaint_number, customer_id, batch_number, complaint_type, risk_level, description, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'Open') RETURNING id"
        );
        $stmt->execute([
            $complaintNumber,
            $customerId,
            $batch,
            $type,
            $risk,
            $desc
        ]);
        $id = (int)$stmt->fetchColumn();

        Audit::log(
            'Create Customer Complaint',
            'customer_complaints',
            $id,
            null,
            ['complaint_number' => $complaintNumber, 'risk' => $risk, 'type' => $type],
            "Logged complaint {$complaintNumber}"
        );

        if ($this->isJsonRequest()) {
            $this->json(['success' => true, 'id' => $id, 'complaint_number' => $complaintNumber]);
            return;
        }

        $_SESSION['flash_success'] = "Complaint {$complaintNumber} registered.";
        $this->redirect('/complaints');
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
