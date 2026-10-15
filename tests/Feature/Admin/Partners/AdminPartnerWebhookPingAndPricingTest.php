<?php

namespace Tests\Feature\Admin\Partners;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\PartnerProductPrice;
use App\Domain\Partner\Models\WebhookDelivery;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPartnerWebhookPingAndPricingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Partner $partner;
    private Product $product;

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

        $this->partner = Partner::create([
            'name' => 'PT Mitra Sejahtera',
            'api_key' => 'partner_key_12345678901234567890123456789012',
            'secret' => encrypt('secret_key_123'),
            'callback_url' => 'https://mitra.example.com/callback/ppob',
            'callback_secret' => encrypt('cb_secret_123'),
            'is_active' => true,
            'rate_limit_rpm' => 120,
        ]);

        $category = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'PULSA',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $provider = Provider::create([
            'name' => 'Digiflazz',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'sku_code' => 'TSEL10',
            'name' => 'Telkomsel 10K',
            'category_id' => $category->id,
            'provider_id' => $provider->id,
            'base_price_cents' => Money::fromCents(1010000),
            'admin_fee_cents' => Money::fromCents(10000),
            'product_type' => 'prepaid',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_ping_partner_webhook_successfully(): void
    {
        Http::fake([
            'https://mitra.example.com/callback/ppob' => Http::response(['status' => 'received'], 200),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.partners.webhooks.ping', $this->partner));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('webhook_deliveries', [
            'partner_id' => $this->partner->id,
            'status' => WebhookDelivery::STATUS_DELIVERED,
            'response_code' => 200,
            'callback_url' => 'https://mitra.example.com/callback/ppob',
        ]);
    }

    public function test_admin_ping_webhook_handles_failure(): void
    {
        Http::fake([
            'https://mitra.example.com/callback/ppob' => Http::response(['error' => 'server error'], 500),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.partners.webhooks.ping', $this->partner));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('webhook_deliveries', [
            'partner_id' => $this->partner->id,
            'status' => WebhookDelivery::STATUS_FAILED,
            'response_code' => 500,
        ]);
    }

    public function test_admin_can_delete_custom_price_override(): void
    {
        PartnerProductPrice::create([
            'partner_id' => $this->partner->id,
            'product_id' => $this->product->id,
            'sell_price_cents' => Money::fromCents(1030000),
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('partner_product_prices', [
            'partner_id' => $this->partner->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.partners.pricing.destroy', [$this->partner, $this->product]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('partner_product_prices', [
            'partner_id' => $this->partner->id,
            'product_id' => $this->product->id,
        ]);
    }
}
