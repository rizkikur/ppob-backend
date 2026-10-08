<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Auth\Models\OtpCode;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Ppob\Services\CircuitBreakerService;
use App\Domain\Product\Models\Provider;
use App\Domain\Security\Models\PinVerificationToken;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduledCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $tier = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular']
        );

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Maintenance User',
            'phone' => '081299998888',
            'email' => 'maint@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);
    }

    public function test_idempotency_cleanup_command_removes_old_keys_and_preserves_recent_keys(): void
    {
        // 1. Key lama (> 30 hari)
        DB::table('idempotency_keys')->insert([
            'user_id' => $this->user->id,
            'key_value' => 'old-idempotency-key-1',
            'endpoint' => 'api/v1/transactions',
            'response_code' => 200,
            'response_body' => json_encode(['status' => 'ok']),
            'created_at' => Carbon::now()->subDays(35),
        ]);

        // 2. Key baru (< 30 hari)
        DB::table('idempotency_keys')->insert([
            'user_id' => $this->user->id,
            'key_value' => 'recent-idempotency-key-2',
            'endpoint' => 'api/v1/transactions',
            'response_code' => 200,
            'response_body' => json_encode(['status' => 'ok']),
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $this->assertDatabaseCount('idempotency_keys', 2);

        $this->artisan('ppob:cleanup:idempotency', ['--days' => 30])
            ->expectsOutputToContain('Berhasil membersihkan 1 record Idempotency Key.')
            ->assertSuccessful();

        $this->assertDatabaseCount('idempotency_keys', 1);
        $this->assertDatabaseHas('idempotency_keys', [
            'key_value' => 'recent-idempotency-key-2',
        ]);
        $this->assertDatabaseMissing('idempotency_keys', [
            'key_value' => 'old-idempotency-key-1',
        ]);
    }

    public function test_tokens_cleanup_command_removes_expired_otps_and_expired_pin_tokens(): void
    {
        // 1. Expired OTP (> 24 jam)
        OtpCode::create([
            'phone' => $this->user->phone,
            'code' => '111111',
            'type' => 'login',
            'attempts' => 1,
            'expires_at' => Carbon::now()->subHours(30),
        ]);

        // 2. Fresh active OTP
        OtpCode::create([
            'phone' => $this->user->phone,
            'code' => '222222',
            'type' => 'login',
            'attempts' => 0,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // 3. Expired PIN token (> 24 jam)
        PinVerificationToken::create([
            'user_id' => $this->user->id,
            'token' => 'expired_pin_token_abc',
            'purpose' => 'transaction',
            'expires_at' => Carbon::now()->subHours(30),
        ]);

        // 4. Fresh active PIN token
        PinVerificationToken::create([
            'user_id' => $this->user->id,
            'token' => 'active_pin_token_xyz',
            'purpose' => 'transaction',
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $this->assertDatabaseCount('otp_codes', 2);
        $this->assertDatabaseCount('pin_verification_tokens', 2);

        $this->artisan('ppob:cleanup:tokens', ['--hours' => 24])
            ->expectsOutputToContain('Berhasil membersihkan 1 record OTP dan 1 record PIN Token.')
            ->assertSuccessful();

        $this->assertDatabaseCount('otp_codes', 1);
        $this->assertDatabaseHas('otp_codes', ['code' => '222222']);
        $this->assertDatabaseMissing('otp_codes', ['code' => '111111']);

        $this->assertDatabaseCount('pin_verification_tokens', 1);
        $this->assertDatabaseHas('pin_verification_tokens', ['token' => 'active_pin_token_xyz']);
        $this->assertDatabaseMissing('pin_verification_tokens', ['token' => 'expired_pin_token_abc']);
    }

    public function test_provider_health_check_command_shows_all_healthy_when_circuits_are_closed(): void
    {
        Provider::create([
            'name' => 'Demo Telkomsel',
            'code' => 'telkomsel_demo',
            'driver' => 'TelkomselDriver',
            'queue_name' => 'supplier_telkomsel',
            'is_active' => true,
        ]);

        $this->artisan('ppob:health:check')
            ->expectsOutputToContain('Semua provider berada dalam kondisi prima (CLOSED / Available).')
            ->assertSuccessful();
    }

    public function test_provider_health_check_command_reports_unhealthy_and_fails_in_strict_mode_when_circuit_open(): void
    {
        $provider = Provider::create([
            'name' => 'Demo Indosat',
            'code' => 'indosat_demo',
            'driver' => 'IndosatDriver',
            'queue_name' => 'supplier_indosat',
            'is_active' => true,
        ]);

        /** @var CircuitBreakerService $cb */
        $cb = app(CircuitBreakerService::class);
        $cb->trip($provider->code);

        $this->artisan('ppob:health:check', ['--strict' => true])
            ->expectsOutputToContain('PERINGATAN: Terdapat provider yang dalam status gangguan / Circuit Breaker OPEN!')
            ->assertExitCode(1);
    }
}
