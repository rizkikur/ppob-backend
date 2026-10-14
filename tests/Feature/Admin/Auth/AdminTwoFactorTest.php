<?php

namespace Tests\Feature\Admin\Auth;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminTwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Admin $adminWith2fa;
    private Admin $adminWithout2fa;
    private AdminTwoFactorService $twoFactorService;
    private string $secret = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        $this->twoFactorService = app(AdminTwoFactorService::class);

        $this->adminWith2fa = Admin::create([
            'name' => '2FA Admin',
            'email' => '2fa@ppob.test',
            'password' => Hash::make('secret123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
            'two_factor_secret' => $this->secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->adminWithout2fa = Admin::create([
            'name' => 'Normal Admin',
            'email' => 'normal@ppob.test',
            'password' => Hash::make('secret123'),
            'role' => AdminRole::OPS_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_admin_with_2fa_redirected_to_two_factor_form(): void
    {
        $response = $this->post('/admin/login', [
            'email' => '2fa@ppob.test',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/admin/two-factor');
        $this->assertTrue(session('admin_2fa_pending'));
    }

    public function test_admin_cannot_access_dashboard_while_2fa_pending(): void
    {
        $this->actingAs($this->adminWith2fa, 'admin');
        session(['admin_2fa_pending' => true]);

        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/admin/two-factor');
    }

    public function test_admin_can_complete_2fa_challenge_with_valid_totp(): void
    {
        $this->actingAs($this->adminWith2fa, 'admin');
        session(['admin_2fa_pending' => true]);

        $timeSlice = (int) floor(time() / 30);
        $validCode = $this->twoFactorService->calculateCode($this->secret, $timeSlice);

        $response = $this->post('/admin/two-factor', [
            'code' => $validCode,
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertFalse(session()->has('admin_2fa_pending'));
    }

    public function test_2fa_challenge_fails_with_invalid_code(): void
    {
        $this->actingAs($this->adminWith2fa, 'admin');
        session(['admin_2fa_pending' => true]);

        $response = $this->post('/admin/two-factor', [
            'code' => '999999',
        ]);

        $response->assertSessionHasErrors(['code']);
        $this->assertTrue(session('admin_2fa_pending'));
    }

    public function test_admin_can_setup_and_confirm_2fa_via_profile(): void
    {
        $this->actingAs($this->adminWithout2fa, 'admin');

        // Step 1: initiate enable
        $response = $this->post('/admin/profile/2fa/enable');
        $response->assertRedirect('/admin/profile/2fa/setup');

        $draftSecret = session('admin_2fa_draft_secret');
        $this->assertNotEmpty($draftSecret);

        // Step 2: confirm with valid TOTP code
        $timeSlice = (int) floor(time() / 30);
        $validCode = $this->twoFactorService->calculateCode($draftSecret, $timeSlice);

        $confirmResponse = $this->post('/admin/profile/2fa/confirm', [
            'code' => $validCode,
        ]);

        $confirmResponse->assertRedirect('/admin/profile');
        $confirmResponse->assertSessionHas('success');

        $this->adminWithout2fa->refresh();
        $this->assertTrue($this->adminWithout2fa->hasTwoFactor());
    }

    public function test_admin_can_disable_2fa_via_profile(): void
    {
        $this->actingAs($this->adminWith2fa, 'admin');

        $response = $this->delete('/admin/profile/2fa/disable');

        $response->assertRedirect('/admin/profile');
        $this->adminWith2fa->refresh();
        $this->assertFalse($this->adminWith2fa->hasTwoFactor());
    }
}
