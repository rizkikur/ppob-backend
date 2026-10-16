<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Contracts\OtpDriverInterface;
use App\Domain\Auth\Drivers\FakeOtpDriver;
use App\Domain\Auth\Models\UserTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiAuthThrottlingTest extends TestCase
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
            ['label' => 'Regular', 'description' => 'Regular User']
        );
    }

    public function test_api_login_is_throttled_by_target_phone(): void
    {
        $targetPhone = '081211112222';

        // 5 requests allowed
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/auth/login', [
                'phone' => $targetPhone,
                'code' => '000000',
            ]);
            // Either 422 (invalid OTP) or other valid validation, but NOT 429 yet
            $this->assertNotEquals(429, $response->status());
        }

        // 6th request must be rejected with 429 TOO_MANY_ATTEMPTS
        $blocked = $this->postJson('/auth/login', [
            'phone' => $targetPhone,
            'code' => '000000',
        ]);

        $blocked->assertStatus(429);
        $blocked->assertJson([
            'success' => false,
            'error_code' => 'TOO_MANY_ATTEMPTS',
        ]);
    }

    public function test_api_login_is_throttled_by_ip(): void
    {
        // Simulate hitting login with different phones from the same IP
        for ($i = 1; $i <= 10; $i++) {
            $phone = '08123000' . sprintf('%04d', $i);
            $response = $this->postJson('/auth/login', [
                'phone' => $phone,
                'code' => '000000',
            ]);
            $this->assertNotEquals(429, $response->status());
        }

        // 11th request from the same IP must be rejected with 429
        $blocked = $this->postJson('/auth/login', [
            'phone' => '081299999999',
            'code' => '000000',
        ]);

        $blocked->assertStatus(429);
        $blocked->assertJson([
            'success' => false,
            'error_code' => 'TOO_MANY_ATTEMPTS',
        ]);
    }

    public function test_api_otp_is_throttled_by_phone(): void
    {
        $phone = '081255556666';

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

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'error_code' => 'OTP_RATE_LIMIT_EXCEEDED',
        ]);
    }
}
