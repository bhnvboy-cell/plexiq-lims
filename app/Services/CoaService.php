<?php

namespace App\Services;

class CoaService
{
    protected ?array $manufacturer = null;

    protected function getManufacturer(): array
    {
        if ($this->manufacturer === null) {
            $this->manufacturer = \App\Models\Manufacturer::getDefault() ?: [];
        }
        return $this->manufacturer;
    }

    protected function manufacturerValue(string $key, string $envKey, string $default): string
    {
        $m = $this->getManufacturer();
        return !empty($m[$key]) ? $m[$key] : (env($envKey, '') ?: $default);
    }

    public function render(string $templateHtml, array $document, array $results, array $template = []): string
    {
        $placeholders = [
            '[[COMPANY_NAME]]' => $this->manufacturerValue('company_name', 'COMPANY_NAME', 'Your Laboratory'),
            '[[COMPANY_ADDRESS]]' => $this->manufacturerValue('address', 'COMPANY_ADDRESS', '123 Lab Street'),
            '[[COMPANY_PHONE]]' => $this->manufacturerValue('phone', 'COMPANY_PHONE', '+1-555-0000'),
            '[[COMPANY_EMAIL]]' => $this->manufacturerValue('email', 'COMPANY_EMAIL', 'info@lab.com'),
            '[[COA_NUMBER]]' => $document['document_number'] ?? '',
            '[[SAMPLE_CODE]]' => $document['sample_code'] ?? '',
            '[[CUSTOMER_NAME]]' => $document['customer_name'] ?? '',
            '[[CUSTOMER_CODE]]' => $document['customer_code'] ?? '',
            '[[PRODUCT_NAME]]' => $document['product_name'] ?? '',
            '[[PRODUCT_CODE]]' => $document['product_code'] ?? '',
            '[[BATCH_NUMBER]]' => $document['batch_number'] ?? '',
            '[[MANUFACTURE_DATE]]' => $document['manufacture_date'] ?? '',
            '[[EXPIRY_DATE]]' => $document['expiry_date'] ?? '',
            '[[ANALYSIS_DATE]]' => $document['coa_released_at'] ?? $document['generated_at'] ?? '',
            '[[STATUS]]' => $document['status'] ?? '',
            '[[REVIEWED_BY]]' => $document['reviewed_by_name'] ?? '',
            '[[REVIEWED_DATE]]' => $document['reviewed_at'] ?? '',
            '[[APPROVED_BY]]' => $document['approved_by_name'] ?? '',
            '[[APPROVED_DATE]]' => $document['approved_at'] ?? '',
        ];

        $html = str_replace(array_keys($placeholders), array_values($placeholders), $templateHtml);

        // Handle [[QR_CODE]] — generate inline QR image via external API for HTML preview
        $qrCodeUrl = urlencode(env('APP_URL', 'http://localhost') . '/coa/' . ($document['id'] ?? '') . '?code=' . ($document['document_number'] ?? ''));
        $qrImg = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . $qrCodeUrl . '" alt="QR Code" style="float:right;">';
        $html = str_replace('[[QR_CODE]]', $qrImg, $html);

        // Handle [[BARCODE]] — simple SVG barcode via external API
        $bcCode = htmlspecialchars($document['sample_code'] ?? $document['document_number'] ?? '');
        $barcodeImg = '<img src="https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($bcCode) . '&code=Code128&dpi=96" alt="Barcode" style="float:right;">';
        $html = str_replace('[[BARCODE]]', $barcodeImg, $html);

        // Handle [[LOGO]] - fallback to manufacturer logo
        $logoSrc = $template['logo_path'] ?? '';
        if (empty($logoSrc)) {
            $m = $this->getManufacturer();
            $logoSrc = $m['logo_path'] ?? '';
        }
        $logoHtml = '';
        if (!empty($logoSrc)) {
            $logoFull = storage_path('app/public/' . $logoSrc);
            if (file_exists($logoFull)) {
                $logoHtml = '<img src="/storage/' . htmlspecialchars($logoSrc) . '" alt="Logo" style="max-height:80px;">';
            }
        }
        $html = str_replace('[[LOGO]]', $logoHtml, $html);

        // Handle [[SCADA_LOGO]]
        $scadaHtml = '';
        if (!empty($template['scada_logo_path'])) {
            $scadaFull = storage_path('app/public/' . $template['scada_logo_path']);
            if (file_exists($scadaFull)) {
                $scadaHtml = '<img src="/storage/' . htmlspecialchars($template['scada_logo_path']) . '" alt="SCADA Logo" style="max-height:80px;">';
            }
        }
        $html = str_replace('[[SCADA_LOGO]]', $scadaHtml, $html);

        // Handle [[WATERMARK]]
        $watermarkText = $template['watermark_text'] ?? '';
        if ($watermarkText !== '') {
            $html = str_replace('[[WATERMARK]]', '<div style="position:fixed;opacity:0.1;font-size:60px;transform:rotate(-45deg);pointer-events:none;z-index:999;">' . htmlspecialchars($watermarkText) . '</div>', $html);
        } else {
            $html = str_replace('[[WATERMARK]]', '', $html);
        }

        // Handle [[SIGNATURE_LINE]]
        $reviewedBy = $document['reviewed_by_name'] ?? '';
        $approvedBy = $document['approved_by_name'] ?? '';
        $sigHtml = '';
        if ($reviewedBy) {
            $sigHtml .= '<div style="margin-top:20px;"><hr style="width:200px;text-align:left;"><strong>' . htmlspecialchars($reviewedBy) . '</strong><br><small>Reviewed</small></div>';
        }
        if ($approvedBy) {
            $sigHtml .= '<div style="margin-top:10px;"><hr style="width:200px;text-align:left;"><strong>' . htmlspecialchars($approvedBy) . '</strong><br><small>Approved</small></div>';
        }
        $html = str_replace('[[SIGNATURE_LINE]]', $sigHtml, $html);

        // Build results rows
        $rowsHtml = '';
        foreach ($results as $r) {
            $status = is_null($r['result_value']) ? 'N/A' : 'Pass';
            if ($r['is_within_spec'] === false) $status = 'Fail';
            if ($r['is_within_spec'] === null) $status = 'N/A';

            $resultValue = $r['result_value'] !== null ? $r['result_value'] : ($r['result_text'] ?? 'N/A');

            $rowsHtml .= '<tr>';
            $rowsHtml .= '<td>' . htmlspecialchars($r['test_name'] ?? '') . '</td>';
            $rowsHtml .= '<td>' . htmlspecialchars($r['method_name'] ?? '') . '</td>';
            $rowsHtml .= '<td>' . htmlspecialchars($r['spec_limit_text'] ?? '') . '</td>';
            $rowsHtml .= '<td>' . htmlspecialchars((string)$resultValue) . '</td>';
            $rowsHtml .= '<td>' . htmlspecialchars($r['unit_code'] ?? '') . '</td>';
            $rowsHtml .= '<td>' . $status . '</td>';
            $rowsHtml .= '</tr>';
        }

        $html = str_replace('[[RESULTS_ROWS]]', $rowsHtml, $html);

        return $html;
    }

