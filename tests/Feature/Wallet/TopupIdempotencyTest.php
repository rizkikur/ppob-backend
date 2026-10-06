<?php

namespace Tests\Feature\Wallet;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Wallet\Models\TopupRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TopupIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserTier $tier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $this->tier->id,
            'name' => 'Idempotency User',
            'phone' => '081255556666',
            'email' => 'idempotency@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);
    }

    /**
     * Endpoint topup wajib menyertakan header Idempotency-Key.
     */
    public function test_topup_requires_idempotency_key_header(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/wallet/topup', [
            'amount' => 5000000,
            'method' => 'manual_transfer',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'IDEMPOTENCY_KEY_REQUIRED',
            ]);
    }

    /**
     * Idempotency-Key melebihi 100 karakter ditolak dengan VALIDATION_ERROR.
     */
    public function test_topup_rejects_idempotency_key_exceeding_100_chars(): void
    {
        Sanctum::actingAs($this->user);

        $tooLongKey = str_repeat('a', 101);

        $response = $this->withHeader('Idempotency-Key', $tooLongKey)
            ->postJson('/wallet/topup', [
                'amount' => 5000000,
                'method' => 'manual_transfer',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
            ]);
    }

    /**
     * Request kedua dengan Idempotency-Key yang sama mengembalikan HTTP 409 dan data transaksi pertama.
     */
    public function test_duplicate_idempotency_key_returns_409_and_identical_data(): void
    {
        Sanctum::actingAs($this->user);

        $key = 'UNIQUE-IDEMP-KEY-999';

        // Request pertama: Berhasil (201 Created)
        $firstResponse = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/wallet/topup', [
                'amount' => 7500000, // Rp 75.000,00
                'method' => 'manual_transfer',
            ]);

        $firstResponse->assertStatus(201);
        $topupId = $firstResponse->json('data.id');
        $this->assertNotNull($topupId);

        // Hanya 1 record dibuat di database
        $this->assertEquals(1, TopupRequest::where('user_id', $this->user->id)->count());

        // Request kedua dengan key yang sama persis: Ditolak dengan 409 IDEMPOTENCY_CONFLICT
        $secondResponse = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/wallet/topup', [
                'amount' => 7500000,
                'method' => 'manual_transfer',
            ]);

        $secondResponse->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'IDEMPOTENCY_CONFLICT',
            ]);

        // Memastikan data yang dikembalikan pada response 409 memuat data asli request pertama
        $secondResponseData = $secondResponse->json('data');
        $this->assertNotNull($secondResponseData);
        $this->assertEquals($firstResponse->json(), $secondResponseData);

        // Pastikan tabel topup_requests tetap hanya memiliki 1 baris (tidak ada duplikasi)
        $this->assertEquals(1, TopupRequest::where('user_id', $this->user->id)->count());
    }
}
