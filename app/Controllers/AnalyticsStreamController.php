<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Database;
use App\Helpers\Auth;

class AnalyticsStreamController extends BaseController
{
    /**
     * NDJSON Streaming endpoint for operational metrics and events
     * Format: application/x-ndjson
     */
    public function stream(): string
    {
        Auth::requireAuth();

        if (!headers_sent()) {
            header('Content-Type: application/x-ndjson');
            header('X-Content-Type-Options: nosniff');
        }

        $db = Database::connect();

        // 1. Stream recent sample events
        $stmt = $db->query("
            SELECT s.id, s.sample_code, s.sample_nature, s.status, s.created_at, s.updated_at,
                   COALESCE(p.product_name, s.sample_nature, 'Standard Specimen') as sample_name
            FROM samples s
            LEFT JOIN products p ON s.product_id = p.id
            ORDER BY s.id DESC
            LIMIT 50
        ");
        $samples = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $output = [];
        foreach ($samples as $sample) {
            $output[] = json_encode([
                'entity' => 'sample',
                'id' => (int)$sample['id'],
                'code' => $sample['sample_code'],
                'name' => $sample['sample_name'],
                'status' => $sample['status'],
                'timestamp' => $sample['updated_at'] ?? $sample['created_at']
            ], JSON_UNESCAPED_SLASHES);
        }

        // 2. Stream recent test results
        $stmtTests = $db->query("
            SELECT st.id, st.sample_id, st.status, st.created_at,
                   r.result_value, r.result_text,
                   t.test_name, s.sample_code
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            JOIN samples s ON st.sample_id = s.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.deleted_at IS NULL
            ORDER BY st.id DESC
            LIMIT 50
        ");
        $tests = $stmtTests->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($tests as $t) {
            $output[] = json_encode([
                'entity' => 'test_result',
                'id' => (int)$t['id'],
                'sample_code' => $t['sample_code'],
                'test_name' => $t['test_name'],
                'status' => $t['status'],
                'value' => $t['result_value'] ?? $t['result_text'],
                'timestamp' => $t['created_at']
            ], JSON_UNESCAPED_SLASHES);
        }

        return implode("\n", $output) . "\n";
    }

    /**
     * Aggregated BI Feed for PowerBI, Tableau, Looker, Snowflake ETL
     */
    public function biFeed(): string
    {
        Auth::requireAuth();
        $db = Database::connect();

        // Summary metrics
        $samplesCount = (int)$db->query("SELECT COUNT(*) FROM samples")->fetchColumn();
        $pendingTests = (int)$db->query("SELECT COUNT(*) FROM sample_tests WHERE status IN ('pending', 'in_progress')")->fetchColumn();
        $completedTests = (int)$db->query("SELECT COUNT(*) FROM sample_tests WHERE status IN ('completed', 'approved', 'pass')")->fetchColumn();
        $failedTests = (int)$db->query("SELECT COUNT(*) FROM sample_tests WHERE status IN ('failed', 'fail', 'rejected')")->fetchColumn();

        // Sample status breakdown
        $statusBreakdown = $db->query("
            SELECT status, COUNT(*) as count 
            FROM samples 
            GROUP BY status 
            ORDER BY count DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // Turn Around Time (TAT) metrics in hours
        $tatStats = $db->query("
            SELECT 
                COUNT(*) as completed_count,
                ROUND(AVG(EXTRACT(EPOCH FROM (updated_at - created_at))/3600)::numeric, 2) as avg_tat_hours,
                ROUND(MIN(EXTRACT(EPOCH FROM (updated_at - created_at))/3600)::numeric, 2) as min_tat_hours,
                ROUND(MAX(EXTRACT(EPOCH FROM (updated_at - created_at))/3600)::numeric, 2) as max_tat_hours
            FROM samples
            WHERE status IN ('completed', 'approved') AND updated_at > created_at
        ")->fetch(\PDO::FETCH_ASSOC) ?: [
            'completed_count' => 0,
            'avg_tat_hours' => 0,
            'min_tat_hours' => 0,
            'max_tat_hours' => 0
        ];

        // Monthly throughput
        $throughput = $db->query("
            SELECT 
                TO_CHAR(created_at, 'YYYY-MM') as month,
                COUNT(*) as sample_count
            FROM samples
            GROUP BY TO_CHAR(created_at, 'YYYY-MM')
            ORDER BY month DESC
            LIMIT 12
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->json([
            'feed_metadata' => [
                'generator' => 'PlexiQ LIMS High-Throughput BI Feed',
                'timestamp' => date('c'),
                'version' => '1.0'
            ],
            'kpis' => [
                'total_samples' => $samplesCount,
                'pending_tests' => $pendingTests,
                'completed_tests' => $completedTests,
                'failed_tests' => $failedTests,
                'first_pass_yield_pct' => ($completedTests + $failedTests) > 0 
                    ? round(($completedTests / ($completedTests + $failedTests)) * 100, 2) 
                    : 100.0
            ],
            'status_distribution' => $statusBreakdown,
            'turn_around_time' => $tatStats,
            'monthly_throughput' => $throughput
        ]);
    }
}
