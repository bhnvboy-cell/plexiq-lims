<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Services\SampleHierarchyService;

class SampleHierarchyController extends BaseController
{
    private SampleHierarchyService $service;

    public function __construct()
    {
        $this->service = new SampleHierarchyService();
    }

    public function aliquot(int $id): void
    {
        Auth::requirePermission('samples.create');

        $count = (int)($_POST['count'] ?? 1);
        $volume = !empty($_POST['volume']) ? trim((string)$_POST['volume']) : null;
        $testIds = !empty($_POST['test_ids']) && is_array($_POST['test_ids']) ? array_map('intval', $_POST['test_ids']) : [];

        try {
            $aliquots = $this->service->createAliquots($id, $count, $volume, $testIds);

            if ($this->isJsonRequest()) {
                $this->json(['success' => true, 'aliquots' => $aliquots]);
                return;
            }

            $_SESSION['flash_success'] = "Successfully generated " . count($aliquots) . " aliquot(s).";
            $this->redirect("/samples/{$id}");
        } catch (\Throwable $e) {
            if ($this->isJsonRequest()) {
                $this->json(['error' => $e->getMessage()], 400);
                return;
            }
            $_SESSION['flash_error'] = "Failed to create aliquots: " . $e->getMessage();
            $this->redirect("/samples/{$id}");
        }
    }

    public function createComposite(): void
    {
        Auth::requirePermission('samples.create');

        $sourceIds = !empty($_POST['source_ids']) && is_array($_POST['source_ids']) ? array_map('intval', $_POST['source_ids']) : [];
        $code = trim((string)($_POST['composite_code'] ?? ''));

        if (empty($code) || count($sourceIds) < 2) {
            if ($this->isJsonRequest()) {
                $this->json(['error' => 'Valid composite code and at least 2 source samples required.'], 400);
                return;
            }
            $_SESSION['flash_error'] = 'Valid composite code and at least 2 source samples required.';
            $this->redirect('/samples');
            return;
        }

        try {
            $comp = $this->service->createComposite($sourceIds, $code);

            if ($this->isJsonRequest()) {
                $this->json(['success' => true, 'composite' => $comp]);
                return;
            }

            $_SESSION['flash_success'] = "Composite sample {$code} created successfully.";
            $this->redirect("/samples/{$comp['id']}");
        } catch (\Throwable $e) {
            if ($this->isJsonRequest()) {
                $this->json(['error' => $e->getMessage()], 400);
                return;
            }
            $_SESSION['flash_error'] = "Failed to create composite: " . $e->getMessage();
            $this->redirect('/samples');
        }
    }

    public function genealogy(int $id): string
    {
        Auth::requirePermission('samples.view');

        try {
            $tree = $this->service->getGenealogyTree($id);
            if ($this->isJsonRequest() || isset($_GET['format']) && $_GET['format'] === 'json') {
                return $this->json(['success' => true, 'tree' => $tree]);
            }

            return $this->render('samples.genealogy', [
                'tree' => $tree,
                'sample' => $tree['sample']
            ]);
        } catch (\Throwable $e) {
            if ($this->isJsonRequest()) {
                return $this->json(['error' => $e->getMessage()], 404);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect('/samples');
            return '';
        }
    }

    private function isJsonRequest(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
            || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }
}
