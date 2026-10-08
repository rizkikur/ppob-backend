<?php

namespace App\Domain\Ppob\Services;

use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\PartnerRoutingRule;
use App\Domain\Ppob\DTOs\ResolvedRoute;
use App\Domain\Ppob\Models\ProductSupplierRoute;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\Exceptions\BusinessException;
use Illuminate\Support\Collection;

/**
 * SupplierRoutingService — Router multi-supplier & failover engine (ADR-004 & ADR-005).
 *
 * Aturan Keras (ADR-005):
 * - Failover hanya boleh terjadi SEBELUM job pertama di-dispatch (di method resolve()).
 * - Sekali transaksi di-dispatch ke supplier X, retry harus tetap ke supplier X.
 * - Konfigurasi failover bersifat per-klien dan per-kategori via partner_routing_rules.
 */
class SupplierRoutingService
{
    public function __construct(
        private readonly ?CircuitBreakerService $circuitBreaker = null
    ) {}

    /**
     * Resolusi supplier terbaik untuk melayani produk tertentu.
     *
     * @throws BusinessException jika tidak ada supplier yang tersedia
     */
    public function resolve(Product $product, ?Partner $partner = null): ResolvedRoute
    {
        $product->loadMissing(['provider', 'category']);

        // 1. Dapatkan daftar kandidat rute supplier untuk produk ini
        $candidates = $this->getCandidateProviders($product);

        if ($candidates->isEmpty()) {
            throw BusinessException::providerUnavailable($product->provider?->name ?? 'Provider');
        }

        // 2. Evaluasi aturan routing mitra jika partner disediakan
        $rule = $partner ? $this->findPartnerRule($partner, $product) : null;

        if ($partner) {
            // Klien tanpa aturan -> default: allow_failover = false, policy = none (ADR-004)
            $allowFailover = $rule ? $rule->allow_failover : false;
            $failoverPolicy = $rule ? ($rule->failover_policy ?: PartnerRoutingRule::POLICY_NONE) : PartnerRoutingRule::POLICY_NONE;
            $preferredProviderId = $rule?->preferred_provider_id;
        } else {
            // Konsumen mobile B2C: gunakan default dari konfigurasi
            $allowFailover = (bool) config('ppob.routing.default_user_allow_failover', true);
            $failoverPolicy = (string) config('ppob.routing.default_user_failover_policy', PartnerRoutingRule::POLICY_SAME_CATEGORY);
            $preferredProviderId = null;
        }

        // 3. Tentukan preferred provider (supplier utama)
        $preferredProvider = null;
        if ($preferredProviderId) {
            $preferredProvider = $candidates->firstWhere('id', $preferredProviderId)
                ?? Provider::where('id', $preferredProviderId)->where('is_active', true)->first();
        }

        if (! $preferredProvider) {
            $preferredProvider = $candidates->first();
        }

        if (! $preferredProvider) {
            throw BusinessException::providerUnavailable($product->provider?->name ?? 'Provider');
        }

        $originalSupplier = $preferredProvider;

        // 4. Periksa ketersediaan preferred provider (status aktif & circuit breaker)
        if ($this->isProviderAvailable($preferredProvider)) {
            $queueName = $preferredProvider->queue_name ?: 'transactions';

            return new ResolvedRoute(
                provider: $preferredProvider,
                originalProvider: $originalSupplier,
                isFailover: false,
                queueName: $queueName
            );
        }

        // 5. Preferred provider unavailable — evaluasi apakah boleh failover
        if (! $allowFailover || $failoverPolicy === PartnerRoutingRule::POLICY_NONE) {
            throw BusinessException::providerUnavailable($preferredProvider->name);
        }

        // 6. Cari provider backup sesuai failover policy
        $backupProvider = $this->resolveBackupProvider($product, $candidates, $preferredProvider, $failoverPolicy);

        if (! $backupProvider) {
            throw BusinessException::providerUnavailable($preferredProvider->name);
        }

        $queueName = $backupProvider->queue_name ?: 'transactions';

        return new ResolvedRoute(
            provider: $backupProvider,
            originalProvider: $originalSupplier,
            isFailover: true,
            queueName: $queueName
        );
    }

    /**
     * Dapatkan daftar kandidat provider yang aktif untuk suatu produk diurutkan berdasarkan prioritas.
     *
     * @return Collection<int, Provider>
     */
    private function getCandidateProviders(Product $product): Collection
    {
        $routes = ProductSupplierRoute::where('product_id', $product->id)
            ->active()
            ->ordered()
            ->with('provider')
            ->get();

        $providers = $routes
            ->map(fn (ProductSupplierRoute $route) => $route->provider)
            ->filter(fn (?Provider $provider) => $provider && $provider->is_active);

        // Jika belum ada pemetaan di product_supplier_routes, gunakan provider default produk
        if ($providers->isEmpty() && $product->provider && $product->provider->is_active) {
            return collect([$product->provider]);
        }

        return $providers->values();
    }

    /**
     * Cari aturan routing mitra yang paling spesifik (kategori match -> fallback ke global).
     */
    private function findPartnerRule(Partner $partner, Product $product): ?PartnerRoutingRule
    {
        $categoryCode = $product->category?->code;

        if ($categoryCode) {
            $specificRule = PartnerRoutingRule::where('partner_id', $partner->id)
                ->where('category_code', $categoryCode)
                ->first();

            if ($specificRule) {
                return $specificRule;
            }
        }

        return PartnerRoutingRule::where('partner_id', $partner->id)
            ->whereNull('category_code')
            ->first();
    }

    /**
     * Cari provider backup yang tersedia berdasarkan failover policy.
     *
     * @param  Collection<int, Provider>  $candidates
     */
    private function resolveBackupProvider(
        Product $product,
        Collection $candidates,
        Provider $failedProvider,
        string $policy
    ): ?Provider {
        // Kandidat dari rute produk yang berbeda dengan supplier yang gagal
        $remainingCandidates = $candidates->reject(fn (Provider $p) => $p->id === $failedProvider->id);

        foreach ($remainingCandidates as $candidate) {
            if ($this->isProviderAvailable($candidate)) {
                return $candidate;
            }
        }

        // Jika policy 'any', cari provider aktif lain di sistem yang memiliki driver kompatibel
        if ($policy === PartnerRoutingRule::POLICY_ANY && $product->provider) {
            $fallbackProviders = Provider::where('is_active', true)
                ->where('id', '!=', $failedProvider->id)
                ->where('driver', $product->provider->driver)
                ->orderBy('priority', 'asc')
                ->get();

            foreach ($fallbackProviders as $fallback) {
                if ($this->isProviderAvailable($fallback)) {
                    return $fallback;
                }
            }
        }

        return null;
    }

    /**
     * Cek apakah provider aktif dan circuit breaker tidak dalam status OPEN.
     */
    public function isProviderAvailable(Provider $provider): bool
    {
        if (! $provider->is_active) {
            return false;
        }

        $cb = $this->circuitBreaker ?? app(CircuitBreakerService::class);

        return $cb->isAvailable($provider->code);
    }
}
