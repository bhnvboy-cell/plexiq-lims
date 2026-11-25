<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Database;
use App\Helpers\Auth;
use App\Helpers\Audit;

class BenchController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = Database::connect();

        // Pending and In-Progress tests optimized for high-speed bench entry
        $tests = $db->query("
            SELECT st.id, st.sample_id, st.status,
                   t.test_code, t.test_name, t.min_spec_limit, t.max_spec_limit, t.spec_limit_text,
                   un.unit_code,
                   s.sample_code, COALESCE(p.product_name, s.sample_nature, 'Lab Specimen') as sample_name,
                   r.result_value, r.result_text, r.is_within_spec
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            JOIN samples s ON st.sample_id = s.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN units un ON t.unit_id = un.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.deleted_at IS NULL
            WHERE st.status IN ('pending', 'in_progress') AND st.deleted_at IS NULL
            ORDER BY s.sample_code ASC, t.test_code ASC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('bench.index', [
            'tests' => $tests
        ]);
    }

    public function saveResult(): string
    {
        Auth::requireAuth();
        $sampleTestId = (int)($_POST['sample_test_id'] ?? 0);
        $value = trim($_POST['value'] ?? '');
        $userId = Auth::id() ?? 1;

        if ($sampleTestId <= 0 || $value === '') {
            return $this->json(['success' => false, 'error' => 'Sample test ID and value are required.'], 400);
        }

        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT st.*, t.min_spec_limit, t.max_spec_limit, t.test_name
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            WHERE st.id = ?
        ");
        $stmt->execute([$sampleTestId]);
        $test = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$test) {
            return $this->json(['success' => false, 'error' => 'Test not found.'], 404);
        }

        $isNumeric = is_numeric($value);
        $withinSpec = true;
        if ($isNumeric) {
            $num = (float)$value;
            if ($test['min_spec_limit'] !== null && $num < (float)$test['min_spec_limit']) {
                $withinSpec = false;
            }
            if ($test['max_spec_limit'] !== null && $num > (float)$test['max_spec_limit']) {
                $withinSpec = false;
            }
        }

        // Insert or update result record
        $ins = $db->prepare("
            INSERT INTO results (sample_test_id, result_value, result_text, is_within_spec, entered_by, entered_at, revision)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 1)
            RETURNING id
        ");
        $ins->execute([
            $sampleTestId,
            $isNumeric ? (float)$value : null,
            $value,
            $withinSpec ? 't' : 'f',
            $userId
        ]);
        $resultId = (int)$ins->fetchColumn();

        // Update sample_test status
        $status = $withinSpec ? 'Completed' : 'Rejected';
        $db->prepare("UPDATE sample_tests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$status, $sampleTestId]);

        Audit::log('Bench Result Entered', 'results', $resultId, null, [
            'sample_test_id' => $sampleTestId,
            'value' => $value,
            'is_within_spec' => $withinSpec,
            'mode' => 'bench_tablet'
        ]);

        return $this->json([
            'success' => true,
            'result_id' => $resultId,
            'is_within_spec' => $withinSpec,
            'status' => $status,
            'message' => $withinSpec ? 'Result within specification.' : 'OUT OF SPECIFICATION (OOS) flagged!'
        ]);
    }
}
