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

    /**
     * GET /wallet/channels — Daftar metode/channel pembayaran top-up aktif.
     */
    public function channels(): JsonResponse
    {
        $channels = [
            [
                'group' => 'Virtual Account',
                'items' => [
                    [
                        'code' => 'bca_va',
                        'name' => 'BCA Virtual Account',
                        'method' => 'payment_gateway',
                        'payment_gateway' => 'midtrans',
                        'min_amount_cents' => 1000000,
                        'max_amount_cents' => 2000000000,
                        'fee_cents' => 250000,
                        'fee_display' => 'Rp 2.500',
                        'fee_type' => 'flat',
                        'is_active' => true,
                    ],
                    [
                        'code' => 'mandiri_va',
                        'name' => 'Mandiri Virtual Account',
                        'method' => 'payment_gateway',
                        'payment_gateway' => 'midtrans',
                        'min_amount_cents' => 1000000,
                        'max_amount_cents' => 2000000000,
                        'fee_cents' => 250000,
                        'fee_display' => 'Rp 2.500',
                        'fee_type' => 'flat',
                        'is_active' => true,
                    ],
                    [
                        'code' => 'bni_va',
                        'name' => 'BNI Virtual Account',
                        'method' => 'payment_gateway',
                        'payment_gateway' => 'midtrans',
                        'min_amount_cents' => 1000000,
                        'max_amount_cents' => 2000000000,
                        'fee_cents' => 250000,
                        'fee_display' => 'Rp 2.500',
                        'fee_type' => 'flat',
                        'is_active' => true,
                    ],
                    [
                        'code' => 'bri_va',
                        'name' => 'BRI Virtual Account',
                        'method' => 'payment_gateway',
                        'payment_gateway' => 'midtrans',
                        'min_amount_cents' => 1000000,
                        'max_amount_cents' => 2000000000,
                        'fee_cents' => 250000,
                        'fee_display' => 'Rp 2.500',
                        'fee_type' => 'flat',
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'group' => 'QRIS & E-Wallet',
                'items' => [
                    [
                        'code' => 'qris',
                        'name' => 'QRIS Instant (Semua E-Wallet & M-Banking)',
                        'method' => 'payment_gateway',
                        'payment_gateway' => 'midtrans',
                        'min_amount_cents' => 500000,
                        'max_amount_cents' => 500000000,
                        'fee_cents' => 0,
                        'fee_display' => '0.7%',
                        'fee_type' => 'percentage',
                        'is_active' => true,
                    ],
                    [
                        'code' => 'gopay',
                        'name' => 'GoPay',
                        'method' => 'payment_gateway',
                        'payment_gateway' => 'midtrans',
                        'min_amount_cents' => 1000000,
                        'max_amount_cents' => 200000000,
                        'fee_cents' => 0,
                        'fee_display' => '1.5%',
                        'fee_type' => 'percentage',
                        'is_active' => true,
                    ],
                    [
                        'code' => 'shopeepay',
                        'name' => 'ShopeePay',
                        'method' => 'payment_gateway',
                        'payment_gateway' => 'midtrans',
                        'min_amount_cents' => 1000000,
                        'max_amount_cents' => 200000000,
                        'fee_cents' => 0,
                        'fee_display' => '1.5%',
                        'fee_type' => 'percentage',
                        'is_active' => true,
                    ],
                ],
            ],
            [
                'group' => 'Transfer Bank Manual',
                'items' => [
                    [
                        'code' => 'manual_bca',
                        'name' => 'Transfer Bank BCA (Konfirmasi Manual)',
                        'method' => 'manual_transfer',
                        'payment_gateway' => null,
                        'account_number' => '1234567890',
                        'account_name' => 'PT PPOB INDONESIA SEJAHTERA',
                        'min_amount_cents' => 5000000,
                        'max_amount_cents' => 10000000000,
                        'fee_cents' => 0,
                        'fee_display' => 'Gratis',
                        'fee_type' => 'flat',
                        'is_active' => true,
                    ],
                ],
            ],
        ];

        return $this->success($channels, 'Daftar metode pembayaran berhasil dimuat');
    }
}
