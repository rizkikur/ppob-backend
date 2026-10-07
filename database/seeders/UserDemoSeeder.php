<?php

namespace Database\Seeders;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserDemoSeeder extends Seeder
{
    public function __construct(private readonly WalletService $walletService) {}

    public function run(): void
    {
        $tierEndUser = UserTier::where('name', 'end_user')->first();
        $tierAgent = UserTier::where('name', 'agent')->first();
        $tierReseller = UserTier::where('name', 'reseller')->first();

        $demoUsers = [
            [
                'email' => 'admin@ppob.internal',
                'name' => 'Administrator Demo',
                'phone' => '081100000001',
                'role' => 'admin',
                'user_tier_id' => $tierEndUser?->id,
                'initial_balance_cents' => 1000000000, // Rp 10.000.000,00
            ],
            [
                'email' => 'agent@demo.com',
                'name' => 'Agen Mitra Retail',
                'phone' => '081200000002',
                'role' => 'user',
                'user_tier_id' => $tierAgent?->id,
                'initial_balance_cents' => 250000000, // Rp 2.500.000,00
            ],
            [
                'email' => 'reseller@demo.com',
                'name' => 'Reseller Grosir Pulsa',
                'phone' => '081300000003',
                'role' => 'user',
                'user_tier_id' => $tierReseller?->id,
                'initial_balance_cents' => 500000000, // Rp 5.000.000,00
            ],
            [
                'email' => 'user@demo.com',
                'name' => 'Budi Pelanggan Setia',
                'phone' => '081400000004',
                'role' => 'user',
                'user_tier_id' => $tierEndUser?->id,
                'initial_balance_cents' => 50000000, // Rp 500.000,00
            ],
        ];

        foreach ($demoUsers as $data) {
            $initialBalance = $data['initial_balance_cents'];
            unset($data['initial_balance_cents']);

            /** @var User $user */
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'pin_hash' => Hash::make('123456'),
                    'is_active' => true,
                    'is_verified' => true,
                ])
            );

            // Inisialisasi saldo jika wallet belum punya mutasi
            $wallet = $user->wallet;
            if (! $wallet || $wallet->balance_cents->toCents() === 0) {
                $this->walletService->credit(
                    $user,
                    Money::fromCents($initialBalance),
                    WalletMutation::REF_TOPUP,
                    null,
                    'Deposit saldo awal demo account'
                );
            }
        }
    }
}
