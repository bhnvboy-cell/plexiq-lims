<?php
namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;

class LabelController extends BaseController
{
    public function printSampleLabel(int $sampleId): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();

        $stmt = $db->prepare("
            SELECT s.*, p.product_name, c.customer_name
            FROM samples s
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$sampleId]);
        $sample = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$sample) {
            session_flash('error', 'Sample not found.');
            redirect('/samples');
        }

        return $this->render('labels.sample-label', ['sample' => $sample]);
    }

    public function printBatchLabels(int $batchId): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();

        $batchStmt = $db->prepare("SELECT * FROM batches WHERE id = ?");
        $batchStmt->execute([$batchId]);
        $batch = $batchStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$batch) { session_flash('error', 'Batch not found.'); redirect('/batches'); }

        $stmt = $db->prepare("
            SELECT s.*, p.product_name, c.customer_name
            FROM samples s
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.batch_id = ? OR s.batch_number = ?
            ORDER BY s.id
        ");
        $stmt->execute([$batchId, $batch['batch_number']]);
        $samples = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($samples)) {
            session_flash('error', 'No samples found in this batch.');
            redirect('/batches/' . $batchId);
        }

        return $this->render('labels.batch-labels', ['samples' => $samples, 'batchId' => $batchId]);
    }
}
