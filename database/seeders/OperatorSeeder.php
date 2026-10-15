<?php

namespace Database\Seeders;

use App\Domain\Product\Models\Operator;
use App\Domain\Product\Models\ProductCategory;
use Illuminate\Database\Seeder;

class OperatorSeeder extends Seeder
{
    public function run(): void
    {
        $pulsaCategory = ProductCategory::where('slug', 'pulsa')->first();
        $emoneyCategory = ProductCategory::where('slug', 'e-money')->first();

        $operators = [
            [
                'code' => 'TELKOMSEL',
                'name' => 'Telkomsel',
                'category_id' => $pulsaCategory?->id,
                'prefix_rules' => ['0811', '0812', '0813', '0821', '0822', '0823', '0852', '0853', '0851'],
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'INDOSAT',
                'name' => 'Indosat Ooredoo',
                'category_id' => $pulsaCategory?->id,
                'prefix_rules' => ['0814', '0815', '0816', '0855', '0856', '0857', '0858'],
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'XL',
                'name' => 'XL Axiata',
                'category_id' => $pulsaCategory?->id,
                'prefix_rules' => ['0817', '0818', '0819', '0859', '0877', '0878'],
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'code' => 'TRI',
                'name' => 'Tri (3)',
                'category_id' => $pulsaCategory?->id,
                'prefix_rules' => ['0895', '0896', '0897', '0898', '0899'],
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'code' => 'SMARTFREN',
                'name' => 'Smartfren',
                'category_id' => $pulsaCategory?->id,
                'prefix_rules' => ['0881', '0882', '0883', '0884', '0885', '0886', '0887', '0888', '0889'],
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'code' => 'DANA',
                'name' => 'DANA E-Wallet',
                'category_id' => $emoneyCategory?->id,
                'prefix_rules' => ['0811', '0812', '0852', '0857'],
                'sort_order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($operators as $item) {
            Operator::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
