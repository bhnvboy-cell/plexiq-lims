<?php
namespace App\Models;

use App\BaseModel;

class Batch extends BaseModel
{
    protected static string $table = 'batches';
    protected static string $primaryKey = 'id';

    public static function allWithRelations(): array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("
            SELECT b.*, p.product_code, p.product_name, p.category,
                   u.full_name AS created_by_name,
                   (SELECT COUNT(*) FROM samples s WHERE s.batch_id = b.id) AS sample_count
            FROM batches b
            LEFT JOIN products p ON b.product_id = p.id
            LEFT JOIN users u ON b.created_by = u.id
            ORDER BY b.created_at DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function findWithRelations(int $id): ?array
    {
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT b.*, p.product_code, p.product_name, p.category,
                   u.full_name AS created_by_name
            FROM batches b
            LEFT JOIN products p ON b.product_id = p.id
            LEFT JOIN users u ON b.created_by = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $batch = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$batch) return null;

        // Get samples in this batch with their test results
        $stmt2 = $db->prepare("
            SELECT s.*, c.customer_name
            FROM samples s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.batch_id = ? ORDER BY s.created_at
        ");
        $stmt2->execute([$id]);
        $batch['samples'] = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        // Get product tests with latest result per sample
        $tests = ProductTest::findByProduct($batch['product_id']);
        $batch['tests'] = $tests;

        return $batch;
    }

    public static function statusSteps(): array
    {
        return ['Registered', 'In Progress', 'Reviewed', 'Approved', 'COA Released'];
    }

    public static function statusIcon(string $status): string
    {
        $map = [
            'Registered' => 'bi-box',
            'In Progress' => 'bi-gear',
            'Reviewed' => 'bi-check2-all',
            'Approved' => 'bi-check-circle',
            'COA Released' => 'bi-file-earmark-check',
        ];
        return $map[$status] ?? 'bi-circle';
    }
}
