<?php

namespace Tests\Feature\Security;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Security\Models\PinVerificationToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PinFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserTier $tier;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $this->tier->id,
            'name' => 'Security User',
            'phone' => '081211112222',
            'email' => 'security@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);
    }

    /**
     * Challenge fails if PIN is not set yet.
     */
    public function test_challenge_fails_if_pin_not_set(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/security/pin/challenge', [
            'purpose' => 'transaction',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_NOT_SET',
            ]);
    }

    /**
     * User can set PIN for the first time.
     */
    public function test_user_can_set_pin_first_time(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/security/pin', [
            'pin' => '123456',
            'pin_confirmation' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->user->refresh();
        $this->assertTrue($this->user->hasPin());
        $this->assertTrue(Hash::check('123456', $this->user->pin_hash));
    }

    /**
     * User cannot set PIN again if already set.
     */
    public function test_user_cannot_set_pin_again(): void
    {
        $this->user->update(['pin_hash' => Hash::make('123456')]);
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/security/pin', [
            'pin' => '654321',
            'pin_confirmation' => '654321',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Full PIN challenge and verify flow returns pin_verification_token.
     */
    public function test_challenge_and_verify_pin_flow(): void
    {
        $this->user->update(['pin_hash' => Hash::make('123456')]);
        Sanctum::actingAs($this->user);

        // Step 1: Challenge
        $challengeResponse = $this->postJson('/security/pin/challenge', [
            'purpose' => 'transaction',
        ]);

        $challengeResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['challenge_id'],
            ]);

        $challengeId = $challengeResponse->json('data.challenge_id');

        // Step 2: Verify with wrong PIN
        $wrongResponse = $this->postJson('/security/pin/verify', [
            'challenge_id' => $challengeId,
            'pin' => '999999',
        ]);

        $wrongResponse->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_INVALID',
            ]);

        // Step 3: Verify with correct PIN
        $verifyResponse = $this->postJson('/security/pin/verify', [
            'challenge_id' => $challengeId,
            'pin' => '123456',
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'pin_verification_token',
                    'expires_at',
                ],
            ]);

        $token = $verifyResponse->json('data.pin_verification_token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('pin_verification_tokens', [
            'user_id' => $this->user->id,
            'token' => $token,
            'purpose' => 'transaction',
        ]);
    }

    /**
     * User can change PIN using pin_verification_token with purpose change_pin.
     */
    public function test_change_pin_flow_with_token(): void
    {
        $this->user->update(['pin_hash' => Hash::make('123456')]);
        Sanctum::actingAs($this->user);

        // Step 1: Challenge with purpose change_pin
        $challengeResponse = $this->postJson('/security/pin/challenge', [
            'purpose' => 'change_pin',
        ]);
        $challengeResponse->assertStatus(200);
        $challengeId = $challengeResponse->json('data.challenge_id');

        // Step 2: Verify old PIN
        $verifyResponse = $this->postJson('/security/pin/verify', [
            'challenge_id' => $challengeId,
            'pin' => '123456',
        ]);
        $verifyResponse->assertStatus(200);
        $pinToken = $verifyResponse->json('data.pin_verification_token');

        // Step 3: PUT /security/pin with X-Pin-Token header
        $changeResponse = $this->withHeader('X-Pin-Token', $pinToken)
            ->putJson('/security/pin', [
                'new_pin' => '654321',
                'new_pin_confirmation' => '654321',
            ]);

        $changeResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->user->refresh();
        $this->assertTrue(Hash::check('654321', $this->user->pin_hash));

        // Token must now be marked as used in DB
        $dbToken = PinVerificationToken::where('token', $pinToken)->first();
        $this->assertNotNull($dbToken->used_at);

        // Reusing the same token must fail with PIN_TOKEN_USED
        $reuseResponse = $this->withHeader('X-Pin-Token', $pinToken)
            ->putJson('/security/pin', [
                'new_pin' => '111111',
                'new_pin_confirmation' => '111111',
            ]);

        $reuseResponse->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_TOKEN_USED',
            ]);
    }

    /**
     * Expired token is rejected with PIN_TOKEN_EXPIRED.
     */
    public function test_expired_token_is_rejected(): void
    {
        $this->user->update(['pin_hash' => Hash::make('123456')]);
        Sanctum::actingAs($this->user);

        $expiredToken = PinVerificationToken::create([
            'user_id' => $this->user->id,
            'token' => bin2hex(random_bytes(32)),
            'purpose' => 'change_pin',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->withHeader('X-Pin-Token', $expiredToken->token)
            ->putJson('/security/pin', [
                'new_pin' => '654321',
                'new_pin_confirmation' => '654321',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_TOKEN_EXPIRED',
            ]);
    }

    /**
     * Token with wrong purpose is rejected with PIN_TOKEN_INVALID.
     */
    public function test_token_with_wrong_purpose_is_rejected(): void
    {
        $this->user->update(['pin_hash' => Hash::make('123456')]);
        Sanctum::actingAs($this->user);

        // Token created for transaction
        $transactionToken = PinVerificationToken::create([
            'user_id' => $this->user->id,
            'token' => bin2hex(random_bytes(32)),
            'purpose' => 'transaction',
            'expires_at' => now()->addMinutes(5),
        ]);

        // Attempting to use transaction token on change_pin endpoint
        $response = $this->withHeader('X-Pin-Token', $transactionToken->token)
            ->putJson('/security/pin', [
                'new_pin' => '654321',
                'new_pin_confirmation' => '654321',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_TOKEN_INVALID',
            ]);
    }
}
