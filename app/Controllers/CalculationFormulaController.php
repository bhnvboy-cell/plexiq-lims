<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Audit;
use App\Services\CalculationEngine;
use PDO;

class CalculationFormulaController extends BaseController
{
    private CalculationEngine $engine;

    public function __construct()
    {
        $this->engine = new CalculationEngine();
    }

    public function index(): string
    {
        Auth::requirePermission('master.methods');

        $db = Database::connect();
        $formulas = $db->query(
            "SELECT cf.*, t.test_name, t.test_code
             FROM calculation_formulas cf
             JOIN tests t ON cf.test_id = t.id
             ORDER BY t.test_name, cf.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $tests = $db->query("SELECT id, test_code, test_name FROM tests WHERE is_active = TRUE ORDER BY test_name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('formulas.index', [
            'formulas' => $formulas,
            'tests' => $tests
        ]);
    }

    public function store(): void
    {
        Auth::requirePermission('master.methods');

        $testId = (int)($_POST['test_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $expression = trim((string)($_POST['expression'] ?? ''));
        $sigFigs = (int)($_POST['sig_figs'] ?? 3);
        $roundingMode = in_array($_POST['rounding_mode'] ?? '', ['half_even', 'half_up', 'truncate'], true)
            ? $_POST['rounding_mode']
            : 'half_even';

        $rawVars = $_POST['variables'] ?? '[]';
        $variables = is_string($rawVars) ? json_decode($rawVars, true) : $rawVars;

        if (empty($name) || empty($expression) || $testId <= 0) {
            $this->redirectWithError('/formulas', 'Test, formula name, and mathematical expression are required.');
            return;
        }

        $db = Database::connect();
        $userId = Auth::id() ?? 1;

        $stmt = $db->prepare(
            "INSERT INTO calculation_formulas (
                test_id, name, expression, variables, sig_figs, rounding_mode, created_by
            ) VALUES (?, ?, ?, ?::jsonb, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([
            $testId,
            $name,
            $expression,
            json_encode($variables ?: []),
            $sigFigs,
            $roundingMode,
            $userId
        ]);
        $id = (int)$stmt->fetchColumn();

        Audit::log(
            'Create Calculation Formula',
            'calculation_formulas',
            $id,
            null,
            ['test_id' => $testId, 'name' => $name, 'expression' => $expression],
            "Created formula {$name} for test #{$testId}"
        );

        if ($this->isJsonRequest()) {
            $this->json(['success' => true, 'id' => $id]);
            return;
        }

        $_SESSION['flash_success'] = "Calculation formula '{$name}' created.";
        $this->redirect('/formulas');
    }

    public function testEval(): void
    {
        Auth::requirePermission('master.methods');

        $expression = trim((string)($_POST['expression'] ?? ''));
        $variables = $_POST['variables'] ?? [];
        $sigFigs = isset($_POST['sig_figs']) ? (int)$_POST['sig_figs'] : null;
        $roundingMode = $_POST['rounding_mode'] ?? 'half_even';

        if (is_string($variables)) {
            $variables = json_decode($variables, true) ?: [];
        }

        try {
            $res = $this->engine->evaluate($expression, $variables, $sigFigs, $roundingMode);
            $this->json([
                'success' => true,
                'result' => $res,
                'expression' => $expression,
                'variables' => $variables
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
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
