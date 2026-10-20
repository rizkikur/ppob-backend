<?php

namespace Tests\Unit\Ppob;

use App\Domain\Ppob\Drivers\MLinkGatewayDriver;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;
use Tests\TestCase;

class MLinkGatewayDriverTest extends TestCase
{
    private MLinkGatewayDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new MLinkGatewayDriver();
        MLinkGatewayDriver::enableSimulation(true);
    }

    public function test_signature_generation_matches_specification(): void
    {
        $clientId = 'xymetri';
        $reqId = '32794735788277';
        $password = 'secret_pass_123';
        $secretKey = 'my_secret_key_abc';

        $expectedPayload = $clientId . ':' . $reqId . ':' . $password;
        $expectedHash = hash_hmac('sha256', $expectedPayload, $secretKey, true);
        $expectedSignature = base64_encode($expectedHash);

        $actualSignature = $this->driver->generateSignature($clientId, $reqId, $password, $secretKey);

        $this->assertSame($expectedSignature, $actualSignature);
    }

    public function test_get_pricelist_retrieves_and_formats_supplier_products(): void
    {
        $provider = new Provider([
            'name' => 'M-Link Gateway',
            'code' => 'mlink',
            'driver' => 'MLinkGatewayDriver',
        ]);

        $products = $this->driver->getPricelist($provider);

        $this->assertNotEmpty($products);
        $skus = array_column($products, 'supplier_sku');
        $this->assertContains('DNA10', $skus);
        $this->assertContains('TS10L3', $skus);
        $this->assertContains('PLN20', $skus);

        // Cari item TS10L3
        $tselItem = collect($products)->firstWhere('supplier_sku', 'TS10L3');
        $this->assertNotNull($tselItem);
        $this->assertSame('Telkomsel 10K L3', $tselItem['name']);
        $this->assertSame('Telkomsel', $tselItem['category_name']);
        $this->assertSame(1010000, $tselItem['cost_cents']); // Rp 10.100 = 1.010.000 cents
        $this->assertSame('active', $tselItem['status']);
    }

    public function test_check_balance_returns_formatted_balance(): void
    {
        $provider = new Provider([
            'name' => 'M-Link Gateway',
            'code' => 'mlink',
            'driver' => 'MLinkGatewayDriver',
        ]);

        $res = $this->driver->checkBalance($provider);

        $this->assertTrue($res['success']);
        $this->assertSame(50000100, $res['balance_cents']); // Rp 500.001 = 50.000.100 cents
        $this->assertStringContainsString('500.001', $res['balance_formatted']);
    }

    public function test_inquiry_pln_prepaid_success(): void
    {
        $result = $this->driver->inquiry('12000000001', 'PLN20');

        $this->assertNotEmpty($result['ref']);
        $this->assertSame('MAHAYANA GARBILEKA 01', $result['customer_name']);
        $this->assertSame(2100000, $result['amount_cents']); // Rp 21.000 = 2.100.000 cents
    }

    public function test_inquiry_pln_prepaid_not_found_throws_exception(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Nomor tidak ditemukan');

        $this->driver->inquiry('12000000000', 'PLN20');
    }

    public function test_inquiry_ewallet_success(): void
    {
        $result = $this->driver->inquiry('081380312000', 'DNAP');

        $this->assertNotEmpty($result['ref']);
        $this->assertSame('GOPAY NAMA AKUN 000', $result['customer_name']);
    }

    public function test_inquiry_pulsa_throws_product_type_mismatch(): void
    {
        $this->expectException(BusinessException::class);

        $this->driver->inquiry('081380312000', 'TS10L3');
    }

    public function test_pay_pulsa_success_scenario(): void
    {
        $result = $this->driver->pay('081380312000', 'TS10L3', Money::fromRupiah(10100), 'TRX-TEST-001');

        $this->assertSame('success', $result['status']);
        $this->assertSame('000', $result['rc']);
        $this->assertSame('0041000180894618', $result['raw']['serial_number']);
    }

    public function test_pay_pulsa_wrong_number_scenario(): void
    {
        $result = $this->driver->pay('081380312001', 'TS10L3', Money::fromRupiah(10100), 'TRX-TEST-002');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('007', $result['rc']);
        $this->assertStringContainsString('Nomor salah', $result['message']);
    }

    public function test_pay_pulsa_biller_down_scenario(): void
    {
        $result = $this->driver->pay('081380312002', 'TS10L3', Money::fromRupiah(10100), 'TRX-TEST-003');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('006', $result['rc']);
        $this->assertStringContainsString('Gangguan Biller', $result['message']);
    }

    public function test_pay_pulsa_pending_scenario(): void
    {
        $result = $this->driver->pay('081380312003', 'TS10L3', Money::fromRupiah(10100), 'TRX-TEST-004');

        $this->assertSame('pending', $result['status']);
        $this->assertSame('030', $result['rc']);
    }

    public function test_pay_pln_token_success_scenario(): void
    {
        $result = $this->driver->pay('12000000001', 'PLN20', Money::fromRupiah(21000), 'TRX-TEST-005');

        $this->assertSame('success', $result['status']);
        $this->assertSame('000', $result['rc']);
        $this->assertSame('3797-4760-0161-1083-4669', $result['raw']['serial_number']);
    }

    public function test_check_status_returns_success(): void
    {
        $result = $this->driver->checkStatus('REF-STATUS-001');

        $this->assertSame('success', $result['status']);
        $this->assertSame('000', $result['rc']);
        $this->assertStringContainsString('SN-MLINK-STATUS-', $result['raw']['serial_number']);
    }

    public function test_verify_webhook_signature(): void
    {
        $secret = 'test_webhook_secret_123';
        config(['ppob.providers.mlink.webhook_secret' => $secret]);

        $body = '{"header":{"req_id":"123"},"body":{"rc":"000"}}';
        $validSignature = hash_hmac('sha256', $body, $secret);

        $this->assertTrue($this->driver->verifyWebhookSignature($body, $validSignature));
        $this->assertTrue($this->driver->verifyWebhookSignature($body, 'sha256=' . $validSignature));
        $this->assertFalse($this->driver->verifyWebhookSignature($body, 'invalid_sig'));
    }

    public function test_resolve_config_with_separated_port_and_direct_url(): void
    {
        // 1. Format dipisah: base_url + port
        $provider1 = new Provider([
            'config' => [
                'base_url' => 'https://api.m-link.id/json',
                'port' => 7771,
                'username' => 'xymetri_user',
                'password' => 'pass123',
                'secret_key' => 'secret123',
            ],
        ]);
        $cfg1 = $this->driver->resolveConfig($provider1);
        $this->assertSame('https://api.m-link.id:7771/json', $cfg1['api_url']);
        $this->assertSame('xymetri_user', $cfg1['client_id']);

        // 2. Format langsung lengkap di base_url
        $provider2 = new Provider([
            'config' => [
                'base_url' => 'https://api.m-link.id:7771/json',
                'username' => 'xymetri_user',
            ],
        ]);
        $cfg2 = $this->driver->resolveConfig($provider2);
        $this->assertSame('https://api.m-link.id:7771/json', $cfg2['api_url']);

        // 3. Format tanpa /json di base_url + port terpisah
        $provider3 = new Provider([
            'config' => [
                'base_url' => 'https://api.m-link.id',
                'port' => 7771,
            ],
        ]);
        $cfg3 = $this->driver->resolveConfig($provider3);
        $this->assertSame('https://api.m-link.id:7771/json', $cfg3['api_url']);
    }
}
