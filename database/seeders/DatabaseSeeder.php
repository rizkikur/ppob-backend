<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserTierSeeder::class,
            ProductCategorySeeder::class,
            OperatorSeeder::class,
            ProviderSeeder::class,
            MLinkProviderSeeder::class,
            ProductSeeder::class,
            ProductTierPriceSeeder::class,
            UserDemoSeeder::class,
            PartnerDemoSeeder::class,
            TransactionDemoSeeder::class,
        ]);
    }
}
