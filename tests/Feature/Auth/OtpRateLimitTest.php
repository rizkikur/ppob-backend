<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Contracts\OtpDriverInterface;
use App\Domain\Auth\Drivers\FakeOtpDriver;
use App\Domain\Auth\Models\UserTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OtpRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private FakeOtpDriver $fakeDriver;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->fakeDriver = new FakeOtpDriver;
        $this->app->instance(OtpDriverInterface::class, $this->fakeDriver);

        UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );
    }

    /**
     * 1. test_otp_rate_limit_after_3_requests_in_10_minutes
     * - Kirim POST /auth/otp 3x berturut-turut → semua 200
     * - Kirim yang ke-4 → 429, error_code = OTP_RATE_LIMIT_EXCEEDED
     */
    public function test_otp_rate_limit_after_3_requests_in_10_minutes(): void
    {
        $phone = '081299990001';

        // 3 requests should succeed
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/auth/otp', [
                'phone' => $phone,
                'purpose' => 'register',
            ]);
            $response->assertStatus(200);
        }

        // 4th request must be rejected with 429
        $response = $this->postJson('/auth/otp', [
            'phone' => $phone,
            'purpose' => 'register',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error_code' => 'OTP_RATE_LIMIT_EXCEEDED',
            ]);
    }

    /**
     * 2. test_otp_rate_limit_resets_after_cooldown
     * - Kirim 3x → block
     * - Travel time +11 menit (gunakan $this->travel(11)->minutes())
     * - Kirim lagi → 200 (berhasil)
     */
    public function test_otp_rate_limit_resets_after_cooldown(): void
    {
        $phone = '081299990002';

        // 3 requests to reach limit
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/auth/otp', [
                'phone' => $phone,
                'purpose' => 'register',
            ]);
            $response->assertStatus(200);
        }

        // Verify blocked
        $blocked = $this->postJson('/auth/otp', [
            'phone' => $phone,
            'purpose' => 'register',
        ]);
        $blocked->assertStatus(429);

        // Travel 11 minutes
        $this->travel(11)->minutes();

        // Should be allowed again
        $response = $this->postJson('/auth/otp', [
            'phone' => $phone,
            'purpose' => 'register',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
