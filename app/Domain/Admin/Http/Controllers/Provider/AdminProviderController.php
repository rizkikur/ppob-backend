<?php

namespace App\Domain\Admin\Http\Controllers\Provider;

use App\Domain\Admin\Http\Requests\ToggleProviderStatusRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminProviderManagementService;
use App\Domain\Product\Models\Provider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AdminProviderController extends Controller
{
    public function __construct(
        protected AdminProviderManagementService $providerService
    ) {}

    /**
     * Display a listing of providers with their circuit breaker status.
     */
    public function index(): View
    {
        $providers = $this->providerService->getProvidersWithCircuitStatus();
        $incidentLogs = $this->providerService->getProviderIncidentLogs(null, 8);

        return view('admin.providers.index', [
            'providers' => $providers,
            'incidentLogs' => $incidentLogs,
        ]);
    }

    /**
     * Display detailed provider statistics and configurations.
     */
    public function show(Provider $provider): View
    {
        $provider->loadMissing([
            'products.category',
            'supplierRoutes.product',
        ]);

        $providers = $this->providerService->getProvidersWithCircuitStatus();
        $matched = $providers->firstWhere('id', $provider->id);
        if ($matched) {
            $provider->circuit_state = $matched->circuit_state;
            $provider->circuit_available = $matched->circuit_available;
        }

        $incidentLogs = $this->providerService->getProviderIncidentLogs($provider, 10);
        $lastPing = Cache::get("provider:{$provider->code}:last_ping");

        return view('admin.providers.show', [
            'provider' => $provider,
            'incidentLogs' => $incidentLogs,
            'lastPing' => $lastPing,
        ]);
    }

    /**
     * Toggle active status of a provider (Emergency Action).
     */
    public function toggleStatus(ToggleProviderStatusRequest $request, Provider $provider): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->providerService->toggleProviderStatus(
            provider: $provider,
            isActive: (bool) $request->validated('is_active'),
            admin: $admin,
            reason: $request->validated('reason')
        );

        $statusText = $request->validated('is_active') ? 'DIAKTIFKAN' : 'DINONAKTIFKAN';

        return redirect()
            ->back()
            ->with('success', "Status provider {$provider->name} berhasil diubah menjadi {$statusText}.");
    }

    /**
     * Handshake / Ping test to provider driver.
     */
    public function ping(Provider $provider): JsonResponse|RedirectResponse
    {
        $result = $this->providerService->pingProvider($provider);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json($result);
        }

        return redirect()
            ->back()
            ->with('success', "Ping ke {$provider->name} berhasil: Latensi {$result['latency_ms']}ms.");
    }
}
