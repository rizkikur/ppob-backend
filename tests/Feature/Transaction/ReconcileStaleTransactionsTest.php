<?php

namespace Tests\Feature\Transaction;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Ppob\Drivers\PlnDriver;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletMutation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReconcileStaleTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Provider $provider;
    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        PlnDriver::setMockCheckStatus(null);
        PlnDriver::setMockPay(null);

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Reconcile User',
            'phone' => '081299990099',
            'email' => 'reconcile@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->wallet = Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 50000000, // Rp 500.000
        ]);

        $category = ProductCategory::create([
            'name' => 'Listrik',
            'code' => 'listrik',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->provider = Provider::create([
            'name' => 'PLN Provider',
            'code' => 'pln',
            'driver' => 'PlnDriver',
            'queue_name' => 'supplier_pln',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'provider_id' => $this->provider->id,
            'category_id' => $category->id,
            'sku_code' => 'PLN20',
            'name' => 'Token PLN 20.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 2000000,
            'admin_fee_cents' => 0,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        PlnDriver::setMockCheckStatus(null);
        PlnDriver::setMockPay(null);
        parent::tearDown();
    }

    public function test_dry_run_lists_stale_transactions_without_updating_status(): void
    {
        // Transaksi PROCESSING 40 menit lalu
        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '12345678901',
            'amount_cents' => 2000000,
            'sell_price_cents' => 2050000,
            'status' => Transaction::STATUS_PROCESSING,
            'provider_ref' => 'REF-DRY-RUN',
            'idempotency_key' => 'TX-RECONCILE-DRY',
        ]);
        Transaction::where('id', $tx->id)->update(['updated_at' => Carbon::now()->subMinutes(40)]);

        $this->artisan('ppob:transactions:reconcile', ['--dry-run' => true])
            ->expectsOutputToContain('Ditemukan 1 transaksi stale PROCESSING')
            ->assertSuccessful();

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_PROCESSING, $tx->status);
    }

    public function test_auto_resolve_marks_success_when_provider_returns_success(): void
    {
        PlnDriver::setMockCheckStatus([
            'status' => 'success',
            'provider_ref' => 'REF-SUCCESS-1',
            'raw' => ['sn' => 'TOKEN-1234-5678'],
        ]);

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '12345678901',
            'amount_cents' => 2000000,
            'sell_price_cents' => 2050000,
            'status' => Transaction::STATUS_PROCESSING,
            'provider_ref' => 'REF-SUCCESS-1',
            'idempotency_key' => 'TX-RECONCILE-SUC',
        ]);
        Transaction::where('id', $tx->id)->update(['updated_at' => Carbon::now()->subMinutes(45)]);

        $this->artisan('ppob:transactions:reconcile', ['--auto-resolve' => true])
            ->assertSuccessful();

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_SUCCESS, $tx->status);
    }

    public function test_auto_resolve_fails_and_refunds_when_provider_returns_failed(): void
    {
        PlnDriver::setMockCheckStatus([
            'status' => 'failed',
            'message' => 'Meteran diblokir PLN',
            'raw' => ['rc' => '99'],
        ]);

        $initialBalance = $this->wallet->balance_cents->toCents();

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '12345678901',
            'amount_cents' => 2000000,
            'sell_price_cents' => 2050000,
            'status' => Transaction::STATUS_PROCESSING,
            'provider_ref' => 'REF-FAIL-1',
            'idempotency_key' => 'TX-RECONCILE-FAIL',
        ]);
        Transaction::where('id', $tx->id)->update(['updated_at' => Carbon::now()->subMinutes(50)]);

        $this->artisan('ppob:transactions:reconcile', ['--auto-resolve' => true])
            ->assertSuccessful();

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED, $tx->status);

        // Saldo user harus di-refund sebesar harga jual (Rp 20.500)
        $this->wallet->refresh();
        $this->assertEquals($initialBalance + 2050000, $this->wallet->balance_cents->toCents());

        $this->assertDatabaseHas('wallet_mutations', [
            'user_id' => $this->user->id,
            'reference_type' => WalletMutation::REF_REFUND,
            'reference_id' => $tx->id,
        ]);
    }

    public function test_auto_resolve_skips_fresh_transactions_under_stale_cutoff(): void
    {
        PlnDriver::setMockCheckStatus([
            'status' => 'success',
            'provider_ref' => 'REF-FRESH-1',
        ]);

        // Transaksi baru 10 menit lalu (default threshold 30 menit)
        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '12345678901',
            'amount_cents' => 2000000,
            'sell_price_cents' => 2050000,
            'status' => Transaction::STATUS_PROCESSING,
            'provider_ref' => 'REF-FRESH-1',
            'idempotency_key' => 'TX-RECONCILE-FRESH',
        ]);
        Transaction::where('id', $tx->id)->update(['updated_at' => Carbon::now()->subMinutes(10)]);

        $this->artisan('ppob:transactions:reconcile', ['--auto-resolve' => true])
            ->expectsOutputToContain('Tidak ada transaksi PROCESSING yang stale')
            ->assertSuccessful();

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_PROCESSING, $tx->status);
    }
}
