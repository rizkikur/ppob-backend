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

    /**
     * Server-Sent Events (SSE) stream for real-time dashboard updates without polling.
     */
    public function stream(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $maxDuration = max(1, min(60, (int) $request->query('duration', 25)));
        $role = $admin->role;

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($role, $maxDuration) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $lastSeq = (int) \Illuminate\Support\Facades\Cache::get('sse:event:seq', 0);
            $startTime = time();
            $lastPing = time();

            // Kirim state awal
            $initialMetrics = $this->dashboardService->getSummaryMetrics($role);
            $initialProviders = $this->dashboardService->getProviderHealthSummary();

            echo "event: initial\n";
            echo "data: " . json_encode([
                'timestamp' => now()->toIso8601String(),
                'metrics' => $initialMetrics,
                'providers' => $initialProviders,
            ]) . "\n\n";

            if (function_exists('flush')) {
                flush();
            }

            while (time() - $startTime < $maxDuration) {
                if (connection_aborted()) {
                    break;
                }

                $currentSeq = (int) \Illuminate\Support\Facades\Cache::get('sse:event:seq', 0);
                if ($currentSeq > $lastSeq) {
                    $lastSeq = $currentSeq;
                    $latestEvent = \Illuminate\Support\Facades\Cache::get('sse:event:latest');

                    // Bersihkan cache dashboard agar angka ringkasan ter-update
                    $this->dashboardService->clearDashboardCache();
                    $freshMetrics = $this->dashboardService->getSummaryMetrics($role);
                    $freshProviders = $this->dashboardService->getProviderHealthSummary();

                    echo "event: update\n";
                    echo "data: " . json_encode([
                        'timestamp' => now()->toIso8601String(),
                        'metrics' => $freshMetrics,
                        'providers' => $freshProviders,
                        'trigger_event' => $latestEvent,
                    ]) . "\n\n";

                    if (function_exists('flush')) {
                        flush();
                    }
                }

                // Heartbeat ping setiap 10 detik agar koneksi tetap hidup
                if (time() - $lastPing >= 10) {
                    echo "event: ping\n";
                    echo "data: {\"time\":" . time() . "}\n\n";
                    if (function_exists('flush')) {
                        flush();
                    }
                    $lastPing = time();
                }

                usleep(250000); // 0.25 detik
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
