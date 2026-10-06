<?php

namespace Database\Seeders;

use App\Domain\Product\Models\Provider;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Telkomsel',
                'code' => 'telkomsel',
                'driver' => 'TelkomselDriver',
                'queue_name' => 'supplier_telkomsel',
                'max_workers' => 20,
            ],
            [
                'name' => 'Indosat',
                'code' => 'indosat',
                'driver' => 'IndosatDriver',
                'queue_name' => 'supplier_indosat',
                'max_workers' => 15,
            ],
            [
                'name' => 'XL Axiata',
                'code' => 'xl',
                'driver' => 'XlDriver',
                'queue_name' => 'supplier_xl',
                'max_workers' => 15,
            ],
            [
                'name' => 'PLN',
                'code' => 'pln',
                'driver' => 'PlnDriver',
                'queue_name' => 'supplier_pln',
                'max_workers' => 20,
            ],
            [
                'name' => 'PDAM',
                'code' => 'pdam',
                'driver' => 'PdamDriver',
                'queue_name' => 'supplier_pdam',
                'max_workers' => 10,
            ],
        ];

        foreach ($providers as $p) {
            Provider::firstOrCreate(['code' => $p['code']], $p);
        }
    }
}
