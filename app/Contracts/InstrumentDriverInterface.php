<?php

declare(strict_types=1);

namespace App\Contracts;

interface InstrumentDriverInterface
{
    /**
     * Unique identifier/name of the driver.
     */
    public function getName(): string;

    /**
     * Protocols supported by this driver (e.g. 'astm_e1394', 'csv_delimited', 'rs232_serial').
     *
     * @return array<string>
     */
    public function getSupportedProtocols(): array;

    /**
     * Parse raw instrument payload into normalized analytical result structures.
     *
     * @param string $rawContent Raw stream or file content
     * @param array<string, mixed> $metadata Contextual information (instrument_id, filename, etc.)
     * @return array<array{
     *   sample_code: string,
     *   test_code: string,
     *   value: float|string,
     *   unit: ?string,
     *   retention_time?: ?float,
     *   peak_area?: ?float,
     *   vial_position?: ?string,
     *   status?: string
     * }>
     */
    public function parseRawData(string $rawContent, array $metadata = []): array;

    /**
     * Generate an instrument worklist sequence formatted for the target hardware.
     *
     * @param array<array{
     *   position: int|string,
     *   sample_code: string,
     *   sample_type: string,
     *   injection_volume_ul?: float,
     *   method?: string
     * }> $sequenceEntries
     * @return string Formatted worklist content
     */
    public function generateWorklist(array $sequenceEntries): string;
}
