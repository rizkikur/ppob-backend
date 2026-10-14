<?php

namespace App\Domain\Admin\Http\Controllers\Product;

use App\Domain\Admin\Http\Requests\StoreProductRequest;
use App\Domain\Admin\Http\Requests\UpdateProductRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminProductManagementService;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProductController extends Controller
{
    public function __construct(
        protected AdminProductManagementService $productService
    ) {}

    /**
     * Display a listing of products with filters.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'category_id', 'provider_id', 'is_active', 'product_type']);
        $products = $this->productService->filterProducts($filters);
        $categories = ProductCategory::query()->orderBy('name')->get();
        $providers = Provider::query()->orderBy('name')->get();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => $categories,
            'providers' => $providers,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View
    {
        $categories = ProductCategory::query()->orderBy('name')->get();
        $providers = Provider::query()->orderBy('name')->get();

        return view('admin.products.create', [
            'categories' => $categories,
            'providers' => $providers,
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $product = $this->productService->createProduct($request->validated(), $admin);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', "Produk {$product->sku_code} berhasil ditambahkan. Silakan atur matriks harga tier di bawah.");
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        $product->loadMissing([
            'category',
            'provider',
            'tierPrices.userTier',
            'supplierRoutes.provider',
        ]);

        $categories = ProductCategory::query()->orderBy('name')->get();
        $providers = Provider::query()->orderBy('name')->get();
        $tiers = UserTier::query()->orderBy('id')->get();

        // Map existing tier prices: [user_tier_id => sell_price_cents]
        $tierPricesMap = $product->tierPrices->pluck('sell_price_cents', 'user_tier_id')->all();

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => $categories,
            'providers' => $providers,
            'tiers' => $tiers,
            'tierPricesMap' => $tierPricesMap,
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->productService->updateProduct($product, $request->validated(), $admin);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', "Informasi produk {$product->sku_code} berhasil diperbarui.");
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $sku = $product->sku_code;
        $this->productService->deleteProduct($product, $admin);

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Produk {$sku} berhasil dihapus.");
    }

    /**
     * 1-Click quick toggle product status.
     */
    public function toggleStatus(Product $product): JsonResponse|RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $newStatus = $this->productService->toggleProductStatus($product, $admin);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $newStatus,
                'message' => "Status {$product->sku_code} diubah menjadi ".($newStatus ? 'Aktif' : 'Nonaktif'),
            ]);
        }

        return back()->with('success', "Status {$product->sku_code} berhasil diubah.");
    }

    /**
     * Emergency bulk freeze / unfreeze products.
     */
    public function bulkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $count = $this->productService->bulkUpdateStatus($validated, $admin);
        $actionText = $validated['is_active'] ? 'diaktifkan' : 'dinonaktifkan (freeze)';

        return back()->with('success', "Sebanyak {$count} produk berhasil {$actionText}.");
    }

    /**
     * Auto-sync pricelist and new SKUs from provider.
     */
    public function syncPricelist(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $provider = Provider::findOrFail($validated['provider_id']);
        $result = $this->productService->syncProviderPricelist($provider, $admin);

        $msg = "Sinkronisasi provider {$provider->name} berhasil! {$result['synced_count']} SKU diperbarui, {$result['new_count']} SKU baru diimpor.";
        if ($result['warning_count'] > 0) {
            $msg .= " Perhatian: {$result['warning_count']} produk terdeteksi margin tipis/negatif.";
        }

        return back()->with('success', $msg);
    }

    /**
     * Update supplier routes per product.
     */
    public function updateRoutes(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'routes' => ['required', 'array'],
            'routes.*.provider_id' => ['required', 'integer', 'exists:providers,id'],
            'routes.*.priority' => ['required', 'integer', 'min:1', 'max:10'],
            'routes.*.is_active' => ['nullable'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->productService->updateSupplierRoutes($product, $validated['routes'], $admin);

        return back()->with('success', "Rute multi-supplier untuk {$product->sku_code} berhasil diperbarui.");
    }
}
