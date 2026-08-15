<?php

namespace App\Services;

class SapSyncService
{
    private ?string $host;
    private ?string $port;
    private ?string $username;
    private ?string $password;
    private ?string $odataUrl;
    private bool $enabled;
    private \PDO $db;

    public function __construct()
    {
        $this->db = \App\Helpers\Database::connect();
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        $stmt = $this->db->query("SELECT config_key, config_value FROM sap_config");
        $config = [];
        foreach ($stmt->fetchAll() as $row) {
            $config[$row['config_key']] = $row['config_value'];
        }

        $this->host = $config['sap_hana_host'] ?? 'localhost';
        $this->port = $config['sap_hana_port'] ?? '30015';
        $this->username = $config['sap_hana_username'] ?? 'SYSTEM';
        $this->password = $config['sap_hana_password'] ?? '';
        $this->odataUrl = $config['sap_odata_url'] ?? '';
        $this->enabled = ($config['sap_sync_enabled'] ?? 'false') === 'true';
    }

    private function logSync(string $type, string $entityType, ?int $entityId, string $status,
                             ?array $request = null, ?array $response = null, ?string $error = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO sap_sync_logs (sync_type, entity_type, entity_id, status, request_payload, response_payload, error_message)
            VALUES (?, ?, ?, ?, ?::jsonb, ?::jsonb, ?)
        ");
        $stmt->execute([$type, $entityType, $entityId, $status,
            $request ? json_encode($request) : null,
            $response ? json_encode($response) : null,
            $error]);
    }

    // ============================================================
    // PUSH: LIMS -> SAP HANA
    // ============================================================

