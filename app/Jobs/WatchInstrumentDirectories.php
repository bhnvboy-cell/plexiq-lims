<?php

namespace App\Jobs;

/**
 * Scans configured instrument watch directories and dispatches
 * ImportInstrumentFile jobs for every new file (auto-fetch).
 * Runs on the 'imports' queue; schedule it via cron/Scheduled Task.
 */
class WatchInstrumentDirectories extends Job
{
    public string $queue = 'imports';

    public function handle(array $payload): void
    {
        $service = new \App\Services\AnalysisParameterService();
        $result = $service->importFromWatchDirectories();
        error_log("Instrument watch scan: dispatched {$result['dispatched']} import job(s).");
    }
}
