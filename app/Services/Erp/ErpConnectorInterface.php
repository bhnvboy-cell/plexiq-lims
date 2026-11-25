<?php

namespace App\Services\Erp;

interface ErpConnectorInterface
{
    /**
     * Test connection to the remote ERP system
     *
     * @return array{success: bool, message: string, latency_ms?: float}
     */
    public function ping(): array;

    /**
     * Pull inspection lots or production orders awaiting quality testing
     *
     * @param array<string, mixed> $filter
     * @return array<int, array<string, mixed>>
     */
    public function pullInspectionLots(array $filter = []): array;

    /**
     * Push completed quality test results and disposition / usage decision
     *
     * @param int|string $lotId ERP inspection lot or order identifier
     * @param array<int, array<string, mixed>> $results Test results array
     * @param string $usageDecision Usage decision (e.g., 'A' for Accepted, 'R' for Rejected)
     * @return array{success: bool, remote_reference?: string, message: string}
     */
    public function pushInspectionResults(int|string $lotId, array $results, string $usageDecision = 'A'): array;

    /**
     * Sync master data (materials, specifications, vendors) from ERP
     *
     * @param string $entityType Entity type ('materials', 'specifications', 'suppliers')
     * @return array{synced_count: int, details: array<string, mixed>}
     */
    public function syncMasterData(string $entityType): array;
}
