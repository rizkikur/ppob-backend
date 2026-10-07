<?php

namespace Database\Seeders;

use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\PartnerProductPrice;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Database\Seeder;

class PartnerDemoSeeder extends Seeder
{
    public function __construct(private readonly WalletService $walletService) {}

    public function run(): void
    {
        $partners = [
            [
                'name' => 'PT Mitra Finansial Solusindo',
                'api_key' => 'partner_live_demo123',
                'secret' => encrypt('partner_secret_demo456'),
                'allowed_ips' => null,
                'is_active' => true,
                'rate_limit_rpm' => 120,
                'initial_deposit_cents' => 1500000000, // Rp 15.000.000,00
                'custom_prices' => [
                    'TSEL20K' => 2020000, // Rp 20.200,00
                    'TSEL50K' => 4980000, // Rp 49.800,00
                    'PLN50' => 5020000,   // Rp 50.200,00
                ],
            ],
            [
                'name' => 'Koperasi Digital Mandiri',
                'api_key' => 'partner_live_koperasi',
                'secret' => encrypt('partner_secret_koperasi'),
                'allowed_ips' => null,
                'is_active' => true,
                'rate_limit_rpm' => 60,
                'initial_deposit_cents' => 500000000, // Rp 5.000.000,00
                'custom_prices' => [
                    'TSEL10K' => 1025000, // Rp 10.250,00
                    'PLN20' => 2020000,   // Rp 20.200,00
                ],
            ],
        ];

        foreach ($partners as $item) {
            $initialDeposit = $item['initial_deposit_cents'];
            $customPrices = $item['custom_prices'];
            unset($item['initial_deposit_cents'], $item['custom_prices']);

            /** @var Partner $partner */
            $partner = Partner::updateOrCreate(
                ['api_key' => $item['api_key']],
                $item
            );

            // Inisialisasi dompet deposit mitra
            $partnerUser = $partner->getOrCreateUser();
            $wallet = $partnerUser->wallet;

            if (! $wallet || $wallet->balance_cents->toCents() === 0) {
                $this->walletService->credit(
                    $partnerUser,
                    Money::fromCents($initialDeposit),
                    WalletMutation::REF_TOPUP,
                    null,
                    "Deposit saldo awal partner {$partner->name}"
                );
            }

            // Inisialisasi custom partner product pricing (ADR-006)
            foreach ($customPrices as $sku => $priceCents) {
                $product = Product::where('sku_code', $sku)->first();
                if ($product) {
                    PartnerProductPrice::updateOrCreate(
                        [
                            'partner_id' => $partner->id,
                            'product_id' => $product->id,
                        ],
                        [
                            'sell_price_cents' => $priceCents,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
