<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Database;
use App\Helpers\Auth;
use App\Services\DynamicForms\FormSchemaService;
use App\Services\DynamicForms\PromotionPackageService;

class DynamicFormController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = Database::connect();
        $schemas = $db->query("SELECT * FROM dynamic_form_schemas ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC);
        $promotions = $db->query("SELECT * FROM environment_promotions ORDER BY id DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('forms.index', [
            'schemas' => $schemas,
            'promotions' => $promotions
        ]);
    }

    public function store(): string
    {
        Auth::requireRole('Admin');
        $service = new FormSchemaService();

        try {
            $data = [
                'schema_code' => $_POST['schema_code'] ?? '',
                'name' => $_POST['name'] ?? '',
                'entity_type' => $_POST['entity_type'] ?? 'sample',
                'status' => $_POST['status'] ?? 'draft',
                'fields_schema' => json_decode($_POST['fields_schema'] ?? '[]', true) ?: [],
                'layout_schema' => json_decode($_POST['layout_schema'] ?? '{"columns": 2}', true) ?: ['columns' => 2],
                'validation_rules' => json_decode($_POST['validation_rules'] ?? '{}', true) ?: []
            ];

            $result = $service->saveSchema($data, Auth::id());
            return $this->json(['success' => true, 'schema' => $result]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function promote(): string
    {
        Auth::requireRole('Admin');
        $sourceEnv = $_POST['source_env'] ?? 'DEV';
        $targetEnv = $_POST['target_env'] ?? 'UAT';
        $schemaId = (int)($_POST['schema_id'] ?? 0);

        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM dynamic_form_schemas WHERE id = ?");
        $stmt->execute([$schemaId]);
        $schema = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$schema) {
            return $this->json(['success' => false, 'error' => 'Schema not found.'], 404);
        }

        $service = new PromotionPackageService();
        $package = $service->createPackage($sourceEnv, $targetEnv, [
            'schemas' => [$schema]
        ], Auth::id());

        return $this->json(['success' => true, 'package' => $package]);
    }

    public function applyPromotion(int $packageId): string
    {
        Auth::requireRole('Admin');
        $service = new PromotionPackageService();
        $result = $service->applyPackage($packageId, Auth::id());

        return $this->json($result, $result['success'] ? 200 : 400);
    }
}
