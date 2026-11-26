<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Models\Product;
use App\Models\Specification;
use App\Models\SpecificationVersion;
use App\Models\Component;
use App\Services\MasterData\SpecificationService;
use PDO;

class SpecificationController extends BaseController
{
    private SpecificationService $specService;

    public function __construct()
    {
        $this->specService = new SpecificationService();
    }

    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer', 'Analyst']);

        $db = Database::connect();
        $specs = $db->query("
            SELECT s.*, p.product_code, p.product_name, c.customer_name,
                   (SELECT COUNT(*) FROM specification_versions sv WHERE sv.specification_id = s.id) as version_count,
                   (SELECT sv.version_number FROM specification_versions sv
                    WHERE sv.specification_id = s.id AND sv.status = 'Approved'
                    ORDER BY sv.version_number DESC LIMIT 1) as active_version
            FROM specifications s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY s.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $products = $db->query("SELECT * FROM products WHERE is_active = TRUE ORDER BY product_name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('specifications.index', [
            'specifications' => $specs,
            'products' => $products
        ]);
    }

    public function show(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer', 'Analyst']);

        $spec = Specification::findWithDetails($id);
        if (!$spec) {
            session_flash('error', 'Specification not found.');
            redirect('/specifications');
        }

        // Selected version (default to latest or requested)
        $selectedVerNum = isset($_GET['version']) ? (int)$_GET['version'] : null;
        $version = null;

        if (!empty($spec['versions'])) {
            $targetVer = $spec['versions'][0];
            if ($selectedVerNum !== null) {
                foreach ($spec['versions'] as $v) {
                    if ((int)$v['version_number'] === $selectedVerNum) {
                        $targetVer = $v;
                        break;
                    }
                }
            }
            $version = SpecificationVersion::findWithLimits((int)$targetVer['id']);
        }

        // Available components for adding limits to draft versions
        $components = Component::all();

        return $this->render('specifications.show', [
            'specification' => $spec,
            'version' => $version,
            'components' => $components
        ]);
    }

    public function create(): string
    {
        Auth::requireRole('Admin');
        $db = Database::connect();
        $products = $db->query("SELECT * FROM products WHERE is_active = TRUE ORDER BY product_name")->fetchAll(PDO::FETCH_ASSOC);
        $customers = $db->query("SELECT id, customer_name FROM customers ORDER BY customer_name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('specifications.create', [
            'products' => $products,
            'customers' => $customers
        ]);
    }

    public function store(): void
    {
        Auth::requireRole('Admin');

        try {
            $specId = $this->specService->createSpecification([
                'spec_code' => $_POST['spec_code'] ?? '',
                'product_id' => $_POST['product_id'] ?? 0,
                'grade' => $_POST['grade'] ?? 'Standard',
                'customer_id' => $_POST['customer_id'] ?? null,
                'effective_from' => $_POST['effective_from'] ?? date('Y-m-d')
            ], Auth::id() ?? 1);

            session_flash('success', "Specification created successfully with Draft Version 1.");
            redirect("/specifications/{$specId}");
        } catch (\Throwable $e) {
            session_flash('error', 'Error creating specification: ' . $e->getMessage());
            redirect('/specifications/create');
        }
    }

    public function createDraft(int $specId): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);

        $cloneFrom = !empty($_POST['clone_from_version_id']) ? (int)$_POST['clone_from_version_id'] : null;
        $effectiveFrom = $_POST['effective_from'] ?? date('Y-m-d');
        $notes = $_POST['notes'] ?? 'Next revision draft';

        try {
            $verId = $this->specService->createDraftVersion($specId, [
                'effective_from' => $effectiveFrom,
                'notes' => $notes
            ], $cloneFrom, Auth::id() ?? 1);

            session_flash('success', "Draft Version created successfully.");
            redirect("/specifications/{$specId}");
        } catch (\Throwable $e) {
            session_flash('error', 'Error creating draft: ' . $e->getMessage());
            redirect("/specifications/{$specId}");
        }
    }

    public function approve(int $versionId): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);

        $reason = trim($_POST['signature_reason'] ?? 'Routine approval of analytical specification version');
        if (empty($reason)) {
            session_flash('error', 'Electronic signature reason is required under 21 CFR Part 11.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/specifications');
            return;
        }

        try {
            $res = $this->specService->approveVersion($versionId, Auth::id() ?? 1, $reason);
            session_flash('success', "Specification Version {$versionId} approved and cryptographically locked (Signature: " . substr($res['signature_hash'], 0, 12) . "...).");
            redirect($_SERVER['HTTP_REFERER'] ?? '/specifications');
        } catch (\Throwable $e) {
            session_flash('error', 'Approval failed: ' . $e->getMessage());
            redirect($_SERVER['HTTP_REFERER'] ?? '/specifications');
        }
    }

    public function addLimit(int $versionId): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);

        $componentId = (int)($_POST['component_id'] ?? 0);
        if ($componentId <= 0) {
            session_flash('error', 'Component selection is required.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/specifications');
            return;
        }

        try {
            $this->specService->addOrUpdateLimit($versionId, $componentId, [
                'limit_type' => $_POST['limit_type'] ?? 'range',
                'min_limit' => $_POST['min_limit'] ?? null,
                'max_limit' => $_POST['max_limit'] ?? null,
                'target_value' => $_POST['target_value'] ?? null,
                'text_match_expected' => $_POST['text_match_expected'] ?? null,
                'warn_min' => $_POST['warn_min'] ?? null,
                'warn_max' => $_POST['warn_max'] ?? null,
                'display_specification' => $_POST['display_specification'] ?? '',
                'oot_rule' => $_POST['oot_rule'] ?? null,
                'sort_order' => (int)($_POST['sort_order'] ?? 0)
            ], Auth::id() ?? 1);

            session_flash('success', 'Specification limit updated successfully.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/specifications');
        } catch (\Throwable $e) {
            session_flash('error', 'Error adding limit: ' . $e->getMessage());
            redirect($_SERVER['HTTP_REFERER'] ?? '/specifications');
        }
    }
}
