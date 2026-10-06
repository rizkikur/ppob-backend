<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Contracts\PaymentGatewayInterface;
use App\Domain\Wallet\Drivers\FakeGatewayDriver;
use App\Domain\Wallet\Drivers\MidtransGatewayDriver;
use App\Domain\Wallet\Exceptions\InvalidPaymentGatewayException;
use App\Domain\Wallet\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Wallet\Exceptions\TopupNotFoundException;
use App\Domain\Wallet\Models\TopupRequest;
use App\Domain\Wallet\Models\WalletMutation;
use Illuminate\Support\Facades\DB;

/**
 * TopupService — mengelola alur permintaan top-up dompet digital.
 */
class TopupService
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $customDrivers = [];

    public function __construct(
        private readonly WalletService $walletService
    ) {}

    public function registerDriver(string $gateway, PaymentGatewayInterface $driver): void
    {
        $this->customDrivers[strtolower($gateway)] = $driver;
    }

    public function getDriver(string $gateway): PaymentGatewayInterface
    {
        $key = strtolower($gateway);

        if (isset($this->customDrivers[$key])) {
            return $this->customDrivers[$key];
        }

        return match ($key) {
            'midtrans' => app(MidtransGatewayDriver::class),
            'fake' => app(FakeGatewayDriver::class),
            default => throw new InvalidPaymentGatewayException("Payment gateway '{$gateway}' tidak didukung"),
        };
    }

    /**
     * Buat permintaan top-up baru.
     */
    public function createTopup(
        User $user,
        Money $amount,
        string $method,
        ?string $gateway = null,
        ?string $idempotencyKey = null
    ): TopupRequest {
        if ($method === 'payment_gateway' && empty($gateway)) {
            throw new InvalidPaymentGatewayException('Payment gateway wajib dipilih untuk metode ini');
        }

        return DB::transaction(function () use ($user, $amount, $method, $gateway, $idempotencyKey) {
            // Pastikan wallet user sudah ada
            $this->walletService->getOrCreateWallet($user);

            /** @var TopupRequest $topup */
            $topup = TopupRequest::create([
                'user_id' => $user->id,
                'amount_cents' => $amount->toCents(),
                'method' => $method,
                'payment_gateway' => $gateway,
                'status' => TopupRequest::STATUS_PENDING,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($method === 'payment_gateway' && $gateway) {
                $driver = $this->getDriver($gateway);
                $paymentInfo = $driver->createPayment($topup, $amount);

                $topup->update([
                    'gateway_ref' => $paymentInfo['gateway_ref'] ?? null,
                    'gateway_payload' => $paymentInfo['payload'] ?? null,
                ]);
            }

            return $topup->fresh();
        });
    }

    /**
     * Tandai top-up sebagai dibayar dan kreditkan saldo ke wallet.
     * Operasi ini idempoten dan mengamankan row lock via database transaction.
     */
    public function markAsPaid(TopupRequest $topup, ?string $note = null): TopupRequest
    {
        return DB::transaction(function () use ($topup, $note) {
            /** @var TopupRequest $lockedTopup */
            $lockedTopup = TopupRequest::where('id', $topup->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Jika sudah berstatus paid, jangan kreditkan ulang (idempotent)
            if ($lockedTopup->isPaid()) {
                return $lockedTopup;
            }

            // Tambah saldo ke wallet user via WalletService
            $this->walletService->credit(
                $lockedTopup->user,
                $lockedTopup->amount_cents,
                WalletMutation::REF_TOPUP,
                $lockedTopup->id,
                $note ?? 'Top-up saldo dompet'
            );

            $lockedTopup->update([
                'status' => TopupRequest::STATUS_PAID,
                'paid_at' => now(),
            ]);

            return $lockedTopup->fresh();
        });
    }

    /**
     * Konfirmasi transfer manual oleh admin.
     */
    public function confirmManual(TopupRequest $topup, User $admin): TopupRequest
    {
        return DB::transaction(function () use ($topup, $admin) {
            /** @var TopupRequest $lockedTopup */
            $lockedTopup = TopupRequest::where('id', $topup->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTopup->isPaid()) {
                return $lockedTopup;
            }

            $this->walletService->credit(
                $lockedTopup->user,
                $lockedTopup->amount_cents,
                WalletMutation::REF_TOPUP,
                $lockedTopup->id,
                'Top-up saldo transfer manual dikonfirmasi oleh admin #'.$admin->id
            );

            $lockedTopup->update([
                'status' => TopupRequest::STATUS_PAID,
                'paid_at' => now(),
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
            ]);

            return $lockedTopup->fresh();
        });
    }

    /**
     * Tangani notifikasi webhook callback dari payment gateway.
     */
    public function handleWebhook(string $gateway, array $payload, string $signature): TopupRequest
    {
        $driver = $this->getDriver($gateway);

        if (! $driver->verifyWebhook($payload, $signature)) {
            throw new InvalidWebhookSignatureException;
        }

        $gatewayRef = $driver->parseWebhookRef($payload);

        /** @var TopupRequest|null $topup */
        $topup = TopupRequest::where('gateway_ref', $gatewayRef)->first();

        if (! $topup) {
            throw new TopupNotFoundException("Permintaan top-up dengan referensi {$gatewayRef} tidak ditemukan");
        }

        $status = $driver->parseWebhookStatus($payload);

        if ($status === 'paid') {
            return $this->markAsPaid($topup, 'Top-up saldo via '.ucfirst($gateway));
        }

        if (in_array($status, ['failed', 'expired']) && $topup->isPending()) {
            $topup->update([
                'status' => $status,
                'gateway_payload' => array_merge($topup->gateway_payload ?? [], $payload),
            ]);
        }

        return $topup->fresh();
    }
}
