<?php

namespace App\Domain\Product\Http\Controllers;

use App\Domain\Product\Http\Resources\CategoryResource;
use App\Domain\Product\Http\Resources\ProductResource;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Services\ProductPricingService;
use App\Domain\Shared\Http\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends ApiController
{
    public function __construct(
        private readonly ProductPricingService $pricingService
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
}
