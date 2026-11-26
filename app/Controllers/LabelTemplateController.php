<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Services\LabelPrinterService;
use PDO;

class LabelTemplateController extends BaseController
{
    private LabelPrinterService $printer;

    public function __construct()
    {
        $this->printer = new LabelPrinterService();
    }

    public function index(): string
    {
        Auth::requirePermission('master.products');

        $db = Database::connect();
        $templates = $db->query('SELECT * FROM label_templates ORDER BY label_type, name')->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('labels.templates', [
            'templates' => $templates
        ]);
    }

    public function printSample(int $sampleId): string
    {
        Auth::requirePermission('samples.view');

        $templateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : null;

        try {
            $label = $this->printer->renderSampleLabel($sampleId, $templateId);

            if (isset($_GET['download']) && $_GET['download'] === '1') {
                header("Content-Type: {$label['mime_type']}");
                header("Content-Disposition: attachment; filename=\"{$label['filename']}\"");
                echo $label['payload'];
                exit;
            }

            if ($this->isJsonRequest()) {
                return $this->json(['success' => true, 'label' => $label]);
            }

            // Render preview page
            return $this->render('labels.preview', [
                'label' => $label,
                'sampleId' => $sampleId
            ]);
        } catch (\Throwable $e) {
            if ($this->isJsonRequest()) {
                return $this->json(['error' => $e->getMessage()], 400);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect("/samples/{$sampleId}");
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
