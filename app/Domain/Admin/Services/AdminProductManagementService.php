<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\Admin;
use App\Domain\Ppob\Models\ProductSupplierRoute;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\ProductTierPrice;
use App\Domain\Product\Models\Provider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminProductManagementService
{
    public function __construct(
        protected AdminAuditLogService $auditLogService
    ) {}

    /**
     * Filter and paginate products with relations.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filterProducts(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->select([
                'id',
                'provider_id',
                'category_id',
                'operator_id',
                'sku_code',
                'name',
                'product_type',
                'base_price_cents',
                'admin_fee_cents',
                'is_active',
                'created_at',
            ])
            ->with([
                'category' => fn ($q) => $q->select(['id', 'name', 'slug']),
                'operator' => fn ($q) => $q->select(['id', 'name', 'code']),
                'provider' => fn ($q) => $q->select(['id', 'name']),
            ]);

        if (! empty($filters['q'])) {
            $q = trim($filters['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('sku_code', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['operator_id'])) {
            $query->where('operator_id', (int) $filters['operator_id']);
        }

        if (! empty($filters['provider_id'])) {
            $query->where('provider_id', (int) $filters['provider_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['product_type'])) {
            $query->where('product_type', $filters['product_type']);
        }

        return $query->orderBy('name')->paginate(20)->withQueryString();
    }

    /**
     * Create a new product.
     *
     * @param  array<string, mixed>  $data
     */
    public function createProduct(array $data, Admin $admin): Product
    {
        return DB::transaction(function () use ($data, $admin) {
            $basePriceCents = (int) round(((float) ($data['base_price_rupiah'] ?? 0)) * 100);
            $adminFeeCents = (int) round(((float) ($data['admin_fee_rupiah'] ?? 0)) * 100);

            $product = Product::create([
                'provider_id' => $data['provider_id'],
                'category_id' => $data['category_id'],
                'operator_id' => ! empty($data['operator_id']) ? (int) $data['operator_id'] : null,
                'sku_code' => strtoupper(trim($data['sku_code'])),
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'product_type' => $data['product_type'],
                'base_price_cents' => $basePriceCents,
                'admin_fee_cents' => $adminFeeCents,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'allow_failover' => (bool) ($data['allow_failover'] ?? true),
            ]);

            // Create initial supplier route
            ProductSupplierRoute::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'provider_id' => $product->provider_id,
                ],
                [
                    'priority' => 1,
                    'is_active' => true,
                ]
            );

            $this->auditLogService->log(
                admin: $admin,
                action: 'product.create',
                target: $product,
                changes: [
                    'sku_code' => $product->sku_code,
                    'name' => $product->name,
                    'base_price_cents' => $basePriceCents,
                ],
                reason: "Tambah produk baru {$product->sku_code}"
            );

            return $product;
        });
    }

    /**
     * Update an existing product.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(Product $product, array $data, Admin $admin): Product
    {
        return DB::transaction(function () use ($product, $data, $admin) {
            $oldValues = [
                'name' => $product->name,
                'base_price_cents' => $product->base_price_cents?->toCents(),
                'is_active' => $product->is_active,
                'provider_id' => $product->provider_id,
            ];

            $basePriceCents = (int) round(((float) ($data['base_price_rupiah'] ?? 0)) * 100);
            $adminFeeCents = (int) round(((float) ($data['admin_fee_rupiah'] ?? 0)) * 100);

            $product->update([
                'provider_id' => $data['provider_id'],
                'category_id' => $data['category_id'],
                'operator_id' => ! empty($data['operator_id']) ? (int) $data['operator_id'] : null,
                'sku_code' => strtoupper(trim($data['sku_code'])),
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'product_type' => $data['product_type'],
                'base_price_cents' => $basePriceCents,
                'admin_fee_cents' => $adminFeeCents,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'allow_failover' => (bool) ($data['allow_failover'] ?? false),
            ]);

            $this->auditLogService->log(
                admin: $admin,
                action: 'product.update',
                target: $product,
                changes: [
                    'old' => $oldValues,
                    'new' => [
                        'name' => $product->name,
                        'base_price_cents' => $basePriceCents,
                        'is_active' => $product->is_active,
                        'provider_id' => $product->provider_id,
                    ],
                ],
                reason: "Perbarui informasi produk {$product->sku_code}"
            );

            return $product;
        });
    }

    /**
     * Update price tiers matrix for a product.
     * Enforces that sell price cannot be lower than base cost.
     *
     * @param  array<int, numeric>  $tierPrices  [user_tier_id => sell_price_rupiah]
     *
     * @throws ValidationException
     */
    public function updateTierPrices(Product $product, array $tierPrices, Admin $admin): void
    {
        $baseCostCents = $product->base_price_cents ? $product->base_price_cents->toCents() : 0;

        DB::transaction(function () use ($product, $tierPrices, $admin, $baseCostCents) {
            $changes = [];

            foreach ($tierPrices as $tierId => $priceRupiah) {
                $sellPriceCents = (int) round(((float) $priceRupiah) * 100);

                if ($sellPriceCents < $baseCostCents) {
                    throw ValidationException::withMessages([
                        "tier_prices.{$tierId}" => 'Harga tier (Rp '.number_format((float) $priceRupiah, 0, ',', '.').') tidak boleh lebih rendah dari harga modal provider.',
                    ]);
                }

                $record = ProductTierPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'user_tier_id' => (int) $tierId,
                    ],
                    [
                        'sell_price_cents' => $sellPriceCents,
                    ]
                );

                $changes[$tierId] = $sellPriceCents;
            }

            $this->auditLogService->log(
                admin: $admin,
                action: 'product.tier_prices_update',
                target: $product,
                changes: $changes,
                reason: "Perbarui matriks harga tier untuk {$product->sku_code}"
            );
        });
    }

    /**
     * Delete a product.
     */
    public function deleteProduct(Product $product, Admin $admin): void
    {
        DB::transaction(function () use ($product, $admin) {
            $sku = $product->sku_code;

            $this->auditLogService->log(
                admin: $admin,
                action: 'product.delete',
                target: $product,
                changes: ['sku_code' => $sku],
                reason: "Hapus produk {$sku}"
            );

            $product->delete();
        });
    }

    /**
     * Toggle active status of a single product.
     */
    public function toggleProductStatus(Product $product, Admin $admin): bool
    {
        return DB::transaction(function () use ($product, $admin) {
            $newStatus = ! $product->is_active;
            $product->update(['is_active' => $newStatus]);

            $this->auditLogService->log(
                admin: $admin,
                action: 'product.toggle_status',
                target: $product,
                changes: [
                    'old' => ! $newStatus,
                    'new' => $newStatus,
                ],
                reason: "Toggle cepat status produk {$product->sku_code} menjadi ".($newStatus ? 'AKTIF' : 'NONAKTIF')
            );

            return $newStatus;
        });
    }

    /**
     * Emergency bulk freeze / unfreeze products by category or provider.
     *
     * @param  array<string, mixed>  $data
     */
    public function bulkUpdateStatus(array $data, Admin $admin): int
    {
        $query = Product::query();

        if (! empty($data['category_id'])) {
            $query->where('category_id', (int) $data['category_id']);
        }

        if (! empty($data['provider_id'])) {
            $query->where('provider_id', (int) $data['provider_id']);
        }

        $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        $count = $query->count();

        DB::transaction(function () use ($query, $isActive, $admin, $data, $count) {
            $query->update(['is_active' => $isActive]);

            $statusText = $isActive ? 'mengaktifkan' : 'menonaktifkan (freeze)';
            $this->auditLogService->log(
                admin: $admin,
                action: 'product.bulk_status_update',
                target: null,
                changes: [
                    'category_id' => $data['category_id'] ?? 'all',
                    'provider_id' => $data['provider_id'] ?? 'all',
                    'is_active' => $isActive,
                    'affected_count' => $count,
                ],
                reason: $data['reason'] ?? "Emergency {$statusText} massal {$count} produk"
            );
        });

        return $count;
    }

    /**
     * Bulk adjust tier selling prices with anti-negative margin guard.
     *
     * @param  array<string, mixed>  $data
     * @return array{updated_count: int, skipped_count: int}
     */
    public function bulkAdjustPrices(array $data, Admin $admin): array
    {
        $query = Product::query()->with('tierPrices');

        if (! empty($data['category_id'])) {
            $query->where('category_id', (int) $data['category_id']);
        }

        if (! empty($data['provider_id'])) {
            $query->where('provider_id', (int) $data['provider_id']);
        }

        $products = $query->get();
        $updatedCount = 0;
        $skippedCount = 0;
        $direction = $data['direction'] ?? 'increase';
        $type = $data['adjustment_type'];
        $amount = (float) $data['amount'];

        DB::transaction(function () use ($products, $direction, $type, $amount, $admin, &$updatedCount, &$skippedCount, $data) {
            foreach ($products as $product) {
                $baseCostCents = $product->base_price_cents ? $product->base_price_cents->toCents() : 0;
                $tierPrices = $product->tierPrices;

                if ($tierPrices->isEmpty()) {
                    continue;
                }

                $productUpdated = false;

                foreach ($tierPrices as $tierPrice) {
                    $currentSellCents = $tierPrice->sell_price_cents ? $tierPrice->sell_price_cents->toCents() : 0;

                    if ($type === 'fixed') {
                        $diffCents = (int) round($amount * 100);
                    } else {
                        $diffCents = (int) round(($currentSellCents * $amount) / 100);
                    }

                    $newSellCents = $direction === 'increase'
                        ? $currentSellCents + $diffCents
                        : $currentSellCents - $diffCents;

                    // Anti-negative margin guard
                    if ($newSellCents < $baseCostCents) {
                        $skippedCount++;

                        continue;
                    }

                    $tierPrice->update(['sell_price_cents' => $newSellCents]);
                    $productUpdated = true;
                }

                if ($productUpdated) {
                    $updatedCount++;
                }
            }

            $this->auditLogService->log(
                admin: $admin,
                action: 'product.bulk_price_adjustment',
                target: null,
                changes: [
                    'category_id' => $data['category_id'] ?? 'all',
                    'provider_id' => $data['provider_id'] ?? 'all',
                    'type' => $type,
                    'direction' => $direction,
                    'amount' => $amount,
                    'updated_products' => $updatedCount,
                    'skipped_prices' => $skippedCount,
                ],
                reason: $data['reason'] ?? 'Penyesuaian harga massal produk'
            );
        });

        return [
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
        ];
    }

    /**
     * Auto-sync pricelist and new SKUs from provider upstream.
     *
     * @return array{synced_count: int, new_count: int, warning_count: int}
     */
    public function syncProviderPricelist(Provider $provider, Admin $admin): array
    {
        return DB::transaction(function () use ($provider, $admin) {
            $upstreamItems = $this->fetchUpstreamPricelist($provider);
            $syncedCount = 0;
            $newCount = 0;
            $warningCount = 0;

            foreach ($upstreamItems as $item) {
                $skuCode = strtoupper(trim($item['sku_code']));
                $basePriceCents = (int) round(((float) $item['base_price_rupiah']) * 100);

                $product = Product::where('sku_code', $skuCode)->first();

                if ($product) {
                    $product->update([
                        'base_price_cents' => $basePriceCents,
                        'is_active' => $item['is_active'] ?? $product->is_active,
                    ]);
                    $syncedCount++;

                    $hasNegativeMargin = $product->tierPrices()
                        ->where('sell_price_cents', '<', $basePriceCents)
                        ->exists();

                    if ($hasNegativeMargin) {
                        $warningCount++;
                    }
                } else {
                    $category = ProductCategory::where('code', $item['category_code'] ?? 'PULSA')->first()
                        ?? ProductCategory::firstOrCreate(
                            ['code' => $item['category_code'] ?? 'PULSA'],
                            ['name' => $item['category_name'] ?? 'Pulsa & Data', 'is_active' => true]
                        );

                    $newProduct = Product::create([
                        'provider_id' => $provider->id,
                        'category_id' => $category->id,
                        'sku_code' => $skuCode,
                        'name' => trim($item['name']),
                        'product_type' => $item['product_type'] ?? Product::TYPE_PREPAID,
                        'base_price_cents' => $basePriceCents,
                        'admin_fee_cents' => 0,
                        'is_active' => $item['is_active'] ?? true,
                    ]);

                    ProductSupplierRoute::create([
                        'product_id' => $newProduct->id,
                        'provider_id' => $provider->id,
                        'priority' => 1,
                        'is_active' => true,
                    ]);

                    $newCount++;
                }
            }

            $this->auditLogService->log(
                admin: $admin,
                action: 'product.sync_provider_pricelist',
                target: $provider,
                changes: [
                    'synced_count' => $syncedCount,
                    'new_count' => $newCount,
                    'warning_count' => $warningCount,
                ],
                reason: "Auto-sync katalog & pricelist untuk provider {$provider->name}"
            );

            return [
                'synced_count' => $syncedCount,
                'new_count' => $newCount,
                'warning_count' => $warningCount,
            ];
        });
    }

    /**
     * Update multi-provider supplier routes for a product.
     *
     * @param  array<int, array{provider_id: int, priority: int, is_active: bool}>  $routes
     */
    public function updateSupplierRoutes(Product $product, array $routes, Admin $admin): void
    {
        DB::transaction(function () use ($product, $routes, $admin) {
            $oldRoutes = $product->supplierRoutes()->get()->map(function ($r) {
                return ['provider_id' => $r->provider_id, 'priority' => $r->priority, 'is_active' => $r->is_active];
            })->all();

            $product->supplierRoutes()->delete();

            foreach ($routes as $route) {
                if (empty($route['provider_id'])) {
                    continue;
                }
                $costCents = ! empty($route['cost_rupiah']) ? (int) round(((float) $route['cost_rupiah']) * 100) : (! empty($route['cost_cents']) ? (int) $route['cost_cents'] : null);
                $supplierSku = ! empty($route['supplier_sku']) ? trim((string) $route['supplier_sku']) : null;
                ProductSupplierRoute::create([
                    'product_id' => $product->id,
                    'provider_id' => (int) $route['provider_id'],
                    'supplier_sku' => $supplierSku,
                    'priority' => (int) ($route['priority'] ?? 1),
                    'cost_cents' => $costCents,
                    'is_active' => filter_var($route['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);
            }

            $this->auditLogService->log(
                admin: $admin,
                action: 'product.routes_update',
                target: $product,
                changes: [
                    'old' => $oldRoutes,
                    'new' => $routes,
                ],
                reason: "Perbarui pemetaan rute multi-supplier untuk produk {$product->sku_code}"
            );
        });
    }

    /**
     * Helper to fetch simulated upstream pricelist.
     */
    protected function fetchUpstreamPricelist(Provider $provider): array
    {
        $existing = Product::where('provider_id', $provider->id)->get();
        if ($existing->isNotEmpty()) {
            return $existing->map(function ($p) {
                return [
                    'sku_code' => $p->sku_code,
                    'name' => $p->name,
                    'base_price_rupiah' => $p->base_price_cents ? $p->base_price_cents->toRupiah() : 5000,
                    'category_code' => $p->category?->code ?? 'PULSA',
                    'category_name' => $p->category?->name ?? 'Pulsa',
                    'product_type' => $p->product_type,
                    'is_active' => $p->is_active,
                ];
            })->all();
        }

        return [
            [
                'sku_code' => strtoupper($provider->code).'-PULSA5K',
                'name' => 'Pulsa '.$provider->name.' 5.000',
                'base_price_rupiah' => 5200,
                'category_code' => 'PULSA',
                'category_name' => 'Pulsa',
                'product_type' => Product::TYPE_PREPAID,
                'is_active' => true,
            ],
            [
                'sku_code' => strtoupper($provider->code).'-PULSA10K',
                'name' => 'Pulsa '.$provider->name.' 10.000',
                'base_price_rupiah' => 10200,
                'category_code' => 'PULSA',
                'category_name' => 'Pulsa',
                'product_type' => Product::TYPE_PREPAID,
                'is_active' => true,
            ],
        ];
    }
}
