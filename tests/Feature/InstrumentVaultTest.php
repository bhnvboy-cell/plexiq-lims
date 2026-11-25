<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\Database;
use App\Services\InstrumentVaultService;
use Tests\TestCase;
use PDO;

class InstrumentVaultTest extends TestCase
{
    private InstrumentVaultService $vaultService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vaultService = new InstrumentVaultService();
    }

    public function test_raw_data_vault_ingestion_and_immutability(): void
    {
        $db = Database::connect();

        $rawContent = "SampleName,Vial,PeakName,Area,Amount,Units\r\n"
                    . "SMP-VAULT-01,1,Assay,1500000,99.5,%\r\n";
        $filename = "empower_export_" . uniqid() . ".csv";
        $secret = 'test_secret_key';
        $hmac = hash_hmac('sha256', $rawContent, $secret);

        $res = $this->vaultService->ingest(
            $rawContent,
            $filename,
            null,
            $hmac,
            $secret
        );

        $this->assertNotEmpty($res['vault_uid']);
        $this->assertEquals(hash('sha256', $rawContent), $res['hash']);
        $this->assertEquals(1, $res['parsed_count']);
        $this->assertEquals('Parsed', $res['status']);

        $vaultId = $res['vault_id'];

        // Verify cryptographic integrity check
        $verify = $this->vaultService->verifyVaultIntegrity($vaultId);
        $this->assertTrue($verify['valid']);
        $this->assertEquals('Verified_Intact', $verify['status']);

        // ALCOA+ Immutability Verification: DELETE must be strictly blocked by trigger
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage("Raw Data Vault records are permanently immutable");
        $db->query("DELETE FROM instrument_raw_vault WHERE id = {$vaultId}");
    }
}
