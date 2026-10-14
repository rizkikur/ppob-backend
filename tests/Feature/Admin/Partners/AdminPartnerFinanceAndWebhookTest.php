<?php

namespace Tests\Feature\Admin\Partners;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Partner\Jobs\DeliverWebhookJob;
use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\WebhookDelivery;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminPartnerFinanceAndWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Partner $partner;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Finance Admin',
            'email' => 'finance@ppob.test',
            'password' => Hash::make('password123'),
            'role' => AdminRole::FINANCE_ADMIN,
            'is_active' => true,
        ]);

        $this->partner = Partner::create([
            'name' => 'PT Partner Finansial',
            'api_key' => 'pk_live_fin123',
            'secret' => encrypt('sk_live_fin123'),
            'is_active' => true,
            'rate_limit_rpm' => 60,
        ]);

        $category = ProductCategory::create([
            'name' => 'Pulsa Reguler',
            'code' => 'pulsa-reguler',
            'is_active' => true,
        ]);

        $provider = Provider::create([
            'name' => 'Digiflazz Upstream',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'provider_id' => $provider->id,
            'name' => 'Telkomsel 10.000',
            'sku_code' => 'TSEL10',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => Money::fromCents(1000000), // HPP = 10.000 IDR
            'admin_fee_cents' => Money::fromCents(50000),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_adjust_partner_balance_credit_and_debit(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Credit (Topup manual) Rp 2.000.000
        $response1 = $this->post("/admin/partners/{$this->partner->id}/adjust-balance", [
            'type' => 'credit',
            'amount' => 2000000,
            'note' => 'Topup manual deposit via transfer Bank Mandiri',
        ]);
        $response1->assertSessionHas('success');
        $this->assertEquals(200000000, $this->partner->fresh()->getBalance()->toCents());

        // Debit (Pemotongan) Rp 500.000
        $response2 = $this->post("/admin/partners/{$this->partner->id}/adjust-balance", [
            'type' => 'debit',
            'amount' => 500000,
            'note' => 'Koreksi kelebihan transfer invoice sebelumnya',
        ]);
        $response2->assertSessionHas('success');
        $this->assertEquals(150000000, $this->partner->fresh()->getBalance()->toCents());

        // Verify audit log created
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'partner.balance_adjusted',
        ]);
    }

    public function test_anti_negative_margin_guard_on_custom_partner_pricing(): void
    {
        $this->actingAs($this->admin, 'admin');

        // HPP is 10,000 IDR. Trying to set custom price 9,500 IDR (negative margin) must fail!
        $response = $this->post("/admin/partners/{$this->partner->id}/pricing", [
            'product_id' => $this->product->id,
            'sell_price' => 9500,
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('sell_price');

        // Now set valid price: 10,200 IDR (positive margin)
        $validResponse = $this->post("/admin/partners/{$this->partner->id}/pricing", [
            'product_id' => $this->product->id,
            'sell_price' => 10200,
            'is_active' => 1,
        ]);
        $validResponse->assertSessionHas('success');

        $this->assertDatabaseHas('partner_product_prices', [
            'partner_id' => $this->partner->id,
            'product_id' => $this->product->id,
            'sell_price_cents' => 1020000,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_retry_failed_webhook_delivery(): void
    {
        Queue::fake();

        $this->actingAs($this->admin, 'admin');

        $user = $this->partner->getOrCreateUser();

        $tx = Transaction::create([
            'user_id' => $user->id,
            'partner_id' => $this->partner->id,
            'product_id' => $this->product->id,
            'order_id' => 'TRX-TEST-001',
            'idempotency_key' => 'idem-test-001',
            'customer_number' => '08123456789',
            'amount_cents' => Money::fromCents(1000000),
            'cost_price_cents' => Money::fromCents(1000000),
            'sell_price_cents' => Money::fromCents(1050000),
            'status' => 'success',
        ]);

        $delivery = WebhookDelivery::create([
            'partner_id' => $this->partner->id,
            'transaction_id' => $tx->id,
            'attempt' => 5,
            'status' => WebhookDelivery::STATUS_FAILED_PERMANENT,
            'callback_url' => 'https://mitra.test/webhook',
            'response_code' => 500,
            'response_body' => 'Internal Server Error',
        ]);

        $response = $this->post("/admin/partners/webhooks/{$delivery->id}/retry");
        $response->assertSessionHas('success');

        // Status reset to pending
        $this->assertEquals(WebhookDelivery::STATUS_PENDING, $delivery->fresh()->status);

        // Job dispatched
        Queue::assertPushed(DeliverWebhookJob::class, function ($job) use ($delivery) {
            return $job->deliveryId === $delivery->id;
        });

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'partner.webhook_retry_dispatched',
        ]);
    }
}
