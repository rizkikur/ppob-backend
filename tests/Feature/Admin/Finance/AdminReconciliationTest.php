<?php

namespace Tests\Feature\Admin\Finance;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Product\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $financeAdmin;

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

        Provider::create([
            'name' => 'Digiflazz Upstream',
            'code' => 'DIGIFLAZZ',
            'driver' => 'DigiflazzDriver',
            'priority' => 1,
            'is_active' => true,
        ]);
    }

    public function test_finance_admin_can_view_provider_reconciliation_dashboard(): void
    {
        $this->actingAs($this->financeAdmin, 'admin');

        $response = $this->get('/admin/finance/reconciliation');
        $response->assertOk()
            ->assertSee('Digiflazz Upstream')
            ->assertSee('DIGIFLAZZ')
            ->assertSee('MATCH (Sesuai)');
    }
}
