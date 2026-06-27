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

    public function lookup(string $barcode): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $tables = ['samples' => 'sample_code', 'products' => 'product_code', 'instruments' => 'instrument_code', 'chemical_inventory' => 'catalog_number'];
        $result = null;
        foreach ($tables as $table => $column) {
            $stmt = $db->prepare("SELECT *, ? AS entity_type FROM {$table} WHERE {$column} = ? LIMIT 1");
            $stmt->execute([$table, $barcode]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) { $result = $row; break; }
        }
        if (!$result) {
            return $this->json(['error' => 'No entity found for barcode: ' . $barcode], 404);
        }
        Audit::log('Barcode Scanned', 'barcode_scans', null, null, ['barcode' => $barcode, 'entity_type' => $result['entity_type']]);
        return $this->json($result);
    }

    public function scanLog(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $logs = $db->query("
            SELECT bs.*, u.full_name AS scanned_by_name
            FROM barcode_scans bs
            LEFT JOIN users u ON bs.scanned_by = u.id
            ORDER BY bs.created_at DESC
            LIMIT 200
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('barcode.scan-log', ['logs' => $logs]);
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
