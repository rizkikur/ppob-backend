<?php

namespace Tests\Feature\Admin\Finance;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminWalletAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private Admin $financeAdmin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financeAdmin = Admin::create([
            'name' => 'Finance Officer',
            'email' => 'finance@ppob.test',
            'password' => Hash::make('password123'),
            'role' => AdminRole::FINANCE_ADMIN,
            'is_active' => true,
        ]);

        $tier = \App\Domain\Auth\Models\UserTier::firstOrCreate(
            ['name' => 'regular'],
            ['label' => 'Regular', 'description' => 'Pengguna Regular']
        );

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Mitra Warung Digital',
            'email' => 'mitra@warung.test',
            'phone' => '087712345678',
            'password' => Hash::make('secret'),
            'role' => 'user',
            'is_active' => true,
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 50000000, // 500.000 IDR
        ]);
    }

    public function test_adjustment_requires_password_confirmation(): void
    {
        $this->actingAs($this->financeAdmin, 'admin');

        $response = $this->post('/admin/finance/adjustments', [
            'user_id' => $this->user->id,
            'type' => 'credit',
            'amount' => 100000,
            'reason' => 'Kompensasi gangguan jaringan pada transaksi TRX-999',
        ]);

        $response->assertRedirect('/admin/confirm-password');
    }

    public function test_short_reason_fails_validation(): void
    {
        $this->actingAs($this->financeAdmin, 'admin');

        $this->post('/admin/confirm-password', ['password' => 'password123']);

        $response = $this->post('/admin/finance/adjustments', [
            'user_id' => $this->user->id,
            'type' => 'credit',
            'amount' => 100000,
            'reason' => 'Pendek', // Less than 15 chars
        ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_confirmed_admin_can_credit_and_debit_balance(): void
    {
        $this->actingAs($this->financeAdmin, 'admin');

        $this->post('/admin/confirm-password', ['password' => 'password123']);

        // 1. Credit 200.000 IDR
        $resCredit = $this->post('/admin/finance/adjustments', [
            'user_id' => $this->user->id,
            'type' => 'credit',
            'amount' => 200000,
            'reason' => 'Kompensasi gangguan jaringan pada transaksi TRX-999',
        ]);
        $resCredit->assertSessionHas('success');

        // Balance: 500.000 + 200.000 = 700.000 IDR (70.000.000 cents)
        $this->assertEquals(70000000, $this->user->wallet->fresh()->balance_cents->toCents());

        // 2. Debit 150.000 IDR
        $resDebit = $this->post('/admin/finance/adjustments', [
            'user_id' => $this->user->id,
            'type' => 'debit',
            'amount' => 150000,
            'reason' => 'Koreksi kelebihan saldo transfer dobel dari invoice #INV-771',
        ]);
        $resDebit->assertSessionHas('success');

        // Balance: 700.000 - 150.000 = 550.000 IDR (55.000.000 cents)
        $this->assertEquals(55000000, $this->user->wallet->fresh()->balance_cents->toCents());

        // Audit log verified
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->financeAdmin->id,
            'action' => 'finance.balance_adjusted',
            'target_id' => (string) $this->user->id,
        ]);
    }
}
