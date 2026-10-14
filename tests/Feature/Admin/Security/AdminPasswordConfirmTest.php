<?php

namespace Tests\Feature\Admin\Security;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordConfirmTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Finance Controller',
            'email' => 'finance@ppob.test',
            'password' => Hash::make('my_secret_pass'),
            'role' => AdminRole::FINANCE_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_password_confirm_prompt_shown_when_accessing_protected_action(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Without recent password confirmation, accessing protected route redirects to confirm form
        $response = $this->get('/admin/security/test-protected-action');

        $response->assertRedirect('/admin/confirm-password');
    }

    public function test_can_render_confirm_password_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get('/admin/confirm-password');

        $response->assertStatus(200);
        $response->assertSee('Konfirmasi Password Diperlukan');
    }

    public function test_password_confirmation_fails_with_invalid_password(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post('/admin/confirm-password', [
            'password' => 'wrong_pass',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertFalse(session()->has('auth.password_confirmed_at'));
    }

    public function test_confirmed_password_is_valid_for_3_hours_without_prompt(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Confirm password successfully
        $response = $this->post('/admin/confirm-password', [
            'password' => 'my_secret_pass',
        ]);

        $this->assertTrue(session()->has('auth.password_confirmed_at'));

        // Access protected route directly within the 3-hour window
        $protectedResponse = $this->get('/admin/security/test-protected-action');
        $protectedResponse->assertStatus(200);
        $protectedResponse->assertJson(['status' => 'confirmed_access_granted']);

        // Check window after 2.5 hours (9000 seconds) - still valid!
        $this->travel(2.5)->hours();
        $protectedResponse = $this->get('/admin/security/test-protected-action');
        $protectedResponse->assertStatus(200);

        // Check window after 3.1 hours (exceeding 3 hour / 10800s timeout) - requires prompt again!
        $this->travel(3.1)->hours();
        $repromptResponse = $this->get('/admin/security/test-protected-action');
        $repromptResponse->assertRedirect('/admin/confirm-password');
    }
}
