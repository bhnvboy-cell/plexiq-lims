<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use PDO;

class LabelPrinterService
{
    /**
     * Render a label for a sample using a specified template (or default template).
     *
     * @param int $sampleId
     * @param int|null $templateId
     * @return array{format: string, payload: string, mime_type: string, filename: string}
     */
    public function renderSampleLabel(int $sampleId, ?int $templateId = null): array
    {
        $db = Database::connect();

        // 1. Fetch sample and joined metadata
        $stmt = $db->prepare(
            'SELECT s.*, p.product_name, c.customer_name,
                    sa.position_label, sl.name as storage_location_name
             FROM samples s
             LEFT JOIN products p ON s.product_id = p.id
             LEFT JOIN customers c ON s.customer_id = c.id
             LEFT JOIN sample_storage_assignments sa ON sa.sample_id = s.id AND sa.status = \'Stored\'
             LEFT JOIN storage_locations sl ON sa.storage_location_id = sl.id
             WHERE s.id = ?'
        );
        $stmt->execute([$sampleId]);
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sample) {
            throw new \InvalidArgumentException("Sample #{$sampleId} not found.");
        }

        // 2. Fetch template
        if ($templateId !== null) {
            $tStmt = $db->prepare('SELECT * FROM label_templates WHERE id = ?');
            $tStmt->execute([$templateId]);
            $template = $tStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $tStmt = $db->prepare("SELECT * FROM label_templates WHERE label_type = 'sample' AND is_default = TRUE LIMIT 1");
            $tStmt->execute();
            $template = $tStmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                // Fallback to first available template
                $template = $db->query("SELECT * FROM label_templates WHERE label_type = 'sample' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            }
        }

        if (!$template) {
            throw new \RuntimeException("No label template found.");
        }

        // 3. Substitution dictionary
        $replacements = [
            '{SAMPLE_CODE}' => $sample['sample_code'] ?? '',
            '{BATCH_NUMBER}' => $sample['batch_number'] ?? 'N/A',
            '{PRODUCT_NAME}' => substr($sample['product_name'] ?? 'General Sample', 0, 30),
            '{CUSTOMER_NAME}' => substr($sample['customer_name'] ?? '', 0, 25),
            '{RECEIVED_DATE}' => $sample['received_date'] ?? date('Y-m-d'),
            '{EXPIRY_DATE}' => $sample['expiry_date'] ?? 'N/A',
            '{STATUS}' => $sample['status'] ?? 'Registered',
            '{PRIORITY}' => $sample['priority'] ?? 'Normal',
            '{HIERARCHY_TYPE}' => $sample['hierarchy_type'] ?? 'primary',
            '{ALIQUOT_INDEX}' => (string)($sample['aliquot_index'] ?? 0),
            '{POSITION_LABEL}' => $sample['position_label'] ?? 'N/A',
            '{STORAGE_NAME}' => $sample['storage_location_name'] ?? 'Ambient',
            '{PRINT_TIMESTAMP}' => date('Y-m-d H:i:s'),
        ];

        $rendered = strtr($template['template_body'], $replacements);
        $format = strtolower($template['output_format']);

        $mimeType = match ($format) {
            'zpl' => 'text/plain',
            'tspl', 'epl' => 'text/plain',
            'svg_qr' => 'image/svg+xml',
            'html' => 'text/html',
            default => 'text/plain',
        };

        $filename = "label_{$sample['sample_code']}.{$format}";

        return [
            'format' => $format,
            'payload' => $rendered,
            'mime_type' => $mimeType,
            'filename' => $filename
        ];
    }
}
