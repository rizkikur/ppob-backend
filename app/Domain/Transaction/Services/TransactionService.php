<?php

namespace App\Domain\Transaction\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Inquiry\Models\Inquiry;
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
 * Aturan keras (lihat CLAUDE.md):
 * - Idempotency key wajib dicek sebelum proses
 * - PIN token sudah divalidasi oleh PinTokenMiddleware sebelum sampai sini
 * - Untuk prepaid: amount dari klien diabaikan
 * - Untuk postpaid: inquiry_id wajib, amount harus cocok
 * - Debit saldo HANYA via WalletService
 */
class TransactionService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly ProductPricingService $pricingService
    ) {}

    /** @throws BusinessException */
    public function create(User $user, Product $product, string $customerNumber, ?int $inquiryId = null, ?int $clientAmount = null): Transaction
    {
        if (! $product->is_active) {
            throw BusinessException::productInactive();
        }
        // Hitung harga jual untuk tier user ini
        $sellPrice = $this->pricingService->getPriceForUser($product, $user);
        // Validasi postpaid
        $inquiry = null;
        if ($product->isPostpaid()) {
            if (! $inquiryId) {
                throw BusinessException::inquiryRequired();
            }
            $inquiry = Inquiry::findOrFail($inquiryId);
            if (! $inquiry->isUsable()) {
                throw BusinessException::inquiryExpired();
            }
            if ($clientAmount !== null && $clientAmount !== $inquiry->amount_cents->toCents()) {
                throw BusinessException::inquiryAmountMismatch();
            }
        }
        $amount = $product->isPostpaid() ? $inquiry->amount_cents : $sellPrice;

        return DB::transaction(function () use ($user, $product, $customerNumber, $inquiry, $amount, $sellPrice) {
            // Debit saldo via WalletService (bukan langsung ke Wallet model)
            $mutation = $this->walletService->debit(
                $user, $sellPrice,
                WalletMutation::REF_TRANSACTION,
                null,
                "Pembelian {$product->name}"
            );
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'inquiry_id' => $inquiry?->id,
                'customer_number' => $customerNumber,
                'amount_cents' => $amount->toCents(),
                'sell_price_cents' => $sellPrice->toCents(),
                'status' => Transaction::STATUS_PENDING,
                'idempotency_key' => request()->header('Idempotency-Key'),
            ]);
            // Update referensi mutasi ke transaksi ini
            $mutation->update(['reference_id' => $transaction->id]);
            // Dispatch job async untuk kirim ke provider
            ProcessTransactionJob::dispatch($transaction)->onQueue('transactions');

            return $transaction;
        });
    }
}
