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
     * Display detailed provider statistics, configurations, catalog, and error mappings.
     */
    public function show(Provider $provider): View
    {
        $provider->loadMissing([
            'products.category',
            'supplierRoutes.product',
            'errorMappings',
            'upstreamProducts',
        ]);

        $providers = $this->providerService->getProvidersWithCircuitStatus();
        $matched = $providers->firstWhere('id', $provider->id);
        if ($matched) {
            $provider->circuit_state = $matched->circuit_state;
            $provider->circuit_available = $matched->circuit_available;
        }

        $incidentLogs = $this->providerService->getProviderIncidentLogs($provider, 12);
        $lastPing = Cache::get("provider:{$provider->code}:last_ping");
        $standardErrors = \App\Domain\Ppob\Services\ProviderErrorMappingService::getStandardErrorDefinitions();

        return view('admin.providers.show', [
            'provider' => $provider,
            'incidentLogs' => $incidentLogs,
            'lastPing' => $lastPing,
            'standardErrors' => $standardErrors,
        ]);
    }

    /**
     * Update provider settings, connection credentials, and routing limits.
     */
    public function update(\App\Domain\Admin\Http\Requests\UpdateProviderSettingsRequest $request, Provider $provider): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->providerService->updateProviderSettings(
            provider: $provider,
            data: $request->validated(),
            admin: $admin
        );

        return redirect()
            ->route('admin.providers.show', $provider)
            ->with('success', "Konfigurasi dan kredensial provider {$provider->name} berhasil diperbarui.");
    }

    /**
     * Check provider balance via API.
     */
    public function checkBalance(Provider $provider): JsonResponse|RedirectResponse
    {
        $result = $this->providerService->checkProviderBalance($provider);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return redirect()
                ->back()
                ->with('success', "Saldo upstream {$provider->name}: {$result['balance_formatted']}");
        }

        return redirect()
            ->back()
            ->with('error', $result['message']);
    }

    /**
     * Store or update error code mapping for provider.
     */
    public function storeErrorMapping(\App\Domain\Admin\Http\Requests\StoreProviderErrorMappingRequest $request, Provider $provider): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->providerService->storeErrorMapping(
            provider: $provider,
            data: $request->validated(),
            admin: $admin
        );

        return redirect()
            ->route('admin.providers.show', ['provider' => $provider, 'tab' => 'errors'])
            ->with('success', "Pemetaan error code [{$request->validated('upstream_code')}] berhasil disimpan.");
    }

    /**
     * Delete error code mapping for provider.
     */
    public function destroyErrorMapping(Provider $provider, \App\Domain\Product\Models\ProviderErrorMapping $mapping): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->providerService->deleteErrorMapping(
            provider: $provider,
            mapping: $mapping,
            admin: $admin
        );

        return redirect()
            ->route('admin.providers.show', ['provider' => $provider, 'tab' => 'errors'])
            ->with('success', "Pemetaan error code [{$mapping->upstream_code}] berhasil dihapus.");
    }

    /**
     * Sync upstream products pricelist catalog.
     */
    public function syncUpstreamProducts(Provider $provider): JsonResponse|RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $result = $this->providerService->syncUpstreamProducts(
            provider: $provider,
            admin: $admin
        );

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json($result);
        }

        return redirect()
            ->route('admin.providers.show', ['provider' => $provider, 'tab' => 'products'])
            ->with('success', "Berhasil mensinkronkan {$result['synced']} produk upstream dari {$provider->name}.");
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
