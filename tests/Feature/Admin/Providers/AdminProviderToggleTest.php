<?php

namespace Tests\Feature\Admin\Providers;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Product\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProviderToggleTest extends TestCase
{
    use RefreshDatabase;

    private Admin $opsAdmin;
    private Admin $financeAdmin;
    private Admin $superAdmin;
    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->opsAdmin = Admin::create([
            'name' => 'Ops Officer',
            'email' => 'ops@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $this->financeAdmin = Admin::create([
            'name' => 'Finance Officer',
            'email' => 'finance@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::FINANCE_ADMIN,
            'is_active' => true,
        ]);

        $this->superAdmin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->provider = Provider::create([
            'name' => 'IAK Upstream',
            'code' => 'IAK',
            'driver' => 'IakDriver',
            'priority' => 2,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_toggle_provider(): void
    {
        $response = $this->post("/admin/providers/{$this->provider->id}/toggle", [
            'is_active' => 0,
            'reason' => 'Emergency shutdown upstream',
        ]);

        $response->assertRedirect('/admin/login');
        $this->assertTrue($this->provider->fresh()->is_active);
    }

    public function test_toggle_validation_requires_reason_min_10_characters(): void
    {
        $this->actingAs($this->opsAdmin, 'admin');

        $response = $this->post("/admin/providers/{$this->provider->id}/toggle", [
            'is_active' => 0,
            'reason' => 'Pendek',
        ]);

        $response->assertSessionHasErrors(['reason']);
        $this->assertTrue($this->provider->fresh()->is_active);
    }

    public function test_ops_admin_can_toggle_provider_and_it_records_audit_and_invalidates_cache(): void
    {
        $this->actingAs($this->opsAdmin, 'admin');

        Cache::put('providers:active', ['dummy'], 60);

        $response = $this->post("/admin/providers/{$this->provider->id}/toggle", [
            'is_active' => 0,
            'reason' => 'Emergency maintenance provider IAK dari upstream',
        ]);

        $response->assertSessionHas('success');
        $this->assertFalse($this->provider->fresh()->is_active);

        // Audit log verified
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->opsAdmin->id,
            'action' => 'provider.toggle_status',
            'target_type' => 'Provider',
            'target_id' => (string) $this->provider->id,
            'reason' => 'Emergency maintenance provider IAK dari upstream',
        ]);

        // Cache invalidated
        $this->assertFalse(Cache::has('providers:active'));
    }

    public function test_finance_and_super_admin_can_also_toggle_provider(): void
    {
        $this->provider->update(['is_active' => false]);

        // Finance admin can reactivate
        $this->actingAs($this->financeAdmin, 'admin');
        $response = $this->post("/admin/providers/{$this->provider->id}/toggle", [
            'is_active' => 1,
            'reason' => 'Maintenance selesai, diaktifkan kembali oleh finance',
        ]);
        $response->assertSessionHas('success');
        $this->assertTrue($this->provider->fresh()->is_active);

        // Super admin can deactivate
        $this->actingAs($this->superAdmin, 'admin');
        $response2 = $this->post("/admin/providers/{$this->provider->id}/toggle", [
            'is_active' => 0,
            'reason' => 'Pemeliharaan berkala dijadwalkan superadmin',
        ]);
        $response2->assertSessionHas('success');
        $this->assertFalse($this->provider->fresh()->is_active);
    }
}
