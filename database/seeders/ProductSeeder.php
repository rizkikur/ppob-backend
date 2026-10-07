<?php

namespace Database\Seeders;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductCategory;
use App\Domain\Product\Models\Provider;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $catPulsa = ProductCategory::where('code', 'pulsa')->first();
        $catData = ProductCategory::where('code', 'paket_data')->first();
        $catPlnPrepaid = ProductCategory::where('code', 'token_listrik')->first();
        $catPlnPostpaid = ProductCategory::where('code', 'tagihan_listrik')->first();
        $catPdam = ProductCategory::where('code', 'pdam')->first();

        $provTsel = Provider::where('code', 'telkomsel')->first();
        $provIsat = Provider::where('code', 'indosat')->first();
        $provXl = Provider::where('code', 'xl')->first();
        $provPln = Provider::where('code', 'pln')->first();
        $provPdam = Provider::where('code', 'pdam')->first();

        $products = [
            // ─── Pulsa Telkomsel ──────────────────────────────────────
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provTsel?->id,
                'sku_code' => 'TSEL5K',
                'name' => 'Telkomsel Pulsa 5.000',
                'description' => 'Pulsa reguler Telkomsel Rp 5.000 masa aktif 7 hari',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 510000, // Rp 5.100,00
                'admin_fee_cents' => 40000,  // Rp 400,00 -> Total Rp 5.500,00
                'is_active' => true,
            ],
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provTsel?->id,
                'sku_code' => 'TSEL10K',
                'name' => 'Telkomsel Pulsa 10.000',
                'description' => 'Pulsa reguler Telkomsel Rp 10.000 masa aktif 15 hari',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 1010000, // Rp 10.100,00
                'admin_fee_cents' => 65000,   // Rp 650,00 -> Total Rp 10.750,00
                'is_active' => true,
            ],
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provTsel?->id,
                'sku_code' => 'TSEL20K',
                'name' => 'Telkomsel Pulsa 20.000',
                'description' => 'Pulsa reguler Telkomsel Rp 20.000 masa aktif 30 hari',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 2000000,
                'admin_fee_cents' => 150000, // Total Rp 21.500,00
                'is_active' => true,
            ],
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provTsel?->id,
                'sku_code' => 'TSEL50K',
                'name' => 'Telkomsel Pulsa 50.000',
                'description' => 'Pulsa reguler Telkomsel Rp 50.000 masa aktif 45 hari',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 4950000,
                'admin_fee_cents' => 150000, // Total Rp 51.000,00
                'is_active' => true,
            ],
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provTsel?->id,
                'sku_code' => 'TSEL100K',
                'name' => 'Telkomsel Pulsa 100.000',
                'description' => 'Pulsa reguler Telkomsel Rp 100.000 masa aktif 60 hari',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 9850000,
                'admin_fee_cents' => 200000, // Total Rp 100.500,00
                'is_active' => true,
            ],

            // ─── Pulsa Indosat ────────────────────────────────────────
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provIsat?->id,
                'sku_code' => 'ISAT10K',
                'name' => 'Indosat Pulsa 10.000',
                'description' => 'Pulsa reguler Indosat Ooredoo Rp 10.000',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 1015000,
                'admin_fee_cents' => 60000,
                'is_active' => true,
            ],
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provIsat?->id,
                'sku_code' => 'ISAT25K',
                'name' => 'Indosat Pulsa 25.000',
                'description' => 'Pulsa reguler Indosat Ooredoo Rp 25.000',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 2480000,
                'admin_fee_cents' => 120000,
                'is_active' => true,
            ],
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provIsat?->id,
                'sku_code' => 'ISAT50K',
                'name' => 'Indosat Pulsa 50.000',
                'description' => 'Pulsa reguler Indosat Ooredoo Rp 50.000',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 4940000,
                'admin_fee_cents' => 150000,
                'is_active' => true,
            ],

            // ─── Pulsa XL Axiata ──────────────────────────────────────
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provXl?->id,
                'sku_code' => 'XL10K',
                'name' => 'XL Pulsa 10.000',
                'description' => 'Pulsa reguler XL Rp 10.000',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 1010000,
                'admin_fee_cents' => 65000,
                'is_active' => true,
            ],
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provXl?->id,
                'sku_code' => 'XL25K',
                'name' => 'XL Pulsa 25.000',
                'description' => 'Pulsa reguler XL Rp 25.000',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 2475000,
                'admin_fee_cents' => 125000,
                'is_active' => true,
            ],
            [
                'category_id' => $catPulsa?->id,
                'provider_id' => $provXl?->id,
                'sku_code' => 'XL50K',
                'name' => 'XL Pulsa 50.000',
                'description' => 'Pulsa reguler XL Rp 50.000',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 4950000,
                'admin_fee_cents' => 150000,
                'is_active' => true,
            ],

            // ─── Paket Data Telkomsel ─────────────────────────────────
            [
                'category_id' => $catData?->id,
                'provider_id' => $provTsel?->id,
                'sku_code' => 'DATA_TSEL_10GB',
                'name' => 'Telkomsel Data 10GB 30 Hari',
                'description' => 'Paket data internet kuota utama 10GB 24 jam',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 4500000,
                'admin_fee_cents' => 250000, // Rp 47.500,00
                'is_active' => true,
            ],
            [
                'category_id' => $catData?->id,
                'provider_id' => $provTsel?->id,
                'sku_code' => 'DATA_TSEL_25GB',
                'name' => 'Telkomsel Data 25GB 30 Hari',
                'description' => 'Paket data internet kuota utama 25GB 24 jam',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 8500000,
                'admin_fee_cents' => 350000, // Rp 88.500,00
                'is_active' => true,
            ],

            // ─── PLN Token Listrik (Prepaid) ──────────────────────────
            [
                'category_id' => $catPlnPrepaid?->id,
                'provider_id' => $provPln?->id,
                'sku_code' => 'PLN20',
                'name' => 'PLN Token Listrik 20.000',
                'description' => 'Token prabayar PLN 20K',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 2000000,
                'admin_fee_cents' => 250000, // Rp 22.500,00
                'is_active' => true,
            ],
            [
                'category_id' => $catPlnPrepaid?->id,
                'provider_id' => $provPln?->id,
                'sku_code' => 'PLN50',
                'name' => 'PLN Token Listrik 50.000',
                'description' => 'Token prabayar PLN 50K',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 5000000,
                'admin_fee_cents' => 250000, // Rp 52.500,00
                'is_active' => true,
            ],
            [
                'category_id' => $catPlnPrepaid?->id,
                'provider_id' => $provPln?->id,
                'sku_code' => 'PLN100',
                'name' => 'PLN Token Listrik 100.000',
                'description' => 'Token prabayar PLN 100K',
                'product_type' => Product::TYPE_PREPAID,
                'base_price_cents' => 10000000,
                'admin_fee_cents' => 250000, // Rp 102.500,00
                'is_active' => true,
            ],

            // ─── PLN Tagihan Listrik (Postpaid) ───────────────────────
            [
                'category_id' => $catPlnPostpaid?->id,
                'provider_id' => $provPln?->id,
                'sku_code' => 'PLNPOST',
                'name' => 'PLN Pasca Bayar (Tagihan)',
                'description' => 'Pembayaran tagihan listrik bulanan PLN',
                'product_type' => Product::TYPE_POSTPAID,
                'base_price_cents' => 0,
                'admin_fee_cents' => 250000, // Biaya admin Rp 2.500,00
                'is_active' => true,
            ],

            // ─── PDAM (Postpaid) ──────────────────────────────────────
            [
                'category_id' => $catPdam?->id,
                'provider_id' => $provPdam?->id,
                'sku_code' => 'PDAM_KOTA',
                'name' => 'PDAM Tagihan Air',
                'description' => 'Pembayaran tagihan air PDAM',
                'product_type' => Product::TYPE_POSTPAID,
                'base_price_cents' => 0,
                'admin_fee_cents' => 250000, // Biaya admin Rp 2.500,00
                'is_active' => true,
            ],
        ];

        foreach ($products as $prod) {
            Product::updateOrCreate(
                ['sku_code' => $prod['sku_code']],
                $prod
            );
        }
    }
}
