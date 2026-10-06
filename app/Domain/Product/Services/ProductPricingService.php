<?php

namespace App\Domain\Product\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Service untuk menghitung harga jual produk berdasarkan user tier.
 * Tier pricing: end_user vs agent vs reseller (harga disesuaikan per tier).
 */
class ProductPricingService
{
    /** Ambil harga jual produk untuk user tertentu (berdasarkan tier). */
    public function getPriceForUser(Product $product, ?User $user = null): Money
    {
        $tierId = $user?->user_tier_id;

        if ($tierId) {
            $tierPrice = $product->tierPrices()
                ->where('user_tier_id', $tierId)
                ->first();

            if ($tierPrice && $tierPrice->sell_price_cents) {
                return $tierPrice->sell_price_cents;
            }
        }

        // Fallback ke base_price + admin_fee jika tidak ada tier price khusus
        $base = $product->base_price_cents ?? Money::zero();
        $adminFee = $product->admin_fee_cents ?? Money::zero();

        return $base->add($adminFee);
    }
}
