<?php

namespace App\Services;

/**
 * SAP HANA API REST Endpoint Handlers
 * Used by /api/sap/* routes for external integration
 */
class SapApiService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = \App\Helpers\Database::connect();
    }

    public function pushSample(array $data): array
    {
        try {
            $sampleCode = $data['sampleCode'] ?? \App\Models\Sample::generateCode();

            $stmt = $this->db->prepare("
                INSERT INTO samples (sample_code, customer_id, product_id, batch_number, batch_size,
                    received_date, priority, status, registered_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Registered', ?, ?) RETURNING id
            ");

            $stmt->execute([
                $sampleCode,
                $data['customer_id'] ?? null,
                $data['product_id'] ?? null,
                $data['batchNumber'] ?? null,
                $data['batchSize'] ?? null,
                $data['receivedDate'] ?? date('Y-m-d'),
                $data['priority'] ?? 'Normal',
                $data['registered_by'] ?? 1,
                $data['notes'] ?? null,
            ]);

            $id = (int)$stmt->fetchColumn();

            // Assign tests if provided
            if (!empty($data['testIds']) && is_array($data['testIds'])) {
                $insertStmt = $this->db->prepare("INSERT INTO sample_tests (sample_id, test_id) VALUES (?, ?)");
                foreach ($data['testIds'] as $testId) {
                    $insertStmt->execute([$id, $testId]);
                }
            }

            \App\Helpers\Audit::log('API Sample Created', 'samples', $id, null, ['source' => 'SAP HANA API']);

            return ['success' => true, 'sample_id' => $id, 'sample_code' => $sampleCode];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function pushResult(array $data): array
    {
        try {
            $sampleCode = $data['sampleCode'] ?? '';
            $testCode = $data['testCode'] ?? '';

            // Find sample_test
            $stmt = $this->db->prepare("
                SELECT st.id, t.min_spec_limit, t.max_spec_limit
                FROM sample_tests st
                JOIN samples s ON st.sample_id = s.id
                JOIN tests t ON st.test_id = t.id
                WHERE s.sample_code = ? AND t.test_code = ?
                LIMIT 1
            ");
            $stmt->execute([$sampleCode, $testCode]);
            $st = $stmt->fetch();

            if (!$st) {
                return ['success' => false, 'error' => 'Sample test combination not found.'];
            }

            $value = $data['resultValue'] ?? null;
            $isWithinSpec = null;

            if ($value !== null && $st['min_spec_limit'] !== null && $st['max_spec_limit'] !== null) {
                $isWithinSpec = (float)$value >= (float)$st['min_spec_limit'] && (float)$value <= (float)$st['max_spec_limit'];
            }

            $stmt = $this->db->prepare("
                INSERT INTO results (sample_test_id, result_value, result_text, is_within_spec, entered_by, remarks)
                VALUES (?, ?, ?, ?, 1, ?) RETURNING id
            ");
            $stmt->execute([
                $st['id'],
                $value,
                $data['resultText'] ?? null,
                $isWithinSpec,
                $data['remarks'] ?? 'API entry from SAP HANA',
            ]);

            $resultId = (int)$stmt->fetchColumn();

            // Update sample_test status
            $this->db->prepare("UPDATE sample_tests SET status = 'Completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?")
                     ->execute([$st['id']]);

            return ['success' => true, 'result_id' => $resultId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function pullCustomers(): array
    {
        $stmt = $this->db->query("SELECT * FROM customers WHERE is_active = TRUE ORDER BY customer_code");
        return ['success' => true, 'data' => $stmt->fetchAll()];
    }

    public function pullProducts(): array
    {
        $stmt = $this->db->query("SELECT * FROM products WHERE is_active = TRUE ORDER BY product_code");
        return ['success' => true, 'data' => $stmt->fetchAll()];
    }

    public function pullSpecifications(): array
    {
        $stmt = $this->db->query("
            SELECT t.*, m.method_name, u.unit_name, u.unit_code
            FROM tests t
            LEFT JOIN methods m ON t.method_id = m.id
            LEFT JOIN units u ON t.unit_id = u.id
            WHERE t.is_active = TRUE
            ORDER BY t.test_code
        ");
        return ['success' => true, 'data' => $stmt->fetchAll()];
    }

    public function authenticate(string $username, string $password): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.username = ? AND u.is_active = TRUE
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }

        return null;
    }
}
