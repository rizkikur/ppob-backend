<?php

namespace Tests\Feature\Admin\Transactions;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminStatusOverrideTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private User $user;
    private Product $product;
    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Regular', 'description' => 'Regular tier']
        );

        $this->user = User::create([
            'name' => 'Koko Pelanggan',
            'phone' => '081234567666',
            'user_tier_id' => $tier->id,
            'is_active' => true,
            'is_verified' => true,
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
            'sku_code' => 'TSEL100K',
            'name' => 'Telkomsel 100.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 9800000,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_admin_cannot_override_status(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567666',
            'amount_cents' => 9800000,
            'sell_price_cents' => 10000000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'idem-ov-1',
        ]);

        $response = $this->post("/admin/transactions/{$transaction->id}/override-status", [
            'status' => Transaction::STATUS_SUCCESS,
            'reason' => 'Verifikasi rekonsiliasi upstream manual',
        ]);

        $response->assertRedirect('/admin/login');
    }

    public function test_override_validates_reason_and_status(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567666',
            'amount_cents' => 9800000,
            'sell_price_cents' => 10000000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'idem-ov-2',
        ]);

        $this->actingAs($this->admin, 'admin');

        // Invalid short reason
        $responseShort = $this->post("/admin/transactions/{$transaction->id}/override-status", [
            'status' => Transaction::STATUS_SUCCESS,
            'reason' => 'Pendek',
        ]);
        $responseShort->assertSessionHasErrors(['reason']);

        // Invalid status enum
        $responseInvalidStatus = $this->post("/admin/transactions/{$transaction->id}/override-status", [
            'status' => 'invalid_status_xyz',
            'reason' => 'Alasan panjang valid tapi status salah',
        ]);
        $responseInvalidStatus->assertSessionHasErrors(['status']);
    }

    public function test_successful_status_override_updates_database_and_records_audit(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567666',
            'amount_cents' => 9800000,
            'sell_price_cents' => 10000000,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => 'idem-ov-3',
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->post("/admin/transactions/{$transaction->id}/override-status", [
            'status' => Transaction::STATUS_FAILED,
            'reason' => 'Manual override: Provider offline lebih dari 2 jam',
        ]);

        $response->assertRedirect("/admin/transactions/{$transaction->id}");
        $response->assertSessionHas('success');

        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED, $transaction->status);
        $this->assertEquals('Manual override: Provider offline lebih dari 2 jam', $transaction->failure_reason);

        // Audit log verified
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'transaction.status_override',
            'target_type' => 'Transaction',
            'target_id' => (string) $transaction->id,
            'reason' => 'Manual override: Provider offline lebih dari 2 jam',
        ]);
    }
}
