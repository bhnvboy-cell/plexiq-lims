<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class SupplierController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $suppliers = $db->query("
            SELECT s.*,
                (SELECT COUNT(*) FROM supplier_qualifications sq WHERE sq.supplier_id = s.id) AS qualification_count,
                (SELECT COUNT(*) FROM supplier_products sp WHERE sp.supplier_id = s.id) AS product_count
            FROM suppliers s
            ORDER BY s.supplier_name
        ")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('suppliers.index', ['suppliers' => $suppliers]);
    }

    public function create(): string
    {
        Auth::requireRole('Admin');
        return $this->render('suppliers.form', ['supplier' => null]);
    }

    public function store(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO suppliers (supplier_code, supplier_name, contact_person, email, phone, address, city, state, country, postal_code, website, supplier_type, payment_terms, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['supplier_code'],
            $_POST['supplier_name'],
            $_POST['contact_person'] ?? null,
            $_POST['email'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['address'] ?? null,
            $_POST['city'] ?? null,
            $_POST['state'] ?? null,
            $_POST['country'] ?? null,
            $_POST['postal_code'] ?? null,
            $_POST['website'] ?? null,
            $_POST['supplier_type'] ?? 'Raw Material',
            $_POST['payment_terms'] ?? null,
            $_POST['notes'] ?? null,
        ]);
        $supplierId = $db->lastInsertId();
        Audit::log('Supplier Created', 'suppliers', $supplierId);
        session_flash('success', 'Supplier created.');
        $this->redirect('/suppliers');
    }

    public function show(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $supplier = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$supplier) { session_flash('error', 'Supplier not found.'); $this->redirect('/suppliers'); }
        $qualifications = $db->prepare("SELECT sq.*, u.full_name AS assessed_by_name FROM supplier_qualifications sq LEFT JOIN users u ON sq.assessed_by = u.id WHERE sq.supplier_id = ? ORDER BY sq.assessment_date DESC");
        $qualifications->execute([$id]);
        $products = $db->prepare("SELECT sp.*, p.product_code, p.product_name FROM supplier_products sp JOIN products p ON sp.product_id = p.id WHERE sp.supplier_id = ? ORDER BY p.product_name");
        $products->execute([$id]);
        return $this->render('suppliers.show', [
            'supplier' => $supplier,
            'qualifications' => $qualifications->fetchAll(\PDO::FETCH_ASSOC),
            'products' => $products->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    public function edit(int $id): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $supplier = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$supplier) { session_flash('error', 'Supplier not found.'); $this->redirect('/suppliers'); }
        return $this->render('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE suppliers SET supplier_code = ?, supplier_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, country = ?, postal_code = ?, website = ?, supplier_type = ?, payment_terms = ?, notes = ?, status = ?, is_approved = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['supplier_code'],
            $_POST['supplier_name'],
            $_POST['contact_person'] ?? null,
            $_POST['email'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['address'] ?? null,
            $_POST['city'] ?? null,
            $_POST['state'] ?? null,
            $_POST['country'] ?? null,
            $_POST['postal_code'] ?? null,
            $_POST['website'] ?? null,
            $_POST['supplier_type'] ?? 'Raw Material',
            $_POST['payment_terms'] ?? null,
            $_POST['notes'] ?? null,
            $_POST['approval_status'] ?? 'Pending',
            !empty($_POST['is_active']),
            $id,
        ]);
        Audit::log('Supplier Updated', 'suppliers', $id);
        session_flash('success', 'Supplier updated.');
        $this->redirect('/suppliers/' . $id);
    }

    public function qualifications(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $supplier = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$supplier) { session_flash('error', 'Supplier not found.'); $this->redirect('/suppliers'); }
        $quals = $db->prepare("SELECT sq.*, u.full_name AS assessed_by_name FROM supplier_qualifications sq LEFT JOIN users u ON sq.assessed_by = u.id WHERE sq.supplier_id = ? ORDER BY sq.assessment_date DESC");
        $quals->execute([$id]);
        return $this->render('suppliers.qualifications', [
            'supplier' => $supplier,
            'qualifications' => $quals->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    public function addQualification(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO supplier_qualifications (supplier_id, qualification_type, qualification_date, result, certificate_number, audited_by, expiry_date, notes, attachment_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
            $id,
            $_POST['qualification_type'] ?? 'Audit',
            $_POST['qualification_date'] ?? $_POST['assessment_date'] ?? date('Y-m-d'),
            $_POST['result'] ?? $_POST['qualification_status'] ?? 'Pending',
            $_POST['certificate_number'] ?? null,
            Auth::id(),
            $_POST['expiry_date'] ?: null,
            $_POST['notes'] ?? $_POST['findings'] ?? null,
            $_POST['attachment_path'] ?? null,
        ]);
        Audit::log('Supplier Qualification Added', 'supplier_qualifications', null, null, ['supplier_id' => $id]);
        session_flash('success', 'Qualification recorded.');
        $this->redirect('/suppliers/' . $id);
    }

    public function deleteQualification(int $supplierId, int $qid): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM supplier_qualifications WHERE id = ? AND supplier_id = ?")->execute([$qid, $supplierId]);
        Audit::log('Supplier Qualification Deleted', 'supplier_qualifications', $qid);
        session_flash('success', 'Qualification deleted.');
        $this->redirect('/suppliers/' . $supplierId . '/qualifications');
    }

    public function products(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $supplier = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$supplier) { session_flash('error', 'Supplier not found.'); $this->redirect('/suppliers'); }
        $products = $db->prepare("SELECT sp.*, p.product_code, p.product_name, p.description FROM supplier_products sp JOIN products p ON sp.product_id = p.id WHERE sp.supplier_id = ? ORDER BY p.product_name");
        $products->execute([$id]);
        $allProducts = $db->query("SELECT id, product_code, product_name FROM products WHERE is_active = TRUE ORDER BY product_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('suppliers.products', [
            'supplier' => $supplier,
            'products' => $products->fetchAll(\PDO::FETCH_ASSOC),
            'allProducts' => $allProducts,
        ]);
    }

    public function linkProduct(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $existing = $db->prepare("SELECT id FROM supplier_products WHERE supplier_id = ? AND product_id = ?");
        $existing->execute([$id, $_POST['product_id']]);
        if (!$existing->fetch()) {
            $db->prepare("INSERT INTO supplier_products (supplier_id, product_id, supplier_product_code, unit_price, lead_time_days) VALUES (?, ?, ?, ?, ?)")->execute([
                $id,
                $_POST['product_id'],
                $_POST['supplier_product_code'] ?? null,
                $_POST['unit_price'] ?: null,
                $_POST['lead_time_days'] ?: null,
            ]);
            Audit::log('Supplier Product Linked', 'supplier_products', null, null, ['supplier_id' => $id, 'product_id' => $_POST['product_id']]);
        }
        session_flash('success', 'Product linked.');
        $this->redirect('/suppliers/' . $id . '/products');
    }
}
