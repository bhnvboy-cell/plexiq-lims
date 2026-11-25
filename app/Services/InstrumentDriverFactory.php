<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\InstrumentDriverInterface;
use App\Services\Drivers\AstmDriver;
use App\Services\Drivers\CdsCsvDriver;
use App\Services\Drivers\SerialBalanceDriver;

class InstrumentDriverFactory
{
    /**
     * @var array<string, InstrumentDriverInterface>
     */
    private array $drivers = [];

    public function __construct()
    {
        $this->registerDriver(new AstmDriver());
        $this->registerDriver(new CdsCsvDriver());
        $this->registerDriver(new SerialBalanceDriver());
    }

    public function registerDriver(InstrumentDriverInterface $driver): void
    {
        $this->drivers[$driver->getName()] = $driver;
    }

    public function getDriver(string $driverName): InstrumentDriverInterface
    {
        if (isset($this->drivers[$driverName])) {
            return $this->drivers[$driverName];
        }

        throw new \InvalidArgumentException("Instrument driver '{$driverName}' is not registered.");
    }

    /**
     * Automatically detect driver based on raw payload or filename extension.
     */
    public function detectDriver(string $rawContent, string $filename = ''): InstrumentDriverInterface
    {
        $trimmed = trim($rawContent);

        // Check for ASTM protocol frame indicators
        if (str_starts_with($trimmed, 'H|') || str_starts_with($trimmed, '1H|') || str_contains($trimmed, "\r\nO|") || str_contains($trimmed, "\nO|")) {
            return $this->getDriver('astm_e1394');
        }

        // Check for Serial Balance SICS / Sartorius stream
        if (preg_match('/^S\s+[SD]\s+[+-]?\d+/m', $trimmed)) {
            return $this->getDriver('serial_balance');
        }

        // Default to CDS / CSV Delimited
        return $this->getDriver('cds_csv');
    }
}
