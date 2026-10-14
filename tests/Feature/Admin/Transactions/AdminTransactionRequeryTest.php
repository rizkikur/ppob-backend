<?php

namespace Tests\Feature\Admin\Transactions;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminAuditLog;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Ppob\Services\PpobService;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AdminTransactionRequeryTest extends TestCase
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
            'name' => 'Ops Agent',
            'email' => 'ops@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Regular', 'description' => 'Regular tier']
        );

        $this->user = User::create([
            'name' => 'Ahmad User',
            'phone' => '081234567888',
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
            'sku_code' => 'TSEL25K',
            'name' => 'Telkomsel 25.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 2500000,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_admin_cannot_trigger_requery(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567888',
            'amount_cents' => 2500000,
            'sell_price_cents' => 2600000,
            'status' => Transaction::STATUS_PROCESSING,
            'idempotency_key' => 'idem-req-1',
            'provider_ref' => 'REF-OLD-11',
        ]);

        $response = $this->post("/admin/transactions/{$transaction->id}/requery");

        $response->assertRedirect('/admin/login');
    }

    public function test_successful_requery_updates_status_and_creates_audit_log(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567888',
            'amount_cents' => 2500000,
            'sell_price_cents' => 2600000,
            'status' => Transaction::STATUS_PROCESSING,
            'idempotency_key' => 'idem-req-2',
            'provider_ref' => 'REF-PENDING-22',
        ]);

        // Mock PpobService checkStatus
        $mockPpob = Mockery::mock(PpobService::class);
        $mockPpob->shouldReceive('checkStatus')
            ->once()
            ->andReturn([
                'status' => 'success',
                'provider_ref' => 'REF-SUCCESS-22',
                'raw' => ['rc' => '00', 'sn' => 'SN12345678'],
            ]);
        $this->app->instance(PpobService::class, $mockPpob);

        $this->actingAs($this->admin, 'admin');

        $response = $this->post("/admin/transactions/{$transaction->id}/requery");

        $response->assertRedirect("/admin/transactions/{$transaction->id}");
        $response->assertSessionHas('success');

        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_SUCCESS, $transaction->status);
        $this->assertEquals('REF-SUCCESS-22', $transaction->provider_ref);

        // Audit log created
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'transaction.requery',
            'target_type' => 'Transaction',
            'target_id' => (string) $transaction->id,
        ]);
    }

    public function test_failed_requery_updates_status_to_failed(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567888',
            'amount_cents' => 2500000,
            'sell_price_cents' => 2600000,
            'status' => Transaction::STATUS_PROCESSING,
            'idempotency_key' => 'idem-req-3',
            'provider_ref' => 'REF-PENDING-33',
        ]);

        $mockPpob = Mockery::mock(PpobService::class);
        $mockPpob->shouldReceive('checkStatus')
            ->once()
            ->andReturn([
                'status' => 'failed',
                'provider_ref' => 'REF-FAIL-33',
                'message' => 'Nomor tujuan tidak terdaftar',
                'raw' => ['rc' => '40'],
            ]);
        $this->app->instance(PpobService::class, $mockPpob);

        $this->actingAs($this->admin, 'admin');

        $response = $this->post("/admin/transactions/{$transaction->id}/requery");

        $response->assertRedirect("/admin/transactions/{$transaction->id}");
        $response->assertSessionHas('success');

        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED, $transaction->status);
        $this->assertEquals('Nomor tujuan tidak terdaftar', $transaction->failure_reason);
    }
}
