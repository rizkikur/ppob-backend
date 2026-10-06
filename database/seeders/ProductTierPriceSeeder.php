<?php

namespace Database\Seeders;

use App\Domain\Auth\Models\UserTier;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductTierPrice;
use Illuminate\Database\Seeder;

class ProductTierPriceSeeder extends Seeder
{
    public function run(): void
    {
        $agentTier = UserTier::where('name', 'agent')->first();
        $resellerTier = UserTier::where('name', 'reseller')->first();

        if (! $agentTier || ! $resellerTier) {
            return;
        }

        $products = Product::where('product_type', Product::TYPE_PREPAID)->get();

        foreach ($products as $product) {
            $base = $product->base_price_cents ? $product->base_price_cents->toCents() : 0;
            $fee = $product->admin_fee_cents ? $product->admin_fee_cents->toCents() : 0;
            $endUserPrice = $base + $fee;

            // Harga Agen: diskon Rp 300,00 (30.000 cents) dari harga normal
            $agentPrice = max($base, $endUserPrice - 30000);

            // Harga Reseller: diskon Rp 600,00 (60.000 cents) dari harga normal
            $resellerPrice = max($base, $endUserPrice - 60000);

            ProductTierPrice::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'user_tier_id' => $agentTier->id,
                ],
                [
                    'sell_price_cents' => $agentPrice,
                ]
            );

            ProductTierPrice::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'user_tier_id' => $resellerTier->id,
                ],
                [
                    'sell_price_cents' => $resellerPrice,
                ]
            );
        }
    }
}
