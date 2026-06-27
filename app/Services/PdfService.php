<?php

namespace App\Services;

class PdfService
{
    public function downloadCoa(int $coaId): void
    {
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
        $stmt->execute([$coaId]);
        $document = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$document) {
            http_response_code(404);
            echo 'COA not found';
            exit;
        }

        // Get test results
        $testStmt = $db->prepare("
            SELECT t.test_name, t.spec_limit_text, m.method_name, u.unit_code,
                   r.result_value, r.result_text, r.is_within_spec
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN methods m ON t.method_id = m.id
            LEFT JOIN units u ON t.unit_id = u.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.revision = (
                SELECT MAX(r2.revision) FROM results r2 WHERE r2.sample_test_id = st.id
            )
            WHERE st.sample_id = ? AND st.status IN ('Completed', 'Reviewed', 'Approved')
            ORDER BY t.test_code
        ");
        $testStmt->execute([$document['sample_id']]);
        $results = $testStmt->fetchAll();

        // Use TCPDF-based COA PDF generation
        $coaService = new CoaService();
        $db2 = \App\Helpers\Database::connect();
        $tplStmt = $db2->prepare("SELECT * FROM coa_templates WHERE id = ?");
        $tplStmt->execute([$document['template_id']]);
        $template = $tplStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$template) {
            $template = \App\Models\CoaTemplate::getDefault();
        }

        $coaService->generatePdf($document, $results, $template ?: []);
        exit;
    }
}
