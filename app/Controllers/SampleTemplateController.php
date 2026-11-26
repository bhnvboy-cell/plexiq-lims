<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Models\SampleTemplate;
use PDO;

class SampleTemplateController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver', 'Reviewer', 'Analyst']);

        $templates = SampleTemplate::active();
        $db = Database::connect();
        $sampleTypes = $db->query("SELECT id, name FROM sample_types WHERE is_active = TRUE ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        return $this->render('sample-templates.index', [
            'templates' => $templates,
            'sampleTypes' => $sampleTypes,
            'stages' => ['Raw Material', 'In-Process', 'Finished Good', 'Stability', 'Release', 'Packaging']
        ]);
    }

    public function store(): void
    {
        Auth::requireRole('Admin');

        $code = trim($_POST['template_code'] ?? '');
        $name = trim($_POST['template_name'] ?? '');
        $stage = trim($_POST['default_stage'] ?? 'Finished Good');
        $storage = trim($_POST['storage_condition'] ?? 'Ambient 15-25°C');
        $labelFormat = trim($_POST['label_format'] ?? 'ZPL_2X1');
        $sampleTypeId = !empty($_POST['sample_type_id']) ? (int)$_POST['sample_type_id'] : null;
        $receiptCheck = !empty($_POST['requires_receipt_check']);

        if (empty($code) || empty($name)) {
            session_flash('error', 'Template code and name are required.');
            redirect('/sample-templates');
            return;
        }

        try {
            SampleTemplate::create([
                'template_code' => $code,
                'template_name' => $name,
                'sample_type_id' => $sampleTypeId,
                'default_stage' => $stage,
                'storage_condition' => $storage,
                'label_format' => $labelFormat,
                'requires_receipt_check' => $receiptCheck
            ]);
            session_flash('success', "Sample Template '{$code}' created successfully.");
        } catch (\Throwable $e) {
            session_flash('error', 'Error creating template: ' . $e->getMessage());
        }

        redirect('/sample-templates');
    }
}