    public function generatePdf(array $document, array $results, array $template): void
    {
        $pdfPath = realpath(__DIR__ . '/../../vendor/tcpdf/tcpdf.php');
        if ($pdfPath === false) {
            http_response_code(500);
            echo 'TCPDF library not found.';
            exit;
        }
        require_once $pdfPath;

        $pageSize = $template['page_size'] ?? 'A4';
        $orientation = ($template['orientation'] ?? 'portrait') === 'landscape' ? 'L' : 'P';

        $pdf = new \TCPDF($orientation, 'mm', $pageSize, true, 'UTF-8', false);

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Margins
        $marginTop = (int)($template['margin_top'] ?? 15);
        $marginBottom = (int)($template['margin_bottom'] ?? 15);
        $marginLeft = (int)($template['margin_left'] ?? 15);
        $marginRight = (int)($template['margin_right'] ?? 15);
        $pdf->SetMargins($marginLeft, $marginTop, $marginRight);
        $pdf->SetAutoPageBreak(true, $marginBottom);

        // Watermark
        $watermarkText = $template['watermark_text'] ?? '';
        if ($watermarkText !== '') {
            $pdf->SetFont('helvetica', 'B', 40);
            $pdf->SetTextColor(230, 230, 230);
            $pdf->StartTransform();
            $pdf->Rotate(45, 110, -50);
            $pdf->SetXY(30, 250);
            $pdf->Write(0, $watermarkText);
            $pdf->StopTransform();
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->AddPage();

        $pageW = $pdf->getPageWidth();
        $contentW = $pageW - $marginLeft - $marginRight;
        $topY = $pdf->GetY(); // = $marginTop

        // --- Logo (top-left) ---
        $logoPath = $template['logo_path'] ?? '';
        if (empty($logoPath)) {
            $m = $this->getManufacturer();
            $logoPath = $m['logo_path'] ?? '';
        }
        if (!empty($logoPath)) {
            $logoFull = storage_path('app/public/' . $logoPath);
            if (file_exists($logoFull)) {
                $ext = strtolower(pathinfo($logoFull, PATHINFO_EXTENSION));
                $pdf->Image($logoFull, $marginLeft, $topY, 50, 0, $ext === 'png' ? 'PNG' : 'JPEG');
            }
        }

        // --- QR Code (top-right) ---
        $showQr = isset($template['show_qr_code']) ? (bool)$template['show_qr_code'] : true;
        if ($showQr) {
            $qrCodeUrl = env('APP_URL', 'http://localhost') . '/client/coa/' . ($document['id'] ?? '');
            $qrCodeUrl .= '?code=' . ($document['document_number'] ?? '');
            $qrStyle = [
                'border' => 0,
                'vpadding' => 0,
                'hpadding' => 0,
                'fgcolor' => [0, 0, 0],
                'bgcolor' => [255, 255, 255],
                'module_width' => 1,
                'module_height' => 1,
            ];
            $pdf->write2DBarcode($qrCodeUrl, 'QRCODE,H', $pageW - $marginRight - 25, $topY, 25, 25, $qrStyle, 'N');
        }

        // --- SCADA Logo (top-right, below QR if present) ---
        if (!empty($template['scada_logo_path'])) {
            $scadaFull = storage_path('app/public/' . $template['scada_logo_path']);
            if (file_exists($scadaFull)) {
                $ext = strtolower(pathinfo($scadaFull, PATHINFO_EXTENSION));
                $scadaY = $topY + ($showQr ? 27 : 0);
                $pdf->Image($scadaFull, $pageW - $marginRight - 50, $scadaY, 50, 0, $ext === 'png' ? 'PNG' : 'JPEG');
            }
        }

        // --- Centered Header (below logos/QR) ---
        $headerTop = $topY + max(25, $showQr ? 27 : 0);
        $pdf->SetY($headerTop);
        $pdf->SetFont('helvetica', 'B', 18);
        $companyName = $this->manufacturerValue('company_name', 'COMPANY_NAME', 'Your Laboratory');
        $pdf->Cell(0, 10, $companyName, 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'CERTIFICATE OF ANALYSIS', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'COA #: ' . ($document['document_number'] ?? ''), 0, 1, 'C');

        $pdf->Ln(3);

        // --- Company Address Block ---
        $pdf->SetFont('helvetica', '', 9);
        $addr = $this->manufacturerValue('address', 'COMPANY_ADDRESS', '');
        $phone = $this->manufacturerValue('phone', 'COMPANY_PHONE', '');
        $email = $this->manufacturerValue('email', 'COMPANY_EMAIL', '');
        $addrLine = trim($addr);
        if ($phone) $addrLine .= ' | Tel: ' . $phone;
        if ($email) $addrLine .= ' | Email: ' . $email;
        if ($addrLine) {
            $pdf->Cell(0, 5, $addrLine, 0, 1, 'C');
        }

        $pdf->Ln(5);

        // --- COA Info Table ---
        $pdf->SetFont('helvetica', '', 9);
        $infoData = [
            ['Customer', $document['customer_name'] ?? ''],
            ['Product', $document['product_name'] ?? ''],
            ['Batch Number', $document['batch_number'] ?? ''],
            ['Sample Code', $document['sample_code'] ?? ''],
            ['Manufacture Date', $document['manufacture_date'] ?? ''],
            ['Expiry Date', $document['expiry_date'] ?? ''],
            ['Analysis Date', $document['coa_released_at'] ?? $document['generated_at'] ?? ''],
        ];

        $html = '<table border="0" cellpadding="3" cellspacing="0" style="font-size:9px;width:100%;">';
        foreach ($infoData as $row) {
            $key = $row[0];
            $val = $row[1];
            $html .= '<tr>';
            $html .= '<td style="font-weight:bold;width:30%;">' . htmlspecialchars($key) . '</td>';
            $html .= '<td style="width:70%;">' . htmlspecialchars($val) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, false, false, '');

        $pdf->Ln(3);

        // --- Barcode (below info) ---
        $showBarcode = isset($template['show_barcode']) ? (bool)$template['show_barcode'] : true;
        if ($showBarcode && !empty($document['sample_code'])) {
            $barcodeCode = $document['sample_code'];
            $style = [
                'position' => '',
                'align' => 'C',
                'stretch' => false,
                'fitwidth' => true,
                'cellfitalign' => '',
                'border' => false,
                'hpadding' => 'auto',
                'vpadding' => 'auto',
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
                'text' => true,
                'label' => $barcodeCode,
                'font' => 'helvetica',
                'fontsize' => 8,
                'stretchtext' => 0,
            ];
            $barcodeW = min(80, $contentW);
            $barcodeX = $marginLeft + ($contentW - $barcodeW) / 2;
            $pdf->write1DBarcode($barcodeCode, 'C39', $barcodeX, '', $barcodeW, 15, '', $style, '');
            $pdf->Ln(3);
        }

        // --- Test Results Table ---
        $pdf->SetFont('helvetica', 'B', 9);
        $html = '<table border="1" cellpadding="4" cellspacing="0" style="font-size:9px;width:100%;border-collapse:collapse;">';
        $html .= '<thead>';
        $html .= '<tr style="background-color:#e0e0e0;font-weight:bold;">';
        $html .= '<th>Test</th><th>Method</th><th>Specification</th><th>Result</th><th>Unit</th><th>Status</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $rowNum = 0;
        foreach ($results as $r) {
            $status = is_null($r['result_value']) ? 'N/A' : 'Pass';
            if ($r['is_within_spec'] === false) $status = 'Fail';
            if ($r['is_within_spec'] === null) $status = 'N/A';

            $resultValue = $r['result_value'] !== null ? $r['result_value'] : ($r['result_text'] ?? 'N/A');
            if ($r['result_value'] !== null && $r['uncertainty'] !== null) {
                $resultValue .= ' ± ' . $r['uncertainty'] . (($r['k_factor'] !== null) ? ' (k=' . $r['k_factor'] . ')' : '');
            }
            $bgColor = ($rowNum % 2 === 0) ? '#f9f9f9' : '#ffffff';
            if ($status === 'Fail') $bgColor = '#ffe0e0';
            if ($status === 'N/A') $bgColor = '#fffde0';

            $html .= '<tr style="background-color:' . $bgColor . ';">';
            $html .= '<td>' . htmlspecialchars($r['test_name'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($r['method_name'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($r['spec_limit_text'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars((string)$resultValue) . '</td>';
            $html .= '<td>' . htmlspecialchars($r['unit_code'] ?? '') . '</td>';
            $html .= '<td>' . $status . '</td>';
            $html .= '</tr>';
            $rowNum++;
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, false, false, '');

        $pdf->Ln(5);

        // --- Conclusion ---
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, 'Conclusion:', 0, 1);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'The above product meets the specified quality standards.', 0, 1);

        $pdf->Ln(5);

        // --- Signature Lines ---
        $showSig = isset($template['show_signature']) ? (bool)$template['show_signature'] : true;
        if ($showSig) {
            $pdf->SetFont('helvetica', '', 9);

            $reviewedBy = $document['reviewed_by_name'] ?? '';
            $reviewedDate = $document['reviewed_at'] ?? '';
            $approvedBy = $document['approved_by_name'] ?? '';
            $approvedDate = $document['approved_at'] ?? '';

            $signatures = [
                ['Reviewed by', $reviewedBy, $reviewedDate],
                ['Approved by', $approvedBy, $approvedDate],
            ];

            $sigW = min(100, $contentW * 0.45);
            $sigX = $marginLeft;

            foreach ($signatures as $sig) {
                if (!empty($sig[1])) {
                    $line = str_repeat('_', 40);
                    $pdf->SetX($sigX);
                    $pdf->Cell($sigW, 5, '', 0, 1);
                    $pdf->SetX($sigX);
                    $pdf->Cell($sigW, 0, $line, 0, 1);
                    $pdf->SetX($sigX);
                    $pdf->SetFont('helvetica', 'B', 9);
                    $pdf->Cell($sigW, 5, $sig[1], 0, 1);
                    $pdf->SetX($sigX);
                    $pdf->SetFont('helvetica', '', 8);
                    $label = $sig[0] . ': ' . ($sig[2] ? date('Y-m-d', strtotime($sig[2])) : '');
                    $pdf->Cell($sigW, 4, $label, 0, 1);
                }
            }
        }

        // --- Footer ---
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(150, 150, 150);
        $footerText = 'Generated by PlexiQ LIMS on ' . date('Y-m-d H:i:s');
        $pdf->Cell(0, 4, $footerText, 0, 1, 'C');

        // Output PDF
        $filename = 'COA_' . ($document['document_number'] ?? 'document') . '.pdf';
        $pdf->Output($filename, 'I');
        exit;
    }

    public function getResultsData(int $sampleId): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT t.test_name, t.test_code, t.spec_limit_text, t.min_spec_limit, t.max_spec_limit,
                   m.method_name, u.unit_code, u.unit_name,
                   r.result_value, r.result_text, r.is_within_spec, r.uncertainty, r.k_factor, r.confidence_interval,
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
        $stmt->execute([$sampleId]);
        return $stmt->fetchAll();
    }
}
