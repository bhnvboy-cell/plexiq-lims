<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;

class ClientController extends BaseController
{
    public function showLogin(): string
    {
        if (Auth::check()) {
            redirect('/client/dashboard');
        }
        return $this->render('client.login');
    }

    public function login(): void
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            session_flash('error', 'Username and password are required.');
            redirect('/client/login');
        }

        $result = Auth::login($username, $password);

        if ($result === '2fa') {
            redirect('/login/2fa');
        }

        if ($result === 'locked') {
            session_flash('error', 'Account temporarily locked due to too many failed attempts. Try again in 15 minutes.');
            redirect('/client/login');
        }

        if ($result === 'ok') {
            if (Auth::role() !== 'Customer') {
                Auth::logout();
                session_flash('error', 'This portal is for customers only. Use the main login.');
                redirect('/client/login');
            }
            redirect('/client/dashboard');
        }

        session_flash('error', 'Invalid username or password.');
        redirect('/client/login');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/client/login');
    }

    public function register(): string
    {
        if (Auth::check()) {
            redirect('/client/dashboard');
        }
        return $this->render('client.register');
    }

    public function store(): void
    {
        $db = \App\Helpers\Database::connect();

        $company = $_POST['company'] ?? '';
        $contact = $_POST['contact_person'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if (empty($company) || empty($contact) || empty($email) || empty($username) || empty($password)) {
            session_flash('error', 'Required fields are missing.');
            redirect('/client/register');
        }

        if ($password !== $confirm) {
            session_flash('error', 'Passwords do not match.');
            redirect('/client/register');
        }

        if (strlen($password) < 6) {
            session_flash('error', 'Password must be at least 6 characters.');
            redirect('/client/register');
        }

        $check = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            session_flash('error', 'Username or email already exists.');
            redirect('/client/register');
        }

        $db->beginTransaction();
        try {
            $code = 'CLT-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $company), 0, 5)) . '-' . date('Ymd');

            $stmt = $db->prepare("
                INSERT INTO customers (customer_code, customer_name, address, city, country, contact_person, email, phone, is_active)
                VALUES (?, ?, ?, ?, 'India', ?, ?, ?, TRUE)
                RETURNING id
            ");
            $stmt->execute([$code, $company, $address, $city, $contact, $email, $phone]);
            $customerId = (int)$stmt->fetchColumn();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password_hash, full_name, role_id, is_active)
                VALUES (?, ?, ?, ?, (SELECT id FROM roles WHERE name = 'Customer'), TRUE)
            ");
            $stmt->execute([$username, $email, $hash, $contact]);

            $db->commit();
            session_flash('success', 'Registration successful! Please login.');
            redirect('/client/login');
        } catch (\Exception $e) {
            $db->rollBack();
            session_flash('error', 'Registration failed: ' . $e->getMessage());
            redirect('/client/register');
        }
    }

    public function dashboard(): string
    {
        Auth::requireAnyRole(['Customer']);
        $user = Auth::user();

        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$user['email']]);
        $cust = $stmt->fetch(\PDO::FETCH_ASSOC);

        $documents = [];
        $customerName = '';

        if ($cust) {
            $customerId = $cust['id'];
            $stmt = $db->prepare("
                SELECT cd.*, s.sample_code, s.batch_number, p.product_name,
                       u.full_name AS generated_by_name
                FROM coa_documents cd
                JOIN samples s ON cd.sample_id = s.id
                LEFT JOIN products p ON s.product_id = p.id
                LEFT JOIN users u ON cd.generated_by = u.id
                WHERE s.customer_id = ? AND cd.deleted_at IS NULL
                ORDER BY cd.generated_at DESC
                LIMIT 50
            ");
            $stmt->execute([$customerId]);
            $documents = $stmt->fetchAll();

            $stmt = $db->prepare("SELECT customer_name FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $customerName = $stmt->fetchColumn() ?: '';
        }

        return $this->render('client.dashboard', [
            'documents' => $documents,
            'customerName' => $customerName,
            'user' => $user,
        ]);
    }

    public function viewCoa(int $id): string
    {
        Auth::requireAnyRole(['Customer']);
        $user = Auth::user();

        $db = \App\Helpers\Database::connect();

        $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$user['email']]);
        $cust = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$cust) {
            session_flash('error', 'Customer profile not found.');
            redirect('/client/dashboard');
        }

        $stmt = $db->prepare("
            SELECT cd.*, s.sample_code, s.batch_number, s.manufacture_date, s.expiry_date,
                   s.received_date, s.status AS sample_status,
                   c.customer_name, c.customer_code, c.address AS customer_address,
                   p.product_name, p.product_code,
                   u.full_name AS generated_by_name, ru.full_name AS reviewed_by_name,
                   au.full_name AS approved_by_name, cou.full_name AS released_by_name
            FROM coa_documents cd
            JOIN samples s ON cd.sample_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON cd.generated_by = u.id
            LEFT JOIN users ru ON s.reviewed_by = ru.id
            LEFT JOIN users au ON s.approved_by = au.id
            LEFT JOIN users cou ON s.coa_released_by = cou.id
            WHERE cd.id = ? AND s.customer_id = ? AND cd.deleted_at IS NULL
        ");
        $stmt->execute([$id, $cust['id']]);
        $document = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$document) {
            session_flash('error', 'COA not found.');
            redirect('/client/dashboard');
        }

        $testStmt = $db->prepare("
            SELECT t.test_name, t.test_code, t.spec_limit_text, t.min_spec_limit, t.max_spec_limit,
                   m.method_name, u.unit_code, u.unit_name,
                   r.result_value, r.result_text, r.is_within_spec, r.uncertainty, r.k_factor,
                   ent.full_name AS entered_by_name
            FROM sample_tests st
            JOIN tests t ON st.test_id = t.id
            LEFT JOIN methods m ON t.method_id = m.id
            LEFT JOIN units u ON t.unit_id = u.id
            LEFT JOIN results r ON r.sample_test_id = st.id AND r.revision = (
                SELECT MAX(r2.revision) FROM results r2 WHERE r2.sample_test_id = st.id
            )
            LEFT JOIN users ent ON r.entered_by = ent.id
            WHERE st.sample_id = ? AND st.status IN ('Completed', 'Reviewed', 'Approved')
            ORDER BY t.test_code
        ");
        $testStmt->execute([$document['sample_id']]);
        $results = $testStmt->fetchAll();

        $template = \App\Models\CoaTemplate::getDefault();
        $coaHtml = '';
        if ($template) {
            $coaService = new \App\Services\CoaService();
            $coaHtml = $coaService->render($template['template_html'], $document, $results, $template);
        }

        return $this->render('client.view-coa', [
            'document' => $document,
            'results' => $results,
            'coaHtml' => $coaHtml,
        ]);
    }

    public function downloadPdf(int $id): void
    {
        Auth::requireAnyRole(['Customer']);
        $pdfService = new \App\Services\PdfService();
        $pdfService->downloadCoa($id);
    }
}
