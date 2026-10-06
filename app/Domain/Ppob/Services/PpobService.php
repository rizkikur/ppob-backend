<?php

namespace App\Domain\Ppob\Services;

use App\Domain\Ppob\Contracts\PpobProviderInterface;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;

/**
 * PpobService — router ke driver provider yang tepat.
 * Driver dipilih berdasarkan provider.driver field dari database.
 */
class PpobService
{
    /** Inquiry tagihan untuk produk postpaid. */
    public function inquiry(Product $product, string $customerNumber): array
    {
        $driver = $this->resolveDriver($product);

        return $driver->inquiry($customerNumber, $product->sku_code);
    }

    /** Eksekusi pembayaran ke provider. */
    public function pay(Product $product, string $customerNumber, Money $amount, string $transactionRef): array
    {
        $driver = $this->resolveDriver($product);

        return $driver->pay($customerNumber, $product->sku_code, $amount, $transactionRef);
    }

    /** Resolve driver berdasarkan provider.driver field. */
    private function resolveDriver(Product $product): PpobProviderInterface
    {
        $product->loadMissing('provider');
        $driverClass = 'App\\Domain\\Ppob\\Drivers\\'.$product->provider->driver;
        if (! class_exists($driverClass)) {
            throw BusinessException::providerUnavailable($product->provider->name);
        }

        return app($driverClass);
    }
}
