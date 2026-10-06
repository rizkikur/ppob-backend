<?php

namespace Tests\Feature\Wallet;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserTier $tier;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $this->tier->id,
            'name' => 'Race Condition User',
            'phone' => '081277778888',
            'email' => 'race@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->walletService = app(WalletService::class);
    }

    /**
     * Debit gagal saat saldo tidak mencukupi (INSUFFICIENT_BALANCE HTTP 422).
     */
    public function test_debit_fails_when_balance_is_insufficient(): void
    {
        $this->walletService->credit(
            $this->user,
            Money::fromCents(1000000), // Rp 10.000,00
            WalletMutation::REF_TOPUP,
            1,
            'Initial credit'
        );

        $this->expectException(BusinessException::class);

        try {
            // Coba debit Rp 20.000,00 padahal saldo hanya Rp 10.000,00
            $this->walletService->debit(
                $this->user,
                Money::fromCents(2000000),
                WalletMutation::REF_TRANSACTION,
                999,
                'Overdraft debit'
            );
        } catch (BusinessException $e) {
            $this->assertEquals('INSUFFICIENT_BALANCE', $e->getErrorCode());
            $this->assertEquals(422, $e->getHttpStatus());

            // Pastikan saldo tidak berkurang sama sekali
            $balance = $this->walletService->getBalance($this->user);
            $this->assertEquals(1000000, $balance->toCents());

            throw $e;
        }
    }

    /**
     * Simulasi race condition debit bersamaan tidak boleh menghasilkan saldo negatif.
     */
    public function test_concurrent_debit_simulation_cannot_produce_negative_balance(): void
    {
        // Saldo awal Rp 50.000,00 (5.000.000 cents)
        $this->walletService->credit(
            $this->user,
            Money::fromCents(5000000),
            WalletMutation::REF_TOPUP,
            1,
            'Initial 50k'
        );

        $amountToDebit = Money::fromCents(3000000); // Rp 30.000,00

        // Transaksi 1: Harus sukses
        $mutation1 = $this->walletService->debit(
            $this->user,
            $amountToDebit,
            WalletMutation::REF_TRANSACTION,
            101,
            'Transaction 1'
        );
        $this->assertEquals(2000000, $mutation1->balance_after_cents->toCents());

        // Transaksi 2: Harus gagal karena saldo sisa Rp 20.000,00 < Rp 30.000,00
        $failed = false;
        try {
            $this->walletService->debit(
                $this->user,
                $amountToDebit,
                WalletMutation::REF_TRANSACTION,
                102,
                'Transaction 2'
            );
        } catch (BusinessException $e) {
            $failed = true;
            $this->assertEquals('INSUFFICIENT_BALANCE', $e->getErrorCode());
        }

        $this->assertTrue($failed, 'Second debit should fail due to insufficient balance');

        // Saldo akhir harus tepat Rp 20.000,00 dan TIDAK BOLEH negatif
        $finalBalance = $this->walletService->getBalance($this->user);
        $this->assertEquals(2000000, $finalBalance->toCents());
        $this->assertGreaterThanOrEqual(0, $finalBalance->toCents());
    }

    /**
     * Ledger wallet_mutations mencatat urutan transaksi dan saldo snapshot secara akurat.
     */
    public function test_wallet_mutation_ledger_records_accurate_balance_after(): void
    {
        $m1 = $this->walletService->credit($this->user, Money::fromCents(1000000), WalletMutation::REF_TOPUP, 1);
        $this->assertEquals(1000000, $m1->balance_after_cents->toCents());

        $m2 = $this->walletService->credit($this->user, Money::fromCents(2000000), WalletMutation::REF_TOPUP, 2);
        $this->assertEquals(3000000, $m2->balance_after_cents->toCents());

        $m3 = $this->walletService->debit($this->user, Money::fromCents(500000), WalletMutation::REF_TRANSACTION, 3);
        $this->assertEquals(2500000, $m3->balance_after_cents->toCents());

        // Verifikasi dari database
        $this->assertEquals(3, WalletMutation::where('user_id', $this->user->id)->count());
        $this->assertEquals(2500000, $this->walletService->getBalance($this->user)->toCents());
    }
}
