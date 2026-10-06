<?php

namespace Tests\Feature\Wallet;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\TopupService;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserTier $tier;

    private WalletService $walletService;

    private TopupService $topupService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $this->tier->id,
            'name' => 'Wallet User',
            'phone' => '081233334444',
            'email' => 'wallet@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->walletService = app(WalletService::class);
        $this->topupService = app(TopupService::class);
    }

    /**
     * Inkuiri saldo user baru yang belum pernah transaksi.
     */
    public function test_balance_inquiry_returns_correct_initial_wallet_data(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/wallet');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 0,
                    'balance_display' => 'Rp 0,00',
                ],
            ]);
    }

    /**
     * Inkuiri saldo setelah ada kredit mutasi.
     */
    public function test_balance_inquiry_reflects_actual_balance(): void
    {
        $this->walletService->credit(
            $this->user,
            Money::fromCents(5000000), // Rp 50.000,00
            WalletMutation::REF_TOPUP,
            1,
            'Initial credit test'
        );

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/wallet');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 5000000,
                    'balance_display' => 'Rp 50.000,00',
                ],
            ]);
    }

    /**
     * Riwayat mutasi saldo dengan filter tipe dan pagination.
     */
    public function test_mutations_history_pagination_and_filter(): void
    {
        // Buat 3 mutasi credit
        $this->walletService->credit($this->user, Money::fromCents(1000000), WalletMutation::REF_TOPUP, 101, 'Topup 1');
        $this->walletService->credit($this->user, Money::fromCents(2000000), WalletMutation::REF_TOPUP, 102, 'Topup 2');
        $this->walletService->credit($this->user, Money::fromCents(3000000), WalletMutation::REF_TOPUP, 103, 'Topup 3');

        // Buat 1 mutasi debit
        $this->walletService->debit($this->user, Money::fromCents(1500000), WalletMutation::REF_TRANSACTION, 201, 'Beli Pulsa');

        Sanctum::actingAs($this->user);

        // Test pagination (per_page = 2)
        $response = $this->getJson('/wallet/mutations?per_page=2');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 2,
                    'total' => 4,
                ],
            ]);
        $this->assertCount(2, $response->json('data'));

        // Test filter credit
        $creditResponse = $this->getJson('/wallet/mutations?type=credit');
        $creditResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 3,
                ],
            ]);

        // Test filter debit
        $debitResponse = $this->getJson('/wallet/mutations?type=debit');
        $debitResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 1,
                ],
            ]);
        $this->assertEquals(1500000, $debitResponse->json('data.0.amount'));
        $this->assertEquals('debit', $debitResponse->json('data.0.type'));
    }

    /**
     * Request top-up metode manual transfer berhasil dibuat.
     */
    public function test_create_topup_request_manual_transfer(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeader('Idempotency-Key', 'MANUAL-TOPUP-001')
            ->postJson('/wallet/topup', [
                'amount' => 10000000, // Rp 100.000,00
                'method' => 'manual_transfer',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 10000000,
                    'amount_display' => 'Rp 100.000,00',
                    'method' => 'manual_transfer',
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('topup_requests', [
            'user_id' => $this->user->id,
            'amount_cents' => 10000000,
            'method' => 'manual_transfer',
            'idempotency_key' => 'MANUAL-TOPUP-001',
            'status' => 'pending',
        ]);
    }

    /**
     * Request top-up metode payment gateway menghasilkan payment_url.
     */
    public function test_create_topup_request_payment_gateway(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeader('Idempotency-Key', 'GATEWAY-TOPUP-001')
            ->postJson('/wallet/topup', [
                'amount' => 20000000, // Rp 200.000,00
                'method' => 'payment_gateway',
                'payment_gateway' => 'fake',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 20000000,
                    'method' => 'payment_gateway',
                    'payment_gateway' => 'fake',
                    'status' => 'pending',
                ],
            ]);

        $this->assertNotNull($response->json('data.payment_url'));
        $this->assertNotNull($response->json('data.gateway_ref'));
    }

    /**
     * Request top-up gagal validasi jika method payment_gateway tapi tidak menyertakan payment_gateway.
     */
    public function test_create_topup_fails_validation_without_gateway_for_gateway_method(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->withHeader('Idempotency-Key', 'INVALID-TOPUP-001')
            ->postJson('/wallet/topup', [
                'amount' => 5000000,
                'method' => 'payment_gateway',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
            ]);
    }
}
