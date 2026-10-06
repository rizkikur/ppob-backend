<?php

namespace App\Domain\Wallet\Http\Controllers;

use App\Domain\Shared\Http\ApiController;
use App\Domain\Wallet\Http\Resources\MutationResource;
use App\Domain\Wallet\Http\Resources\WalletResource;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends ApiController
{
    public function __construct(
        private readonly WalletService $walletService
    ) {}

    public function balance(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        return $this->success(new WalletResource($wallet));
    }

    public function mutations(Request $request): JsonResponse
    {
        $paginator = WalletMutation::where('user_id', $request->user()->id)
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 15));

        return $this->paginated($paginator, fn ($m) => new MutationResource($m));
    }
}
