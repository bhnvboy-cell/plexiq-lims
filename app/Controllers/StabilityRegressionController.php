<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Services\StabilityRegressionService;
use PDO;

class StabilityRegressionController extends BaseController
{
    private StabilityRegressionService $service;

    public function __construct()
    {
        $this->service = new StabilityRegressionService();
    }

    public function index(): string
    {
        Auth::requirePermission('quality.manage');

        $db = Database::connect();
        $studies = $db->query(
            "SELECT ss.*, p.product_name, p.product_code
             FROM stability_studies ss
             LEFT JOIN products p ON ss.product_id = p.id
             ORDER BY ss.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('quality.stability.regression', [
            'studies' => $studies
        ]);
    }

    public function evaluate(): void
    {
        Auth::requirePermission('quality.manage');

        $rawData = $_POST['data'] ?? [];
        if (is_string($rawData)) {
            $rawData = json_decode($rawData, true) ?: [];
        }
        $lowerSpec = isset($_POST['lower_spec_limit']) && $_POST['lower_spec_limit'] !== '' ? (float)$_POST['lower_spec_limit'] : null;
        $upperSpec = isset($_POST['upper_spec_limit']) && $_POST['upper_spec_limit'] !== '' ? (float)$_POST['upper_spec_limit'] : null;

        try {
            $res = $this->service->evaluateStability($rawData, $lowerSpec, $upperSpec);
            $this->json(['success' => true, 'regression' => $res]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    private function isJsonRequest(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
            || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }
}
