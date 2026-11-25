<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Services\UncertaintyBudgetService;
use PDO;

class UncertaintyController extends BaseController
{
    private UncertaintyBudgetService $service;

    public function __construct()
    {
        $this->service = new UncertaintyBudgetService();
    }

    public function index(): void
    {
        Auth::requirePermission('master.methods');

        $db = Database::connect();
        $budgets = $db->query(
            "SELECT ub.*, t.test_name, t.test_code,
                    (SELECT COUNT(*) FROM uncertainty_components uc WHERE uc.budget_id = ub.id) as component_count
             FROM uncertainty_budgets ub
             JOIN tests t ON ub.test_id = t.id
             ORDER BY t.test_name, ub.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $tests = $db->query("SELECT id, test_code, test_name FROM tests WHERE is_active = TRUE ORDER BY test_name")->fetchAll(PDO::FETCH_ASSOC);

        $this->view('quality/uncertainty/index', [
            'budgets' => $budgets,
            'tests' => $tests
        ]);
    }

    public function calculate(): void
    {
        Auth::requirePermission('master.methods');

        $rawComponents = $_POST['components'] ?? [];
        if (is_string($rawComponents)) {
            $rawComponents = json_decode($rawComponents, true) ?: [];
        }
        $k = (float)($_POST['k_factor'] ?? 2.0);

        try {
            $result = $this->service->calculateBudget($rawComponents, $k);
            $this->json(['success' => true, 'budget' => $result]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function store(): void
    {
        Auth::requirePermission('master.methods');

        $testId = (int)($_POST['test_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $unit = trim((string)($_POST['unit'] ?? '%'));
        $k = (float)($_POST['k_factor'] ?? 2.0);
        $rawComponents = $_POST['components'] ?? [];
        if (is_string($rawComponents)) {
            $rawComponents = json_decode($rawComponents, true) ?: [];
        }

        if (empty($name) || $testId <= 0 || empty($rawComponents)) {
            $this->redirectWithError('/uncertainty', 'Test, budget name, and at least one uncertainty component are required.');
            return;
        }

        try {
            $budgetId = $this->service->saveBudget($testId, $name, $unit, $rawComponents, $k);

            if ($this->isJsonRequest()) {
                $this->json(['success' => true, 'budget_id' => $budgetId]);
                return;
            }

            $_SESSION['flash_success'] = "Uncertainty Budget {$name} saved successfully.";
            $this->redirect('/uncertainty');
        } catch (\Throwable $e) {
            $this->redirectWithError('/uncertainty', $e->getMessage());
        }
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
