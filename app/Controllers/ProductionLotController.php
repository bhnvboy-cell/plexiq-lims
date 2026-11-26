<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Models\ProductionLot;
use App\Services\Batches\ProductionLotService;
use PDO;

class ProductionLotController extends BaseController
{
    private ProductionLotService $lotService;

    public function __construct()
    {
        $this->lotService = new ProductionLotService();
    }

    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer', 'Analyst']);

        $filters = [];
        if (!empty($_GET['disposition'])) $filters['disposition'] = $_GET['disposition'];
        if (!empty($_GET['product_id'])) $filters['product_id'] = (int)$_GET['product_id'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        $lots = ProductionLot::allWithRelations($filters);
        $db = Database::connect();
        $products = $db->query("SELECT id, product_code, product_name FROM products WHERE is_active = TRUE AND product_code NOT LIKE 'PROD-OOT-%' ORDER BY product_name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('production-lots.index', [
            'lots' => $lots,
            'products' => $products,
            'filters' => $filters,
            'dispositions' => ['Pending', 'Released', 'On Hold', 'Rejected', 'Conditionally Released']
        ]);
    }

    public function create(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Approver']);

        $db = Database::connect();
        $products = $db->query("SELECT id, product_code, product_name FROM products WHERE is_active = TRUE AND product_code NOT LIKE 'PROD-OOT-%' ORDER BY product_name")->fetchAll(PDO::FETCH_ASSOC);
        $sites = $db->query("SELECT id, name FROM sites WHERE is_active = TRUE ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('production-lots.create', [
            'products' => $products,
            'sites' => $sites
        ]);
    }

    public function store(): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Approver']);

        try {
            $res = $this->lotService->createLot($_POST, Auth::id() ?? 1);
            session_flash('success', "Production Lot '{$res['lot_number']}' created successfully.");
            redirect('/production-lots/' . $res['lot_id']);
        } catch (\Throwable $e) {
            session_flash('error', 'Error creating production lot: ' . $e->getMessage());
            redirect('/production-lots/create');
        }
    }

    public function show(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer', 'Analyst']);

        $lot = ProductionLot::findWithRelations($id);
        if (!$lot) {
            session_flash('error', 'Production lot not found.');
            redirect('/production-lots');
        }

        $readiness = $this->lotService->evaluateLotReadiness($id);

        return $this->render('production-lots.show', [
            'lot' => $lot,
            'readiness' => $readiness,
            'samples' => $lot['samples'] ?? [],
            'oosRecords' => $lot['oos_records'] ?? []
        ]);
    }

    public function setDisposition(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);

        $disposition = trim($_POST['disposition'] ?? '');
        $reason = trim($_POST['disposition_reason'] ?? '');
        $password = $_POST['signature_password'] ?? null;

        if (empty($disposition) || empty($reason)) {
            session_flash('error', 'Disposition and mandatory justification reason are required.');
            redirect('/production-lots/' . $id);
            return;
        }

        try {
            $res = $this->lotService->setDisposition($id, $disposition, $reason, Auth::id() ?? 1, $password);
            session_flash('success', "Lot disposition updated to '{$disposition}' with 21 CFR Part 11 electronic signature.");
        } catch (\Throwable $e) {
            session_flash('error', 'Disposition failed: ' . $e->getMessage());
        }

        redirect('/production-lots/' . $id);
    }

    public function syncErp(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);

        $connector = $_POST['connector'] ?? 'sap';

        try {
            $res = $this->lotService->syncLotToErp($id, $connector);
            if ($res['success']) {
                session_flash('success', 'Production lot disposition successfully posted to ERP.');
            } else {
                session_flash('warning', 'ERP sync queued or pending retry: ' . ($res['error'] ?? 'Unknown error'));
            }
        } catch (\Throwable $e) {
            session_flash('error', 'ERP sync error: ' . $e->getMessage());
        }

        redirect('/production-lots/' . $id);
    }

    public function releaseReport(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer', 'Analyst']);

        $lot = ProductionLot::findWithRelations($id);
        if (!$lot) {
            session_flash('error', 'Production lot not found.');
            redirect('/production-lots');
        }

        $readiness = $this->lotService->evaluateLotReadiness($id);

        return $this->render('production-lots.release-report', [
            'lot' => $lot,
            'readiness' => $readiness,
            'samples' => $lot['samples'] ?? []
        ]);
    }
}
