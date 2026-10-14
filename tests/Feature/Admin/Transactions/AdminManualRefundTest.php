<?php

namespace Tests\Feature\Admin\Transactions;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminAuditLog;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletMutation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManualRefundTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private User $user;
    private Wallet $wallet;
    private Product $product;
    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Finance Controller',
            'email' => 'finance@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::FINANCE_ADMIN,
            'is_active' => true,
        ]);

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Regular', 'description' => 'Regular tier']
        );

        $this->user = User::create([
            'name' => 'Doni Pelanggan',
            'phone' => '081234567777',
            'user_tier_id' => $tier->id,
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->wallet = Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 1000000, // Rp 10.000 awal
        ]);

        $category = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'PULSA',
            'is_active' => true,
        ]);

        $this->provider = Provider::create([
            'name' => 'Digiflazz',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'provider_id' => $this->provider->id,
            'sku_code' => 'TSEL50K',
            'name' => 'Telkomsel 50.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 5000000,
            'is_active' => true,
        ]);
    }

    public function test_refund_requires_recent_password_confirmation(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567777',
            'amount_cents' => 5000000,
            'sell_price_cents' => 5100000,
            'status' => Transaction::STATUS_FAILED,
            'idempotency_key' => 'idem-ref-pwd',
        ]);

        $this->actingAs($this->admin, 'admin');

        // Without password confirmed session
        $response = $this->post("/admin/transactions/{$transaction->id}/refund", [
            'reason' => 'Tiket kendala #999 transaksi gagal provider',
        ]);

        $response->assertRedirect('/admin/confirm-password');
        $this->assertEquals(Transaction::STATUS_FAILED, $transaction->fresh()->status);
    }

    public function test_refund_validation_requires_minimum_10_character_reason(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567777',
            'amount_cents' => 5000000,
            'sell_price_cents' => 5100000,
            'status' => Transaction::STATUS_FAILED,
            'idempotency_key' => 'idem-ref-val',
        ]);

        $this->actingAs($this->admin, 'admin');
        session(['auth.password_confirmed_at' => time()]);

        $response = $this->post("/admin/transactions/{$transaction->id}/refund", [
            'reason' => 'Pendek',
        ]);

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_successful_manual_refund_credits_wallet_and_updates_status(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567777',
            'amount_cents' => 5000000,
            'sell_price_cents' => 5100000, // Rp 51.000
            'status' => Transaction::STATUS_FAILED,
            'idempotency_key' => 'idem-ref-succ',
        ]);

        $this->actingAs($this->admin, 'admin');
        session(['auth.password_confirmed_at' => time()]);

        $response = $this->post("/admin/transactions/{$transaction->id}/refund", [
            'reason' => 'Tiket kendala #404 nomor tujuan tidak dapat menerima pulsa',
        ]);

        $response->assertRedirect("/admin/transactions/{$transaction->id}");
        $response->assertSessionHas('success');

        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_REFUNDED, $transaction->status);

        // Wallet balance increased from 1.000.000 to 6.100.000 cents
        $this->wallet->refresh();
        $this->assertEquals(6100000, $this->wallet->balance_cents->toCents());

        // Wallet mutation created
        $this->assertDatabaseHas('wallet_mutations', [
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->user->id,
            'type' => WalletMutation::TYPE_CREDIT,
            'reference_type' => WalletMutation::REF_REFUND,
            'reference_id' => $transaction->id,
            'amount_cents' => 5100000,
        ]);

        // Audit log created
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'transaction.manual_refund',
            'target_type' => 'Transaction',
            'target_id' => (string) $transaction->id,
        ]);
    }

    public function test_cannot_refund_already_refunded_transaction(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567777',
            'amount_cents' => 5000000,
            'sell_price_cents' => 5100000,
            'status' => Transaction::STATUS_REFUNDED,
            'idempotency_key' => 'idem-ref-dup',
        ]);

        $this->actingAs($this->admin, 'admin');
        session(['auth.password_confirmed_at' => time()]);

        $response = $this->post("/admin/transactions/{$transaction->id}/refund", [
            'reason' => 'Tiket refund duplikat tidak boleh',
        ]);

        $response->assertSessionHasErrors(['refund']);
        $this->assertEquals(1000000, $this->wallet->fresh()->balance_cents->toCents());
    }

    public function test_cannot_refund_successful_transaction(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567777',
            'amount_cents' => 5000000,
            'sell_price_cents' => 5100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-ref-succ-block',
        ]);

        $this->actingAs($this->admin, 'admin');
        session(['auth.password_confirmed_at' => time()]);

        $response = $this->post("/admin/transactions/{$transaction->id}/refund", [
            'reason' => 'Percobaan refund transaksi yang sudah sukses',
        ]);

        $response->assertSessionHasErrors(['refund']);
        $this->assertEquals(Transaction::STATUS_SUCCESS, $transaction->fresh()->status);
        $this->assertEquals(1000000, $this->wallet->fresh()->balance_cents->toCents());
    }
}
