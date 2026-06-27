<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TestItem;
use App\Models\Method;
use App\Models\Unit;
use App\Models\SampleType;
use App\Models\InstrumentLocation;
use App\Models\InstrumentCalibration;
use App\Models\ChemicalInventory;
use App\Models\EmailConfig;

class MasterDataController extends BaseController
{
    // ============================================================
    // CONTROL PANEL
    // ============================================================
    public function controlPanel(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $stats = [
            'customers' => (int)$db->query("SELECT COUNT(*) FROM customers WHERE is_active = TRUE")->fetchColumn(),
            'customers_active' => (int)$db->query("SELECT COUNT(*) FROM customers WHERE is_active = TRUE")->fetchColumn(),
            'products' => (int)$db->query("SELECT COUNT(*) FROM products WHERE is_active = TRUE")->fetchColumn(),
            'tests' => (int)$db->query("SELECT COUNT(*) FROM tests WHERE is_active = TRUE")->fetchColumn(),
            'methods' => (int)$db->query("SELECT COUNT(*) FROM methods")->fetchColumn(),
            'units' => (int)$db->query("SELECT COUNT(*) FROM units")->fetchColumn(),
            'sample_types' => (int)$db->query("SELECT COUNT(*) FROM sample_types WHERE is_active = TRUE")->fetchColumn(),
            'locations' => (int)$db->query("SELECT COUNT(*) FROM instrument_locations WHERE is_active = TRUE")->fetchColumn(),
            'chemicals' => (int)$db->query("SELECT COUNT(*) FROM chemical_inventory WHERE is_active = TRUE")->fetchColumn(),
            'calibrations' => (int)$db->query("SELECT COUNT(*) FROM instrument_calibrations")->fetchColumn(),
            'instruments' => (int)$db->query("SELECT COUNT(*) FROM instruments WHERE is_active = TRUE")->fetchColumn(),
            'users' => (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = TRUE")->fetchColumn(),
            'coa_templates' => (int)$db->query("SELECT COUNT(*) FROM coa_templates")->fetchColumn(),
            'email_configs' => (int)$db->query("SELECT COUNT(*) FROM email_configurations")->fetchColumn(),
            'product_tests' => (int)$db->query("SELECT COUNT(*) FROM products_tests")->fetchColumn(),
            'manufacturers' => (int)$db->query("SELECT COUNT(*) FROM manufacturers")->fetchColumn(),
        ];
        $recentActivity = $db->query("
            SELECT al.*, u.full_name AS user_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.entity_type IN ('customers','products','tests','methods','units','sample_types','instrument_locations','chemical_inventory','coa_templates','manufacturers')
            ORDER BY al.created_at DESC LIMIT 15
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('master.control-panel', [
            'stats' => $stats,
            'recentActivity' => $recentActivity,
        ]);
    }

    // ============================================================
    // GLOBAL MASTER DATA SEARCH
    // ============================================================
    public function search(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) return $this->json([]);
        $db = \App\Helpers\Database::connect();
        $term = '%' . $q . '%';
        $results = [];

        $tables = [
            'customers' => ['label_col' => 'customer_name', 'code_col' => 'customer_code', 'url_prefix' => '/master/customers/'],
            'products' => ['label_col' => 'product_name', 'code_col' => 'product_code', 'url_prefix' => '/master/products/'],
            'tests' => ['label_col' => 'test_name', 'code_col' => 'test_code', 'url_prefix' => '/master/tests/'],
            'methods' => ['label_col' => 'method_name', 'code_col' => 'method_code', 'url_prefix' => '/master/methods/'],
            'units' => ['label_col' => 'unit_name', 'code_col' => 'unit_code', 'url_prefix' => '#'],
            'sample_types' => ['label_col' => 'type_name', 'code_col' => 'type_code', 'url_prefix' => '/master/sample-types/'],
            'instrument_locations' => ['label_col' => 'location_name', 'code_col' => 'location_code', 'url_prefix' => '/master/instrument-locations/'],
            'chemical_inventory' => ['label_col' => 'chemical_name', 'code_col' => 'cas_number', 'url_prefix' => '#'],
            'manufacturers' => ['label_col' => 'company_name', 'code_col' => '', 'url_prefix' => '/master/manufacturers/'],
        ];

        foreach ($tables as $table => $cfg) {
            $stmt = $db->prepare("SELECT id, {$cfg['label_col']} AS label, {$cfg['code_col']} AS code FROM {$table} WHERE {$cfg['label_col']} ILIKE ? OR {$cfg['code_col']} ILIKE ? LIMIT 5");
            $stmt->execute([$term, $term]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $results[] = [
                    'table' => $table,
                    'label' => $row['label'] . ($row['code'] ? " ({$row['code']})" : ''),
                    'subtitle' => $row['code'] ?? '',
                    'url' => $cfg['url_prefix'] . $row['id'],
                ];
            }
        }

        return $this->json($results);
    }

    // ============================================================
    // EXPORT MASTER DATA AS CSV
    // ============================================================
    public function export(string $table): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $allowed = ['customers','products','tests','methods','units','sample_types','instrument_locations','chemical_inventory','manufacturers'];
        if (!in_array($table, $allowed)) { exit('Invalid table'); }

        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("SELECT * FROM {$table} ORDER BY id LIMIT 5000");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $table . '_export_' . date('Y-m-d') . '.csv"');
        $fp = fopen('php://output', 'w');
        fputcsv($fp, array_keys($rows[0] ?? []));
        foreach ($rows as $row) fputcsv($fp, $row);
        fclose($fp);
        exit;
    }

    // ============================================================
    // CUSTOMERS
    // ============================================================
    public function customers(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $data = Customer::paginate(20);
        return $this->render('master.customers', $data);
    }

    public function createCustomer(): string
    {
        Auth::requireRole('Admin');
        return $this->render('master.customer-form', ['customer' => null]);
    }

    public function storeCustomer(): void
    {
        Auth::requireRole('Admin');
        $customer = Customer::create([
            'customer_code' => $_POST['customer_code'],
            'customer_name' => $_POST['customer_name'],
            'address' => $_POST['address'] ?? null,
            'city' => $_POST['city'] ?? null,
            'state' => $_POST['state'] ?? null,
            'country' => $_POST['country'] ?? null,
            'postal_code' => $_POST['postal_code'] ?? null,
            'contact_person' => $_POST['contact_person'] ?? null,
            'email' => $_POST['email'] ?? null,
            'phone' => $_POST['phone'] ?? null,
        ]);
        Audit::log('Customer Created', 'customers', $customer['id'] ?? null);
        session_flash('success', 'Customer created successfully.');
        redirect('/master/customers');
    }

    public function editCustomer(int $id): string
    {
        Auth::requireRole('Admin');
        $customer = Customer::find($id);
        if (!$customer) { session_flash('error', 'Customer not found.'); redirect('/master/customers'); }
        return $this->render('master.customer-form', ['customer' => $customer]);
    }

    public function updateCustomer(int $id): void
    {
        Auth::requireRole('Admin');
        Customer::update($id, [
            'customer_code' => $_POST['customer_code'],
            'customer_name' => $_POST['customer_name'],
            'address' => $_POST['address'] ?? null,
            'city' => $_POST['city'] ?? null,
            'state' => $_POST['state'] ?? null,
            'country' => $_POST['country'] ?? null,
            'postal_code' => $_POST['postal_code'] ?? null,
            'contact_person' => $_POST['contact_person'] ?? null,
            'email' => $_POST['email'] ?? null,
            'phone' => $_POST['phone'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 'TRUE' : 'FALSE',
        ]);
        Audit::log('Customer Updated', 'customers', $id);
        session_flash('success', 'Customer updated successfully.');
        redirect('/master/customers');
    }

    // ============================================================
    // PRODUCTS
    // ============================================================
    public function products(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $data = Product::paginate(20);
        return $this->render('master.products', $data);
    }

    public function createProduct(): string
    {
        Auth::requireRole('Admin');
        return $this->render('master.product-form', ['product' => null]);
    }

    public function storeProduct(): void
    {
        Auth::requireRole('Admin');
        $product = Product::create([
            'product_code' => $_POST['product_code'],
            'product_name' => $_POST['product_name'],
            'description' => $_POST['description'] ?? null,
            'category' => $_POST['category'] ?? null,
        ]);
        Audit::log('Product Created', 'products', $product['id'] ?? null);
        session_flash('success', 'Product created successfully.');
        redirect('/master/products');
    }

    public function editProduct(int $id): string
    {
        Auth::requireRole('Admin');
        $product = Product::find($id);
        if (!$product) { session_flash('error', 'Product not found.'); redirect('/master/products'); }
        return $this->render('master.product-form', ['product' => $product]);
    }

    public function updateProduct(int $id): void
    {
        Auth::requireRole('Admin');
        Product::update($id, [
            'product_code' => $_POST['product_code'],
            'product_name' => $_POST['product_name'],
            'description' => $_POST['description'] ?? null,
            'category' => $_POST['category'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 'TRUE' : 'FALSE',
        ]);
        Audit::log('Product Updated', 'products', $id);
        session_flash('success', 'Product updated successfully.');
        redirect('/master/products');
    }

    // ============================================================
    // TEST PARAMETERS
    // ============================================================
    public function tests(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $tests = TestItem::allWithDetails();
        return $this->render('master.tests', ['tests' => $tests]);
    }

    public function createTest(): string
    {
        Auth::requireRole('Admin');
        $methods = Method::all();
        $units = Unit::all();
        $sampleTypes = SampleType::active();
        return $this->render('master.test-form', [
            'test' => null, 'methods' => $methods, 'units' => $units, 'sampleTypes' => $sampleTypes,
        ]);
    }

    public function storeTest(): void
    {
        Auth::requireRole('Admin');
        TestItem::create([
            'test_code' => $_POST['test_code'],
            'test_name' => $_POST['test_name'],
            'method_id' => $_POST['method_id'] ?: null,
            'unit_id' => $_POST['unit_id'] ?: null,
            'sample_type_id' => $_POST['sample_type_id'] ?: null,
            'min_spec_limit' => $_POST['min_spec_limit'] ?: null,
            'max_spec_limit' => $_POST['max_spec_limit'] ?: null,
            'spec_limit_text' => $_POST['spec_limit_text'] ?? null,
        ]);
        Audit::log('Test Created', 'tests');
        session_flash('success', 'Test parameter created successfully.');
        redirect('/master/tests');
    }

    public function editTest(int $id): string
    {
        Auth::requireRole('Admin');
        $test = TestItem::findWithDetails($id);
        if (!$test) { session_flash('error', 'Test not found.'); redirect('/master/tests'); }
        $methods = Method::all();
        $units = Unit::all();
        $sampleTypes = SampleType::active();
        return $this->render('master.test-form', [
            'test' => $test, 'methods' => $methods, 'units' => $units, 'sampleTypes' => $sampleTypes,
        ]);
    }

    public function updateTest(int $id): void
    {
        Auth::requireRole('Admin');
        TestItem::update($id, [
            'test_code' => $_POST['test_code'],
            'test_name' => $_POST['test_name'],
            'method_id' => $_POST['method_id'] ?: null,
            'unit_id' => $_POST['unit_id'] ?: null,
            'sample_type_id' => $_POST['sample_type_id'] ?: null,
            'min_spec_limit' => $_POST['min_spec_limit'] ?: null,
            'max_spec_limit' => $_POST['max_spec_limit'] ?: null,
            'spec_limit_text' => $_POST['spec_limit_text'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 'TRUE' : 'FALSE',
        ]);
        Audit::log('Test Updated', 'tests', $id);
        session_flash('success', 'Test parameter updated successfully.');
        redirect('/master/tests');
    }

    // ============================================================
    // METHODS (Tile Format)
    // ============================================================
    public function methods(): string
    {
        Auth::requireRole('Admin');
        $items = Method::all();
        return $this->render('master.methods', ['items' => $items]);
    }

    public function createMethod(): void
    {
        Auth::requireRole('Admin');
        Method::create([
            'method_code' => $_POST['method_code'],
            'method_name' => $_POST['method_name'],
            'description' => $_POST['description'] ?? null,
        ]);
        session_flash('success', 'Method created successfully.');
        redirect('/master/methods');
    }

    public function editMethodJson(int $id): string
    {
        Auth::requireRole('Admin');
        $method = Method::find($id);
        return $this->json($method ?: ['error' => 'Not found']);
    }

    public function updateMethod(int $id): void
    {
        Auth::requireRole('Admin');
        Method::update($id, [
            'method_code' => $_POST['method_code'],
            'method_name' => $_POST['method_name'],
            'description' => $_POST['description'] ?? null,
        ]);
        session_flash('success', 'Method updated successfully.');
        redirect('/master/methods');
    }

    public function deleteMethod(int $id): void
    {
        Auth::requireRole('Admin');
        Method::delete($id);
        session_flash('success', 'Method deleted.');
        redirect('/master/methods');
    }

    // ============================================================
    // UNITS
    // ============================================================
    public function units(): string
    {
        Auth::requireRole('Admin');
        $data = Unit::paginate(20);
        return $this->render('master.units', $data);
    }

    public function createUnit(): void
    {
        Auth::requireRole('Admin');
        Unit::create([
            'unit_code' => $_POST['unit_code'],
            'unit_name' => $_POST['unit_name'],
        ]);
        session_flash('success', 'Unit created successfully.');
        redirect('/master/units');
    }

    // ============================================================
    // SAMPLE TYPES
    // ============================================================
    public function sampleTypes(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $sampleTypes = SampleType::paginate(50);
        return $this->render('master.sample-types', ['sampleTypes' => $sampleTypes['items']]);
    }

    public function storeSampleType(): void
    {
        Auth::requireRole('Admin');
        SampleType::create([
            'type_code' => $_POST['type_code'],
            'type_name' => $_POST['type_name'],
            'description' => $_POST['description'] ?? null,
        ]);
        session_flash('success', 'Sample type created.');
        redirect('/master/sample-types');
    }

    public function editSampleTypeJson(int $id): string
    {
        Auth::requireRole('Admin');
        return $this->json(SampleType::find($id) ?: ['error' => 'Not found']);
    }

    public function updateSampleType(int $id): void
    {
        Auth::requireRole('Admin');
        SampleType::update($id, [
            'type_code' => $_POST['type_code'],
            'type_name' => $_POST['type_name'],
            'description' => $_POST['description'] ?? null,
        ]);
        session_flash('success', 'Sample type updated.');
        redirect('/master/sample-types');
    }

    public function toggleSampleType(int $id): void
    {
        Auth::requireRole('Admin');
        $st = SampleType::find($id);
        if ($st) SampleType::update($id, ['is_active' => $st['is_active'] ? 'FALSE' : 'TRUE']);
        redirect('/master/sample-types');
    }

    // ============================================================
    // INSTRUMENT LOCATIONS
    // ============================================================
    public function instrumentLocations(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $locations = InstrumentLocation::paginate(50);
        return $this->render('master.instrument-locations', ['locations' => $locations['items']]);
    }

    public function storeInstrumentLocation(): void
    {
        Auth::requireRole('Admin');
        InstrumentLocation::create([
            'location_code' => $_POST['location_code'],
            'location_name' => $_POST['location_name'],
            'building' => $_POST['building'] ?? null,
            'floor' => $_POST['floor'] ?? null,
            'room' => $_POST['room'] ?? null,
        ]);
        session_flash('success', 'Location created.');
        redirect('/master/instrument-locations');
    }

    public function editInstrumentLocationJson(int $id): string
    {
        Auth::requireRole('Admin');
        return $this->json(InstrumentLocation::find($id) ?: ['error' => 'Not found']);
    }

    public function updateInstrumentLocation(int $id): void
    {
        Auth::requireRole('Admin');
        InstrumentLocation::update($id, [
            'location_code' => $_POST['location_code'],
            'location_name' => $_POST['location_name'],
            'building' => $_POST['building'] ?? null,
            'floor' => $_POST['floor'] ?? null,
            'room' => $_POST['room'] ?? null,
        ]);
        session_flash('success', 'Location updated.');
        redirect('/master/instrument-locations');
    }

    public function toggleInstrumentLocation(int $id): void
    {
        Auth::requireRole('Admin');
        $loc = InstrumentLocation::find($id);
        if ($loc) InstrumentLocation::update($id, ['is_active' => $loc['is_active'] ? 'FALSE' : 'TRUE']);
        redirect('/master/instrument-locations');
    }

    // ============================================================
    // INSTRUMENT CALIBRATIONS
    // ============================================================
    public function calibrations(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $calibrations = $db->query("
            SELECT ic.*, i.instrument_name, i.instrument_code
            FROM instrument_calibrations ic
            JOIN instruments i ON ic.instrument_id = i.id
            ORDER BY ic.calibration_date DESC
            LIMIT 100
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $upcoming = $db->query("
            SELECT ic.*, i.instrument_name
            FROM instrument_calibrations ic
            JOIN instruments i ON ic.instrument_id = i.id
            WHERE ic.next_calibration_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days'
            ORDER BY ic.next_calibration_date
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('master.instrument-calibrations', [
            'calibrations' => $calibrations,
            'upcoming' => $upcoming,
        ]);
    }

    public function createCalibration(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $instruments = $db->query("SELECT id, instrument_code, instrument_name FROM instruments WHERE is_active = TRUE ORDER BY instrument_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('master.calibration-form', [
            'calibration' => null, 'instruments' => $instruments,
        ]);
    }

    public function storeCalibration(): void
    {
        Auth::requireRole('Admin');
        InstrumentCalibration::create([
            'instrument_id' => $_POST['instrument_id'],
            'calibration_date' => $_POST['calibration_date'],
            'calibrated_by' => $_POST['calibrated_by'] ?? null,
            'calibration_standard' => $_POST['calibration_standard'] ?? null,
            'result' => $_POST['result'] ?? 'Pass',
            'certificate_number' => $_POST['certificate_number'] ?? null,
            'next_calibration_date' => $_POST['next_calibration_date'] ?? null,
            'notes' => $_POST['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);
        // Update instrument's last/next calibration dates
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE instruments SET last_calibration_date = ?, next_calibration_date = ? WHERE id = ?")
            ->execute([$_POST['calibration_date'], $_POST['next_calibration_date'] ?: null, $_POST['instrument_id']]);
        Audit::log('Calibration Recorded', 'instrument_calibrations');
        session_flash('success', 'Calibration recorded.');
        redirect('/master/calibrations');
    }

    // ============================================================
    // CHEMICAL INVENTORY
    // ============================================================
    public function chemicalInventory(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $where = '';
        $params = [];
        if (!empty($_GET['status'])) {
            $where = 'WHERE status = ?';
            $params[] = $_GET['status'];
        }
        $stmt = $db->prepare("SELECT * FROM chemical_inventory {$where} ORDER BY chemical_name LIMIT 100");
        $stmt->execute($params);
        $chemicals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stats = ChemicalInventory::dashboardStats();
        return $this->render('master.chemical-inventory', [
            'chemicals' => $chemicals, 'stats' => $stats,
        ]);
    }

    public function storeChemical(): void
    {
        Auth::requireRole('Admin');
        $data = [
            'chemical_name' => $_POST['chemical_name'],
            'cas_number' => $_POST['cas_number'] ?? null,
            'catalog_number' => $_POST['catalog_number'] ?? null,
            'supplier' => $_POST['supplier'] ?? null,
            'unit_type' => $_POST['unit_type'],
            'quantity' => $_POST['quantity'] ?? 0,
            'minimum_quantity' => $_POST['minimum_quantity'] ?? 0,
            'unit_price' => $_POST['unit_price'] ?: null,
            'storage_location' => $_POST['storage_location'] ?? null,
            'safety_data_sheet' => $_POST['safety_data_sheet'] ?? null,
            'received_date' => $_POST['received_date'] ?: null,
            'expiry_date' => $_POST['expiry_date'] ?: null,
            'created_by' => Auth::id(),
        ];
        ChemicalInventory::create($data);
        Audit::log('Chemical Added', 'chemical_inventory');
        session_flash('success', 'Chemical added to inventory.');
        redirect('/master/chemical-inventory');
    }

    public function editChemicalJson(int $id): string
    {
        Auth::requireRole('Admin');
        return $this->json(ChemicalInventory::find($id) ?: ['error' => 'Not found']);
    }

    public function updateChemical(int $id): void
    {
        Auth::requireRole('Admin');
        ChemicalInventory::update($id, [
            'chemical_name' => $_POST['chemical_name'],
            'cas_number' => $_POST['cas_number'] ?? null,
            'catalog_number' => $_POST['catalog_number'] ?? null,
            'supplier' => $_POST['supplier'] ?? null,
            'unit_type' => $_POST['unit_type'],
            'quantity' => $_POST['quantity'] ?? 0,
            'minimum_quantity' => $_POST['minimum_quantity'] ?? 0,
            'unit_price' => $_POST['unit_price'] ?: null,
            'storage_location' => $_POST['storage_location'] ?? null,
            'safety_data_sheet' => $_POST['safety_data_sheet'] ?? null,
            'received_date' => $_POST['received_date'] ?: null,
            'expiry_date' => $_POST['expiry_date'] ?: null,
        ]);
        session_flash('success', 'Chemical updated.');
        redirect('/master/chemical-inventory');
    }

    public function adjustChemical(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $chem = ChemicalInventory::find($id);
        if ($chem) {
            $adj = (float)($_POST['adjustment'] ?? 0);
            $newQty = max(0, (float)$chem['quantity'] + $adj);
            $status = $chem['status'];
            if ($newQty <= 0) $status = 'Depleted';
            elseif ($newQty <= (float)$chem['minimum_quantity']) $status = 'Low Stock';
            else $status = 'In Stock';
            ChemicalInventory::update($id, ['quantity' => $newQty, 'status' => $status]);
            Audit::log('Chemical Adjusted', 'chemical_inventory', $id, ['quantity' => $chem['quantity']], ['quantity' => $newQty]);
        }
        session_flash('success', 'Inventory adjusted.');
        redirect('/master/chemical-inventory');
    }

    // ============================================================
    // COA TEMPLATES
    // ============================================================
    public function coaTemplates(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $templates = $db->query("SELECT * FROM coa_templates ORDER BY is_default DESC, template_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('master.coa-templates', ['templates' => $templates]);
    }

    public function storeCoaTemplate(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        if (!empty($_POST['is_default'])) {
            $db->exec("UPDATE coa_templates SET is_default = FALSE");
        }
        $stmt = $db->prepare("INSERT INTO coa_templates (template_name, template_html, is_default, is_active, page_size, orientation, margin_top, margin_bottom, margin_left, margin_right, logo_path, scada_logo_path, watermark_text, show_qr_code, show_barcode, show_signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['template_name'], $_POST['template_html'],
            !empty($_POST['is_default']) ? true : false,
            !empty($_POST['is_active']) ? true : false,
            $_POST['page_size'] ?? 'A4',
            $_POST['orientation'] ?? 'portrait',
            (int)($_POST['margin_top'] ?? 15),
            (int)($_POST['margin_bottom'] ?? 15),
            (int)($_POST['margin_left'] ?? 15),
            (int)($_POST['margin_right'] ?? 15),
            $_POST['logo_path'] ?? null,
            $_POST['scada_logo_path'] ?? null,
            $_POST['watermark_text'] ?? null,
            !empty($_POST['show_qr_code']) ? true : false,
            !empty($_POST['show_barcode']) ? true : false,
            !empty($_POST['show_signature']) ? true : false,
        ]);
        Audit::log('COA Template Created', 'coa_templates');
        session_flash('success', 'COA template created.');
        redirect('/master/coa-templates');
    }

    public function editCoaTemplateJson(int $id): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM coa_templates WHERE id = ?");
        $stmt->execute([$id]);
        return $this->json($stmt->fetch(\PDO::FETCH_ASSOC) ?: ['error' => 'Not found']);
    }

    public function updateCoaTemplate(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        if (!empty($_POST['is_default'])) {
            $db->exec("UPDATE coa_templates SET is_default = FALSE");
        }
        $stmt = $db->prepare("UPDATE coa_templates SET template_name = ?, template_html = ?, is_default = ?, is_active = ?, page_size = ?, orientation = ?, margin_top = ?, margin_bottom = ?, margin_left = ?, margin_right = ?, logo_path = ?, scada_logo_path = ?, watermark_text = ?, show_qr_code = ?, show_barcode = ?, show_signature = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            $_POST['template_name'], $_POST['template_html'],
            !empty($_POST['is_default']) ? true : false,
            !empty($_POST['is_active']) ? true : false,
            $_POST['page_size'] ?? 'A4',
            $_POST['orientation'] ?? 'portrait',
            (int)($_POST['margin_top'] ?? 15),
            (int)($_POST['margin_bottom'] ?? 15),
            (int)($_POST['margin_left'] ?? 15),
            (int)($_POST['margin_right'] ?? 15),
            $_POST['logo_path'] ?? null,
            $_POST['scada_logo_path'] ?? null,
            $_POST['watermark_text'] ?? null,
            !empty($_POST['show_qr_code']) ? true : false,
            !empty($_POST['show_barcode']) ? true : false,
            !empty($_POST['show_signature']) ? true : false,
            $id,
        ]);
        session_flash('success', 'COA template updated.');
        redirect('/master/coa-templates');
    }

    public function setDefaultCoaTemplate(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->exec("UPDATE coa_templates SET is_default = FALSE");
        $db->prepare("UPDATE coa_templates SET is_default = TRUE WHERE id = ?")->execute([$id]);
        session_flash('success', 'Default template updated.');
        redirect('/master/coa-templates');
    }

    public function previewCoaTemplate(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM coa_templates WHERE id = ?");
        $stmt->execute([$id]);
        $tpl = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$tpl) { echo 'Template not found'; exit; }
        $html = $tpl['template_html'];
        $placeholders = [
            '[[COMPANY_NAME]]' => 'Your Laboratory',
            '[[COMPANY_ADDRESS]]' => '123 Lab Street',
            '[[COA_NUMBER]]' => 'COA-DEMO-2026-00001',
            '[[SAMPLE_CODE]]' => 'SMP-DEMO-001',
            '[[CUSTOMER_NAME]]' => 'Demo Customer Inc.',
            '[[PRODUCT_NAME]]' => 'Glucose Syrup',
            '[[BATCH_NUMBER]]' => 'BATCH-2026-001',
            '[[MANUFACTURE_DATE]]' => '2026-06-01',
            '[[EXPIRY_DATE]]' => '2027-06-01',
            '[[ANALYSIS_DATE]]' => '2026-06-10',
            '[[APPROVED_BY]]' => 'Admin User',
        ];
        $html = str_replace(array_keys($placeholders), array_values($placeholders), $html);
        $demoRows = '';
        foreach (['pH', 'Dry Substance', 'DP1 (Glucose)', 'Viscosity', 'Color'] as $t) {
            $demoRows .= "<tr><td>{$t}</td><td>HPLC</td><td>95.0-99.5%</td><td>97.2</td><td>%</td><td>Pass</td></tr>";
        }
        $html = str_replace('[[RESULTS_ROWS]]', $demoRows, $html);
        echo $html;
        exit;
    }

    // ============================================================
    // EMAIL CONFIGURATION
    // ============================================================
    public function emailConfig(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $configs = $db->query("SELECT * FROM email_configurations ORDER BY is_default DESC, config_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('master.email-config', ['configs' => $configs]);
    }

    public function storeEmailConfig(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        if (!empty($_POST['is_default'])) {
            $db->exec("UPDATE email_configurations SET is_default = FALSE");
        }
        $db->prepare("INSERT INTO email_configurations (config_name, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, from_address, from_name, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $_POST['config_name'], $_POST['smtp_host'], $_POST['smtp_port'] ?? 587,
                $_POST['smtp_encryption'] ?? 'tls', $_POST['smtp_username'] ?? null,
                $_POST['smtp_password'] ? password_hash($_POST['smtp_password'], PASSWORD_DEFAULT) : null,
                $_POST['from_address'], $_POST['from_name'] ?? null,
                !empty($_POST['is_default']),
            ]);
        session_flash('success', 'Email configuration saved.');
        redirect('/master/email-config');
    }

    public function editEmailConfigJson(int $id): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM email_configurations WHERE id = ?");
        $stmt->execute([$id]);
        $cfg = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($cfg) unset($cfg['smtp_password']);
        return $this->json($cfg ?: ['error' => 'Not found']);
    }

    public function updateEmailConfig(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        if (!empty($_POST['is_default'])) {
            $db->exec("UPDATE email_configurations SET is_default = FALSE");
        }
        $sql = "UPDATE email_configurations SET config_name=?, smtp_host=?, smtp_port=?, smtp_encryption=?, smtp_username=?, from_address=?, from_name=?, is_default=?, updated_at=CURRENT_TIMESTAMP";
        $params = [$_POST['config_name'], $_POST['smtp_host'], $_POST['smtp_port'] ?? 587, $_POST['smtp_encryption'] ?? 'tls', $_POST['smtp_username'] ?? null, $_POST['from_address'], $_POST['from_name'] ?? null, !empty($_POST['is_default'])];
        if (!empty($_POST['smtp_password'])) {
            $sql .= ", smtp_password=?";
            $params[] = password_hash($_POST['smtp_password'], PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id=?";
        $params[] = $id;
        $db->prepare($sql)->execute($params);
        session_flash('success', 'Email configuration updated.');
        redirect('/master/email-config');
    }

    public function setDefaultEmailConfig(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->exec("UPDATE email_configurations SET is_default = FALSE");
        $db->prepare("UPDATE email_configurations SET is_default = TRUE WHERE id = ?")->execute([$id]);
        session_flash('success', 'Default email config updated.');
        redirect('/master/email-config');
    }

    public function testEmailConfig(int $id): string
    {
        Auth::requireRole('Admin');
        return $this->json(['message' => 'Test email functionality requires PHPMailer. Configure SMTP and install phpmailer/phpmailer.']);
    }

    // ============================================================
    // MANUFACTURERS
    // ============================================================
    public function manufacturers(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $items = $db->query("SELECT * FROM manufacturers ORDER BY company_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('master.manufacturers', ['items' => $items]);
    }

    public function createManufacturer(): string
    {
        Auth::requireRole('Admin');
        return $this->render('master.manufacturer-form', ['manufacturer' => null]);
    }

    public function storeManufacturer(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO manufacturers (company_name, address, city, state, country, postal_code, phone, email, website, logo_path, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $_POST['company_name'],
                $_POST['address'] ?? null,
                $_POST['city'] ?? null,
                $_POST['state'] ?? null,
                $_POST['country'] ?? null,
                $_POST['postal_code'] ?? null,
                $_POST['phone'] ?? null,
                $_POST['email'] ?? null,
                $_POST['website'] ?? null,
                $_POST['logo_path'] ?? null,
                !empty($_POST['is_active']),
            ]);
        Audit::log('Manufacturer Created', 'manufacturers');
        session_flash('success', 'Manufacturer created successfully.');
        redirect('/master/manufacturers');
    }

    public function editManufacturer(int $id): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM manufacturers WHERE id = ?");
        $stmt->execute([$id]);
        $manufacturer = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$manufacturer) { session_flash('error', 'Manufacturer not found.'); redirect('/master/manufacturers'); }
        return $this->render('master.manufacturer-form', ['manufacturer' => $manufacturer]);
    }

    public function updateManufacturer(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE manufacturers SET company_name=?, address=?, city=?, state=?, country=?, postal_code=?, phone=?, email=?, website=?, logo_path=?, is_active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([
                $_POST['company_name'],
                $_POST['address'] ?? null,
                $_POST['city'] ?? null,
                $_POST['state'] ?? null,
                $_POST['country'] ?? null,
                $_POST['postal_code'] ?? null,
                $_POST['phone'] ?? null,
                $_POST['email'] ?? null,
                $_POST['website'] ?? null,
                $_POST['logo_path'] ?? null,
                !empty($_POST['is_active']),
                $id,
            ]);
        Audit::log('Manufacturer Updated', 'manufacturers', $id);
        session_flash('success', 'Manufacturer updated successfully.');
        redirect('/master/manufacturers');
    }

    public function deleteManufacturer(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM manufacturers WHERE id = ?")->execute([$id]);
        Audit::log('Manufacturer Deleted', 'manufacturers', $id);
        session_flash('success', 'Manufacturer deleted.');
        redirect('/master/manufacturers');
    }

    // ============================================================
    // PRODUCT-TEST MAPPING
    // ============================================================
    public function productTests(): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $mappings = \App\Models\ProductTest::withProductAndTest();
        $products = \App\Models\Product::active();
        $allTests = \App\Models\TestItem::allWithDetails();
        return $this->render('master.product-tests', [
            'mappings' => $mappings,
            'products' => $products,
            'allTests' => $allTests,
        ]);
    }

    public function storeProductTest(): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO products_tests (product_id, test_id, min_spec_limit, max_spec_limit, spec_limit_text, sort_order) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([
                $_POST['product_id'],
                $_POST['test_id'],
                $_POST['min_spec_limit'] ?: null,
                $_POST['max_spec_limit'] ?: null,
                $_POST['spec_limit_text'] ?: null,
                $_POST['sort_order'] ?? 0,
            ]);
        Audit::log('Product-Test Mapping Created', 'products_tests', null, null, ['product_id' => $_POST['product_id'], 'test_id' => $_POST['test_id']]);
        session_flash('success', 'Product-test mapping created.');
        redirect('/master/product-tests');
    }

    public function editProductTestJson(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT pt.*, p.product_code, p.product_name, t.test_code, t.test_name
            FROM products_tests pt
            JOIN products p ON pt.product_id = p.id
            JOIN tests t ON pt.test_id = t.id
            WHERE pt.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $this->json($data ?: ['error' => 'Not found']);
    }

    public function updateProductTest(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE products_tests SET min_spec_limit=?, max_spec_limit=?, spec_limit_text=?, sort_order=?, is_active=? WHERE id=?")
            ->execute([
                $_POST['min_spec_limit'] ?: null,
                $_POST['max_spec_limit'] ?: null,
                $_POST['spec_limit_text'] ?: null,
                $_POST['sort_order'] ?? 0,
                $_POST['is_active'] ?? true,
                $id,
            ]);
        Audit::log('Product-Test Mapping Updated', 'products_tests', $id);
        session_flash('success', 'Product-test mapping updated.');
        redirect('/master/product-tests');
    }

    public function deleteProductTest(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM products_tests WHERE id = ?")->execute([$id]);
        Audit::log('Product-Test Mapping Deleted', 'products_tests', $id);
        session_flash('success', 'Product-test mapping deleted.');
        redirect('/master/product-tests');
    }
}
