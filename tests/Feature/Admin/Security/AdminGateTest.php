<?php

namespace Tests\Feature\Admin\Security;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminGateTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $financeAdmin;
    private Admin $opsAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->financeAdmin = Admin::create([
            'name' => 'Finance Admin',
            'email' => 'finance@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::FINANCE_ADMIN,
            'is_active' => true,
        ]);

        $this->opsAdmin = Admin::create([
            'name' => 'Ops Admin',
            'email' => 'ops@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_access_finance_gate(): void
    {
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('access-finance'));
        $this->assertTrue(Gate::forUser($this->financeAdmin)->allows('access-finance'));
        $this->assertFalse(Gate::forUser($this->opsAdmin)->allows('access-finance'));
    }

    public function test_manage_admins_gate(): void
    {
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('manage-admins'));
        $this->assertFalse(Gate::forUser($this->financeAdmin)->allows('manage-admins'));
        $this->assertFalse(Gate::forUser($this->opsAdmin)->allows('manage-admins'));
    }

    public function test_toggle_providers_gate_allows_all_roles(): void
    {
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('toggle-providers'));
        $this->assertTrue(Gate::forUser($this->financeAdmin)->allows('toggle-providers'));
        $this->assertTrue(Gate::forUser($this->opsAdmin)->allows('toggle-providers'));
    }

    public function test_refund_transactions_gate_allows_all_roles(): void
    {
        $this->assertTrue(Gate::forUser($this->superAdmin)->allows('refund-transactions'));
        $this->assertTrue(Gate::forUser($this->financeAdmin)->allows('refund-transactions'));
        $this->assertTrue(Gate::forUser($this->opsAdmin)->allows('refund-transactions'));
    }
}
