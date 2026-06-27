<?php

namespace App\Controllers\Api;

use App\BaseController;
use App\Helpers\Database;
use App\Models\Sample;
use App\Models\SampleTest;
use App\Models\Result;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Notification;

class GeneralApiController extends BaseController
{
    private function checkPermission(string $perm): bool
    {
        $perms = $_REQUEST['_api_permissions'] ?? [];
        return in_array($perm, $perms) || in_array('*', $perms);
    }

    // ============================================================
    // SAMPLES API
    // ============================================================
    public function listSamples(): string
    {
        if (!$this->checkPermission('samples.read')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $page = (int)($_GET['page'] ?? 1);
        $limit = min(100, (int)($_GET['limit'] ?? 20));
        $offset = ($page - 1) * $limit;
        $db = Database::connect();
        $stmt = $db->prepare("SELECT s.*, c.customer_name, p.product_name FROM samples s LEFT JOIN customers c ON s.customer_id = c.id LEFT JOIN products p ON s.product_id = p.id ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        return $this->json($stmt->fetchAll());
    }

    public function getSample(int $id): string
    {
        if (!$this->checkPermission('samples.read')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $db = Database::connect();
        $stmt = $db->prepare("SELECT s.*, c.customer_name, p.product_name FROM samples s LEFT JOIN customers c ON s.customer_id = c.id LEFT JOIN products p ON s.product_id = p.id WHERE s.id = ?");
        $stmt->execute([$id]);
        $sample = $stmt->fetch();
        if (!$sample) return $this->json(['error' => 'Not found'], 404);

        $tests = $db->prepare("SELECT st.*, t.test_name, t.test_code, t.min_spec_limit, t.max_spec_limit, t.unit_id, u.unit_code, u.unit_name FROM sample_tests st JOIN tests t ON st.test_id = t.id LEFT JOIN units u ON t.unit_id = u.id WHERE st.sample_id = ? ORDER BY st.sort_order");
        $tests->execute([$id]);
        $sample['tests'] = $tests->fetchAll();

        return $this->json($sample);
    }

    public function createSample(): string
    {
        if (!$this->checkPermission('samples.write')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['product_id'])) {
            return $this->json(['error' => 'product_id is required'], 400);
        }
        // Generate sample code
        $db = Database::connect();
        $codeStmt = $db->query("SELECT nextval('sample_code_seq')");
        $seq = $codeStmt->fetchColumn();
        $sampleCode = 'SMP-' . date('Ymd') . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO samples (sample_code, customer_id, product_id, batch_number, sample_type_id, priority, status, source, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Registered', 'api', ?) RETURNING *");
            $stmt->execute([
                $sampleCode,
                $data['customer_id'] ?? null,
                $data['product_id'],
                $data['batch_number'] ?? null,
                $data['sample_type_id'] ?? null,
                $data['priority'] ?? 'Routine',
                $_REQUEST['_api_user_id']
            ]);
            $sample = $stmt->fetch();

            // Auto-assign tests from product-test mapping
            $testStmt = $db->prepare("SELECT test_id, min_spec_limit, max_spec_limit, spec_limit_text FROM products_tests WHERE product_id = ? AND is_active = TRUE ORDER BY sort_order");
            $testStmt->execute([$data['product_id']]);
            foreach ($testStmt->fetchAll() as $pt) {
                $db->prepare("INSERT INTO sample_tests (sample_id, test_id, min_spec_limit, max_spec_limit, spec_limit_text, status, sort_order) VALUES (?, ?, ?, ?, ?, 'Pending', 0)")
                    ->execute([$sample['id'], $pt['test_id'], $pt['min_spec_limit'], $pt['max_spec_limit'], $pt['spec_limit_text']]);
            }

            $db->commit();
            return $this->json($sample, 201);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ============================================================
    // RESULTS API
    // ============================================================
    public function listResults(): string
    {
        if (!$this->checkPermission('results.read')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $db = Database::connect();
        $stmt = $db->query("SELECT r.*, st.test_id, t.test_name, t.test_code, s.sample_code FROM results r JOIN sample_tests st ON r.sample_test_id = st.id JOIN tests t ON st.test_id = t.id JOIN samples s ON st.sample_id = s.id ORDER BY r.created_at DESC LIMIT 100");
        return $this->json($stmt->fetchAll());
    }

    public function submitResult(int $sampleTestId): string
    {
        if (!$this->checkPermission('results.write')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['result_value'])) {
            return $this->json(['error' => 'result_value is required'], 400);
        }
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM sample_tests WHERE id = ?");
            $stmt->execute([$sampleTestId]);
            $st = $stmt->fetch();
            if (!$st) return $this->json(['error' => 'Sample test not found'], 404);

            $resultStatus = 'Completed';
            $resultValue = $data['result_value'];
            if ($st['min_spec_limit'] !== null && $st['max_spec_limit'] !== null) {
                if (is_numeric($resultValue)) {
                    $val = (float)$resultValue;
                    if ($val < (float)$st['min_spec_limit'] || $val > (float)$st['max_spec_limit']) {
                        $resultStatus = 'Failed';
                    }
                }
            }

            $insertStmt = $db->prepare("INSERT INTO results (sample_test_id, result_value, result_status, min_spec_limit, max_spec_limit, spec_limit_text, unit, tested_by, tested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP) RETURNING *");
            $insertStmt->execute([
                $sampleTestId, $resultValue, $resultStatus,
                $st['min_spec_limit'], $st['max_spec_limit'], $st['spec_limit_text'],
                $data['unit'] ?? null, $_REQUEST['_api_user_id']
            ]);
            $result = $insertStmt->fetch();

            $db->prepare("UPDATE sample_tests SET status = ?, completed_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$resultStatus === 'Failed' ? 'Completed' : 'Completed', $sampleTestId]);

            $db->commit();
            return $this->json($result, 201);
        } catch (\Exception $e) {
            $db->rollBack();
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ============================================================
    // PRODUCTS & CUSTOMERS API
    // ============================================================
    public function listProducts(): string
    {
        if (!$this->checkPermission('products.read')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        return $this->json(Product::all());
    }

    public function listCustomers(): string
    {
        if (!$this->checkPermission('customers.read')) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        return $this->json(Customer::all());
    }

    // ============================================================
    // NOTIFICATIONS API
    // ============================================================
    public function myNotifications(): string
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY sent_at DESC LIMIT 50");
        $stmt->execute([$_REQUEST['_api_user_id']]);
        return $this->json($stmt->fetchAll());
    }

    public function unreadCount(): string
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$_REQUEST['_api_user_id']]);
        return $this->json(['unread_count' => (int)$stmt->fetchColumn()]);
    }

    // ============================================================
    // HEALTH / STATUS
    // ============================================================
    public function status(): string
    {
        $db = Database::connect();
        $sampleCount = (int)$db->query("SELECT COUNT(*) FROM samples")->fetchColumn();
        $testCount = (int)$db->query("SELECT COUNT(*) FROM tests")->fetchColumn();
        return $this->json([
            'status' => 'ok',
            'version' => '2.1.0',
            'database' => 'connected',
            'stats' => [
                'samples' => $sampleCount,
                'tests' => $testCount,
            ],
            'authenticated_as' => $_REQUEST['_api_user_id'] ?? null,
        ]);
    }

    // ============================================================
    // BARCODE LOOKUP (for mobile scanning)
    // ============================================================
    public function barcodeLookup(): string
    {
        $barcode = $_GET['barcode'] ?? '';
        if (empty($barcode)) return $this->json(['error' => 'barcode parameter required'], 400);
        $db = Database::connect();
        $results = [];

        // Check samples
        $stmt = $db->prepare("SELECT id, sample_code AS code, 'sample' AS type, status FROM samples WHERE sample_code = ?");
        $stmt->execute([$barcode]);
        foreach ($stmt->fetchAll() as $r) $results[] = $r;

        // Check batches
        $stmt = $db->prepare("SELECT id, batch_number AS code, 'batch' AS type, status FROM batches WHERE batch_number = ?");
        $stmt->execute([$barcode]);
        foreach ($stmt->fetchAll() as $r) $results[] = $r;

        return $this->json($results);
    }
}
