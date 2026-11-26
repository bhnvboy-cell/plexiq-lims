<?php

declare(strict_types=1);

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Services\Results\ResultLineageService;

class ResultLineageController extends BaseController
{
    private ResultLineageService $lineageService;

    public function __construct()
    {
        $this->lineageService = new ResultLineageService();
    }

    public function show(int $resultId): string
    {
        Auth::requireAnyRole(['Admin', 'Reviewer', 'Approver', 'Analyst']);

        $lineage = $this->lineageService->getLineage($resultId);

        return $this->render('results.lineage', [
            'resultId' => $resultId,
            'lineage' => $lineage
        ]);
    }

    public function invalidate(int $resultId): void
    {
        Auth::requireAnyRole(['Admin', 'Reviewer', 'Approver']);

        $reason = trim($_POST['invalidation_reason'] ?? '');
        if (empty($reason)) {
            session_flash('error', 'Mandatory justification reason is required to invalidate a result.');
            redirect('/results/' . $resultId . '/lineage');
            return;
        }

        try {
            $this->lineageService->invalidateResult($resultId, $reason, Auth::id() ?? 1);
            session_flash('success', 'Result invalidated and test requeued for testing per GxP protocol.');
        } catch (\Throwable $e) {
            session_flash('error', 'Invalidation failed: ' . $e->getMessage());
        }

        redirect('/results/' . $resultId . '/lineage');
    }

    public function retest(int $sampleTestId): void
    {
        Auth::requireAnyRole(['Admin', 'Reviewer', 'Approver', 'Analyst']);

        $reason = trim($_POST['retest_reason'] ?? '');
        $sampleId = (int)($_POST['sample_id'] ?? 0);

        if (empty($reason)) {
            session_flash('error', 'Mandatory justification reason is required to authorize a retest.');
            redirect($sampleId ? '/samples/' . $sampleId : '/tests/pending');
            return;
        }

        try {
            $newTestId = $this->lineageService->createRetest($sampleTestId, $reason, Auth::id() ?? 1);
            session_flash('success', "Authorized retest test #{$newTestId} scheduled successfully.");
        } catch (\Throwable $e) {
            session_flash('error', 'Retest creation failed: ' . $e->getMessage());
        }

        redirect($sampleId ? '/samples/' . $sampleId : '/tests/pending');
    }
}