    public function pushToSap(string $type): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'SAP sync is disabled. Enable it in configuration.'];
        }

        try {
            switch ($type) {
                case 'sample':
                    return $this->pushSamples();
                case 'result':
                    return $this->pushResults();
                case 'coa':
                    return $this->pushCoaStatus();
                default:
                    return ['success' => false, 'message' => "Unknown push type: {$type}"];
            }
        } catch (\Exception $e) {
            $this->logSync('Push', $type, null, 'Failed', null, null, $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function pushSamples(): array
    {
        // Get samples pending sync
        $stmt = $this->db->query("
            SELECT s.*, c.customer_code, p.product_code
            FROM samples s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            WHERE s.sap_sync_status = 'Pending'
            ORDER BY s.created_at ASC
            LIMIT 50
        ");
        $samples = $stmt->fetchAll();
        $count = 0;

        foreach ($samples as $sample) {
            try {
                $payload = [
                    'sampleCode' => $sample['sample_code'],
                    'customerCode' => $sample['customer_code'] ?? '',
                    'productCode' => $sample['product_code'] ?? '',
                    'batchNumber' => $sample['batch_number'] ?? '',
                    'batchSize' => $sample['batch_size'] ?? '',
                    'receivedDate' => $sample['received_date'],
                    'priority' => $sample['priority'] ?? 'Normal',
                    'status' => $sample['status'] ?? 'Registered',
                ];

                $response = $this->callSapOdata('POST', 'SampleSet', $payload);

                // Update sync status
                $updateStmt = $this->db->prepare("
                    UPDATE samples SET sap_sync_status = 'Synced', sap_sync_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $updateStmt->execute([$sample['id']]);

                $this->logSync('Push', 'sample', $sample['id'], 'Success', $payload, $response);
                $count++;
            } catch (\Exception $e) {
                $this->db->prepare("UPDATE samples SET sap_sync_status = 'Failed', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                         ->execute([$sample['id']]);
                $this->logSync('Push', 'sample', $sample['id'], 'Failed', $payload ?? null, null, $e->getMessage());
            }
        }

        return ['success' => $count > 0, 'message' => "Pushed {$count} samples to SAP HANA."];
    }

    private function pushResults(): array
    {
        // Get results not yet synced (no sap sync tracking on results, use recent completed)
        $stmt = $this->db->query("
            SELECT r.*, s.sample_code, t.test_code,
                   st.id AS sample_test_id
            FROM results r
            JOIN sample_tests st ON r.sample_test_id = st.id
            JOIN samples s ON st.sample_id = s.id
            JOIN tests t ON st.test_id = t.id
            WHERE r.approved_at IS NOT NULL
            AND r.created_at > COALESCE((SELECT config_value::timestamp FROM sap_config WHERE config_key = 'sap_last_sync_at'), '1970-01-01')
            ORDER BY r.created_at ASC
            LIMIT 50
        ");
        $results = $stmt->fetchAll();
        $count = 0;

        foreach ($results as $result) {
            try {
                $payload = [
                    'sampleCode' => $result['sample_code'],
                    'testCode' => $result['test_code'],
                    'resultValue' => $result['result_value'],
                    'resultText' => $result['result_text'],
                    'isWithinSpec' => $result['is_within_spec'],
                    'approvedAt' => $result['approved_at'],
                ];

                $response = $this->callSapOdata('POST', 'ResultSet', $payload);
                $this->logSync('Push', 'result', $result['id'], 'Success', $payload, $response);
                $count++;
            } catch (\Exception $e) {
                $this->logSync('Push', 'result', $result['id'], 'Failed', $payload ?? null, null, $e->getMessage());
            }
        }

        return ['success' => $count > 0, 'message' => "Pushed {$count} results to SAP HANA."];
    }

    private function pushCoaStatus(): array
    {
        $stmt = $this->db->query("
            SELECT cd.*, s.sample_code
            FROM coa_documents cd
            JOIN samples s ON cd.sample_id = s.id
            WHERE cd.status = 'Released' AND cd.updated_at > COALESCE(
                (SELECT config_value::timestamp FROM sap_config WHERE config_key = 'sap_last_sync_at'), '1970-01-01'
            )
            LIMIT 50
        ");
        $docs = $stmt->fetchAll();
        $count = 0;

        foreach ($docs as $doc) {
            try {
                $payload = [
                    'sampleCode' => $doc['sample_code'],
                    'documentNumber' => $doc['document_number'],
                    'status' => $doc['status'],
                    'releasedAt' => $doc['released_at'],
                ];

                $response = $this->callSapOdata('POST', 'CoaStatusSet', $payload);
                $this->logSync('Push', 'coa', $doc['id'], 'Success', $payload, $response);
                $count++;
            } catch (\Exception $e) {
                $this->logSync('Push', 'coa', $doc['id'], 'Failed', $payload ?? null, null, $e->getMessage());
            }
        }

        // Update last sync timestamp
        $this->db->query("UPDATE sap_config SET config_value = CURRENT_TIMESTAMP::text WHERE config_key = 'sap_last_sync_at'");

        return ['success' => $count > 0, 'message' => "Pushed {$count} COA status updates to SAP HANA."];
    }

    // ============================================================
    // PULL: SAP HANA -> LIMS
    // ============================================================

    public function pullFromSap(string $type): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'SAP sync is disabled.'];
        }

        try {
            switch ($type) {
                case 'customer':
                    return $this->pullCustomers();
                case 'product':
                    return $this->pullProducts();
                case 'specification':
                    return $this->pullSpecifications();
                default:
                    return ['success' => false, 'message' => "Unknown pull type: {$type}"];
            }
        } catch (\Exception $e) {
            $this->logSync('Pull', $type, null, 'Failed', null, null, $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function pullCustomers(): array
    {
        try {
            $response = $this->callSapOdata('GET', 'CustomerSet');
            $customers = $response['d']['results'] ?? $response['value'] ?? [];
            $count = 0;

            foreach ($customers as $cust) {
                $sapId = $cust['CustomerCode'] ?? $cust['CustomerID'] ?? '';
                $existing = \App\Models\Customer::findBySapId($sapId);

                $data = [
                    'customer_code' => $cust['CustomerCode'] ?? $cust['CustomerID'] ?? 'EXT-' . uniqid(),
                    'customer_name' => $cust['CustomerName'] ?? $cust['Name'] ?? 'Unknown',
                    'address' => $cust['Address'] ?? null,
                    'city' => $cust['City'] ?? null,
                    'country' => $cust['Country'] ?? null,
                    'email' => $cust['Email'] ?? null,
                    'phone' => $cust['Phone'] ?? null,
                    'sap_id' => $sapId,
                    'last_synced_at' => date('Y-m-d H:i:s'),
                ];

                if ($existing) {
                    \App\Models\Customer::update($existing['id'], $data);
                } else {
                    \App\Models\Customer::create($data);
                }
                $count++;
            }

            $this->logSync('Pull', 'customer', null, 'Success');
            return ['success' => true, 'message' => "Pulled {$count} customers from SAP HANA."];
        } catch (\Exception $e) {
            $this->logSync('Pull', 'customer', null, 'Failed', null, null, $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function pullProducts(): array
    {
        try {
            $response = $this->callSapOdata('GET', 'ProductSet');
            $products = $response['d']['results'] ?? $response['value'] ?? [];
            $count = 0;

            foreach ($products as $prod) {
                $sapId = $prod['ProductCode'] ?? $prod['ProductID'] ?? '';
                $existing = \App\Models\Product::findBySapId($sapId);

                $data = [
                    'product_code' => $prod['ProductCode'] ?? $prod['ProductID'] ?? 'EXT-' . uniqid(),
                    'product_name' => $prod['ProductName'] ?? $prod['Name'] ?? 'Unknown',
                    'description' => $prod['Description'] ?? null,
                    'category' => $prod['Category'] ?? null,
                    'sap_id' => $sapId,
                    'last_synced_at' => date('Y-m-d H:i:s'),
                ];

                if ($existing) {
                    \App\Models\Product::update($existing['id'], $data);
                } else {
                    \App\Models\Product::create($data);
                }
                $count++;
            }

            $this->logSync('Pull', 'product', null, 'Success');
            return ['success' => true, 'message' => "Pulled {$count} products from SAP HANA."];
        } catch (\Exception $e) {
            $this->logSync('Pull', 'product', null, 'Failed', null, null, $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function pullSpecifications(): array
    {
        try {
            $response = $this->callSapOdata('GET', 'SpecificationSet');
            $specs = $response['d']['results'] ?? $response['value'] ?? [];
            $count = 0;

            foreach ($specs as $spec) {
                $testCode = $spec['TestCode'] ?? $spec['TestID'] ?? '';
                if (empty($testCode)) continue;

                $existing = \App\Models\TestItem::where('test_code', $testCode);
                $existingTest = !empty($existing) ? $existing[0] : null;

                $data = [
                    'test_name' => $spec['TestName'] ?? $testCode,
                    'min_spec_limit' => $spec['MinLimit'] ?? null,
                    'max_spec_limit' => $spec['MaxLimit'] ?? null,
                    'spec_limit_text' => $spec['Specification'] ?? null,
                    'sap_id' => $spec['SpecificationID'] ?? '',
                    'last_synced_at' => date('Y-m-d H:i:s'),
                ];

                if ($existingTest) {
                    \App\Models\TestItem::update($existingTest->id, $data);
                } else {
                    $data['test_code'] = $testCode;
                    \App\Models\TestItem::create($data);
                }
                $count++;
            }

            $this->logSync('Pull', 'specification', null, 'Success');
            return ['success' => true, 'message' => "Pulled {$count} specifications from SAP HANA."];
        } catch (\Exception $e) {
            $this->logSync('Pull', 'specification', null, 'Failed', null, null, $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ============================================================
    // SAP HANA / ODATA CALLER
    // ============================================================

    private function callSapOdata(string $method, string $entity, ?array $data = null): ?array
    {
        $url = rtrim($this->odataUrl, '/') . '/' . $entity;

        if ($method === 'GET' && $data) {
            $query = http_build_query($data);
            $url .= '?' . $query;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
                'x-csrf-token: ' . $this->getCsrfToken(),
            ],
        ]);

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("SAP HANA connection error: {$error}");
        }

        if ($httpCode >= 400) {
            throw new \Exception("SAP HANA HTTP {$httpCode}: {$response}");
        }

        return json_decode($response, true) ?: null;
    }

    private function getCsrfToken(): string
    {
        if (empty($this->odataUrl)) return '';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($this->odataUrl, '/'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'x-csrf-token: Fetch',
                'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
            ],
        ]);
        $response = curl_exec($ch);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false || $headerSize <= 0) {
            return '';
        }

        $headers = substr((string)$response, 0, $headerSize);
        foreach (preg_split('/\r?\n/', $headers) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2 && strtolower(trim($parts[0])) === 'x-csrf-token') {
                return trim($parts[1]);
            }
        }
        return '';
    }

    // ============================================================
    // ODBC DIRECT CONNECTION (ALTERNATIVE)
    // ============================================================

    private function connectOdbc(): ?\PDO
    {
        if (!extension_loaded('odbc')) {
            throw new \Exception('ODBC extension is not loaded.');
        }

        $dsn = "DRIVER={HDBODBC};SERVERNODE={$this->host}:{$this->port}";
        $conn = odbc_connect($dsn, $this->username, $this->password);
        if (!$conn) {
            throw new \Exception('ODBC connection failed: ' . odbc_errormsg());
        }

        // Wrap in PDO
        return new \PDO('odbc:SAP HANA', $this->username, $this->password);
    }

    // ============================================================
    // STATUS & HEALTH
    // ============================================================

    public function getStatus(): array
    {
        $lastSync = $this->db->query("SELECT config_value FROM sap_config WHERE config_key = 'sap_last_sync_at'")->fetchColumn();

        $pendingSamples = (int)$this->db->query("SELECT COUNT(*) FROM samples WHERE sap_sync_status = 'Pending'")->fetchColumn();
        $failedSamples = (int)$this->db->query("SELECT COUNT(*) FROM samples WHERE sap_sync_status = 'Failed'")->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) FROM sap_sync_logs WHERE status = 'Failed' AND retry_count < max_retries");
        $pendingRetries = (int)$stmt->fetchColumn();

        return [
            'enabled' => $this->enabled,
            'host' => $this->host,
            'port' => $this->port,
            'last_sync_at' => $lastSync ?: 'Never',
            'pending_samples' => $pendingSamples,
            'failed_samples' => $failedSamples,
            'pending_retries' => $pendingRetries,
            'odata_url' => $this->odataUrl,
        ];
    }

    // ============================================================
    // CRON / SCHEDULED TASK ENTRY POINT
    // ============================================================

    public function runScheduledSync(): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'SAP sync disabled.'];
        }

        $results = [];

        // Push
        foreach (['sample', 'result', 'coa'] as $type) {
            $results["push_{$type}"] = $this->pushToSap($type);
        }

        // Pull
        foreach (['customer', 'product', 'specification'] as $type) {
            $results["pull_{$type}"] = $this->pullFromSap($type);
        }

        // Retry failed
        $this->retryFailed();

        return $results;
    }

    private function retryFailed(): void
    {
        $failed = \App\Models\SapSyncLog::pendingRetries();
        foreach ($failed as $log) {
            $this->db->prepare("UPDATE sap_sync_logs SET retry_count = retry_count + 1, status = 'In Progress' WHERE id = ?")
                     ->execute([$log['id']]);

            try {
                if ($log['sync_type'] === 'Push') {
                    $this->pushToSap($log['entity_type']);
                } else {
                    $this->pullFromSap($log['entity_type']);
                }
                $this->db->prepare("UPDATE sap_sync_logs SET status = 'Success', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                         ->execute([$log['id']]);
            } catch (\Exception $e) {
                $nextRetry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                $this->db->prepare("UPDATE sap_sync_logs SET status = 'Failed', error_message = ?, next_retry_at = ? WHERE id = ?")
                         ->execute([$e->getMessage(), $nextRetry, $log['id']]);
            }
        }
    }

    // ============================================================
    // DIRECT DB ACCESS (ALTERNATIVE TO ODATA)
    // ============================================================

    public function executeHanaQuery(string $sql, array $params = []): array
    {
        try {
            $pdo = $this->connectOdbc();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $this->logSync('Pull', 'direct_query', null, 'Failed', null, null, $e->getMessage());
            throw $e;
        }
    }
}
