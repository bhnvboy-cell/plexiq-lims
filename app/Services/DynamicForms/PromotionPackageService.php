<?php

namespace App\Services\DynamicForms;

use App\Helpers\Database;
use App\Helpers\Audit;

class PromotionPackageService
{
    private string $signingSecret;

    public function __construct(?string $secret = null)
    {
        $this->signingSecret = $secret ?? (getenv('APP_KEY') ?: 'plexiq_promotion_master_signing_key_2026');
    }

    /**
     * Create an exportable promotion package from dynamic schemas and configurations
     *
     * @param string $sourceEnv ('DEV', 'UAT')
     * @param string $targetEnv ('UAT', 'PROD')
     * @param array<string, mixed> $entities Form schemas, formulas, or workflows to bundle
     * @param int|null $userId
     * @return array<string, mixed>
     */
    public function createPackage(string $sourceEnv, string $targetEnv, array $entities, ?int $userId = null): array
    {
        $packageCode = 'PKG-' . strtoupper($sourceEnv) . '-' . strtoupper($targetEnv) . '-' . date('Ymd') . '-' . substr(uniqid(), -4);
        $payload = [
            'package_code' => $packageCode,
            'source_environment' => $sourceEnv,
            'target_environment' => $targetEnv,
            'created_at' => date('c'),
            'generator' => 'PlexiQ Promotion Engine v2.0',
            'entities' => $entities
        ];

        $sortedPayload = $this->canonicalizeArray($payload);
        $payloadJson = json_encode($sortedPayload, JSON_UNESCAPED_SLASHES);
        $sha256 = hash('sha256', $payloadJson);
        $signature = hash_hmac('sha256', $sha256, $this->signingSecret);

        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO environment_promotions (package_code, source_environment, target_environment, package_type, payload_bundle, sha256_checksum, signature_payload, status, promoted_by)
            VALUES (?, ?, ?, 'full_config', ?, ?, ?, 'pending', ?)
            RETURNING id
        ");
        $stmt->execute([$packageCode, $sourceEnv, $targetEnv, $payloadJson, $sha256, $signature, $userId]);
        $pkgId = (int)$stmt->fetchColumn();

        Audit::log('Promotion Package Created', 'environment_promotions', $pkgId, null, [
            'package_code' => $packageCode,
            'sha256' => $sha256
        ]);

        return [
            'id' => $pkgId,
            'package_code' => $packageCode,
            'sha256_checksum' => $sha256,
            'signature' => $signature,
            'payload' => $payload
        ];
    }

    /**
     * Verify and apply a promotion package into the target environment
     *
     * @param int|string $packageIdOrCode
     * @param int|null $approverUserId
     * @return array{success: bool, message: string, imported_count?: int}
     */
    public function applyPackage(int|string $packageIdOrCode, ?int $approverUserId = null): array
    {
        $db = Database::connect();
        $stmt = is_numeric($packageIdOrCode)
            ? $db->prepare("SELECT * FROM environment_promotions WHERE id = ?")
            : $db->prepare("SELECT * FROM environment_promotions WHERE package_code = ?");
        $stmt->execute([$packageIdOrCode]);
        $pkg = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pkg) {
            return ['success' => false, 'message' => 'Promotion package not found.'];
        }

        if ($pkg['status'] === 'applied') {
            return ['success' => false, 'message' => 'Package has already been applied.'];
        }

        // Verify SHA-256 and Signature using canonical JSON representation
        $payload = is_string($pkg['payload_bundle']) ? json_decode($pkg['payload_bundle'], true) : $pkg['payload_bundle'];
        $sortedPayload = $this->canonicalizeArray($payload);
        $canonicalJson = json_encode($sortedPayload, JSON_UNESCAPED_SLASHES);
        $computedSha = hash('sha256', $canonicalJson);
        $computedSig = hash_hmac('sha256', $computedSha, $this->signingSecret);

        if ($computedSha !== $pkg['sha256_checksum'] || $computedSig !== $pkg['signature_payload']) {
            return ['success' => false, 'message' => 'Cryptographic signature mismatch! Package was altered or corrupted.'];
        }

        $entities = $payload['entities'] ?? [];
        $appliedCount = 0;

        $db->beginTransaction();
        try {
            // Apply Dynamic Form Schemas
            if (isset($entities['schemas']) && is_array($entities['schemas'])) {
                $formService = new FormSchemaService();
                foreach ($entities['schemas'] as $schema) {
                    $schema['status'] = 'published';
                    $formService->saveSchema($schema, $approverUserId);
                    $appliedCount++;
                }
            }

            // Update status
            $upd = $db->prepare("
                UPDATE environment_promotions
                SET status = 'applied', approved_by = ?, applied_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $upd->execute([$approverUserId, $pkg['id']]);

            $db->commit();

            Audit::log('Promotion Package Applied', 'environment_promotions', (int)$pkg['id'], ['status' => 'pending'], ['status' => 'applied']);
            return [
                'success' => true,
                'message' => "Package {$pkg['package_code']} successfully promoted and applied.",
                'imported_count' => $appliedCount
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Failed to apply package: ' . $e->getMessage()];
        }
    }

    /**
     * Recursively sort keys of associative arrays for deterministic canonical JSON
     */
    private function canonicalizeArray(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        $isAssoc = array_keys($data) !== range(0, count($data) - 1);
        if ($isAssoc) {
            ksort($data);
        }
        foreach ($data as $k => $v) {
            $data[$k] = $this->canonicalizeArray($v);
        }
        return $data;
    }
}
