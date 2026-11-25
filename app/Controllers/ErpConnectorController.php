<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Database;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Services\Erp\ErpConnectorFactory;

class ErpConnectorController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = Database::connect();
        $connectors = $db->query("SELECT * FROM erp_connectors ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('erp.index', [
            'connectors' => $connectors
        ]);
    }

    public function testConnection(int $id): string
    {
        Auth::requireRole('Admin');
        try {
            $connector = ErpConnectorFactory::create($id);
            $result = $connector->ping();
            Audit::log('ERP Connector Ping Tested', 'erp_connectors', $id, null, $result);
            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function pullLots(int $id): string
    {
        Auth::requireAuth();
        try {
            $connector = ErpConnectorFactory::create($id);
            $lots = $connector->pullInspectionLots(['limit' => 5]);

            $db = Database::connect();
            $db->prepare("UPDATE erp_connectors SET last_synced_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);

            Audit::log('ERP Inspection Lots Pulled', 'erp_connectors', $id, null, ['pulled_count' => count($lots)]);
            return $this->json([
                'success' => true,
                'count' => count($lots),
                'lots' => $lots
            ]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function pushResults(int $id): string
    {
        Auth::requireRole('QA Manager');
        $sampleId = (int)($_POST['sample_id'] ?? 1);
        $decision = $_POST['usage_decision'] ?? 'A';

        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT st.id, st.status, t.test_name, ua.full_name AS analyst_name,
                       r.result_value, r.result_text
                FROM sample_tests st
                JOIN tests t ON st.test_id = t.id
                LEFT JOIN users ua ON st.assigned_to = ua.id
                LEFT JOIN results r ON r.sample_test_id = st.id AND r.deleted_at IS NULL
                WHERE st.sample_id = ?
            ");
            $stmt->execute([$sampleId]);
            $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $resultsPayload = [];
            foreach ($tests as $t) {
                $resultsPayload[] = [
                    'test_name' => $t['test_name'],
                    'numeric_value' => $t['result_value'] ?? $t['result_text'],
                    'status' => $t['status'],
                    'analyst_name' => $t['analyst_name'] ?? 'LIMS Analyst'
                ];
            }

            $connector = ErpConnectorFactory::create($id);
            $res = $connector->pushInspectionResults('LOT-' . $sampleId, $resultsPayload, $decision);

            Audit::log('ERP Inspection Results Pushed', 'erp_connectors', $id, null, [
                'sample_id' => $sampleId,
                'usage_decision' => $decision,
                'remote_reference' => $res['remote_reference'] ?? null
            ]);

            return $this->json($res);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function syncMaster(int $id): string
    {
        Auth::requireRole('Admin');
        $type = $_POST['entity_type'] ?? 'materials';

        try {
            $connector = ErpConnectorFactory::create($id);
            $res = $connector->syncMasterData($type);

            Audit::log('ERP Master Data Synced', 'erp_connectors', $id, null, ['type' => $type, 'count' => $res['synced_count']]);
            return $this->json($res);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
