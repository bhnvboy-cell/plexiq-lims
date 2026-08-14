<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class BarcodeController extends BaseController
{
    public function scan(): string
    {
        Auth::requireAuth();
        return $this->render('barcode.scan', []);
    }

    public function lookup(): string
    {
        Auth::requireAuth();
        $barcode = trim($_GET['code'] ?? '');
        if ($barcode === '') {
            return $this->json(['error' => 'Please enter or scan a barcode.'], 400);
        }
        $db = \App\Helpers\Database::connect();
        $tables = [
            'samples' => ['code' => 'sample_code', 'label' => 'sample_code', 'fields' => ['sample_code', 'product_name', 'batch_number', 'status', 'created_at'], 'url' => '/samples/'],
            'products' => ['code' => 'product_code', 'label' => 'product_name', 'fields' => ['product_code', 'product_name', 'category', 'is_active'], 'url' => '/master/products/{id}/edit'],
            'instruments' => ['code' => 'instrument_code', 'label' => 'instrument_name', 'fields' => ['instrument_code', 'instrument_name', 'location', 'status'], 'url' => '/instruments/{id}/edit'],
            'chemical_inventory' => ['code' => 'catalog_number', 'label' => 'chemical_name', 'fields' => ['chemical_name', 'cas_number', 'catalog_number', 'quantity', 'unit_type', 'expiry_date'], 'url' => '/master/chemical-inventory/{id}/edit'],
        ];
        $result = null;
        foreach ($tables as $table => $cfg) {
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE {$cfg['code']} = ? LIMIT 1");
            $stmt->execute([$barcode]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) { $result = ['table' => $table, 'cfg' => $cfg, 'row' => $row]; break; }
        }
        if (!$result) {
            return $this->json(['error' => 'No entity found for barcode: ' . $barcode], 404);
        }
        $fields = [];
        foreach ($result['cfg']['fields'] as $f) {
            if (array_key_exists($f, $result['row'])) $fields[$f] = $result['row'][$f];
        }
        $db->prepare("INSERT INTO barcode_scan_logs (barcode_value, entity_type, entity_id, scanned_by) VALUES (?, ?, ?, ?)")
            ->execute([$barcode, $result['table'], $result['row']['id'], Auth::id()]);
        Audit::log('Barcode Scanned', 'barcode_scan_logs', null, null, ['barcode' => $barcode, 'entity_type' => $result['table']]);
        return $this->json([
            'entity_type' => $result['table'],
            'label' => $result['row'][$result['cfg']['label']],
            'fields' => $fields,
            'action_url' => str_replace('{id}', $result['row']['id'], $result['cfg']['url']),
        ]);
    }

    public function scanLog(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $filters = [
            'q' => $_GET['q'] ?? '',
            'entity_type' => $_GET['entity_type'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $where = [];
        $params = [];
        if ($filters['q'] !== '') { $where[] = 'bs.barcode_value ILIKE ?'; $params[] = '%' . $filters['q'] . '%'; }
        if ($filters['entity_type'] !== '') { $where[] = 'bs.entity_type ILIKE ?'; $params[] = $filters['entity_type']; }
        if ($filters['date_from'] !== '') { $where[] = 'bs.scanned_at >= ?'; $params[] = $filters['date_from']; }
        if ($filters['date_to'] !== '') { $where[] = 'bs.scanned_at <= ?'; $params[] = $filters['date_to'] . ' 23:59:59'; }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $db->prepare("
            SELECT bs.*, u.full_name AS scanned_by_name
            FROM barcode_scan_logs bs
            LEFT JOIN users u ON bs.scanned_by = u.id
            {$whereSql}
            ORDER BY bs.scanned_at DESC
            LIMIT 200
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('barcode.logs', ['logs' => $logs, 'filters' => $filters]);
    }

    public function printLabel(string $entityType, int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $allowed = ['sample', 'product', 'instrument', 'chemical', 'location'];
        if (!in_array($entityType, $allowed)) { session_flash('error', 'Invalid entity type.'); $this->redirect('/barcode/scan'); }
        $tableMap = ['sample' => 'samples', 'product' => 'products', 'instrument' => 'instruments', 'chemical' => 'chemical_inventory', 'location' => 'instrument_locations'];
        $codeMap = ['sample' => 'sample_code', 'product' => 'product_code', 'instrument' => 'instrument_code', 'chemical' => 'catalog_number', 'location' => 'location_code'];
        $nameMap = ['sample' => 'sample_code', 'product' => 'product_name', 'instrument' => 'instrument_name', 'chemical' => 'chemical_name', 'location' => 'location_name'];
        $table = $tableMap[$entityType];
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        $entity = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$entity) { session_flash('error', 'Entity not found.'); $this->redirect('/barcode/scan'); }
        $barcodeData = json_encode([
            'type' => $entityType,
            'id' => $id,
            'code' => $entity[$codeMap[$entityType]],
            'name' => $entity[$nameMap[$entityType]],
        ]);
        Audit::log('Label Printed', 'barcode_labels', $id, null, ['entity_type' => $entityType]);
        echo $this->render('barcode.print-label', [
            'entity' => $entity,
            'entityType' => $entityType,
            'barcodeData' => $barcodeData,
            'codeField' => $codeMap[$entityType],
            'nameField' => $nameMap[$entityType],
        ]);
        exit;
    }
}
