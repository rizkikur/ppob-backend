<?php

namespace App\Domain\Transaction\Http\Controllers;

use App\Domain\Product\Models\Product;
use App\Domain\Shared\Http\ApiController;
use App\Domain\Transaction\Http\Requests\CreateTransactionRequest;
use App\Domain\Transaction\Http\Resources\TransactionResource;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends ApiController
{
    public function __construct(private readonly TransactionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = Transaction::where('user_id', $request->user()->id)
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')->paginate(15);

        return $this->paginated($paginator, fn ($t) => new TransactionResource($t));
    }

    public function store(CreateTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $product = Product::where('sku_code', $data['sku_code'])->where('is_active', true)->firstOrFail();
        $tx = $this->service->create($request->user(), $product, $data['customer_number'], $data['inquiry_id'] ?? null, $data['amount'] ?? null);

        return $this->created(new TransactionResource($tx));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tx = Transaction::where('user_id', $request->user()->id)->findOrFail($id);

        return $this->success(new TransactionResource($tx));
    }
}
