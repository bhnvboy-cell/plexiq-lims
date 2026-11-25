<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Services\Ops\QueueService;
use App\Services\Ops\SbomService;
use App\Services\Ops\PartitioningService;

class OpsController extends BaseController
{
    public function queue(): string
    {
        Auth::requireRole('Admin');
        $queueService = new QueueService();
        $stats = $queueService->stats();

        $db = \App\Helpers\Database::connect();
        $recentJobs = $db->query("
            SELECT * FROM job_queues
            ORDER BY id DESC
            LIMIT 30
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('ops.queue', [
            'stats' => $stats,
            'recentJobs' => $recentJobs
        ]);
    }

    public function sbom(): string
    {
        Auth::requireAuth();
        $sbomService = new SbomService();
        $summary = $sbomService->getSecuritySummary();
        $cycloneDx = $sbomService->generateCycloneDx();

        return $this->render('ops.sbom', [
            'summary' => $summary,
            'bom' => $cycloneDx
        ]);
    }

    public function sbomJson(): string
    {
        Auth::requireAuth();
        $sbomService = new SbomService();
        return $this->json($sbomService->generateCycloneDx());
    }

    public function partitioning(): string
    {
        Auth::requireRole('Admin');
        $partService = new PartitioningService();
        return $this->render('ops.partitioning', [
            'plan' => $partService->getPartitionPlan()
        ]);
    }
}
