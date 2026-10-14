<?php

namespace Tests\Feature\Admin\Partners;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Partner\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminIpWhitelistTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Ops Officer',
            'email' => 'ops@ppob.test',
            'password' => Hash::make('password123'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $this->partner = Partner::create([
            'name' => 'PT Whitelist Test',
            'api_key' => 'pk_live_wl123',
            'secret' => encrypt('sk_live_wl123'),
            'allowed_ips' => ['103.1.1.1'],
            'is_active' => true,
            'rate_limit_rpm' => 60,
        ]);
    }

    public function test_admin_can_add_valid_ipv4_and_cidr_to_partner(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Add IPv4
        $response1 = $this->post("/admin/partners/{$this->partner->id}/ip-whitelist", [
            'ip' => '103.10.20.5',
        ]);
        $response1->assertSessionHas('success');

        // Add CIDR
        $response2 = $this->post("/admin/partners/{$this->partner->id}/ip-whitelist", [
            'ip' => '103.20.0.0/24',
        ]);
        $response2->assertSessionHas('success');

        $this->partner->refresh();
        $this->assertContains('103.1.1.1', $this->partner->allowed_ips);
        $this->assertContains('103.10.20.5', $this->partner->allowed_ips);
        $this->assertContains('103.20.0.0/24', $this->partner->allowed_ips);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'partner.ip_whitelist_added',
        ]);
    }

    public function test_invalid_ip_format_is_rejected(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post("/admin/partners/{$this->partner->id}/ip-whitelist", [
            'ip' => 'not-an-ip-address',
        ]);

        $response->assertSessionHasErrors('ip');
    }

    public function test_admin_can_remove_ip_from_whitelist(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->delete("/admin/partners/{$this->partner->id}/ip-whitelist/".urlencode('103.1.1.1'));
        $response->assertSessionHas('success');

        $this->partner->refresh();
        $this->assertEmpty($this->partner->allowed_ips ?? []);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'partner.ip_whitelist_removed',
        ]);
    }
}
