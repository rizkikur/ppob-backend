<?php

namespace Tests\Feature\Admin\Auth;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminBruteForceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Super Administrator',
            'email' => 'admin@ppob.test',
            'password' => Hash::make('correct_password'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        RateLimiter::clear('admin-login:127.0.0.1');
    }

    public function test_login_is_throttled_after_5_failed_attempts_from_same_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/admin/login', [
                'email' => 'admin@ppob.test',
                'password' => 'wrong_password',
            ]);
            $response->assertSessionHasErrors(['email']);
        }

        // 6th attempt should hit rate limiter message
        $response = $this->post('/admin/login', [
            'email' => 'admin@ppob.test',
            'password' => 'wrong_password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $errors = session('errors')->get('email');
        $this->assertStringContainsString('Terlalu banyak percobaan login', $errors[0]);
    }

    public function test_throttle_cleared_after_successful_login(): void
    {
        // 2 failed attempts
        for ($i = 0; $i < 2; $i++) {
            $this->post('/admin/login', [
                'email' => 'admin@ppob.test',
                'password' => 'wrong_password',
            ]);
        }

        // 3rd attempt is successful
        $response = $this->post('/admin/login', [
            'email' => 'admin@ppob.test',
            'password' => 'correct_password',
        ]);

        $response->assertRedirect('/admin/dashboard');

        // Throttle key should now be 0 attempts
        $this->assertEquals(0, RateLimiter::attempts('admin-login:127.0.0.1'));
    }
}
