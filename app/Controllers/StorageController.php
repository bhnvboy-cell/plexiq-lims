<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Services\StorageManagementService;
use PDO;

class StorageController extends BaseController
{
    private StorageManagementService $service;

    public function __construct()
    {
        $this->service = new StorageManagementService();
    }

    public function index(): void
    {
        Auth::requirePermission('inventory.view');

        $db = Database::connect();
        $locations = $db->query(
            "SELECT l.*, p.name as parent_name,
                    (SELECT COUNT(*) FROM sample_storage_assignments a WHERE a.storage_location_id = l.id AND a.status = 'Stored') as occupied_count
             FROM storage_locations l
             LEFT JOIN storage_locations p ON l.parent_id = p.id
             ORDER BY l.location_type, l.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->view('storage/index', [
            'locations' => $locations
        ]);
    }

    public function box(int $id): void
    {
        Auth::requirePermission('inventory.view');

        try {
            $data = $this->service->getBoxMatrix($id);
            if ($this->isJsonRequest()) {
                $this->json(['success' => true, 'data' => $data]);
                return;
            }

            $this->view('storage/box', $data);
        } catch (\Throwable $e) {
            if ($this->isJsonRequest()) {
                $this->json(['error' => $e->getMessage()], 404);
                return;
            }
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect('/storage');
        }
    }

    public function checkIn(): void
    {
        Auth::requirePermission('inventory.manage');

        $sampleId = (int)($_POST['sample_id'] ?? 0);
        $locationId = (int)($_POST['storage_location_id'] ?? 0);
        $row = (int)($_POST['row'] ?? 1);
        $col = (int)($_POST['col'] ?? 1);

        try {
            $res = $this->service->checkIn($sampleId, $locationId, $row, $col);

            if ($this->isJsonRequest()) {
                $this->json(['success' => true, 'assignment' => $res]);
                return;
            }

            $_SESSION['flash_success'] = "Sample assigned to position {$res['position_label']} successfully.";
            $this->redirect("/storage/box/{$locationId}");
        } catch (\Throwable $e) {
            if ($this->isJsonRequest()) {
                $this->json(['error' => $e->getMessage()], 400);
                return;
            }
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect("/storage/box/{$locationId}");
        }
    }

    public function checkOut(): void
    {
        Auth::requirePermission('inventory.manage');

        $sampleId = (int)($_POST['sample_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (empty($reason)) {
            if ($this->isJsonRequest()) {
                $this->json(['error' => 'Reason for check-out is required.'], 400);
                return;
            }
            $_SESSION['flash_error'] = 'Reason for check-out is mandatory.';
            $this->redirect('/storage');
            return;
        }

        try {
            $this->service->checkOut($sampleId, $reason);

            if ($this->isJsonRequest()) {
                $this->json(['success' => true, 'message' => "Sample #{$sampleId} checked out."]);
                return;
            }

            $_SESSION['flash_success'] = "Sample #{$sampleId} checked out successfully.";
            $this->redirect('/storage');
        } catch (\Throwable $e) {
            if ($this->isJsonRequest()) {
                $this->json(['error' => $e->getMessage()], 400);
                return;
            }
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect('/storage');
        }
    }

    private function isJsonRequest(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
            || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }
}
