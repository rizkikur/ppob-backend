<?php

namespace Database\Seeders;

use App\Domain\Product\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Pulsa',          'code' => 'pulsa',          'sort_order' => 1],
            ['name' => 'Paket Data',     'code' => 'paket_data',     'sort_order' => 2],
            ['name' => 'Token Listrik',  'code' => 'token_listrik',  'sort_order' => 3],
            ['name' => 'Tagihan Listrik', 'code' => 'tagihan_listrik', 'sort_order' => 4],
            ['name' => 'PDAM',           'code' => 'pdam',           'sort_order' => 5],
        ];
        foreach ($categories as $cat) {
            ProductCategory::firstOrCreate(['code' => $cat['code']], $cat);
        }
    }
}
