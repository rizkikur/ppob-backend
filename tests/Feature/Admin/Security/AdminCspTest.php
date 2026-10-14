<?php

namespace Tests\Feature\Admin\Security;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCspTest extends TestCase
{
    use RefreshDatabase;

    public function test_csp_header_allows_unsafe_eval_for_alpine_and_jsdelivr(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@ppob.test',
            'password' => bcrypt('password123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringContainsString("connect-src 'self' https://cdn.jsdelivr.net", $csp);
    }
}
