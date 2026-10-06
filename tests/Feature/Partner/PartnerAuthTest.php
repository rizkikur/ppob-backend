<?php

namespace Tests\Feature\Partner;

use App\Domain\Partner\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerAuthTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    private string $rawSecret = 'super-secret-key-12345';

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = Partner::create([
            'name' => 'PT Mitra Finansial Sejahtera',
            'api_key' => 'partner_key_live_abc123',
            'secret' => encrypt($this->rawSecret),
            'allowed_ips' => null,
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

    private function partnerHeaders(Partner $partner, string $method, string $path, array $body = [], ?string $timestamp = null, ?string $signature = null): array
    {
        $ts = $timestamp ?? (string) now()->timestamp;
        $rawBody = ! empty($body) ? json_encode($body) : '';
        $sig = $signature ?? $this->generateSignature($partner, $method, $path, $ts, $rawBody);

        return [
            'X-Api-Key' => $partner->api_key,
            'X-Timestamp' => $ts,
            'X-Signature' => $sig,
        ];
    }

    public function test_missing_api_key_returns_401(): void
    {
        $response = $this->getJson('/partner/balance');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'PARTNER_KEY_INVALID',
            ]);
    }

    public function test_invalid_api_key_returns_401(): void
    {
        $response = $this->getJson('/partner/balance', [
            'X-Api-Key' => 'non_existent_key',
            'X-Timestamp' => (string) now()->timestamp,
            'X-Signature' => 'fake_signature',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'PARTNER_KEY_INVALID',
            ]);
    }

    public function test_inactive_partner_returns_401(): void
    {
        $this->partner->update(['is_active' => false]);

        $headers = $this->partnerHeaders($this->partner, 'GET', '/partner/balance');
        $response = $this->getJson('/partner/balance', $headers);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'PARTNER_KEY_INVALID',
            ]);
    }

    public function test_missing_signature_returns_401(): void
    {
        $response = $this->getJson('/partner/balance', [
            'X-Api-Key' => $this->partner->api_key,
            'X-Timestamp' => (string) now()->timestamp,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'PARTNER_SIGNATURE_INVALID',
            ]);
    }

    public function test_invalid_signature_returns_401(): void
    {
        $headers = $this->partnerHeaders(
            $this->partner,
            'GET',
            '/partner/balance',
            [],
            null,
            'invalid_signature_hash_12345'
        );

        $response = $this->getJson('/partner/balance', $headers);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'PARTNER_SIGNATURE_INVALID',
            ]);

        // Audit log harus tetap mencatat request gagal untuk partner yang teridentifikasi
        $this->assertDatabaseHas('partner_logs', [
            'partner_id' => $this->partner->id,
            'response_code' => 401,
        ]);
    }

    public function test_stale_timestamp_returns_422(): void
    {
        // Timestamp 305 detik yang lalu (> 300 detik)
        $staleTimestamp = (string) (now()->timestamp - 305);
        $headers = $this->partnerHeaders($this->partner, 'GET', '/partner/balance', [], $staleTimestamp);

        $response = $this->getJson('/partner/balance', $headers);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'WEBHOOK_TIMESTAMP_INVALID',
            ]);
    }

    public function test_future_timestamp_beyond_tolerance_returns_422(): void
    {
        // Timestamp 305 detik di masa depan
        $futureTimestamp = (string) (now()->timestamp + 305);
        $headers = $this->partnerHeaders($this->partner, 'GET', '/partner/balance', [], $futureTimestamp);

        $response = $this->getJson('/partner/balance', $headers);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'WEBHOOK_TIMESTAMP_INVALID',
            ]);
    }

    public function test_valid_request_returns_200_and_logs_audit(): void
    {
        $headers = $this->partnerHeaders($this->partner, 'GET', '/partner/balance');
        $response = $this->getJson('/partner/balance', $headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'partner_id' => $this->partner->id,
                    'partner_name' => $this->partner->name,
                ],
            ]);

        $this->assertDatabaseHas('partner_logs', [
            'partner_id' => $this->partner->id,
            'method' => 'GET',
            'response_code' => 200,
        ]);
    }

    public function test_valid_request_with_sha256_prefix_succeeds(): void
    {
        $ts = (string) now()->timestamp;
        $sig = $this->generateSignature($this->partner, 'GET', '/partner/balance', $ts, '');

        $headers = [
            'X-Api-Key' => $this->partner->api_key,
            'X-Timestamp' => $ts,
            'X-Signature' => 'sha256='.$sig,
        ];

        $response = $this->getJson('/partner/balance', $headers);
        $response->assertStatus(200);
    }
}
