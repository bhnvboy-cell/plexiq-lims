<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\ChainOfCustody;

class CocController extends BaseController
{
    public function index(): string
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $result = \App\Helpers\Pagination::run($db, "
            SELECT c.*, s.sample_code,
                   tf.full_name AS transferred_by_name,
                   rc.full_name AS received_by_name
            FROM chain_of_custody c
            JOIN samples s ON c.sample_id = s.id
            LEFT JOIN users tf ON c.transferred_by = tf.id
            LEFT JOIN users rc ON c.received_by = rc.id
        ", "
            SELECT COUNT(*)
            FROM chain_of_custody c
        ", [], 50, 'c.transferred_at DESC');
        $stats = ChainOfCustody::dashboardStats();
        return $this->render('coc.index', [
            'transfers' => $result['items'],
            'paginator' => $result,
            'stats' => $stats,
        ]);
    }

    public function store(int $sampleId): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $db = \App\Helpers\Database::connect();

        $sampleStmt = $db->prepare("SELECT id FROM samples WHERE id = ? AND deleted_at IS NULL");
        $sampleStmt->execute([$sampleId]);
        if (!$sampleStmt->fetch()) {
            session_flash('error', 'Sample not found.');
            redirect('/samples');
        }

        $transferredAt = $_POST['transferred_at'] ?: date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO chain_of_custody
                (sample_id, transfer_from, transfer_to, transferred_by, transferred_at, location, condition_note, sealed, seal_number, custody_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sampleId,
            $_POST['transfer_from'] ?: null,
            $_POST['transfer_to'] ?: null,
            Auth::id(),
            date('Y-m-d H:i:s', strtotime($transferredAt)),
            $_POST['location'] ?: null,
            $_POST['condition_note'] ?: null,
            !empty($_POST['sealed']) ? 'TRUE' : 'FALSE',
            $_POST['seal_number'] ?: null,
            $_POST['custody_reason'] ?: null,
        ]);
        $cocId = $db->lastInsertId();
        Audit::log('Chain of Custody Transfer', 'chain_of_custody', (int)$cocId, null, ['sample_id' => $sampleId]);
        session_flash('success', 'Chain of custody transfer recorded.');
        redirect('/samples/' . $sampleId);
    }

    public function receive(int $id): void
    {
        Auth::requireAnyRole(['Admin', 'Analyst', 'Reviewer', 'Approver']);
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("UPDATE chain_of_custody SET received_by = ?, received_at = CURRENT_TIMESTAMP WHERE id = ? AND received_at IS NULL");
        $stmt->execute([Auth::id(), $id]);
        Audit::log('Chain of Custody Received', 'chain_of_custody', $id);
        session_flash('success', 'Transfer acknowledged as received.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/coc');
    }

    public function delete(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM chain_of_custody WHERE id = ?")->execute([$id]);
        Audit::log('Chain of Custody Entry Deleted', 'chain_of_custody', $id);
        session_flash('success', 'Chain of custody entry deleted.');
        redirect('/coc');
    }
}
