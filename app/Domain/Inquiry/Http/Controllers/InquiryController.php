<?php

namespace App\Domain\Inquiry\Http\Controllers;

use App\Domain\Inquiry\Http\Requests\InquiryRequest;
use App\Domain\Inquiry\Http\Resources\InquiryResource;
use App\Domain\Inquiry\Services\InquiryService;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\Http\ApiController;
use Illuminate\Http\JsonResponse;

class InquiryController extends ApiController
{
    public function __construct(
        private readonly InquiryService $service
    ) {}

    public function store(InquiryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = Product::with(['provider', 'category'])
            ->where('sku_code', $data['sku_code'])
            ->first();

        if (! $product) {
            throw BusinessException::productNotFound($data['sku_code']);
        }

        $inquiry = $this->service->inquiry($request->user(), $product, $data['customer_number']);

        $inquiry->setRelation('product', $product);

        return $this->success(new InquiryResource($inquiry));
    }
}
