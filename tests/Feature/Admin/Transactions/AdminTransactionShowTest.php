<?php

namespace Tests\Feature\Admin\Transactions;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminAuditLogService;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletMutation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTransactionShowTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private User $user;
    private Product $product;
    private Provider $provider;
    private Transaction $transaction;

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
            'name' => 'Siti Pelanggan',
            'phone' => '081234567899',
            'user_tier_id' => $tier->id,
            'is_active' => true,
            'is_verified' => true,
        ]);

        $wallet = Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 5000000,
        ]);

        $category = ProductCategory::create([
            'name' => 'Data',
            'code' => 'DATA',
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
            'sku_code' => 'DATA10GB',
            'name' => 'Paket Data 10GB',
            'product_type' => Product::TYPE_PREPAID,
            'base_price_cents' => 4500000,
            'is_active' => true,
        ]);

        $this->transaction = Transaction::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'customer_number' => '081234567899',
            'amount_cents' => 4500000,
            'sell_price_cents' => 5000000,
            'status' => Transaction::STATUS_SUCCESS,
            'idempotency_key' => 'idem-show-123',
            'provider_ref' => 'REF-PROVIDER-123',
            'provider_response' => [
                'rc' => '00',
                'sn' => 'SN99887766',
                'api_key' => 'SUPER_SECRET_KEY_123',
                'secret' => 'SECRET_TOKEN_456',
            ],
        ]);

        WalletMutation::create([
            'wallet_id' => $wallet->id,
            'user_id' => $this->user->id,
            'type' => WalletMutation::TYPE_DEBIT,
            'amount_cents' => 5000000,
            'balance_after_cents' => 0,
            'reference_type' => WalletMutation::REF_TRANSACTION,
            'reference_id' => $this->transaction->id,
            'note' => "Pembelian paket data {$this->transaction->id}",
        ]);
    }

    public function test_unauthenticated_user_cannot_access_transaction_detail(): void
    {
        $response = $this->get("/admin/transactions/{$this->transaction->id}");

        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_transaction_detail(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get("/admin/transactions/{$this->transaction->id}");

        $response->assertStatus(200);
        $response->assertSee("Transaksi #{$this->transaction->id}");
        $response->assertSee('Siti Pelanggan');
        $response->assertSee('Paket Data 10GB');
        $response->assertSee('REF-PROVIDER-123');
        $response->assertSee('SN99887766');
    }

    public function test_sensitive_credentials_in_provider_response_are_masked(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get("/admin/transactions/{$this->transaction->id}");

        $response->assertStatus(200);
        // Sensitive values must NOT appear in raw text
        $response->assertDontSee('SUPER_SECRET_KEY_123');
        $response->assertDontSee('SECRET_TOKEN_456');
        $response->assertSee('******');
    }

    public function test_related_wallet_mutations_and_audit_logs_are_displayed(): void
    {
        /** @var AdminAuditLogService $auditService */
        $auditService = app(AdminAuditLogService::class);
        $auditService->log(
            admin: $this->admin,
            action: 'transaction.requery',
            target: $this->transaction,
            changes: ['status' => 'success'],
            reason: 'Audit log requery check'
        );

        $this->actingAs($this->admin, 'admin');

        $response = $this->get("/admin/transactions/{$this->transaction->id}");

        $response->assertStatus(200);
        $response->assertSee('Pembelian paket data');
        $response->assertSee('Audit log requery check');
        $response->assertSee('Requery Provider oleh Admin');
    }

    public function test_receipt_modal_and_whatsapp_format_are_rendered(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get("/admin/transactions/{$this->transaction->id}");

        $response->assertStatus(200);
        $response->assertSee('Cetak Struk / Salin WA');
        $response->assertSee('Bukti Pembayaran Pelanggan');
        $response->assertSee('STRUK PEMBAYARAN RESMI');
        $response->assertSee('BUKTI TRANSAKSI PEMBAYARAN RESMI');
        $response->assertSee('Salin Teks WhatsApp');
    }
}
