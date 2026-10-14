<?php

namespace Tests\Feature\Admin\Partners;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Partner\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPartnerCrudTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@ppob.test',
            'password' => Hash::make('password123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_view_partners(): void
    {
        $response = $this->get('/admin/partners');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_partner_index(): void
    {
        $this->actingAs($this->admin, 'admin');

        Partner::create([
            'name' => 'PT Mitra Finansial',
            'api_key' => 'pk_live_test123',
            'secret' => encrypt('sk_live_secret123'),
            'is_active' => true,
            'rate_limit_rpm' => 60,
        ]);

        $response = $this->get('/admin/partners');
        $response->assertOk()
            ->assertSee('PT Mitra Finansial')
            ->assertSee('pk_live_test123');
    }

    public function test_admin_can_create_new_partner_and_receives_one_time_secret(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->post('/admin/partners', [
            'name' => 'CV Kios Pembayaran Bersama',
            'email' => 'kios@mitra.test',
            'phone' => '081234567890',
            'rate_limit_rpm' => 120,
            'protocol' => 'json',
            'response_mode' => 'async',
            'response_timeout_ms' => 6000,
            'callback_url' => 'https://callback.mitra.test/webhook',
            'allowed_ips' => "103.10.20.1\n103.10.20.2",
            'initial_balance' => 500000,
        ]);

        $partner = Partner::where('name', 'CV Kios Pembayaran Bersama')->first();
        $this->assertNotNull($partner);

        $response->assertRedirect(route('admin.partners.show', $partner));
        $response->assertSessionHas('newly_generated_secret');
        $response->assertSessionHas('newly_generated_key');

        $plainSecret = session('newly_generated_secret');
        $this->assertStringStartsWith('sk_live_', $plainSecret);

        // Verify stored secret is encrypted and can be decrypted to plainSecret
        $this->assertEquals($plainSecret, decrypt($partner->secret));

        // Verify allowed_ips parsed correctly
        $this->assertEquals(['103.10.20.1', '103.10.20.2'], $partner->allowed_ips);

        // Verify wallet initial balance credited (500,000 IDR = 50,000,000 cents)
        $this->assertEquals(50000000, $partner->getBalance()->toCents());

        // Verify audit log
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'partner.created',
            'target_id' => (string) $partner->id,
        ]);
    }

    public function test_admin_can_update_partner_profile(): void
    {
        $this->actingAs($this->admin, 'admin');

        $partner = Partner::create([
            'name' => 'Old Name Corp',
            'api_key' => 'pk_live_old',
            'secret' => encrypt('sk_live_old'),
            'rate_limit_rpm' => 60,
            'protocol' => 'json',
            'response_mode' => 'sync',
            'response_timeout_ms' => 5000,
            'is_active' => true,
        ]);

        $response = $this->put("/admin/partners/{$partner->id}", [
            'name' => 'New Name Corp',
            'rate_limit_rpm' => 200,
            'protocol' => 'otomax',
            'response_mode' => 'async',
            'response_timeout_ms' => 8000,
            'callback_url' => 'https://new.callback.test/ppob',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.partners.show', $partner));

        $partner->refresh();
        $this->assertEquals('New Name Corp', $partner->name);
        $this->assertEquals(200, $partner->rate_limit_rpm);
        $this->assertEquals('otomax', $partner->protocol);
        $this->assertEquals('async', $partner->response_mode);
        $this->assertEquals(8000, $partner->response_timeout_ms);
        $this->assertEquals('https://new.callback.test/ppob', $partner->callback_url);
    }

    public function test_admin_can_toggle_partner_status(): void
    {
        $this->actingAs($this->admin, 'admin');

        $partner = Partner::create([
            'name' => 'Partner Suspend Target',
            'api_key' => 'pk_live_suspend',
            'secret' => encrypt('sk_live_suspend'),
            'is_active' => true,
            'rate_limit_rpm' => 60,
        ]);

        // Toggle to suspend
        $response = $this->post("/admin/partners/{$partner->id}/toggle", [
            'reason' => 'Tunggakan tagihan bulanan belum lunas',
        ]);

        $response->assertSessionHas('success');
        $this->assertFalse($partner->fresh()->is_active);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'partner.status_toggled',
            'reason' => 'Tunggakan tagihan bulanan belum lunas',
        ]);
    }
}
