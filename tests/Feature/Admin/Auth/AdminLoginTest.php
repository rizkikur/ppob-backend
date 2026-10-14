<?php

namespace Tests\Feature\Admin\Auth;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private Admin $activeAdmin;

    private Admin $inactiveAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activeAdmin = Admin::create([
            'name' => 'Super Administrator',
            'email' => 'admin@ppob.test',
            'password' => Hash::make('secret123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->inactiveAdmin = Admin::create([
            'name' => 'Nonaktif Admin',
            'email' => 'disabled@ppob.test',
            'password' => Hash::make('secret123'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => false,
        ]);
    }

    public function test_can_render_login_page(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('Backoffice Portal');
        $response->assertSee('Alamat Email');
        $response->assertSee('Password');
    }

    public function test_active_admin_can_login_with_correct_credentials(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@ppob.test',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->activeAdmin, 'admin');

        $this->activeAdmin->refresh();
        $this->assertNotNull($this->activeAdmin->last_login_at);
        $this->assertNotNull($this->activeAdmin->last_login_ip);
    }

    public function test_inactive_admin_cannot_login(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'disabled@ppob.test',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('admin');
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@ppob.test',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('admin');
    }

    public function test_admin_session_is_regenerated_after_successful_login(): void
    {
        $this->get('/admin/login');
        $initialSessionId = session()->getId();

        $this->post('/admin/login', [
            'email' => 'admin@ppob.test',
            'password' => 'secret123',
        ]);

        $newSessionId = session()->getId();
        $this->assertNotEquals($initialSessionId, $newSessionId);
    }

    public function test_admin_can_logout_and_session_is_invalidated(): void
    {
        $this->actingAs($this->activeAdmin, 'admin');

        $response = $this->post('/admin/logout');

        $response->assertRedirect('/admin/login');
        $this->assertGuest('admin');

        $auditLog = AdminAuditLog::where('action', 'admin.logout')
            ->where('admin_id', $this->activeAdmin->id)
            ->first();

        $this->assertNotNull($auditLog);
    }

    public function test_guest_accessing_admin_root_redirects_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_authenticated_admin_accessing_admin_root_redirects_to_dashboard(): void
    {
        $response = $this->actingAs($this->activeAdmin, 'admin')->get('/admin');

        $response->assertRedirect('/admin/dashboard');
    }

    public function test_login_ignores_external_intended_url_and_redirects_to_admin_dashboard(): void
    {
        session(['url.intended' => 'http://localhost:8000/docs']);

        $response = $this->post('/admin/login', [
            'email' => 'admin@ppob.test',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/admin/dashboard');
    }
}
