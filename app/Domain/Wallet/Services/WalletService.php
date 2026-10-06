<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletMutation;
use Illuminate\Support\Facades\DB;

/**
 * WalletService — SATU-SATUNYA service yang boleh mengubah saldo wallet.
 *
 * Aturan keras (lihat CLAUDE.md aturan 2):
 * - Semua perubahan saldo WAJIB lewat service ini
 * - Setiap operasi menggunakan SELECT ... FOR UPDATE (row lock)
 * - INSERT ke wallet_mutations dalam satu DB transaction
 * - Tidak ada controller/job lain yang boleh memanggil Wallet::update() langsung
 *
 * Penggunaan di service lain:
 * - Inject WalletService, jangan import Wallet model langsung
 */
class WalletService
{
    /**
     * Tambah saldo (credit) ke wallet user.
     *
     * @param  string  $referenceType  Tipe referensi (topup, refund, adjustment)
     * @param  int|null  $referenceId  ID baris di tabel referensi
     */
    public function credit(
        User $user,
        Money $amount,
        string $referenceType,
        ?int $referenceId = null,
        ?string $note = null
    ): WalletMutation {
        return DB::transaction(function () use ($user, $amount, $referenceType, $referenceId, $note) {
            // Lock baris wallet untuk mencegah race condition
            /** @var Wallet|null $wallet */
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $user->id,
                    'balance_cents' => 0,
                ]);
                $wallet = Wallet::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $newBalance = $wallet->balance()->add($amount);

            // Update saldo
            $wallet->update(['balance_cents' => $newBalance->toCents()]);

            // Catat mutasi (append-only)
            return WalletMutation::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => WalletMutation::TYPE_CREDIT,
                'amount_cents' => $amount->toCents(),
                'balance_after_cents' => $newBalance->toCents(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
            ]);
        });
    }

    /**
     * Kurangi saldo (debit) dari wallet user.
     *
     * @throws BusinessException jika saldo tidak cukup
     */
    public function debit(
        User $user,
        Money $amount,
        string $referenceType,
        ?int $referenceId = null,
        ?string $note = null
    ): WalletMutation {
        return DB::transaction(function () use ($user, $amount, $referenceType, $referenceId, $note) {
            // Lock baris wallet untuk mencegah race condition
            /** @var Wallet $wallet */
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $wallet->hasSufficientBalance($amount)) {
                throw BusinessException::insufficientBalance();
            }

            $newBalance = $wallet->balance()->subtract($amount);

            // Update saldo
            $wallet->update(['balance_cents' => $newBalance->toCents()]);

            // Catat mutasi (append-only)
            return WalletMutation::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => WalletMutation::TYPE_DEBIT,
                'amount_cents' => $amount->toCents(),
                'balance_after_cents' => $newBalance->toCents(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
            ]);
        });
    }

    /**
     * Ambil atau buat wallet untuk user (idempotent).
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance_cents' => 0]
        );
    }

    /**
     * Ambil saldo saat ini.
     */
    public function getBalance(User $user): Money
    {
        $wallet = Wallet::where('user_id', $user->id)->first();

        return $wallet?->balance() ?? Money::zero();
    }
}
