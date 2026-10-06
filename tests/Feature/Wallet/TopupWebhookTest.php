<?php

namespace Tests\Feature\Wallet;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Drivers\FakeGatewayDriver;
use App\Domain\Wallet\Models\TopupRequest;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\TopupService;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopupWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private UserTier $tier;

    private TopupService $topupService;

    private WalletService $walletService;

    private FakeGatewayDriver $fakeDriver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $this->tier->id,
            'name' => 'Webhook User',
            'phone' => '081299990000',
            'email' => 'webhook@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->admin = User::create([
            'user_tier_id' => $this->tier->id,
            'name' => 'Admin User',
            'phone' => '081288887777',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->walletService = app(WalletService::class);
        $this->fakeDriver = new FakeGatewayDriver;
        $this->app->instance(FakeGatewayDriver::class, $this->fakeDriver);

        $this->topupService = app(TopupService::class);
        $this->topupService->registerDriver('fake', $this->fakeDriver);
        $this->app->instance(TopupService::class, $this->topupService);
    }

    /**
     * Webhook payment gateway yang valid berhasil mengkredit saldo dan menandai top-up paid.
     */
    public function test_gateway_webhook_marks_topup_as_paid_and_credits_wallet(): void
    {
        $topup = $this->topupService->createTopup(
            $this->user,
            Money::fromCents(10000000), // Rp 100.000,00
            'payment_gateway',
            'fake',
            'TOPUP-WH-001'
        );

        $this->assertEquals(TopupRequest::STATUS_PENDING, $topup->status);
        $this->assertNotNull($topup->gateway_ref);

        // Simulasi webhook callback
        $response = $this->postJson('/wallet/callback', [
            'gateway' => 'fake',
            'gateway_ref' => $topup->gateway_ref,
            'status' => 'paid',
        ], [
            'X-Signature' => 'valid-signature',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $topup->id,
                    'status' => 'paid',
                ],
            ]);

        // Verifikasi status top-up di database
        $freshTopup = $topup->fresh();
        $this->assertTrue($freshTopup->isPaid());
        $this->assertNotNull($freshTopup->paid_at);

        // Verifikasi saldo bertambah
        $balance = $this->walletService->getBalance($this->user);
        $this->assertEquals(10000000, $balance->toCents());

        // Verifikasi mutasi wallet tercatat
        $mutation = WalletMutation::where('wallet_id', $this->walletService->getOrCreateWallet($this->user)->id)
            ->where('reference_type', WalletMutation::REF_TOPUP)
            ->where('reference_id', $topup->id)
            ->first();

        $this->assertNotNull($mutation);
        $this->assertEquals(10000000, $mutation->amount_cents->toCents());
        $this->assertEquals(10000000, $mutation->balance_after_cents->toCents());
    }

    /**
     * Webhook duplikat bersifat idempoten dan tidak menambah saldo dua kali.
     */
    public function test_duplicate_webhook_is_idempotent_and_does_not_double_credit(): void
    {
        $topup = $this->topupService->createTopup(
            $this->user,
            Money::fromCents(5000000), // Rp 50.000,00
            'payment_gateway',
            'fake',
            'TOPUP-WH-002'
        );

        // Webhook pertama
        $this->postJson('/wallet/callback', [
            'gateway' => 'fake',
            'gateway_ref' => $topup->gateway_ref,
            'status' => 'paid',
        ], ['X-Signature' => 'valid-signature'])->assertStatus(200);

        $this->assertEquals(5000000, $this->walletService->getBalance($this->user)->toCents());

        // Webhook kedua (duplikat)
        $this->postJson('/wallet/callback', [
            'gateway' => 'fake',
            'gateway_ref' => $topup->gateway_ref,
            'status' => 'paid',
        ], ['X-Signature' => 'valid-signature'])->assertStatus(200);

        // Saldo tetap Rp 50.000,00 (TIDAK menjadi Rp 100.000,00)
        $this->assertEquals(5000000, $this->walletService->getBalance($this->user)->toCents());

        // Mutasi tetap hanya 1
        $this->assertEquals(1, WalletMutation::where('reference_type', WalletMutation::REF_TOPUP)->count());
    }

    /**
     * Webhook ditolak jika signature tidak valid (HTTP 401 WEBHOOK_SIGNATURE_INVALID).
     */
    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $topup = $this->topupService->createTopup(
            $this->user,
            Money::fromCents(5000000),
            'payment_gateway',
            'fake',
            'TOPUP-WH-003'
        );

        $this->fakeDriver->setVerifyResult(false);

        $response = $this->postJson('/wallet/callback', [
            'gateway' => 'fake',
            'gateway_ref' => $topup->gateway_ref,
            'status' => 'paid',
        ], [
            'X-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'WEBHOOK_SIGNATURE_INVALID',
            ]);

        // Saldo tidak bertambah
        $this->assertEquals(0, $this->walletService->getBalance($this->user)->toCents());
        $this->assertTrue($topup->fresh()->isPending());
    }

    /**
     * Admin dapat mengonfirmasi top-up transfer manual.
     */
    public function test_admin_can_confirm_manual_transfer_topup(): void
    {
        $topup = $this->topupService->createTopup(
            $this->user,
            Money::fromCents(2500000), // Rp 25.000,00
            'manual_transfer',
            null,
            'TOPUP-MANUAL-CONFIRM-001'
        );

        $this->assertTrue($topup->isPending());

        // Konfirmasi manual oleh admin
        $confirmed = $this->topupService->confirmManual($topup, $this->admin);

        $this->assertTrue($confirmed->isPaid());
        $this->assertEquals($this->admin->id, $confirmed->confirmed_by);
        $this->assertNotNull($confirmed->confirmed_at);

        // Saldo bertambah
        $this->assertEquals(2500000, $this->walletService->getBalance($this->user)->toCents());

        // Konfirmasi kedua kali tidak mendobel saldo
        $this->topupService->confirmManual($topup, $this->admin);
        $this->assertEquals(2500000, $this->walletService->getBalance($this->user)->toCents());
    }
}
