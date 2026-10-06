<?php

namespace Tests\Feature\Security;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PinBruteForceTest extends TestCase
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
            'name' => 'Brute Force User',
            'phone' => '081233334444',
            'email' => 'bruteforce@example.com',
            'pin_hash' => Hash::make('123456'),
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);
    }

    /**
     * Test 5 wrong PIN attempts trigger lock (HTTP 429, PIN_LOCKED).
     */
    public function test_5_wrong_pin_attempts_triggers_lock(): void
    {
        Sanctum::actingAs($this->user);

        // Make challenge
        $challengeResponse = $this->postJson('/security/pin/challenge', [
            'purpose' => 'transaction',
        ]);
        $challengeResponse->assertStatus(200);
        $challengeId = $challengeResponse->json('data.challenge_id');

        // First 4 attempts return 422 PIN_INVALID
        for ($i = 1; $i <= 4; $i++) {
            $response = $this->postJson('/security/pin/verify', [
                'challenge_id' => $challengeId,
                'pin' => '000000',
            ]);

            $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error_code' => 'PIN_INVALID',
                ]);
        }

        // 5th attempt triggers lock (HTTP 429 PIN_LOCKED)
        $fifthResponse = $this->postJson('/security/pin/verify', [
            'challenge_id' => $challengeId,
            'pin' => '000000',
        ]);

        $fifthResponse->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_LOCKED',
            ]);

        // Further attempt while locked is blocked immediately with 429
        $sixthResponse = $this->postJson('/security/pin/verify', [
            'challenge_id' => $challengeId,
            'pin' => '123456', // Even correct PIN is blocked
        ]);

        $sixthResponse->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_LOCKED',
            ]);
    }

    /**
     * Test challenge is rejected with 429 while locked.
     */
    public function test_challenge_rejected_while_locked(): void
    {
        Sanctum::actingAs($this->user);

        // Lock user
        Cache::put("pin:lock:{$this->user->id}", true, now()->addMinutes(15));

        $response = $this->postJson('/security/pin/challenge', [
            'purpose' => 'transaction',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error_code' => 'PIN_LOCKED',
            ]);
    }

    /**
     * Test lock resets after 15 minutes cooldown.
     */
    public function test_lock_resets_after_cooldown(): void
    {
        Sanctum::actingAs($this->user);

        // Challenge and lock via 5 wrong attempts
        $challengeResponse = $this->postJson('/security/pin/challenge', [
            'purpose' => 'transaction',
        ]);
        $challengeId = $challengeResponse->json('data.challenge_id');

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/security/pin/verify', [
                'challenge_id' => $challengeId,
                'pin' => '000000',
            ]);
        }

        // Verify locked
        $blocked = $this->postJson('/security/pin/challenge', [
            'purpose' => 'transaction',
        ]);
        $blocked->assertStatus(429);

        // Travel time 16 minutes
        $this->travel(16)->minutes();

        // Should now be able to request challenge again
        $newChallenge = $this->postJson('/security/pin/challenge', [
            'purpose' => 'transaction',
        ]);
        $newChallenge->assertStatus(200);
        $newChallengeId = $newChallenge->json('data.challenge_id');

        // And verify successfully with correct PIN
        $verifyResponse = $this->postJson('/security/pin/verify', [
            'challenge_id' => $newChallengeId,
            'pin' => '123456',
        ]);
        $verifyResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
