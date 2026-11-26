<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Models\Product;
use App\Models\Analysis;
use App\Models\ProductTestPlan;
use App\Services\MasterData\ProductTestPlanService;
use PDO;

class ProductTestPlanController extends BaseController
{
    private ProductTestPlanService $planService;

    public function __construct()
    {
        $this->planService = new ProductTestPlanService();
    }

    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer', 'Analyst']);

        $db = Database::connect();
        $products = $db->query("SELECT * FROM products WHERE is_active = TRUE AND product_code NOT LIKE 'PROD-OOT-%' ORDER BY product_name")->fetchAll(PDO::FETCH_ASSOC);

        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        if ($productId <= 0 && !empty($products)) {
            $productId = (int)$products[0]['id'];
        }

        $selectedProduct = null;
        if ($productId > 0) {
            $pStmt = $db->prepare("SELECT * FROM products WHERE id = ?");
            $pStmt->execute([$productId]);
            $selectedProduct = $pStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $testPlans = $productId > 0 ? ProductTestPlan::findByProduct($productId) : [];
        $analyses = Analysis::all();

        return $this->render('product-test-plans.index', [
            'products' => $products,
            'selectedProduct' => $selectedProduct,
            'testPlans' => $testPlans,
            'analyses' => $analyses,
            'stages' => ['Raw Material', 'In-Process', 'Finished Good', 'Stability', 'Release', 'Packaging']
        ]);
    }

    public function assign(): void
    {
        Auth::requireRole('Admin');

        $productId = (int)($_POST['product_id'] ?? 0);
        $stage = trim($_POST['stage'] ?? 'Finished Good');
        $analysisId = (int)($_POST['analysis_id'] ?? 0);
        $isMandatory = !empty($_POST['is_mandatory']);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($productId <= 0 || $analysisId <= 0) {
            session_flash('error', 'Product and analysis are required.');
            redirect("/product-test-plans?product_id={$productId}");
            return;
        }

        try {
            $this->planService->assignAnalysisToStage($productId, $stage, $analysisId, $isMandatory, $sortOrder);
            session_flash('success', "Analysis added to {$stage} test plan.");
        } catch (\Throwable $e) {
            session_flash('error', 'Error assigning analysis: ' . $e->getMessage());
        }

        redirect("/product-test-plans?product_id={$productId}");
    }

    public function remove(): void
    {
        Auth::requireRole('Admin');

        $productId = (int)($_POST['product_id'] ?? 0);
        $stage = trim($_POST['stage'] ?? '');
        $analysisId = (int)($_POST['analysis_id'] ?? 0);

        if ($productId > 0 && $analysisId > 0 && !empty($stage)) {
            $this->planService->removeAnalysisFromStage($productId, $stage, $analysisId);
            session_flash('success', 'Analysis removed from test plan.');
        }

        redirect("/product-test-plans?product_id={$productId}");
    }

    /**
     * API endpoint to dynamically resolve applicable analyses & frozen spec limits for sample creation
     */
    public function resolvePlan(int $productId): string
    {
        $stage = trim($_GET['stage'] ?? 'Finished Good');
        $effectiveDate = !empty($_GET['date']) ? trim($_GET['date']) : null;

        $plan = $this->planService->resolveAnalysesAndLimitsForSample($productId, $stage, $effectiveDate);

        return $this->json([
            'success' => true,
            'data' => $plan
        ]);
    }
}
