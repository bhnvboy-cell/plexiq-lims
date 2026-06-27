<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\Sample;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TestItem;
use App\Models\SampleTest;
use App\Models\User;

class SampleController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $filters = [];

        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['customer_id'])) $filters['customer_id'] = $_GET['customer_id'];
        if (!empty($_GET['product_id'])) $filters['product_id'] = $_GET['product_id'];
        if (!empty($_GET['priority'])) $filters['priority'] = $_GET['priority'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        $data = Sample::withAllRelations($filters);
        $data['customers'] = Customer::active();
        $data['products'] = Product::active();
        $data['filters'] = $filters;

        return $this->render('samples.index', $data);
    }

    public function create(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $customers = Customer::active();
        $products = Product::active();
        $tests = TestItem::allWithDetails();
        $analysts = User::analysts();
        $reviewers = User::reviewers();
        $approvers = User::approvers();

        return $this->render('samples.create', [
            'customers' => $customers,
            'products' => $products,
            'tests' => $tests,
            'analysts' => $analysts,
            'reviewers' => $reviewers,
            'approvers' => $approvers,
        ]);
    }

    public function store(): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);

        $db = \App\Helpers\Database::connect();
        $db->beginTransaction();

        try {
            $sampleCode = Sample::generateCode();

            $stmt = $db->prepare("
                INSERT INTO samples (sample_code, customer_id, product_id, batch_number, batch_size,
                    manufacture_date, expiry_date, received_date, target_completion_date, priority,
                    assigned_analyst_id, assigned_reviewer_id, assigned_approver_id, registered_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");

            $stmt->execute([
                $sampleCode,
                $_POST['customer_id'] ?: null,
                $_POST['product_id'] ?: null,
                $_POST['batch_number'] ?: null,
                $_POST['batch_size'] ?: null,
                $_POST['manufacture_date'] ?: null,
                $_POST['expiry_date'] ?: null,
                $_POST['received_date'] ?? date('Y-m-d'),
                $_POST['target_completion_date'] ?: null,
                $_POST['priority'] ?? 'Normal',
                $_POST['assigned_analyst_id'] ?: null,
                $_POST['assigned_reviewer_id'] ?: null,
                $_POST['assigned_approver_id'] ?: null,
                Auth::id(),
                $_POST['notes'] ?: null,
            ]);

            $sampleId = (int)$stmt->fetchColumn();

            // Assign selected tests
            if (!empty($_POST['test_ids']) && is_array($_POST['test_ids'])) {
                $insertStmt = $db->prepare("
                    INSERT INTO sample_tests (sample_id, test_id, assigned_to) VALUES (?, ?, ?)
                ");
                foreach ($_POST['test_ids'] as $testId) {
                    $insertStmt->execute([$sampleId, $testId, $_POST['assigned_analyst_id'] ?: null]);
                }
            }

            $db->commit();

            Audit::log('Sample Created', 'samples', $sampleId, null, ['sample_code' => $sampleCode]);
            session_flash('success', "Sample {$sampleCode} created successfully.");
            redirect('/samples');

        } catch (\Exception $e) {
            $db->rollBack();
            session_flash('error', 'Error creating sample: ' . $e->getMessage());
            redirect('/samples/create');
        }
    }

    public function show(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $sample = Sample::withRelations($id);
        if (!$sample) {
            session_flash('error', 'Sample not found.');
            redirect('/samples');
        }

        $sampleTests = SampleTest::findBySample($id);
        $analysts = User::analysts();
        $reviewers = User::reviewers();
        $approvers = User::approvers();

        return $this->render('samples.show', [
            'sample' => $sample,
            'sampleTests' => $sampleTests,
            'analysts' => $analysts,
            'reviewers' => $reviewers,
            'approvers' => $approvers,
        ]);
    }

    public function edit(int $id): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $sample = Sample::withRelations($id);
        if (!$sample) {
            session_flash('error', 'Sample not found.');
            redirect('/samples');
        }

        $customers = Customer::active();
        $products = Product::active();
        $tests = TestItem::allWithDetails();
        $analysts = User::analysts();
        $reviewers = User::reviewers();
        $approvers = User::approvers();
        $sampleTests = SampleTest::findBySample($id);

        return $this->render('samples.edit', [
            'sample' => $sample,
            'customers' => $customers,
            'products' => $products,
            'tests' => $tests,
            'analysts' => $analysts,
            'reviewers' => $reviewers,
            'approvers' => $approvers,
            'sampleTests' => $sampleTests,
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();

        $stmt = $db->prepare("
            UPDATE samples SET
                customer_id = ?, product_id = ?, batch_number = ?, batch_size = ?,
                manufacture_date = ?, expiry_date = ?, received_date = ?,
                target_completion_date = ?, priority = ?,
                assigned_analyst_id = ?, assigned_reviewer_id = ?, assigned_approver_id = ?,
                notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['customer_id'] ?: null,
            $_POST['product_id'] ?: null,
            $_POST['batch_number'] ?: null,
            $_POST['batch_size'] ?: null,
            $_POST['manufacture_date'] ?: null,
            $_POST['expiry_date'] ?: null,
            $_POST['received_date'] ?? date('Y-m-d'),
            $_POST['target_completion_date'] ?: null,
            $_POST['priority'] ?? 'Normal',
            $_POST['assigned_analyst_id'] ?: null,
            $_POST['assigned_reviewer_id'] ?: null,
            $_POST['assigned_approver_id'] ?: null,
            $_POST['notes'] ?: null,
            $id,
        ]);

        Audit::log('Sample Updated', 'samples', $id);
        session_flash('success', 'Sample updated successfully.');
        redirect('/samples/' . $id);
    }

    public function updateWorkflow(int $id): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $newStatus = $_POST['status'] ?? '';

        $allowedTransitions = [
            'Registered' => ['In Progress'],
            'In Progress' => ['Reviewed', 'Rejected'],
            'Reviewed' => ['Approved', 'Rejected'],
            'Approved' => ['COA Released'],
        ];

        $sample = Sample::withRelations($id);
        if (!$sample) {
            session_flash('error', 'Sample not found.');
            redirect('/samples');
        }

        $currentStatus = $sample['status'];

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            session_flash('error', "Invalid status transition from {$currentStatus} to {$newStatus}.");
            redirect('/samples/' . $id);
        }

        $updateSql = "UPDATE samples SET status = ?, updated_at = CURRENT_TIMESTAMP";

        switch ($newStatus) {
            case 'In Progress':
                $updateSql .= ", sap_sync_status = 'Pending'";
                break;
            case 'Reviewed':
                $updateSql .= ", reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP";
                break;
            case 'Approved':
                $updateSql .= ", approved_by = ?, approved_at = CURRENT_TIMESTAMP";
                break;
            case 'COA Released':
                $updateSql .= ", coa_released_by = ?, coa_released_at = CURRENT_TIMESTAMP, sap_sync_status = 'Pending'";
                break;
        }

        $updateSql .= " WHERE id = ?";
        $stmt = $db->prepare($updateSql);

        $params = [$newStatus];
        if (in_array($newStatus, ['Reviewed', 'Approved', 'COA Released'])) {
            $params[] = Auth::id();
        }
        $params[] = $id;

        $stmt->execute($params);

        Audit::log('Sample Status Changed', 'samples', $id, ['status' => $currentStatus], ['status' => $newStatus]);
        session_flash('success', "Sample status changed to {$newStatus}.");
        redirect('/samples/' . $id);
    }

    public function assignTests(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst']);
        $db = \App\Helpers\Database::connect();

        $db->beginTransaction();
        try {
            // Remove existing untested assignments
            $db->prepare("DELETE FROM sample_tests WHERE sample_id = ? AND status = 'Pending'")->execute([$id]);

            if (!empty($_POST['test_ids']) && is_array($_POST['test_ids'])) {
                $insertStmt = $db->prepare("INSERT INTO sample_tests (sample_id, test_id, assigned_to) VALUES (?, ?, ?)");
                foreach ($_POST['test_ids'] as $testId) {
                    $insertStmt->execute([$id, $testId, $_POST['assigned_to'] ?: null]);
                }
            }

            $db->commit();
            Audit::log('Tests Assigned', 'samples', $id);
            session_flash('success', 'Tests assigned successfully.');
        } catch (\Exception $e) {
            $db->rollBack();
            session_flash('error', 'Error assigning tests: ' . $e->getMessage());
        }
        redirect('/samples/' . $id);
    }
}
