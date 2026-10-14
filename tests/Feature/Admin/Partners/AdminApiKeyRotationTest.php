<?php

namespace Tests\Feature\Admin\Partners;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Partner\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApiKeyRotationTest extends TestCase
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
            'password' => Hash::make('secretpass123'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);

        $this->partner = Partner::create([
            'name' => 'PT Mitra Api Test',
            'api_key' => 'pk_live_initial12345678',
            'secret' => encrypt('sk_live_initialsecret12345678'),
            'is_active' => true,
            'rate_limit_rpm' => 60,
        ]);
    }

    public function test_rotating_secret_requires_admin_password_confirmation(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Without password confirmation, it should redirect to confirm-password form
        $response = $this->post("/admin/partners/{$this->partner->id}/rotate-api-secret");
        $response->assertRedirect('/admin/confirm-password');
    }

    public function test_confirmed_admin_can_rotate_secret_and_receives_one_time_secret(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Confirm password first
        $this->post('/admin/confirm-password', [
            'password' => 'secretpass123',
        ])->assertSessionHasNoErrors();

        // Now post rotation request
        $response = $this->post("/admin/partners/{$this->partner->id}/rotate-api-secret", [
            'reason' => 'Kunci terindikasi bocor di log klien',
        ]);

        $response->assertSessionHas('newly_generated_secret');
        $newSecret = session('newly_generated_secret');

        $this->assertStringStartsWith('sk_live_', $newSecret);
        $this->assertEquals($newSecret, decrypt($this->partner->fresh()->secret));

        // Audit log created
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'partner.api_secret_rotated',
            'target_id' => (string) $this->partner->id,
            'reason' => 'Kunci terindikasi bocor di log klien',
        ]);
    }

    public function test_confirmed_admin_can_rotate_public_api_key(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Confirm password first
        $this->post('/admin/confirm-password', [
            'password' => 'secretpass123',
        ])->assertSessionHasNoErrors();

        $response = $this->post("/admin/partners/{$this->partner->id}/rotate-api-key", [
            'reason' => 'Migrasi endpoint H2H baru',
        ]);

        $response->assertSessionHas('success');
        $this->partner->refresh();

        $this->assertNotEquals('pk_live_initial12345678', $this->partner->api_key);
        $this->assertStringStartsWith('pk_live_', $this->partner->api_key);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'partner.api_key_rotated',
            'target_id' => (string) $this->partner->id,
            'reason' => 'Migrasi endpoint H2H baru',
        ]);
    }
}
