<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Services\DynamicForms\FormSchemaService;
use App\Services\DynamicForms\PromotionPackageService;

class DynamicFormsAndPromotionTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::connect();
    }

    public function testDynamicFormSchemaCreationAndValidation(): void
    {
        $service = new FormSchemaService();
        $code = 'FORM-TEST-' . uniqid();

        $schemaData = [
            'schema_code' => $code,
            'name' => 'Dissolution Testing Dynamic Form',
            'entity_type' => 'test_method',
            'status' => 'published',
            'fields_schema' => [
                ['name' => 'media_temperature', 'label' => 'Media Temperature (C)', 'type' => 'number', 'required' => true, 'min' => 36.5, 'max' => 37.5],
                ['name' => 'vessel_apparatus', 'label' => 'Apparatus Type', 'type' => 'select', 'required' => true, 'options' => ['Apparatus 1 (Basket)', 'Apparatus 2 (Paddle)']],
                ['name' => 'operator_notes', 'label' => 'Notes', 'type' => 'text', 'required' => false]
            ],
            'layout_schema' => ['columns' => 2],
            'validation_rules' => []
        ];

        // 1. Save Schema
        $saved = $service->saveSchema($schemaData, 1);
        $this->assertSame($code, $saved['schema_code']);
        $this->assertSame(1, $saved['version']);

        // 2. Validate Valid Submission Payload
        $validPayload = [
            'media_temperature' => '37.0',
            'vessel_apparatus' => 'Apparatus 2 (Paddle)',
            'operator_notes' => 'Vessels filled to 900mL.'
        ];
        $valResult = $service->validateSubmission($schemaData, $validPayload);
        $this->assertTrue($valResult['is_valid']);
        $this->assertSame(37.0, $valResult['sanitized_data']['media_temperature']);
        $this->assertSame('Apparatus 2 (Paddle)', $valResult['sanitized_data']['vessel_apparatus']);

        // 3. Validate Out-of-Bounds & Missing Required Payload
        $invalidPayload = [
            'media_temperature' => '39.5', // Exceeds max 37.5
            'vessel_apparatus' => 'Apparatus 3' // Invalid option
        ];
        $valInvalid = $service->validateSubmission($schemaData, $invalidPayload);
        $this->assertFalse($valInvalid['is_valid']);
        $this->assertArrayHasKey('media_temperature', $valInvalid['errors']);
        $this->assertArrayHasKey('vessel_apparatus', $valInvalid['errors']);

        // 4. Update Schema & Verify Version Increment
        $schemaData['name'] = 'Dissolution Testing Dynamic Form Rev B';
        $updated = $service->saveSchema($schemaData, 1);
        $this->assertSame(2, $updated['version']);
    }

    public function testPromotionPackageCreationAndVerification(): void
    {
        $pkgService = new PromotionPackageService();
        $code = 'TEST-SCHEMA-' . uniqid();

        $entities = [
            'schemas' => [
                [
                    'schema_code' => $code,
                    'name' => 'Promotion Test Form',
                    'entity_type' => 'sample',
                    'fields_schema' => [
                        ['name' => 'ph_value', 'label' => 'pH', 'type' => 'number', 'required' => true]
                    ]
                ]
            ]
        ];

        // 1. Create Promotion Package
        $pkg = $pkgService->createPackage('DEV', 'UAT', $entities, 1);
        $this->assertNotEmpty($pkg['package_code']);
        $this->assertNotEmpty($pkg['sha256_checksum']);
        $this->assertNotEmpty($pkg['signature']);

        // 2. Apply Valid Package
        $applyResult = $pkgService->applyPackage($pkg['id'], 1);
        $this->assertTrue($applyResult['success'], $applyResult['message'] ?? 'Unknown apply error');
        $this->assertSame(1, $applyResult['imported_count']);

        // 3. Verify Idempotence (Already Applied)
        $reapply = $pkgService->applyPackage($pkg['id'], 1);
        $this->assertFalse($reapply['success']);
        $this->assertStringContainsString('already been applied', $reapply['message']);

        // 4. Verify Cryptographic Tamper Detection
        $tamperedPkg = $pkgService->createPackage('DEV', 'PROD', $entities, 1);
        // Deliberately tamper with payload in database
        $this->db->prepare("UPDATE environment_promotions SET payload_bundle = '{\"tampered\": true}' WHERE id = ?")
            ->execute([$tamperedPkg['id']]);

        $tamperResult = $pkgService->applyPackage($tamperedPkg['id'], 1);
        $this->assertFalse($tamperResult['success']);
        $this->assertStringContainsString('signature mismatch', strtolower($tamperResult['message']));
    }
}
