<?php

namespace App\Domain\Admin\Http\Controllers\Dashboard;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardService $dashboardService
    ) {}

    /**
     * Render the admin monitoring dashboard.
     */
    public function index(Request $request): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $metrics = $this->dashboardService->getSummaryMetrics($admin->role);
        $providers = $this->dashboardService->getProviderHealthSummary();
        $chartData = $this->dashboardService->getHourlyTrend24Hours();

        return view('admin.dashboard.index', [
            'admin' => $admin,
            'metrics' => $metrics,
            'providers' => $providers,
            'chartData' => $chartData,
        ]);
    }

    /**
     * Realtime JSON metrics endpoint for Alpine.js polling.
     */
    public function metrics(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $metrics = $this->dashboardService->getSummaryMetrics($admin->role);
        $providers = $this->dashboardService->getProviderHealthSummary();

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'metrics' => $metrics,
            'providers' => $providers,
        ]);
    }
}
