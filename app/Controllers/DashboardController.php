<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Models\Sample;
use App\Models\SampleTest;

class DashboardController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $stats = Sample::dashboardStats();
        $recentSamples = \App\Helpers\Cache::remember('dashboard.recent_samples', 60, function () {
            return \App\Helpers\Database::connect()->query("
                SELECT s.*, c.customer_name, p.product_name
                FROM samples s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN products p ON s.product_id = p.id
                ORDER BY s.created_at DESC LIMIT 10
            ")->fetchAll();
        });

        $pendingTests = SampleTest::pendingCount();
        $inProgressTests = SampleTest::inProgressCount();

        return $this->render('dashboard.index', [
            'stats' => $stats,
            'recentSamples' => $recentSamples,
            'pendingTests' => $pendingTests,
            'inProgressTests' => $inProgressTests,
        ]);
    }
}
