<?php

namespace App\Domain\Admin\Http\Controllers\Product;

use App\Domain\Admin\Http\Requests\UpdateTierPricesRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminProductManagementService;
use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminProductPriceController extends Controller
{
    public function __construct(
        protected AdminProductManagementService $productService
    ) {}

    /**
     * Update tier pricing matrix for a product.
     */
    public function updatePrices(UpdateTierPricesRequest $request, Product $product): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        try {
            $this->productService->updateTierPrices(
                product: $product,
                tierPrices: $request->validated('tier_prices'),
                admin: $admin
            );

            return redirect()
                ->route('admin.products.edit', $product)
                ->with('success', "Matriks harga tier untuk produk {$product->sku_code} berhasil diperbarui.");
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.products.edit', $product)
                ->withErrors($e->errors());
        }
    }

    /**
     * Bulk adjust tier selling prices across products.
     */
    public function bulkUpdatePrices(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'adjustment_type' => ['required', 'string', 'in:fixed,percentage'],
            'direction' => ['required', 'string', 'in:increase,decrease'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $result = $this->productService->bulkAdjustPrices($validated, $admin);

        $msg = "Penyesuaian harga massal selesai: {$result['updated_count']} produk diperbarui.";
        if ($result['skipped_count'] > 0) {
            $msg .= " Sebanyak {$result['skipped_count']} harga tier dilewati demi mencegah margin negatif (di bawah modal).";
        }

        return back()->with('success', $msg);
    }
}
