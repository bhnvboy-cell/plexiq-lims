<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class BillingController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $status = $_GET['status'] ?? '';
        $where = '';
        $params = [];
        if ($status) {
            $where = 'WHERE i.status = ?';
            $params[] = $status;
        }
        $invoices = $db->prepare("
            SELECT i.*, c.customer_name, c.customer_code,
                (SELECT COALESCE(SUM(ip.amount), 0) FROM payments ip WHERE ip.invoice_id = i.id) AS paid_amount
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            {$where}
            ORDER BY i.created_at DESC
            LIMIT 100
        ");
        $invoices->execute($params);
        $rows = $invoices->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$inv) {
            $total = (float)($inv['total_amount'] ?? 0);
            $paid = (float)($inv['paid_amount'] ?? 0);
            $inv['payment_status'] = $paid >= $total && $total > 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending');
        }
        unset($inv);
        return $this->render('billing.index', ['invoices' => $rows]);
    }

    public function create(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $customers = $db->query("SELECT id, customer_code, customer_name FROM customers WHERE is_active = TRUE ORDER BY customer_name")->fetchAll(\PDO::FETCH_ASSOC);
        $samples = $db->query("SELECT id, sample_code FROM samples ORDER BY id DESC LIMIT 200")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('billing.form', ['invoice' => null, 'customers' => $customers, 'samples' => $samples, 'items' => []]);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO invoices (invoice_number, customer_id, sample_id, invoice_date, due_date, subtotal, tax_amount, discount_amount, total_amount, notes, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['invoice_number'] ?? 'INV-' . time(),
            $_POST['customer_id'],
            $_POST['sample_id'] ?? null,
            $_POST['invoice_date'] ?? date('Y-m-d'),
            $_POST['due_date'] ?? null,
            $_POST['subtotal'] ?? 0,
            $_POST['tax_amount'] ?? 0,
            $_POST['discount_amount'] ?? 0,
            $_POST['total_amount'] ?? 0,
            $_POST['notes'] ?? null,
            'Draft',
            Auth::id(),
        ]);
        $invoiceId = $db->lastInsertId();
        Audit::log('Invoice Created', 'invoices', $invoiceId);
        session_flash('success', 'Invoice created.');
        $this->redirect('/billing');
    }

    public function show(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT i.*, c.customer_name, c.customer_code, c.address, c.city, c.state, c.country, c.postal_code, u.full_name AS created_by_name
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            LEFT JOIN users u ON i.created_by = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$invoice) { session_flash('error', 'Invoice not found.'); $this->redirect('/billing'); }
        $items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
        $items->execute([$id]);
        $payments = $db->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date");
        $payments->execute([$id]);
        $payments = $payments->fetchAll(\PDO::FETCH_ASSOC);
        $total = (float)($invoice['total_amount'] ?? 0);
        $paid = (float)array_sum(array_column($payments, 'amount'));
        $invoice['paid_amount'] = $paid;
        $invoice['payment_status'] = $paid >= $total && $total > 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending');
        return $this->render('billing.show', [
            'invoice' => $invoice,
            'items' => $items->fetchAll(\PDO::FETCH_ASSOC),
            'payments' => $payments,
        ]);
    }

    public function edit(int $id): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$invoice) { session_flash('error', 'Invoice not found.'); $this->redirect('/billing'); }
        $customers = $db->query("SELECT id, customer_code, customer_name FROM customers WHERE is_active = TRUE ORDER BY customer_name")->fetchAll(\PDO::FETCH_ASSOC);
        $samples = $db->query("SELECT id, sample_code FROM samples ORDER BY id DESC LIMIT 200")->fetchAll(\PDO::FETCH_ASSOC);
        $items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
        $items->execute([$id]);
        return $this->render('billing.form', [
            'invoice' => $invoice,
            'customers' => $customers,
            'samples' => $samples,
            'items' => $items->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE invoices SET invoice_number = ?, customer_id = ?, sample_id = ?, invoice_date = ?, due_date = ?, subtotal = ?, tax_amount = ?, discount_amount = ?, total_amount = ?, notes = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['invoice_number'],
            $_POST['customer_id'],
            $_POST['sample_id'] ?? null,
            $_POST['invoice_date'] ?? date('Y-m-d'),
            $_POST['due_date'] ?? null,
            $_POST['subtotal'] ?? 0,
            $_POST['tax_amount'] ?? 0,
            $_POST['discount_amount'] ?? 0,
            $_POST['total_amount'] ?? 0,
            $_POST['notes'] ?? null,
            $_POST['status'] ?? 'Draft',
            $id,
        ]);
        Audit::log('Invoice Updated', 'invoices', $id);
        session_flash('success', 'Invoice updated.');
        $this->redirect('/billing/' . $id);
    }

    public function addItem(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)")->execute([
            $id,
            $_POST['description'],
            $_POST['quantity'] ?? 1,
            $_POST['unit_price'] ?? 0,
            ($_POST['quantity'] ?? 1) * ($_POST['unit_price'] ?? 0),
        ]);
        // Recalculate invoice totals
        $stmt = $db->prepare("SELECT SUM(total_price) AS subtotal FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$id]);
        $subtotal = (float)($stmt->fetchColumn() ?: 0);
        $taxRate = (float)($_POST['tax_rate'] ?? 0);
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;
        $db->prepare("UPDATE invoices SET subtotal = ?, tax_amount = ?, total_amount = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$subtotal, $taxAmount, $total, $id]);
        Audit::log('Invoice Item Added', 'invoice_items', null, null, ['invoice_id' => $id]);
        session_flash('success', 'Item added to invoice.');
        $this->redirect('/billing/' . $id);
    }

    public function recordPayment(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO payments (invoice_id, payment_date, amount, payment_method, reference_number, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([
            $id,
            $_POST['payment_date'] ?? date('Y-m-d'),
            $_POST['amount'],
            $_POST['payment_method'] ?? 'Bank Transfer',
            $_POST['reference_number'] ?? null,
            $_POST['notes'] ?? null,
            Auth::id(),
        ]);
        // Update invoice status based on total paid
        $invStmt = $db->prepare("SELECT total_amount FROM invoices WHERE id = ?");
        $invStmt->execute([$id]);
        $total = (float)($invStmt->fetchColumn() ?: 0);
        $paidStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?");
        $paidStmt->execute([$id]);
        $paid = (float)($paidStmt->fetchColumn() ?: 0);
        $status = $paid >= $total ? 'Paid' : ($paid > 0 ? 'Partial' : 'Sent');
        $db->prepare("UPDATE invoices SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$status, $id]);
        Audit::log('Payment Recorded', 'payments', null, null, ['invoice_id' => $id, 'amount' => $_POST['amount']]);
        session_flash('success', 'Payment recorded.');
        $this->redirect('/billing/' . $id);
    }

    public function downloadPdf(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT i.*, c.customer_name, c.customer_code, c.address, c.city, c.state, c.country, c.postal_code
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$invoice) { session_flash('error', 'Invoice not found.'); $this->redirect('/billing'); }
        $items = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $items->execute([$id]);
        Audit::log('Invoice PDF Downloaded', 'invoices', $id);
        $html = $this->render('billing.pdf', [
            'invoice' => $invoice,
            'items' => $items->fetchAll(\PDO::FETCH_ASSOC),
        ]);
        // In production, use a PDF library like Dompdf or TCPDF
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}
