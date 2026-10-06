<?php

namespace Tests\Feature\Ppob;

use App\Domain\Ppob\Drivers\IndosatDriver;
use App\Domain\Ppob\Drivers\PdamDriver;
use App\Domain\Ppob\Drivers\PlnDriver;
use App\Domain\Ppob\Drivers\TelkomselDriver;
use App\Domain\Ppob\Drivers\XlDriver;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;
use Tests\TestCase;

class ProviderDriverTest extends TestCase
{
    public function test_telkomsel_driver_pay_and_inquiry_rejection(): void
    {
        $driver = new TelkomselDriver;

        // Telkomsel adalah prepaid, inquiry harus ditolak
        $this->expectException(BusinessException::class);
        $driver->inquiry('081234567890', 'TLS-5000');
    }

    public function test_telkomsel_driver_pay_success(): void
    {
        $driver = new TelkomselDriver;
        $result = $driver->pay('081234567890', 'TLS-5000', Money::fromCents(500000), 'TX-01');

        $this->assertEquals('success', $result['status']);
        $this->assertNotEmpty($result['provider_ref']);
        $this->assertArrayHasKey('serial_number', $result['raw']);
        $this->assertEquals('Telkomsel', $result['raw']['provider']);
    }

    public function test_indosat_driver_pay_success(): void
    {
        $driver = new IndosatDriver;
        $result = $driver->pay('085712345678', 'ISAT-10000', Money::fromCents(1000000), 'TX-02');

        $this->assertEquals('success', $result['status']);
        $this->assertNotEmpty($result['provider_ref']);
        $this->assertArrayHasKey('serial_number', $result['raw']);
        $this->assertEquals('Indosat', $result['raw']['provider']);
    }

    public function test_xl_driver_pay_success(): void
    {
        $driver = new XlDriver;
        $result = $driver->pay('087812345678', 'XL-25000', Money::fromCents(2500000), 'TX-03');

        $this->assertEquals('success', $result['status']);
        $this->assertNotEmpty($result['provider_ref']);
        $this->assertArrayHasKey('serial_number', $result['raw']);
        $this->assertEquals('XL', $result['raw']['provider']);
    }

    public function test_pln_driver_inquiry_and_pay(): void
    {
        $driver = new PlnDriver;

        // Inquiry postpaid PLN
        $inquiryResult = $driver->inquiry('1234567890', 'PLN-POSTPAID');
        $this->assertNotEmpty($inquiryResult['ref']);
        $this->assertEquals(25000000, $inquiryResult['amount_cents']);

        // Pay PLN
        $payResult = $driver->pay('1234567890', 'PLN-POSTPAID', Money::fromCents(25000000), 'TX-04');
        $this->assertEquals('success', $payResult['status']);
        $this->assertNotEmpty($payResult['provider_ref']);
    }

    public function test_pdam_driver_inquiry_and_pay(): void
    {
        $driver = new PdamDriver;

        // Inquiry postpaid PDAM
        $inquiryResult = $driver->inquiry('9876543210', 'PDAM-SURABAYA');
        $this->assertNotEmpty($inquiryResult['ref']);
        $this->assertEquals(17500000, $inquiryResult['amount_cents']);

        // Pay PDAM
        $payResult = $driver->pay('9876543210', 'PDAM-SURABAYA', Money::fromCents(17500000), 'TX-05');
        $this->assertEquals('success', $payResult['status']);
        $this->assertNotEmpty($payResult['provider_ref']);
    }

    public function test_driver_webhook_signature_verification(): void
    {
        config(['ppob.providers.telkomsel.webhook_secret' => 'tsel_secret']);
        $driver = new TelkomselDriver;

        $body = '{"event":"test"}';
        $validSignature = hash_hmac('sha256', $body, 'tsel_secret');

        $this->assertTrue($driver->verifyWebhookSignature($body, $validSignature));
        $this->assertTrue($driver->verifyWebhookSignature($body, "sha256={$validSignature}"));
        $this->assertFalse($driver->verifyWebhookSignature($body, 'invalid_signature_hex'));
    }
}
