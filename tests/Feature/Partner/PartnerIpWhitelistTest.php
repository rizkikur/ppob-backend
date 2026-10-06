<?php

namespace Tests\Feature\Partner;

use App\Domain\Partner\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerIpWhitelistTest extends TestCase
{
    use RefreshDatabase;

    private Partner $whitelistedPartner;

    private string $rawSecret = 'ip-whitelist-secret-key-123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->whitelistedPartner = Partner::create([
            'name' => 'PT Mitra Whitelist',
            'api_key' => 'partner_key_ip_whitelist',
            'secret' => encrypt($this->rawSecret),
            'allowed_ips' => ['192.168.1.50', '10.0.0.99'],
            'is_active' => true,
            'rate_limit_rpm' => 60,
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

    public function test_blocked_ip_returns_403_and_partner_ip_blocked(): void
    {
        $headers = $this->partnerHeaders($this->whitelistedPartner, 'GET', '/partner/balance');

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.195'])
            ->getJson('/partner/balance', $headers);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'PARTNER_IP_BLOCKED',
            ]);

        // Audit log mencatat IP yang diblokir
        $this->assertDatabaseHas('partner_logs', [
            'partner_id' => $this->whitelistedPartner->id,
            'response_code' => 403,
            'ip_address' => '203.0.113.195',
        ]);
    }

    public function test_whitelisted_ip_is_allowed(): void
    {
        $headers = $this->partnerHeaders($this->whitelistedPartner, 'GET', '/partner/balance');

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->getJson('/partner/balance', $headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_partner_with_null_whitelist_allows_any_ip(): void
    {
        $openPartner = Partner::create([
            'name' => 'PT Mitra Terbuka',
            'api_key' => 'partner_key_open_ip',
            'secret' => encrypt('open-secret-12345'),
            'allowed_ips' => null,
            'is_active' => true,
            'rate_limit_rpm' => 60,
        ]);

        $headers = $this->partnerHeaders($openPartner, 'GET', '/partner/balance');

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->getJson('/partner/balance', $headers);

        $response->assertStatus(200);
    }
}
