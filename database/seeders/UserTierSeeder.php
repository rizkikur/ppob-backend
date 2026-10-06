<?php

namespace Database\Seeders;

use App\Domain\Auth\Models\UserTier;
use Illuminate\Database\Seeder;

class UserTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['name' => 'end_user',  'label' => 'Pengguna Akhir', 'description' => 'Pelanggan regular aplikasi'],
            ['name' => 'agent',     'label' => 'Agen',           'description' => 'Agen dengan harga lebih murah dari end_user'],
            ['name' => 'reseller',  'label' => 'Reseller',       'description' => 'Reseller dengan harga paling murah'],
            ['name' => 'partner',   'label' => 'Mitra / Partner', 'description' => 'Tier mitra B2B/Open API'],
        ];
        foreach ($tiers as $tier) {
            UserTier::firstOrCreate(['name' => $tier['name']], $tier);
        }
    }
}
