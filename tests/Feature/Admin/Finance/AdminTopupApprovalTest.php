<?php

namespace Tests\Feature\Admin\Finance;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Wallet\Models\TopupRequest;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTopupApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Admin $financeAdmin;
    private Admin $opsAdmin;
    private User $user;
    private TopupRequest $topup;

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

        $this->opsAdmin = Admin::create([
            'name' => 'Ops Officer',
            'email' => 'ops@ppob.test',
            'password' => Hash::make('password123'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $tier = \App\Domain\Auth\Models\UserTier::firstOrCreate(
            ['name' => 'regular'],
            ['label' => 'Regular', 'description' => 'Pengguna Regular']
        );

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Pelanggan Setia',
            'email' => 'user@ppob.test',
            'phone' => '08123456789',
            'password' => Hash::make('secret'),
            'role' => 'user',
            'is_active' => true,
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 10000000, // 100.000 IDR
        ]);

        $this->topup = TopupRequest::create([
            'user_id' => $this->user->id,
            'amount_cents' => 25000000, // 250.000 IDR
            'method' => 'manual_transfer',
            'payment_gateway' => 'bca_manual',
            'status' => TopupRequest::STATUS_PENDING,
        ]);
    }

    public function test_ops_admin_cannot_access_finance_topups(): void
    {
        $this->actingAs($this->opsAdmin, 'admin');

        $response = $this->get('/admin/finance/topups');
        $response->assertForbidden();
    }

    public function test_finance_admin_can_view_topup_requests_and_metrics(): void
    {
        $this->actingAs($this->financeAdmin, 'admin');

        $response = $this->get('/admin/finance/topups');
        $response->assertOk()
            ->assertSee('Pelanggan Setia')
            ->assertSee('250.000');
    }

    public function test_approving_topup_requires_password_confirmation(): void
    {
        $this->actingAs($this->financeAdmin, 'admin');

        $response = $this->post("/admin/finance/topups/{$this->topup->id}/approve");
        $response->assertRedirect('/admin/confirm-password');
    }

    public function test_confirmed_admin_can_approve_topup_and_credits_wallet(): void
    {
        $this->actingAs($this->financeAdmin, 'admin');

        // Confirm password
        $this->post('/admin/confirm-password', ['password' => 'password123']);

        $response = $this->post("/admin/finance/topups/{$this->topup->id}/approve", [
            'notes' => 'Transfer mutasi BCA telah sesuai nominal',
        ]);

        $response->assertSessionHas('success');

        $this->topup->refresh();
        $this->assertEquals(TopupRequest::STATUS_PAID, $this->topup->status);
        $this->assertEquals($this->financeAdmin->id, $this->topup->confirmed_by);
        $this->assertNotNull($this->topup->paid_at);

        // Wallet credited: 100.000 + 250.000 = 350.000 IDR (35.000.000 cents)
        $this->assertEquals(35000000, $this->user->wallet->fresh()->balance_cents->toCents());

        // Audit log created
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->financeAdmin->id,
            'action' => 'finance.topup_approved',
            'target_id' => (string) $this->topup->id,
        ]);
    }

    public function test_confirmed_admin_can_reject_topup_with_reason(): void
    {
        $this->actingAs($this->financeAdmin, 'admin');

        $this->post('/admin/confirm-password', ['password' => 'password123']);

        $response = $this->post("/admin/finance/topups/{$this->topup->id}/reject", [
            'reason' => 'Bukti struk palsu dan tidak ada mutasi bank masuk',
        ]);

        $response->assertSessionHas('success');

        $this->topup->refresh();
        $this->assertEquals(TopupRequest::STATUS_FAILED, $this->topup->status);
        $this->assertEquals($this->financeAdmin->id, $this->topup->confirmed_by);

        // Wallet saldo tetap tidak berubah
        $this->assertEquals(10000000, $this->user->wallet->fresh()->balance_cents->toCents());

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->financeAdmin->id,
            'action' => 'finance.topup_rejected',
            'reason' => 'Penolakan tiket topup #'.$this->topup->id.': Bukti struk palsu dan tidak ada mutasi bank masuk',
        ]);
    }
}
