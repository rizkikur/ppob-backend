<?php

namespace App\Domain\Transaction\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Inquiry\Models\Inquiry;
use App\Domain\Partner\Jobs\DeliverWebhookJob;
use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\WebhookDelivery;
use App\Domain\Ppob\Services\SupplierRoutingService;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Services\ProductPricingService;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;

/**
 * Service transaksi PPOB.
 *
 * Aturan keras (lihat CLAUDE.md & dev-roadmap.md):
 * - Idempotency key wajib dicek sebelum proses (via IdempotencyMiddleware)
 * - PIN token sudah divalidasi oleh PinTokenMiddleware sebelum sampai sini
 * - Untuk prepaid: amount dari klien diabaikan, harga diambil dari server
 * - Untuk postpaid: inquiry_id wajib, amount harus cocok persis dengan inquiry
 * - Debit saldo HANYA via WalletService::debit() dengan row locking
 * - Dispatch job ke queue provider per ADR-002 & ADR-004
 */
class TransactionService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly ProductPricingService $pricingService,
        private readonly ?SupplierRoutingService $routingService = null
    ) {}

    private function getRoutingService(): SupplierRoutingService
    {
        return $this->routingService ?? app(SupplierRoutingService::class);
    }

    /**
     * Buat transaksi baru.
     *
     * @throws BusinessException
     */
    public function create(
        User $user,
        Product $product,
        string $customerNumber,
        ?int $inquiryId = null,
        ?int $clientAmount = null
    ): Transaction {
        if (! $product->is_active) {
            throw BusinessException::productInactive();
        }

        // Hitung harga jual untuk tier user ini
        $sellPrice = $this->pricingService->getPriceForUser($product, $user);

        // Validasi postpaid vs prepaid
        $inquiry = null;
        if ($product->isPostpaid()) {
            if (! $inquiryId) {
                throw BusinessException::inquiryRequired();
            }

            $inquiry = Inquiry::where('id', $inquiryId)
                ->where('user_id', $user->id)
                ->first();

            if (! $inquiry) {
                throw BusinessException::inquiryRequired();
            }

            if ($inquiry->product_id !== $product->id || $inquiry->customer_number !== $customerNumber) {
                throw BusinessException::inquiryRequired();
            }

            if (! $inquiry->isUsable()) {
                throw BusinessException::inquiryExpired();
            }

            if ($clientAmount === null || $clientAmount !== $inquiry->amount_cents->toCents()) {
                throw BusinessException::inquiryAmountMismatch();
            }

            // Untuk postpaid: total tagihan + admin fee yang didebit dari saldo
            $sellPrice = $inquiry->totalAmount();
            $amount = $inquiry->amount_cents;
        } else {
            // Untuk prepaid: amount dari klien diabaikan, harga dari server
            $amount = $product->base_price_cents ?? $sellPrice;
        }

        // Resolusi supplier & failover routing (ADR-004 & ADR-005)
        $resolvedRoute = $this->getRoutingService()->resolve($product);

        return DB::transaction(function () use ($user, $product, $customerNumber, $inquiry, $amount, $sellPrice, $resolvedRoute) {
            // Debit saldo via WalletService (bukan langsung update model Wallet)
            $mutation = $this->walletService->debit(
                $user,
                $sellPrice,
                WalletMutation::REF_TRANSACTION,
                null,
                "Pembelian {$product->name}"
            );

            $idempotencyKey = request()->header('Idempotency-Key') ?? '';

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'inquiry_id' => $inquiry?->id,
                'customer_number' => $customerNumber,
                'amount_cents' => $amount->toCents(),
                'sell_price_cents' => $sellPrice->toCents(),
                'status' => Transaction::STATUS_PENDING,
                'idempotency_key' => $idempotencyKey,
                'supplier_id' => $resolvedRoute->getProviderId(),
                'original_supplier_id' => $resolvedRoute->getOriginalProviderId(),
                'is_failover' => $resolvedRoute->isFailover,
            ]);

            // Hubungkan ID transaksi ke referensi mutasi dompet
            $mutation->update(['reference_id' => $transaction->id]);

            // Dispatch job async ke queue provider yang di-resolve (ADR-002 & ADR-004)
            ProcessTransactionJob::dispatch($transaction)->onQueue($resolvedRoute->queueName);

            return $transaction;
        });
    }

    /**
     * Buat transaksi baru untuk partner (Open API / B2B).
     *
     * @throws BusinessException
     */
    public function createForPartner(
        Partner $partner,
        Product $product,
        string $customerNumber,
        ?int $inquiryId = null,
        ?int $clientAmount = null
    ): Transaction {
        if (! $product->is_active) {
            throw BusinessException::productInactive();
        }

        // Ambil harga khusus partner (ADR-006: partner_product_prices atau fallback base+fee)
        $sellPrice = $partner->getPriceForProduct($product);

        $partnerUser = $partner->getOrCreateUser();
        $inquiry = null;

        if ($product->isPostpaid()) {
            if (! $inquiryId) {
                throw BusinessException::inquiryRequired();
            }

            $inquiry = Inquiry::where('id', $inquiryId)
                ->where('user_id', $partnerUser->id)
                ->first();

            if (! $inquiry) {
                throw BusinessException::inquiryRequired();
            }

            if ($inquiry->product_id !== $product->id || $inquiry->customer_number !== $customerNumber) {
                throw BusinessException::inquiryRequired();
            }

            if (! $inquiry->isUsable()) {
                throw BusinessException::inquiryExpired();
            }

            if ($clientAmount === null || $clientAmount !== $inquiry->amount_cents->toCents()) {
                throw BusinessException::inquiryAmountMismatch();
            }

            $sellPrice = $inquiry->totalAmount();
            $amount = $inquiry->amount_cents;
        } else {
            // Prepaid: amount adalah base_price produk atau sellPrice
            $amount = $product->base_price_cents ?? $sellPrice;
        }

        // Resolusi supplier & failover routing berdasarkan aturan partner (ADR-004 & ADR-005)
        $resolvedRoute = $this->getRoutingService()->resolve($product, $partner);

        return DB::transaction(function () use ($partner, $partnerUser, $product, $customerNumber, $inquiry, $amount, $sellPrice, $resolvedRoute) {
            // Debit saldo deposit partner via WalletService
            $mutation = $this->walletService->debit(
                $partnerUser,
                $sellPrice,
                WalletMutation::REF_TRANSACTION,
                null,
                "Partner {$partner->name} - Pembelian {$product->name}"
            );

            $idempotencyKey = request()->header('Idempotency-Key') ?? '';

            $transaction = Transaction::create([
                'user_id' => $partnerUser->id,
                'partner_id' => $partner->id,
                'product_id' => $product->id,
                'inquiry_id' => $inquiry?->id,
                'customer_number' => $customerNumber,
                'amount_cents' => $amount->toCents(),
                'sell_price_cents' => $sellPrice->toCents(),
                'status' => Transaction::STATUS_PENDING,
                'idempotency_key' => $idempotencyKey,
                'supplier_id' => $resolvedRoute->getProviderId(),
                'original_supplier_id' => $resolvedRoute->getOriginalProviderId(),
                'is_failover' => $resolvedRoute->isFailover,
            ]);

            // Hubungkan ID transaksi ke referensi mutasi dompet
            $mutation->update(['reference_id' => $transaction->id]);

            // Dispatch job async ke queue provider yang di-resolve (ADR-002 & ADR-004)
            ProcessTransactionJob::dispatch($transaction)->onQueue($resolvedRoute->queueName);

            return $transaction;
        });

    }

    /**
     * Tandai transaksi berhasil.
     */
    public function markSuccess(Transaction $transaction, string $providerRef, ?array $response = null): Transaction
    {
        $transaction->update([
            'status' => Transaction::STATUS_SUCCESS,
            'provider_ref' => $providerRef,
            'provider_response' => $response,
        ]);

        $this->dispatchPartnerWebhook($transaction);

        return $transaction;
    }

    /**
     * Tandai transaksi sedang diproses provider (async).
     */
    public function markProcessing(Transaction $transaction, ?string $providerRef = null, ?array $response = null): Transaction
    {
        $transaction->update([
            'status' => Transaction::STATUS_PROCESSING,
            'provider_ref' => $providerRef ?? $transaction->provider_ref,
            'provider_response' => $response ?? $transaction->provider_response,
        ]);

        return $transaction;
    }

    /**
     * Batalkan transaksi dan refund saldo ke user.
     */
    public function failAndRefund(Transaction $transaction, string $reason, ?array $response = null): Transaction
    {
        return DB::transaction(function () use ($transaction, $reason, $response) {
            /** @var Transaction $lockedTx */
            $lockedTx = Transaction::where('id', $transaction->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedTx->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_FAILED, Transaction::STATUS_REFUNDED], true)) {
                return $lockedTx;
            }

            $lockedTx->update([
                'status' => Transaction::STATUS_FAILED,
                'failure_reason' => $reason,
                'provider_response' => $response,
            ]);

            // Kembalikan dana via WalletService::credit (append-only ledger)
            $this->walletService->credit(
                $lockedTx->user,
                $lockedTx->sell_price_cents,
                WalletMutation::REF_REFUND,
                $lockedTx->id,
                "Pengembalian dana transaksi #{$lockedTx->id}: {$reason}"
            );

            $this->dispatchPartnerWebhook($lockedTx);

            return $lockedTx;
        });
    }

    /**
     * Dispatch webhook callback ke partner jika transaksi milik partner dan memiliki callback_url (ADR-007 & ADR-008).
     */
    public function dispatchPartnerWebhook(Transaction $transaction): ?WebhookDelivery
    {
        if (! $transaction->partner_id) {
            return null;
        }

        $transaction->loadMissing('partner');
        $partner = $transaction->partner;

        if (! $partner || ! $partner->callback_url) {
            return null;
        }

        $delivery = WebhookDelivery::create([
            'partner_id' => $partner->id,
            'transaction_id' => $transaction->id,
            'attempt' => 1,
            'status' => WebhookDelivery::STATUS_PENDING,
            'callback_url' => $partner->callback_url,
        ]);

        DeliverWebhookJob::dispatch($delivery->id);

        return $delivery;
    }
}
