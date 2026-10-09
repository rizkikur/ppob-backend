<?php

namespace App\Domain\Product\Http\Controllers;

use App\Domain\Product\Http\Resources\CategoryResource;
use App\Domain\Product\Http\Resources\ProductResource;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Product\Services\PhoneOperatorService;
use App\Domain\Product\Services\ProductPricingService;
use App\Domain\Shared\Http\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends ApiController
{
    public function __construct(
        private readonly ProductPricingService $pricingService,
        private readonly PhoneOperatorService $operatorService
    ) {}

    public function categories(): JsonResponse
    {
        $categories = ProductCategory::active()
            ->orderBy('sort_order')
            ->get();

        return $this->success(
            $categories->map(fn ($c) => new CategoryResource($c))
        );
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $products = Product::with(['provider', 'category'])
            ->active()
            ->when($request->input('category'), function ($q, $cat) {
                $q->whereHas('category', fn ($c) => $c->where('code', $cat));
            })
            ->when($request->input('provider'), function ($q, $prov) {
                $q->whereHas('provider', fn ($p) => $p->where('code', $prov));
            })
            ->when($request->input('product_type'), function ($q, $type) {
                $q->where('product_type', $type);
            })
            ->orderBy('category_id')
            ->orderBy('id')
            ->get()
            ->map(fn ($p) => new ProductResource($p, $this->pricingService->getPriceForUser($p, $user)));

        return $this->success($products);
    }

    /**
     * Deteksi otomatis operator seluler berdasarkan nomor HP
     * dan kembalikan produk prabayar aktif (pulsa & paket data).
     */
    public function operatorPrefix(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:4'],
        ]);

        $rawPhone = $request->input('phone');
        $normalizedPhone = $this->operatorService->normalize($rawPhone);
        $prefix = $this->operatorService->getPrefix($normalizedPhone);
        $providerCode = $this->operatorService->detectOperator($normalizedPhone);

        if (! $providerCode) {
            return $this->error(
                'OPERATOR_NOT_FOUND',
                "Operator tidak dikenali untuk nomor telepon '{$rawPhone}'",
                422
            );
        }

        $provider = Provider::where('code', $providerCode)
            ->where('is_active', true)
            ->first();

        if (! $provider) {
            return $this->error(
                'PROVIDER_INACTIVE',
                "Layanan untuk operator '{$providerCode}' sedang tidak tersedia",
                422
            );
        }

        $user = $request->user();

        $products = Product::with(['provider', 'category'])
            ->active()
            ->where('provider_id', $provider->id)
            ->where('product_type', Product::TYPE_PREPAID)
            ->orderBy('base_price_cents')
            ->get()
            ->map(fn ($p) => new ProductResource($p, $this->pricingService->getPriceForUser($p, $user)));

        return $this->success([
            'phone' => $normalizedPhone,
            'prefix' => $prefix,
            'provider' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'code' => $provider->code,
            ],
            'products' => $products,
        ], 'Operator berhasil dideteksi');
    }
}
