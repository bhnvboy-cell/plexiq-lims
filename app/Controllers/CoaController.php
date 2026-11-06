<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\Sample;
use App\Models\CoaTemplate;
use App\Models\CoaDocument;
use App\Models\SampleTest;

class CoaController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver', 'Customer']);
        $db = \App\Helpers\Database::connect();

        $where = 'WHERE cd.deleted_at IS NULL';
        $params = [];

        // Customers only see their own COAs
        if (Auth::role() === 'Customer') {
            $user = Auth::user();
            if ($user) {
                $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
                $stmt->execute([$user['email']]);
                $cust = $stmt->fetch();
                if ($cust) {
                    $where .= ' AND s.customer_id = ?';
                    $params[] = $cust['id'];
                }
            }
        }

        $result = \App\Helpers\Pagination::run($db, "
            SELECT cd.*, s.sample_code, c.customer_name, p.product_name,
                   u.full_name AS generated_by_name
            FROM coa_documents cd
            JOIN samples s ON cd.sample_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON cd.generated_by = u.id
            {$where}
        ", "
            SELECT COUNT(*)
            FROM coa_documents cd
            JOIN samples s ON cd.sample_id = s.id
            {$where}
        ", $params, 20, 'cd.generated_at DESC');

        return $this->render('coa.index', ['documents' => $result['items'], 'paginator' => $result]);
    }

    public function generate(int $sampleId): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();

        $sample = Sample::withRelations($sampleId);
        if (!$sample) {
            session_flash('error', 'Sample not found.');
            redirect('/samples');
        }

        if ($sample['status'] !== 'Approved' && $sample['status'] !== 'COA Released') {
            session_flash('error', 'Sample must be approved before generating COA.');
            redirect('/samples/' . $sampleId);
        }

        // Compliance Gate: Check for open OOS before generating COA
        $checkOos = $db->prepare("SELECT COUNT(*) FROM oos_records WHERE sample_id = ? AND status != 'Closed' AND deleted_at IS NULL");
        $checkOos->execute([$sampleId]);
        if ((int)$checkOos->fetchColumn() > 0) {
            session_flash('error', 'Compliance Gate: Cannot generate COA while an open OOS investigation exists for this sample.');
            redirect('/samples/' . $sampleId);
            return;
        }

        $db->beginTransaction();
        try {
            $docNumber = CoaDocument::generateNumber();

            // Insert COA document (fall back to any template if none marked default)
            $stmt = $db->prepare("
                INSERT INTO coa_documents (sample_id, template_id, document_number, generated_by, status)
                VALUES (?, (SELECT id FROM coa_templates WHERE is_default = TRUE OR is_active = TRUE ORDER BY is_default DESC LIMIT 1), ?, ?, 'Draft')
                RETURNING id
            ");
            $stmt->execute([$sampleId, $docNumber, Auth::id()]);
            $coaId = (int)$stmt->fetchColumn();

            // Update sample status
            $stmt = $db->prepare("
                UPDATE samples SET status = 'COA Released', coa_released_by = ?, coa_released_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([Auth::id(), $sampleId]);

            $db->commit();

            Audit::log('COA Generated', 'coa_documents', $coaId, null, ['document_number' => $docNumber, 'sample_id' => $sampleId]);
            session_flash('success', "COA {$docNumber} generated successfully.");
        } catch (\Exception $e) {
            $db->rollBack();
            session_flash('error', 'Error generating COA: ' . $e->getMessage());
        }

        redirect('/coa');
    }

    public function view(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver', 'Customer']);
        $db = \App\Helpers\Database::connect();

        $stmt = $db->prepare("
            SELECT cd.*, s.customer_id, s.sample_code, s.batch_number, s.manufacture_date, s.expiry_date,
                   s.received_date, s.status AS sample_status,
                   c.customer_name, c.customer_code, c.address AS customer_address,
                   p.product_name, p.product_code,
                   u.full_name AS generated_by_name, ru.full_name AS reviewed_by_name,
                   au.full_name AS approved_by_name, cou.full_name AS released_by_name
            FROM coa_documents cd
            JOIN samples s ON cd.sample_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON cd.generated_by = u.id
            LEFT JOIN users ru ON s.reviewed_by = ru.id
            LEFT JOIN users au ON s.approved_by = au.id
            LEFT JOIN users cou ON s.coa_released_by = cou.id
            WHERE (cd.id = ? OR cd.sample_id = ?) AND cd.deleted_at IS NULL
            ORDER BY cd.id DESC
            LIMIT 1
        ");
        $stmt->execute([$id, $id]);
        $document = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$document) {
            session_flash('error', 'COA document not found.');
            redirect('/coa');
        }

        if (Auth::role() === 'Customer') {
            $user = Auth::user();
            $custStmt = $db->prepare("SELECT id FROM customers WHERE email = ? AND is_active = TRUE");
            $custStmt->execute([$user['email'] ?? '']);
            $cust = $custStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$cust || (int)$document['customer_id'] !== (int)$cust['id']) {
                abort(403, 'Access denied.');
            }
        }

        // Get unified test results (sample_tests + sample_analysis_parameters)
        $coaService = new \App\Services\CoaService();
        $results = $coaService->getResultsData($document['sample_id']);

        // Get COA HTML
        $template = CoaTemplate::getDefault();
        $coaHtml = '';

        if ($template) {
            $coaHtml = $coaService->render($template['template_html'], $document, $results, $template);
        }

        return $this->render('coa.view', [
            'document' => $document,
            'results' => $results,
            'coaHtml' => $coaHtml,
        ]);
    }

    public function downloadPdf(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver', 'Customer']);
        $db = \App\Helpers\Database::connect();

        $coaStmt = $db->prepare("
            SELECT cd.id, cd.status, s.customer_id
            FROM coa_documents cd
            JOIN samples s ON cd.sample_id = s.id
            WHERE (cd.id = ? OR cd.sample_id = ?) AND cd.deleted_at IS NULL
            ORDER BY cd.id DESC
            LIMIT 1
        ");
        $coaStmt->execute([$id, $id]);
        $coa = $coaStmt->fetch(\PDO::FETCH_ASSOC);

        if (Auth::role() === 'Customer') {
            $user = Auth::user();
            $custStmt = $db->prepare("SELECT id FROM customers WHERE email = ? AND is_active = TRUE");
            $custStmt->execute([$user['email'] ?? '']);
            $cust = $custStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$cust || !$coa || (int)$coa['customer_id'] !== (int)$cust['id']) {
                abort(403, 'Access denied.');
            }
        }

        $pdfService = new \App\Services\PdfService();
        $pdfService->downloadCoa($coa ? (int)$coa['id'] : $id);
    }

    public function release(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();

        // Compliance Gate: Check for open OOS before releasing COA
        $checkOos = $db->prepare("
            SELECT COUNT(*) FROM oos_records o
            JOIN coa_documents cd ON cd.sample_id = o.sample_id
            WHERE cd.id = ? AND o.status != 'Closed' AND o.deleted_at IS NULL
        ");
        $checkOos->execute([$id]);
        if ((int)$checkOos->fetchColumn() > 0) {
            session_flash('error', 'Compliance Gate: Cannot release COA while an open OOS investigation exists.');
            redirect('/coa');
            return;
        }

        $stmt = $db->prepare("UPDATE coa_documents SET status = 'Released', released_by = ?, released_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([Auth::id(), $id]);

        Audit::log('COA Released', 'coa_documents', $id);
        session_flash('success', 'COA released successfully.');
        redirect('/coa');
    }
}
