<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Helpers\Database;
use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Models\Specification;
use App\Models\SpecificationVersion;
use PDO;

class SpecificationService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function createSpecification(array $data, int $userId): int
    {
        $specCode = trim($data['spec_code'] ?? '');
        $productId = (int)($data['product_id'] ?? 0);
        $grade = trim($data['grade'] ?? 'Standard');
        $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;

        if (empty($specCode) || $productId <= 0) {
            throw new \InvalidArgumentException('Specification code and product ID are required.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO specifications (spec_code, product_id, grade, customer_id, created_by)
            VALUES (?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([$specCode, $productId, $grade, $customerId, $userId]);
        $specId = (int)$stmt->fetchColumn();

        Audit::log('Specification Created', 'specifications', $specId, null, [
            'spec_code' => $specCode,
            'product_id' => $productId,
            'grade' => $grade
        ]);

        // Auto-create Version 1 (Draft)
        $this->createDraftVersion($specId, [
            'effective_from' => $data['effective_from'] ?? date('Y-m-d'),
            'notes' => 'Initial specification draft'
        ], null, $userId);

        return $specId;
    }

    public function createDraftVersion(int $specId, array $versionData, ?int $cloneFromVersionId = null, int $userId = 1): int
    {
        // Find next version number
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(version_number), 0) + 1 FROM specification_versions WHERE specification_id = ?");
        $stmt->execute([$specId]);
        $nextVer = (int)$stmt->fetchColumn();

        $effectiveFrom = $versionData['effective_from'] ?? date('Y-m-d');
        $effectiveTo = !empty($versionData['effective_to']) ? $versionData['effective_to'] : null;
        $changeControlId = !empty($versionData['change_control_id']) ? (int)$versionData['change_control_id'] : null;
        $notes = $versionData['notes'] ?? null;

        $ins = $this->db->prepare("
            INSERT INTO specification_versions (
                specification_id, version_number, effective_from, effective_to,
                status, change_control_id, notes, created_by
            ) VALUES (?, ?, ?, ?, 'Draft', ?, ?, ?) RETURNING id
        ");
        $ins->execute([$specId, $nextVer, $effectiveFrom, $effectiveTo, $changeControlId, $notes, $userId]);
        $versionId = (int)$ins->fetchColumn();

        // If cloning from an earlier version, duplicate its specification limits
        if ($cloneFromVersionId !== null && $cloneFromVersionId > 0) {
            $cloneLimits = $this->db->prepare("
                INSERT INTO specification_limits (
                    spec_version_id, component_id, limit_type, min_limit, max_limit,
                    target_value, text_match_expected, warn_min, warn_max,
                    display_specification, oot_rule, sort_order
                )
                SELECT ?, component_id, limit_type, min_limit, max_limit,
                       target_value, text_match_expected, warn_min, warn_max,
                       display_specification, oot_rule, sort_order
                FROM specification_limits
                WHERE spec_version_id = ?
            ");
            $cloneLimits->execute([$versionId, $cloneFromVersionId]);
        }

        Audit::log('Specification Draft Version Created', 'specification_versions', $versionId, null, [
            'specification_id' => $specId,
            'version_number' => $nextVer,
            'cloned_from' => $cloneFromVersionId
        ]);

        return $versionId;
    }

    public function approveVersion(int $versionId, int $userId, string $signatureReason, ?string $signaturePassword = null): array
    {
        $stmt = $this->db->prepare("SELECT * FROM specification_versions WHERE id = ?");
        $stmt->execute([$versionId]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$version) {
            throw new \RuntimeException('Specification version not found.');
        }

        if ($version['status'] === 'Approved') {
            throw new \RuntimeException('Specification version is already approved and locked.');
        }

        // Verify there is at least one specification limit defined
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM specification_limits WHERE spec_version_id = ?");
        $countStmt->execute([$versionId]);
        if ((int)$countStmt->fetchColumn() === 0) {
            throw new \RuntimeException('Cannot approve a specification version with zero component limits defined.');
        }

        $appKey = getenv('APP_KEY') ?: 'plexiq_secret_master_key_2026';
        $timestamp = date('c');
        $manifestation = "I approve this specification version as legally binding and compliant with 21 CFR Part 11 / EU Annex 11.";
        $sigPayload = "{$versionId}:{$userId}:{$timestamp}:{$signatureReason}:{$manifestation}";
        $signatureHash = hash_hmac('sha256', $sigPayload, $appKey);

        $this->db->beginTransaction();
        try {
            // Supersede any previously approved versions for this specification whose effective_from <= this effective_from
            $supStmt = $this->db->prepare("
                UPDATE specification_versions
                SET status = 'Superseded', effective_to = CURRENT_DATE, updated_at = CURRENT_TIMESTAMP
                WHERE specification_id = ? AND status = 'Approved' AND id != ?
            ");
            $supStmt->execute([$version['specification_id'], $versionId]);

            // Mark this version approved
            $upd = $this->db->prepare("
                UPDATE specification_versions
                SET status = 'Approved',
                    approved_by = ?,
                    approved_at = CURRENT_TIMESTAMP,
                    approval_signature_hash = ?,
                    approval_reason = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $upd->execute([$userId, $signatureHash, $signatureReason, $versionId]);

            $this->db->commit();

            Audit::log('Specification Version Approved', 'specification_versions', $versionId, null, [
                'specification_id' => $version['specification_id'],
                'version_number' => $version['version_number'],
                'approved_by' => $userId,
                'signature_hash' => $signatureHash,
                'reason' => $signatureReason
            ]);

            return [
                'success' => true,
                'version_id' => $versionId,
                'status' => 'Approved',
                'signature_hash' => $signatureHash,
                'approved_at' => $timestamp
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getActiveSpecification(int $productId, ?string $effectiveDate = null): ?array
    {
        $date = $effectiveDate ?: date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT sv.*, s.spec_code, s.product_id, s.grade, p.product_code, p.product_name
            FROM specification_versions sv
            JOIN specifications s ON sv.specification_id = s.id
            JOIN products p ON s.product_id = p.id
            WHERE s.product_id = ?
              AND sv.status = 'Approved'
              AND sv.effective_from <= ?
              AND (sv.effective_to IS NULL OR sv.effective_to >= ?)
            ORDER BY sv.version_number DESC
            LIMIT 1
        ");
        $stmt->execute([$productId, $date, $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return SpecificationVersion::findWithLimits((int)$row['id']);
    }

    public function addOrUpdateLimit(int $versionId, int $componentId, array $data, int $userId = 1): int
    {
        // Immutability check: cannot edit an approved version
        $vStmt = $this->db->prepare("SELECT status FROM specification_versions WHERE id = ?");
        $vStmt->execute([$versionId]);
        $status = $vStmt->fetchColumn();

        if ($status === 'Approved') {
            throw new \RuntimeException('Approved specification versions are immutable under 21 CFR Part 11. Create a new draft version to modify limits.');
        }

        $limitType = $data['limit_type'] ?? 'range';
        $minLimit = isset($data['min_limit']) && $data['min_limit'] !== '' ? (float)$data['min_limit'] : null;
        $maxLimit = isset($data['max_limit']) && $data['max_limit'] !== '' ? (float)$data['max_limit'] : null;
        $targetValue = isset($data['target_value']) && $data['target_value'] !== '' ? (float)$data['target_value'] : null;
        $textMatch = !empty($data['text_match_expected']) ? trim($data['text_match_expected']) : null;
        $warnMin = isset($data['warn_min']) && $data['warn_min'] !== '' ? (float)$data['warn_min'] : null;
        $warnMax = isset($data['warn_max']) && $data['warn_max'] !== '' ? (float)$data['warn_max'] : null;
        $displaySpec = trim($data['display_specification'] ?? '');
        $ootRule = !empty($data['oot_rule']) ? trim($data['oot_rule']) : null;
        $sortOrder = (int)($data['sort_order'] ?? 0);

        $stmt = $this->db->prepare("
            INSERT INTO specification_limits (
                spec_version_id, component_id, limit_type, min_limit, max_limit,
                target_value, text_match_expected, warn_min, warn_max,
                display_specification, oot_rule, sort_order, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (spec_version_id, component_id) DO UPDATE SET
                limit_type = EXCLUDED.limit_type,
                min_limit = EXCLUDED.min_limit,
                max_limit = EXCLUDED.max_limit,
                target_value = EXCLUDED.target_value,
                text_match_expected = EXCLUDED.text_match_expected,
                warn_min = EXCLUDED.warn_min,
                warn_max = EXCLUDED.warn_max,
                display_specification = EXCLUDED.display_specification,
                oot_rule = EXCLUDED.oot_rule,
                sort_order = EXCLUDED.sort_order,
                updated_at = CURRENT_TIMESTAMP
            RETURNING id
        ");
        $stmt->execute([
            $versionId, $componentId, $limitType, $minLimit, $maxLimit,
            $targetValue, $textMatch, $warnMin, $warnMax,
            $displaySpec, $ootRule, $sortOrder
        ]);

        return (int)$stmt->fetchColumn();
    }
}
