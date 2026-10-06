<?php

namespace Tests\Feature\Transaction;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Ppob\Drivers\PlnDriver;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Jobs\CheckTransactionStatusJob;
use App\Domain\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletMutation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TransactionJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        PlnDriver::setMockPay(null);
        PlnDriver::setMockCheckStatus(null);

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Job Test User',
            'phone' => '081299990006',
            'email' => 'jobuser@example.com',
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

        $provider = Provider::create([
            'name' => 'PLN Provider',
            'code' => 'pln',
            'driver' => 'PlnDriver',
            'queue_name' => 'supplier_pln',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'sku_code' => 'PLN-PRE-50K',
            'name' => 'Token PLN 50.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 5050000,
            'admin_fee_cents' => 0,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        PlnDriver::setMockPay(null);
        PlnDriver::setMockCheckStatus(null);
        parent::tearDown();
    }

    public function test_process_transaction_job_success(): void
    {
        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '1234567890',
            'amount_cents' => 5050000,
            'sell_price_cents' => 5050000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'TX-JOB-01',
        ]);

        PlnDriver::setMockPay([
            'status' => 'success',
            'provider_ref' => 'PLN-PAY-TEST-999',
            'raw' => ['token' => '1234-5678-9012-3456'],
        ]);

        $job = new ProcessTransactionJob($tx);
        app()->call([$job, 'handle']);

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_SUCCESS, $tx->status);
        $this->assertEquals('PLN-PAY-TEST-999', $tx->provider_ref);
        $this->assertNull($tx->failure_reason);

        // Tidak ada refund karena transaksi sukses
        $refundMutations = WalletMutation::where('reference_type', WalletMutation::REF_REFUND)->count();
        $this->assertEquals(0, $refundMutations);
    }

    public function test_process_transaction_job_failure_triggers_auto_refund(): void
    {
        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '1234567890',
            'amount_cents' => 5050000,
            'sell_price_cents' => 5050000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'TX-JOB-02',
        ]);

        // Simulasikan provider menolak transaksi
        PlnDriver::setMockPay([
            'status' => 'failed',
            'message' => 'Nomor meteran sedang diblokir',
            'raw' => ['err_code' => 'BLOCKED_METER'],
        ]);

        $initialBalance = $this->wallet->balance_cents->toCents();

        $job = new ProcessTransactionJob($tx);
        app()->call([$job, 'handle']);

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED, $tx->status);
        $this->assertEquals('Nomor meteran sedang diblokir', $tx->failure_reason);

        // Saldo user harus di-refund otomatis sebesar sell_price_cents
        $this->wallet->refresh();
        $this->assertEquals($initialBalance + 5050000, $this->wallet->balance_cents->toCents());

        // Catatan mutasi pengembalian dana tercatat
        $refundMutation = WalletMutation::where('user_id', $this->user->id)
            ->where('reference_type', WalletMutation::REF_REFUND)
            ->where('reference_id', $tx->id)
            ->first();

        $this->assertNotNull($refundMutation);
        $this->assertEquals(WalletMutation::TYPE_CREDIT, $refundMutation->type);
        $this->assertEquals(5050000, $refundMutation->amount_cents->toCents());
    }

    public function test_process_transaction_job_async_dispatches_check_status_job(): void
    {
        Queue::fake();

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '1234567890',
            'amount_cents' => 5050000,
            'sell_price_cents' => 5050000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'TX-JOB-03',
        ]);

        // Provider mengembalikan status pending (async)
        PlnDriver::setMockPay([
            'status' => 'pending',
            'provider_ref' => 'PLN-ASYNC-REF-123',
            'raw' => ['info' => 'Sedang dalam antrian provider'],
        ]);

        $job = new ProcessTransactionJob($tx);
        app()->call([$job, 'handle']);

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_PROCESSING, $tx->status);
        $this->assertEquals('PLN-ASYNC-REF-123', $tx->provider_ref);

        Queue::assertPushed(CheckTransactionStatusJob::class);
    }

    public function test_check_transaction_status_job_success(): void
    {
        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '1234567890',
            'amount_cents' => 5050000,
            'sell_price_cents' => 5050000,
            'status' => Transaction::STATUS_PROCESSING,
            'provider_ref' => 'PLN-CHECK-01',
            'idempotency_key' => 'TX-JOB-04',
        ]);

        PlnDriver::setMockCheckStatus([
            'status' => 'success',
            'provider_ref' => 'PLN-CHECK-01',
            'raw' => ['sn' => 'TOKEN123456'],
        ]);

        $job = new CheckTransactionStatusJob($tx);
        app()->call([$job, 'handle']);

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_SUCCESS, $tx->status);
    }

    public function test_check_transaction_status_job_failed_triggers_refund(): void
    {
        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '1234567890',
            'amount_cents' => 5050000,
            'sell_price_cents' => 5050000,
            'status' => Transaction::STATUS_PROCESSING,
            'provider_ref' => 'PLN-CHECK-02',
            'idempotency_key' => 'TX-JOB-05',
        ]);

        PlnDriver::setMockCheckStatus([
            'status' => 'failed',
            'message' => 'Transaksi gagal di pihak provider',
            'raw' => ['status' => 'failed'],
        ]);

        $initialBalance = $this->wallet->balance_cents->toCents();

        $job = new CheckTransactionStatusJob($tx);
        app()->call([$job, 'handle']);

        $tx->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED, $tx->status);

        // Cek refund
        $this->wallet->refresh();
        $this->assertEquals($initialBalance + 5050000, $this->wallet->balance_cents->toCents());
    }
}
