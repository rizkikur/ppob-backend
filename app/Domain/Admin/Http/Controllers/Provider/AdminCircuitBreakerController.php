<?php

namespace App\Domain\Admin\Http\Controllers\Provider;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminProviderManagementService;
use App\Domain\Product\Models\Provider;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminCircuitBreakerController extends Controller
{
    public function __construct(
        protected AdminProviderManagementService $providerService
    ) {}

    /**
     * Reset Circuit Breaker status back to CLOSED for a provider.
     */
    public function reset(Provider $provider, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $reason = $request->input('reason', "Manual reset Circuit Breaker provider {$provider->code}");

        $this->providerService->resetCircuitBreaker(
            provider: $provider,
            admin: $admin,
            reason: $reason
        );

        return redirect()
            ->back()
            ->with('success', "Circuit Breaker untuk provider {$provider->name} berhasil direset ke status CLOSED (Normal).");
    }

    /**
     * Update dynamic failure threshold and cooldown timeout for Circuit Breaker.
     */
    public function updateConfig(Provider $provider, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'failure_threshold' => ['required', 'integer', 'min:1', 'max:50'],
            'cooldown_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->providerService->updateCircuitBreakerConfig(
            provider: $provider,
            data: $validated,
            admin: $admin,
            reason: $request->input('reason')
        );

        return redirect()
            ->back()
            ->with('success', "Konfigurasi Circuit Breaker untuk provider {$provider->name} berhasil diperbarui.");
    }
}
