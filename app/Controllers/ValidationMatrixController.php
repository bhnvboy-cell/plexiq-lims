<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Services\Validation\TraceabilityService;

class ValidationMatrixController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $service = new TraceabilityService();
        $rtmData = $service->generateTraceabilityMatrix();

        return $this->render('validation.matrix', [
            'rtm' => $rtmData
        ]);
    }

    public function exportJson(): string
    {
        Auth::requireAuth();
        $service = new TraceabilityService();
        $rtmData = $service->generateTraceabilityMatrix();

        return $this->json($rtmData);
    }
}
