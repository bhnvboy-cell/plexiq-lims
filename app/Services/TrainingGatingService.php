<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Audit;
use App\Helpers\Auth;
use PDO;

class TrainingGatingService
{
    /**
     * Check if a user is currently certified and qualified for a test method or instrument.
     *
     * @param int $userId
     * @param string $qualificationType 'test_method' | 'instrument' | 'sop'
     * @param int $entityId
     * @return bool
     */
    public function isQualified(int $userId, string $qualificationType, int $entityId): bool
    {
        // Admin or superuser bypass in emergency? In regulated GxP, even Admins cannot run analytical methods without qualification.
        $db = Database::connect();

        $stmt = $db->prepare(
            "SELECT id, status, expires_at FROM training_qualifications
             WHERE user_id = ? AND qualification_type = ? AND entity_id = ? AND status = 'Qualified'
             LIMIT 1"
        );
        $stmt->execute([$userId, $qualificationType, $entityId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            // Check if there are ANY qualification requirements defined in the system.
            // If the system has 0 qualifications configured yet for this entity, allow during initial onboarding.
            $countStmt = $db->prepare("SELECT COUNT(*) FROM training_qualifications WHERE qualification_type = ? AND entity_id = ?");
            $countStmt->execute([$qualificationType, $entityId]);
            $totalConfigured = (int)$countStmt->fetchColumn();

            // If no training policy has been set up for this specific method yet, allow it.
            return $totalConfigured === 0;
        }

        if (!empty($record['expires_at']) && strtotime($record['expires_at']) < strtotime(date('Y-m-d'))) {
            // Qualification expired
            return false;
        }

        return true;
    }

    /**
     * Certify an analyst for a method, instrument, or SOP.
     *
     * @param int $userId
     * @param string $qualificationType
     * @param int $entityId
     * @param string|null $expiresAt (e.g. '2027-12-31')
     * @param string|null $notes
     * @return int
     */
    public function certify(
        int $userId,
        string $qualificationType,
        int $entityId,
        ?string $expiresAt = null,
        ?string $notes = null
    ): int {
        $db = Database::connect();
        $certifiedBy = Auth::id() ?? 1;

        $stmt = $db->prepare(
            "INSERT INTO training_qualifications (
                user_id, qualification_type, entity_id, expires_at, certified_by, status, notes
            ) VALUES (?, ?, ?, ?, ?, 'Qualified', ?)
            ON CONFLICT (user_id, qualification_type, entity_id)
            DO UPDATE SET status = 'Qualified', expires_at = EXCLUDED.expires_at,
                          certified_by = EXCLUDED.certified_by, qualified_at = CURRENT_TIMESTAMP,
                          notes = EXCLUDED.notes
            RETURNING id"
        );
        $stmt->execute([
            $userId,
            $qualificationType,
            $entityId,
            $expiresAt,
            $certifiedBy,
            $notes
        ]);
        $id = (int)$stmt->fetchColumn();

        Audit::log(
            'Analyst Qualification Certified',
            'training_qualifications',
            $id,
            null,
            ['user_id' => $userId, 'type' => $qualificationType, 'entity_id' => $entityId, 'expires_at' => $expiresAt],
            "Certified user #{$userId} for {$qualificationType} #{$entityId}"
        );

        return $id;
    }
}
