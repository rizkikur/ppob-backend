<?php

namespace App\Domain\Ppob\Services;

use App\Domain\Ppob\Contracts\PpobProviderInterface;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;

/**
 * PpobService — router ke driver provider yang tepat dengan Circuit Breaker (ADR-003).
 * Driver dipilih berdasarkan provider.driver field dari database.
 */
class PpobService
{
    public function __construct(
        private readonly ?CircuitBreakerService $circuitBreaker = null
    ) {}

    /**
     * Inquiry tagihan untuk produk postpaid.
     *
     * @throws BusinessException
     */
    public function inquiry(Product $product, string $customerNumber): array
    {
        $driver = $this->resolveDriver($product);
        $providerCode = $product->provider?->code ?? 'default';

        $cb = $this->getCircuitBreaker();
        if (! $cb->isAvailable($providerCode)) {
            throw BusinessException::providerUnavailable($product->provider?->name ?? 'Provider');
        }

        try {
            $result = $driver->inquiry($customerNumber, $product->sku_code);
            $cb->recordSuccess($providerCode);

            return $result;
        } catch (\Throwable $e) {
            $cb->recordFailure($providerCode);
            throw $e;
        }
    }

    /**
     * Eksekusi pembayaran ke provider.
     *
     * @throws BusinessException
     */
    public function pay(Product $product, string $customerNumber, Money $amount, string $transactionRef): array
    {
        $driver = $this->resolveDriver($product);
        $providerCode = $product->provider?->code ?? 'default';

        $cb = $this->getCircuitBreaker();
        if (! $cb->isAvailable($providerCode)) {
            throw BusinessException::providerUnavailable($product->provider?->name ?? 'Provider');
        }

        try {
            $result = $driver->pay($customerNumber, $product->sku_code, $amount, $transactionRef);

            if (($result['status'] ?? '') === 'failed') {
                $cb->recordFailure($providerCode);
            } else {
                $cb->recordSuccess($providerCode);
            }

            return $result;
        } catch (\Throwable $e) {
            $cb->recordFailure($providerCode);
            throw $e;
        }
    }

    /**
     * Cek status transaksi (untuk provider async/polling).
     */
    public function checkStatus(Product $product, string $providerRef): array
    {
        $driver = $this->resolveDriver($product);

        return $driver->checkStatus($providerRef);
    }

    /**
     * Verifikasi signature webhook untuk provider tertentu.
     */
    public function verifyWebhook(string $providerCode, string $rawBody, string $signature): bool
    {
        $driver = $this->resolveDriverByCode($providerCode);

        return $driver->verifyWebhookSignature($rawBody, $signature);
    }

    /**
     * Resolve driver berdasarkan provider.driver field produk.
     */
    public function resolveDriver(Product $product): PpobProviderInterface
    {
        $product->loadMissing('provider');
        $driverName = $product->provider?->driver;

        if (! $driverName) {
            throw BusinessException::providerUnavailable($product->provider?->name ?? 'Unknown');
        }

        return $this->resolveDriverByName($driverName, $product->provider?->name ?? 'Unknown');
    }

    /**
     * Resolve driver berdasarkan kode provider (e.g. 'telkomsel', 'pln', 'pdam').
     */
    public function resolveDriverByCode(string $providerCode): PpobProviderInterface
    {
        $driverMap = [
            'telkomsel' => 'TelkomselDriver',
            'indosat' => 'IndosatDriver',
            'xl' => 'XlDriver',
            'pln' => 'PlnDriver',
            'pdam' => 'PdamDriver',
        ];

        $code = strtolower(trim($providerCode));
        $driverName = $driverMap[$code] ?? ucfirst($code).'Driver';

        return $this->resolveDriverByName($driverName, $providerCode);
    }

    private function resolveDriverByName(string $driverName, string $displayName): PpobProviderInterface
    {
        $driverClass = 'App\\Domain\\Ppob\\Drivers\\'.$driverName;

        if (! class_exists($driverClass)) {
            throw BusinessException::providerUnavailable($displayName);
        }

        return app($driverClass);
    }

    private function getCircuitBreaker(): CircuitBreakerService
    {
        return $this->circuitBreaker ?? app(CircuitBreakerService::class);
    }
}
