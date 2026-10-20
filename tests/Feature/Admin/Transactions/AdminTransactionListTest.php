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

class AdminTransactionListTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private User $user;
    private Provider $providerA;
    private Provider $providerB;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Ops Supervisor',
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
            'name' => 'Budi Pelanggan',
            'phone' => '081234567890',
            'user_tier_id' => $tier->id,
            'is_active' => true,
            'is_verified' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'Pulsa',
            'code' => 'PULSA',
            'is_active' => true,
        ]);

        $this->providerA = Provider::create([
            'name' => 'Digiflazz',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'is_active' => true,
        ]);

        $this->providerB = Provider::create([
            'name' => 'Iak Provider',
            'code' => 'IAK',
            'driver' => 'IakDriver',
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'category_id' => $category->id,
            'provider_id' => $this->providerA->id,
            'sku_code' => 'TSEL10K',
            'name' => 'Telkomsel 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'is_active' => true,
        ]);

        $this->productB = Product::create([
            'category_id' => $category->id,
            'provider_id' => $this->providerB->id,
            'sku_code' => 'ISAT10K',
            'name' => 'Indosat 10.000',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 1000000,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_transactions_index(): void
    {
        $response = $this->get('/admin/transactions');

        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_transactions_list(): void
    {
        $trx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081299998888',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-list-1',
            'provider_ref' => 'REF-DIGI-101',
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/admin/transactions');

        $response->assertStatus(200);
        $response->assertSee('Manajemen & Monitoring Transaksi', false);
        $response->assertSee('#'.$trx->id);
        $response->assertSee('081299998888');
        $response->assertSee('REF-DIGI-101');
    }

    public function test_filter_by_keyword_searches_customer_number_and_provider_ref(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081211112222',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-q-1',
            'provider_ref' => 'REF-TARGET-999',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productB->id,
            'customer_number' => '089977776666',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-q-2',
            'provider_ref' => 'REF-OTHER-000',
        ]);

        $this->actingAs($this->admin, 'admin');

        // Search by customer number
        $response = $this->get('/admin/transactions?q=081211112222');
        $response->assertStatus(200);
        $response->assertSee('081211112222');
        $response->assertDontSee('089977776666');

        // Search by provider ref
        $responseRef = $this->get('/admin/transactions?q=REF-TARGET-999');
        $responseRef->assertStatus(200);
        $responseRef->assertSee('081211112222');
        $responseRef->assertDontSee('089977776666');
    }

    public function test_filter_by_status(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081233334444',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_FAILED,
            'idempotency_key' => 'idem-st-1',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081255556666',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-st-2',
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/admin/transactions?status=failed');
        $response->assertStatus(200);
        $response->assertSee('081233334444');
        $response->assertDontSee('081255556666');
    }

    public function test_filter_by_provider(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id, // Digiflazz
            'customer_number' => '081277778888',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-prov-1',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productB->id, // IAK
            'customer_number' => '081200001111',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-prov-2',
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->get("/admin/transactions?provider_id={$this->providerA->id}");
        $response->assertStatus(200);
        $response->assertSee('081277778888');
        $response->assertDontSee('081200001111');
    }

    public function test_filter_by_failover_route(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081233330000',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-failover-1',
            'is_failover' => true,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081233339999',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-failover-2',
            'is_failover' => false,
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/admin/transactions?is_failover=1');
        $response->assertStatus(200);
        $response->assertSee('081233330000');
        $response->assertDontSee('081233339999');
    }

    public function test_admin_can_export_transactions_to_csv(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081299991234',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-export-1',
            'provider_response' => ['sn' => 'SN-EXCEL-TEST'],
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/admin/transactions?export=csv');

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
        $this->assertStringContainsString('transaksi-ppob-', (string) $response->headers->get('content-disposition'));

        // Capture streamed content
        $csvContent = $response->streamedContent();
        $this->assertStringContainsString('081299991234', $csvContent);
        $this->assertStringContainsString('SN-EXCEL-TEST', $csvContent);
    }

    public function test_live_refresh_returns_table_component_only(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->withHeaders([
            'X-Live-Refresh' => '1',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/admin/transactions?live=1');

        $response->assertStatus(200);
        // It returns the table component directly without full page shell layout
        $response->assertSee('SN / Token PLN');
        $response->assertDontSee('Kelola riwayat transaksi PPOB, pantau status upstream');
    }

    public function test_default_filter_defaults_to_today_and_excludes_older_transactions(): void
    {
        // Yesterday's transaction
        $oldTrx = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081299990001',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-old-1',
        ]);
        $oldTrx->created_at = now()->subDays(2);
        $oldTrx->save();

        // Today's transaction
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081299990002',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-today-1',
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/admin/transactions');

        $response->assertStatus(200);
        $response->assertSee('081299990002');
        $response->assertDontSee('081299990001');
    }

    public function test_validation_fails_when_date_end_is_before_date_start(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/admin/transactions?date_start=2026-10-15&date_end=2026-10-10');

        $response->assertSessionHasErrors(['date_end']);
    }

    public function test_search_keyword_sanitizes_html_tags(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081277773333',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-xss-1',
        ]);

        $this->actingAs($this->admin, 'admin');

        // Pass HTML tag in q, should be stripped to clean phone number
        $response = $this->get('/admin/transactions?q=<script>alert("xss")</script>081277773333');

        $response->assertStatus(200);
        $response->assertSee('081277773333');
    }

    public function test_ajax_filter_returns_partial_table_without_full_layout_wrapper(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->productA->id,
            'customer_number' => '081288884444',
            'amount_cents' => 1000000,
            'sell_price_cents' => 1100000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-ajax-1',
        ]);

        $this->actingAs($this->admin, 'admin');

        $response = $this->getJson('/admin/transactions?q=081288884444', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);
        $response->assertSee('081288884444');
        $response->assertSee('Zero-Count O(1) Pagination');
        // Pastikan TIDAK merender seluruh layout / header luar untuk menghemat bandwidth & CPU
        $response->assertDontSee('Manajemen & Monitoring Transaksi');
    }
}


