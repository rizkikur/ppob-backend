<?php

namespace App\Domain\Ppob\Services;

use App\Domain\Ppob\Contracts\PpobProviderInterface;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\Provider;
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
    public function inquiry(Product $product, string $customerNumber, ?Provider $provider = null): array
    {
        $actualProvider = $provider ?? $product->provider;
        if (! $actualProvider) {
            $product->loadMissing('provider');
            $actualProvider = $product->provider;
        }

        $driver = $this->resolveDriver($product, $actualProvider);
        $providerCode = $actualProvider?->code ?? 'default';

        $cb = $this->getCircuitBreaker();
        if (! $cb->isAvailable($providerCode)) {
            throw BusinessException::providerUnavailable($actualProvider?->name ?? 'Provider');
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
    public function pay(
        Product $product,
        string $customerNumber,
        Money $amount,
        string $transactionRef,
        ?Provider $provider = null
    ): array {
        $actualProvider = $provider ?? $product->provider;
        if (! $actualProvider) {
            $product->loadMissing('provider');
            $actualProvider = $product->provider;
        }

        $driver = $this->resolveDriver($product, $actualProvider);
        $providerCode = $actualProvider?->code ?? 'default';

        $cb = $this->getCircuitBreaker();
        if (! $cb->isAvailable($providerCode)) {
            throw BusinessException::providerUnavailable($actualProvider?->name ?? 'Provider');
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
    public function checkStatus(Product $product, string $providerRef, ?Provider $provider = null): array
    {
        $driver = $this->resolveDriver($product, $provider);

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
     * Resolve driver berdasarkan provider.driver field produk atau provider eksplisit.
     */
    public function resolveDriver(Product $product, ?Provider $provider = null): PpobProviderInterface
    {
        $actualProvider = $provider ?? $product->provider;
        if (! $actualProvider) {
            $product->loadMissing('provider');
            $actualProvider = $product->provider;
        }

        $driverName = $actualProvider?->driver;

        if (! $driverName) {
            throw BusinessException::providerUnavailable($actualProvider?->name ?? 'Unknown');
        }

        return $this->resolveDriverByName($driverName, $actualProvider?->name ?? 'Unknown');
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
