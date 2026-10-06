<?php

namespace App\Domain\Wallet\Http\Controllers;

use App\Domain\Shared\Http\ApiController;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Http\Requests\TopupRequestForm;
use App\Domain\Wallet\Http\Resources\TopupResource;
use App\Domain\Wallet\Services\TopupService;
use Illuminate\Http\JsonResponse;

class TopupController extends ApiController
{
    public function __construct(
        private readonly TopupService $topupService
    ) {}

    public function topup(TopupRequestForm $request): JsonResponse
    {
        $amount = Money::fromCents($request->integer('amount'));
        $method = $request->string('method')->toString();
        $gateway = $request->input('payment_gateway');
        $idempotencyKey = $request->header('Idempotency-Key');

        $topup = $this->topupService->createTopup(
            $request->user(),
            $amount,
            $method,
            $gateway,
            $idempotencyKey
        );

        return $this->created(new TopupResource($topup));
    }
}
