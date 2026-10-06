<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Contracts\OtpDriverInterface;
use App\Domain\Auth\Drivers\FakeOtpDriver;
use App\Domain\Auth\Models\OtpCode;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterLoginTest extends TestCase
{
    use RefreshDatabase;

    private FakeOtpDriver $fakeDriver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDriver = new FakeOtpDriver;
        $this->app->instance(OtpDriverInterface::class, $this->fakeDriver);

        UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );
    }

    /**
     * 1. test_user_can_register_and_receive_otp
     * - POST /auth/otp dengan purpose=register → 200, OTP terkirim (fake driver)
     * - Assert otp_codes ada 1 row
     */
    public function test_user_can_register_and_receive_otp(): void
    {
        $response = $this->postJson('/auth/otp', [
            'phone' => '081234567890',
            'purpose' => 'register',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['message' => 'OTP telah dikirim.'],
            ]);

        $this->assertDatabaseCount('otp_codes', 1);
        $this->assertDatabaseHas('otp_codes', [
            'phone' => '081234567890',
            'type' => 'register',
        ]);
        $this->assertTrue($this->fakeDriver->wasSentTo('081234567890'));
    }

    /**
     * 2. test_user_can_verify_registration_and_get_token
     * - Setup: buat user + OTP code di DB
     * - POST /auth/verify-register → 200, dapat token + user data
     */
    public function test_user_can_verify_registration_and_get_token(): void
    {
        $tier = UserTier::first();

        $user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Test User',
            'phone' => '081234567891',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => false,
        ]);

        OtpCode::create([
            'phone' => '081234567891',
            'code' => '123456',
            'type' => 'register',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/auth/verify-register', [
            'phone' => '081234567891',
            'code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'phone',
                        'role',
                        'is_verified',
                    ],
                ],
            ]);

        $this->assertTrue($user->fresh()->is_verified);
        $this->assertNotNull(OtpCode::where('phone', '081234567891')->first()->verified_at);
    }

    /**
     * 3. test_otp_expires_after_5_minutes
     * - Buat OTP dengan expires_at = now()->subMinute()
     * - POST /auth/verify-register → 422, error_code = OTP_EXPIRED
     */
    public function test_otp_expires_after_5_minutes(): void
    {
        $tier = UserTier::first();

        User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Expired User',
            'phone' => '081234567892',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => false,
        ]);

        OtpCode::create([
            'phone' => '081234567892',
            'code' => '123456',
            'type' => 'register',
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/auth/verify-register', [
            'phone' => '081234567892',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'OTP_EXPIRED',
            ]);
    }

    /**
     * 4. test_otp_invalid_increments_attempt_count
     * - Kirim kode salah → 422, error_code = OTP_INVALID
     * - Assert attempt_count di DB = 1
     */
    public function test_otp_invalid_increments_attempt_count(): void
    {
        $tier = UserTier::first();

        User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Invalid Code User',
            'phone' => '081234567893',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => false,
        ]);

        $otp = OtpCode::create([
            'phone' => '081234567893',
            'code' => '123456',
            'type' => 'register',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/auth/verify-register', [
            'phone' => '081234567893',
            'code' => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'OTP_INVALID',
            ]);

        $this->assertEquals(1, $otp->fresh()->attempts);
        $this->assertEquals(1, $otp->fresh()->attempt_count);
    }

    /**
     * 5. test_otp_blocked_after_3_wrong_attempts
     * - Buat OTP dengan attempt_count = 3
     * - Kirim request → 422, error_code = OTP_MAX_ATTEMPTS_EXCEEDED
     */
    public function test_otp_blocked_after_3_wrong_attempts(): void
    {
        $tier = UserTier::first();

        User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Blocked User',
            'phone' => '081234567894',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => false,
        ]);

        OtpCode::create([
            'phone' => '081234567894',
            'code' => '123456',
            'type' => 'register',
            'attempts' => 3,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/auth/verify-register', [
            'phone' => '081234567894',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'OTP_MAX_ATTEMPTS_EXCEEDED',
            ]);
    }

    /**
     * 6. test_duplicate_phone_registration_rejected
     * - Buat user dengan phone yang sama
     * - POST /auth/otp dengan purpose=register → 409, error_code = USER_ALREADY_EXISTS
     */
    public function test_duplicate_phone_registration_rejected(): void
    {
        $tier = UserTier::first();

        User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Existing User',
            'phone' => '081234567895',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $response = $this->postJson('/auth/otp', [
            'phone' => '081234567895',
            'purpose' => 'register',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'USER_ALREADY_EXISTS',
            ]);
    }

    /**
     * Test login flow with OTP and logout
     */
    public function test_user_can_login_with_otp_and_logout(): void
    {
        $tier = UserTier::first();

        $user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Login User',
            'phone' => '081234567896',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        // Request login OTP
        $otpResponse = $this->postJson('/auth/otp', [
            'phone' => '081234567896',
            'purpose' => 'login',
        ]);

        $otpResponse->assertStatus(200);

        $code = $this->fakeDriver->lastCodeFor('081234567896');
        $this->assertNotNull($code);

        // Verify login
        $loginResponse = $this->postJson('/auth/login', [
            'phone' => '081234567896',
            'code' => $code,
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'phone'],
                ],
            ]);

        $token = $loginResponse->json('data.token');

        // Check GET /auth/me
        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'phone' => '081234567896',
                ],
            ]);

        // Logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/auth/logout');

        $logoutResponse->assertStatus(204);
    }
}
