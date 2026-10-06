<?php

namespace Tests\Feature\Partner;

use App\Domain\Partner\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PartnerRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    private string $rawSecret = 'rate-limit-secret-key-12345';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->partner = Partner::create([
            'name' => 'PT Mitra Rate Limited',
            'api_key' => 'partner_key_rpm_test',
            'secret' => encrypt($this->rawSecret),
            'allowed_ips' => null,
            'is_active' => true,
            'rate_limit_rpm' => 3, // Batas 3 RPM untuk test
        ]);
    }

    private function generateSignature(Partner $partner, string $method, string $path, string $timestamp, string $rawBody = ''): string
    {
        $bodyHash = hash('sha256', $rawBody);
        $pathWithSlash = '/'.ltrim($path, '/');
        $stringToSign = strtoupper($method)."\n".$pathWithSlash."\n".$timestamp."\n".$bodyHash;
        $secret = $partner->getSecretDecrypted();

        return hash_hmac('sha256', $stringToSign, $secret);
    }

    private function partnerHeaders(Partner $partner, string $method, string $path): array
    {
        $ts = (string) now()->timestamp;
        $sig = $this->generateSignature($partner, $method, $path, $ts, '');

        return [
            'X-Api-Key' => $partner->api_key,
            'X-Timestamp' => $ts,
            'X-Signature' => $sig,
        ];
    }

    public function test_rate_limit_enforced_when_exceeded(): void
    {
        // 3 request pertama harus berhasil (200 OK)
        for ($i = 1; $i <= 3; $i++) {
            $headers = $this->partnerHeaders($this->partner, 'GET', '/partner/balance');
            $response = $this->getJson('/partner/balance', $headers);
            $response->assertStatus(200);
        }

        // Request ke-4 harus ditolak dengan 429 PARTNER_RATE_LIMITED
        $headers = $this->partnerHeaders($this->partner, 'GET', '/partner/balance');
        $response = $this->getJson('/partner/balance', $headers);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error_code' => 'PARTNER_RATE_LIMITED',
            ]);
    }

    public function test_rate_limits_are_isolated_per_partner(): void
    {
        $partnerB = Partner::create([
            'name' => 'Partner B',
            'api_key' => 'partner_b_key',
            'secret' => encrypt('secret-b-12345'),
            'allowed_ips' => null,
            'is_active' => true,
            'rate_limit_rpm' => 5,
        ]);

        // Habiskan kuota partner A
        for ($i = 1; $i <= 3; $i++) {
            $this->getJson('/partner/balance', $this->partnerHeaders($this->partner, 'GET', '/partner/balance'))
                ->assertStatus(200);
        }

        // Partner A kena limit
        $this->getJson('/partner/balance', $this->partnerHeaders($this->partner, 'GET', '/partner/balance'))
            ->assertStatus(429);

        // Partner B masih bisa request dengan sukses
        $this->getJson('/partner/balance', $this->partnerHeaders($partnerB, 'GET', '/partner/balance'))
            ->assertStatus(200);
    }
}
