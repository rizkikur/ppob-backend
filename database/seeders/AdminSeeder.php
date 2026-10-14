<?php

namespace Database\Seeders;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => 'superadmin@ppob.test'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password123'),
                'role' => AdminRole::SUPER_ADMIN,
                'is_active' => true,
            ]
        );

        Admin::firstOrCreate(
            ['email' => 'ops@ppob.test'],
            [
                'name' => 'Operations Admin',
                'password' => Hash::make('password123'),
                'role' => AdminRole::OPS_ADMIN,
                'is_active' => true,
            ]
        );

        Admin::firstOrCreate(
            ['email' => 'finance@ppob.test'],
            [
                'name' => 'Finance Admin',
                'password' => Hash::make('password123'),
                'role' => AdminRole::FINANCE_ADMIN,
                'is_active' => true,
            ]
        );
    }
}
