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

        $where = '';
        $params = [];

        // Customers only see their own COAs
        if (Auth::role() === 'Customer') {
            $user = Auth::user();
            if ($user) {
                $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
                $stmt->execute([$user['email']]);
                $cust = $stmt->fetch();
                if ($cust) {
                    $where = 'WHERE s.customer_id = ?';
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

        $db->beginTransaction();
        try {
            $docNumber = CoaDocument::generateNumber();

            // Insert COA document
            $stmt = $db->prepare("
                INSERT INTO coa_documents (sample_id, template_id, document_number, generated_by, status)
                VALUES (?, (SELECT id FROM coa_templates WHERE is_default = TRUE LIMIT 1), ?, ?, 'Draft')
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
            SELECT cd.*, s.sample_code, s.batch_number, s.manufacture_date, s.expiry_date,
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
            WHERE cd.id = ?
        ");
        $stmt->execute([$id]);
        $document = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$document) {
            session_flash('error', 'COA document not found.');
            redirect('/coa');
        }

        // Get test results
        $testStmt = $db->prepare("
            SELECT t.test_name, t.test_code, t.spec_limit_text, t.min_spec_limit, t.max_spec_limit,
                   m.method_name, u.unit_code, u.unit_name,
                   r.result_value, r.result_text, r.is_within_spec,
                   ent.full_name AS entered_by_name
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN methods m ON t.method_id = m.id
            LEFT JOIN units u ON t.unit_id = u.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.revision = (
                SELECT MAX(r2.revision) FROM results r2 WHERE r2.sample_test_id = st.id
            )
            LEFT JOIN users ent ON r.entered_by = ent.id
            WHERE st.sample_id = ? AND st.status IN ('Completed', 'Reviewed', 'Approved')
            ORDER BY t.test_code
        ");
        $testStmt->execute([$document['sample_id']]);
        $results = $testStmt->fetchAll();

        // Get COA HTML
        $template = CoaTemplate::getDefault();
        $coaHtml = '';

        if ($template) {
            $coaService = new \App\Services\CoaService();
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
        $pdfService = new \App\Services\PdfService();
        $pdfService->downloadCoa($id);
    }

    public function release(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();

        $stmt = $db->prepare("UPDATE coa_documents SET status = 'Released', released_by = ?, released_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([Auth::id(), $id]);

        Audit::log('COA Released', 'coa_documents', $id);
        session_flash('success', 'COA released successfully.');
        redirect('/coa');
    }
}
