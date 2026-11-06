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
            WHERE cd.id = ? AND cd.deleted_at IS NULL
        ");
        $stmt->execute([$coaId]);
        $document = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$document) {
            http_response_code(404);
            echo 'COA not found';
            exit;
        }

        // Get unified test results
        $coaService = new CoaService();
        $results = $coaService->getResultsData($document['sample_id']);

        // Use TCPDF-based COA PDF generation
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
