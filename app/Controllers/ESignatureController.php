<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class ESignatureController extends BaseController
{
    private const ALLOWED_TYPES = ['sample_test', 'coa', 'deviation', 'capa', 'oos', 'batch_record'];

    public function sign(string $entityType, int $entityId): string
    {
        Auth::requireAuth();
        if (!in_array($entityType, self::ALLOWED_TYPES)) {
            session_flash('error', 'Invalid entity type for signing.');
            $this->back();
            return '';
        }
        $password = $_POST['password'] ?? '';
        $user = Auth::user();
        if (!$user || !password_verify($password, $user['password_hash'] ?? '')) {
            session_flash('error', 'Invalid password. Signature not recorded.');
            $this->back();
            return '';
        }

        $actionType = $_POST['signature_type'] ?? 'approval';
        $reason = $_POST['reason'] ?? 'Approved';

        $db = \App\Helpers\Database::connect();

        // Snapshot the current entity state so the signature is bound to content.
        $signedData = $this->captureEntityState($db, $entityType, $entityId);

        // Cryptographic binding: hash of the signed content + user + reason + timestamp.
        $payload = json_encode([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => Auth::id(),
            'action_type' => $actionType,
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'content_hash' => hash('sha256', json_encode($signedData)),
            'signed_at' => gmdate('c'),
        ]);
        $signatureHash = hash('sha256', $payload);

        $db->prepare("INSERT INTO electronic_signatures (user_id, action_type, entity_type, entity_id, signature_hash, signed_data, ip_address) VALUES (?, ?, ?, ?, ?, ?::jsonb, ?)")->execute([
            Auth::id(),
            $actionType,
            $entityType,
            $entityId,
            $signatureHash,
            json_encode([
                'signed_data' => $signedData,
                'reason' => $reason,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'signed_at' => gmdate('c'),
            ]),
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        $sigId = $db->lastInsertId();
        Audit::log('E-Signature Recorded', 'electronic_signatures', (int)$sigId, null, ['entity_type' => $entityType, 'entity_id' => $entityId, 'signature_hash' => $signatureHash]);
        session_flash('success', 'Electronic signature recorded.');
        $this->back();
        return '';
    }

    public function verify(int $sigId): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("
            SELECT es.*, u.full_name, u.email, u.username
            FROM electronic_signatures es
            JOIN users u ON es.user_id = u.id
            WHERE es.id = ?
        ");
        $stmt->execute([$sigId]);
        $sig = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$sig) {
            return $this->json(['error' => 'Signature not found'], 404);
        }

        // Recompute the signature hash from stored data to detect tampering.
        $stored = json_decode($sig['signed_data'] ?? '{}', true) ?: [];
        $signedData = $stored['signed_data'] ?? [];
        $contentHash = hash('sha256', json_encode($signedData));
        $recomputed = hash('sha256', json_encode([
            'entity_type' => $sig['entity_type'],
            'entity_id' => $sig['entity_id'],
            'user_id' => $sig['user_id'],
            'action_type' => $sig['action_type'],
            'reason' => $stored['reason'] ?? '',
            'ip_address' => $sig['ip_address'],
            'user_agent' => $stored['user_agent'] ?? '',
            'content_hash' => $contentHash,
            'signed_at' => $stored['signed_at'] ?? '',
        ]));

        $sig['verification_status'] = hash_equals($sig['signature_hash'], $recomputed)
            ? 'Valid - signature hash matches'
            : 'INVALID - signature data has been altered';
        return $this->json($sig);
    }

    public function audit(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();

        $where = [];
        $params = [];
        if (!empty($_GET['user_id'])) {
            $where[] = 'es.user_id = ?';
            $params[] = (int)$_GET['user_id'];
        }
        if (!empty($_GET['action_type'])) {
            $where[] = 'es.action_type = ?';
            $params[] = $_GET['action_type'];
        }
        if (!empty($_GET['entity_type'])) {
            $where[] = 'es.entity_type = ?';
            $params[] = $_GET['entity_type'];
        }
        if (!empty($_GET['date_from'])) {
            $where[] = 'es.created_at >= ?';
            $params[] = $_GET['date_from'] . ' 00:00:00';
        }
        if (!empty($_GET['date_to'])) {
            $where[] = 'es.created_at <= ?';
            $params[] = $_GET['date_to'] . ' 23:59:59';
        }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $countStmt = $db->prepare("SELECT COUNT(*) FROM electronic_signatures es {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT es.*, u.full_name, u.username
            FROM electronic_signatures es
            JOIN users u ON es.user_id = u.id
            {$whereClause}
            ORDER BY es.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $signatures = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $users = $db->query("SELECT id, full_name, username FROM users ORDER BY full_name")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('esignature.audit', [
            'signatures' => $signatures,
            'users' => $users,
            'filters' => $_GET,
            'total' => $total,
            'currentPage' => $page,
            'lastPage' => max(1, (int)ceil($total / $perPage)),
        ]);
    }

    private function captureEntityState(\PDO $db, string $entityType, int $entityId): array
    {
        $table = match ($entityType) {
            'sample_test' => 'sample_tests',
            'coa' => 'coa_documents',
            'deviation' => 'deviations',
            'capa' => 'capa_records',
            'oos' => 'oos_records',
            'batch_record' => 'batches',
        };
        try {
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
            $stmt->execute([$entityId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return ['error' => 'Entity not found', 'entity_id' => $entityId];
            }
            return $row;
        } catch (\Throwable $e) {
            return ['error' => 'Could not capture state', 'entity_id' => $entityId];
        }
    }
}
