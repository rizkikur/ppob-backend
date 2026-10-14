<?php

namespace Database\Seeders;

use App\Domain\Auth\Models\User;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TransactionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all()->keyBy('email');
        $adminUser = $users->get('admin@ppob.internal') ?? User::first();
        $agentUser = $users->get('agent@demo.com') ?? $adminUser;
        $resellerUser = $users->get('reseller@demo.com') ?? $adminUser;
        $budiUser = $users->get('user@demo.com') ?? $adminUser;

        $products = Product::with('provider')->get()->keyBy('sku_code');
        $providers = Provider::all()->keyBy('code');

        $tselProv = $providers->get('telkomsel');
        $isatProv = $providers->get('indosat');
        $plnProv = $providers->get('pln');

        $now = Carbon::now();

        $demoTrxs = [
            [
                'user_id' => $agentUser->id,
                'product' => $products->get('TSEL10K'),
                'customer_number' => '081234567890',
                'amount_cents' => 1010000,
                'sell_price_cents' => 1075000,
                'status' => Transaction::STATUS_SUCCESS,
                'idempotency_key' => 'DEMO-IDEMP-' . Str::random(12),
                'provider_ref' => 'REF-TSEL-881920',
                'provider_response' => [
                    'sn' => '1029384756201928374',
                    'message' => 'Transaksi pulsa 10.000 sukses',
                    'rc' => '00',
                ],
                'supplier_id' => $tselProv?->id,
                'original_supplier_id' => $tselProv?->id,
                'is_failover' => false,
                'created_at' => $now->copy()->subMinutes(12),
                'updated_at' => $now->copy()->subMinutes(12)->addSeconds(4),
            ],
            [
                'user_id' => $resellerUser->id,
                'product' => $products->get('PLN50'),
                'customer_number' => '14234567890',
                'amount_cents' => 5000000,
                'sell_price_cents' => 5250000,
                'status' => Transaction::STATUS_SUCCESS,
                'idempotency_key' => 'DEMO-IDEMP-' . Str::random(12),
                'provider_ref' => 'REF-PLN-994812',
                'provider_response' => [
                    'sn' => '4521-8890-1234-9901-2311',
                    'message' => 'Token PLN 50K berhasil diterbitkan',
                    'kwh' => '33.2',
                    'rc' => '00',
                ],
                'supplier_id' => $plnProv?->id,
                'original_supplier_id' => $plnProv?->id,
                'is_failover' => false,
                'created_at' => $now->copy()->subMinutes(35),
                'updated_at' => $now->copy()->subMinutes(35)->addSeconds(6),
            ],
            [
                'user_id' => $budiUser->id,
                'product' => $products->get('DATA_TSEL_10GB') ?? $products->first(),
                'customer_number' => '081298765432',
                'amount_cents' => 4500000,
                'sell_price_cents' => 4750000,
                'status' => Transaction::STATUS_SUCCESS,
                'idempotency_key' => 'DEMO-IDEMP-' . Str::random(12),
                'provider_ref' => 'REF-DATA-550192',
                'provider_response' => [
                    'sn' => 'TRX-DS-9912048',
                    'message' => 'Paket data Telkomsel 10GB aktif',
                    'rc' => '00',
                ],
                'supplier_id' => $tselProv?->id,
                'original_supplier_id' => $tselProv?->id,
                'is_failover' => false,
                'created_at' => $now->copy()->subHours(1)->subMinutes(5),
                'updated_at' => $now->copy()->subHours(1)->subMinutes(5)->addSeconds(8),
            ],
            [
                'user_id' => $agentUser->id,
                'product' => $products->get('ISAT25K') ?? $products->first(),
                'customer_number' => '085712345678',
                'amount_cents' => 2480000,
                'sell_price_cents' => 2600000,
                'status' => Transaction::STATUS_SUCCESS,
                'idempotency_key' => 'DEMO-IDEMP-' . Str::random(12),
                'provider_ref' => 'REF-ISAT-FAILOVER-3921',
                'provider_response' => [
                    'sn' => 'SN-ISAT-881290234',
                    'message' => 'Pengisian pulsa via rute alternatif sukses',
                    'rc' => '00',
                ],
                'supplier_id' => $isatProv?->id,
                'original_supplier_id' => $tselProv?->id,
                'is_failover' => true,
                'created_at' => $now->copy()->subHours(2)->subMinutes(15),
                'updated_at' => $now->copy()->subHours(2)->subMinutes(15)->addSeconds(14),
            ],
            [
                'user_id' => $agentUser->id,
                'product' => $products->get('TSEL50K') ?? $products->first(),
                'customer_number' => '081211223344',
                'amount_cents' => 4950000,
                'sell_price_cents' => 5100000,
                'status' => Transaction::STATUS_PENDING,
                'idempotency_key' => 'DEMO-IDEMP-' . Str::random(12),
                'provider_ref' => null,
                'provider_response' => null,
                'supplier_id' => $tselProv?->id,
                'original_supplier_id' => $tselProv?->id,
                'is_failover' => false,
                'created_at' => $now->copy()->subSeconds(45),
                'updated_at' => $now->copy()->subSeconds(45),
            ],
            [
                'user_id' => $resellerUser->id,
                'product' => $products->get('PLN100') ?? $products->first(),
                'customer_number' => '32019928341',
                'amount_cents' => 10000000,
                'sell_price_cents' => 10250000,
                'status' => Transaction::STATUS_PROCESSING,
                'idempotency_key' => 'DEMO-IDEMP-' . Str::random(12),
                'provider_ref' => 'REF-PLN-PROC-7712',
                'provider_response' => [
                    'message' => 'Sedang diproses oleh switching PLN',
                    'rc' => '03',
                ],
                'supplier_id' => $plnProv?->id,
                'original_supplier_id' => $plnProv?->id,
                'is_failover' => false,
                'created_at' => $now->copy()->subMinutes(3),
                'updated_at' => $now->copy()->subMinutes(3),
            ],
            [
                'user_id' => $resellerUser->id,
                'product' => $products->get('TSEL20K') ?? $products->first(),
                'customer_number' => '081299998888',
                'amount_cents' => 2000000,
                'sell_price_cents' => 2150000,
                'status' => Transaction::STATUS_FAILED,
                'idempotency_key' => 'DEMO-IDEMP-' . Str::random(12),
                'provider_ref' => 'REF-TSEL-ERR-4412',
                'provider_response' => [
                    'error' => 'Nomor tujuan berada dalam masa tenggang',
                    'rc' => '41',
                ],
                'failure_reason' => 'Nomor tujuan berada dalam masa tenggang',
                'supplier_id' => $tselProv?->id,
                'original_supplier_id' => $tselProv?->id,
                'is_failover' => false,
                'created_at' => $now->copy()->subHours(4),
                'updated_at' => $now->copy()->subHours(4)->addSeconds(5),
            ],
            [
                'user_id' => $budiUser->id,
                'product' => $products->get('ISAT10K') ?? $products->first(),
                'customer_number' => '085600001111',
                'amount_cents' => 1015000,
                'sell_price_cents' => 1075000,
                'status' => Transaction::STATUS_FAILED,
                'idempotency_key' => 'DEMO-IDEMP-' . Str::random(12),
                'provider_ref' => 'REF-ISAT-TO-9921',
                'provider_response' => [
                    'error' => 'Cut-off provider',
                    'rc' => '68',
                ],
                'failure_reason' => 'Provider timeout (Cut-off maintenance)',
                'supplier_id' => $isatProv?->id,
                'original_supplier_id' => $isatProv?->id,
                'is_failover' => false,
                'created_at' => $now->copy()->subHours(6),
                'updated_at' => $now->copy()->subHours(6)->addSeconds(12),
            ],
            [
                'user_id' => $resellerUser->id,
                'product' => $products->get('PLN20') ?? $products->first(),
                'customer_number' => '11029384756',
                'amount_cents' => 2000000,
                'sell_price_cents' => 2250000,
                'status' => Transaction::STATUS_REFUNDED,
                'idempotency_key' => 'DEMO-IDEMP-' . Str::random(12),
                'provider_ref' => 'REF-PLN-REF-1002',
                'provider_response' => [
                    'refund_id' => 'RFD-990182',
                    'note' => 'Refund balance issued to wallet',
                ],
                'failure_reason' => 'Transaksi gagal dari provider, dana dikembalikan ke saldo wallet',
                'supplier_id' => $plnProv?->id,
                'original_supplier_id' => $plnProv?->id,
                'is_failover' => false,
                'created_at' => $now->copy()->subDay(),
                'updated_at' => $now->copy()->subDay()->addMinutes(10),
            ],
            [
                'user_id' => $agentUser->id,
                'product' => $products->get('TSEL100K') ?? $products->first(),
                'customer_number' => '081199887766',
                'amount_cents' => 9850000,
                'sell_price_cents' => 10050000,
                'status' => Transaction::STATUS_SUCCESS,
                'idempotency_key' => 'DEMO-IDEMP-' . Str::random(12),
                'provider_ref' => 'REF-TSEL-VIP-5541',
                'provider_response' => [
                    'sn' => '099123847510293847',
                    'message' => 'Pulsa 100K sukses terkirim',
                    'rc' => '00',
                ],
                'supplier_id' => $tselProv?->id,
                'original_supplier_id' => $tselProv?->id,
                'is_failover' => false,
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2)->addSeconds(3),
            ],
        ];

        foreach ($demoTrxs as $trxData) {
            $product = $trxData['product'];
            if (!$product) {
                continue;
            }

            Transaction::create([
                'user_id' => $trxData['user_id'],
                'product_id' => $product->id,
                'inquiry_id' => null,
                'customer_number' => $trxData['customer_number'],
                'amount_cents' => $trxData['amount_cents'],
                'sell_price_cents' => $trxData['sell_price_cents'],
                'status' => $trxData['status'],
                'idempotency_key' => $trxData['idempotency_key'],
                'provider_ref' => $trxData['provider_ref'],
                'provider_response' => $trxData['provider_response'],
                'failure_reason' => $trxData['failure_reason'] ?? null,
                'supplier_id' => $trxData['supplier_id'],
                'original_supplier_id' => $trxData['original_supplier_id'],
                'is_failover' => $trxData['is_failover'],
                'created_at' => $trxData['created_at'],
                'updated_at' => $trxData['updated_at'],
            ]);
        }
    }
}
