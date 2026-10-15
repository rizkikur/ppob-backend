<?php

namespace Tests\Feature\Admin\Users;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private UserTier $tierRetail;
    private UserTier $tierAgent;
    private UserTier $tierReseller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@ppob.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->tierRetail = UserTier::firstOrCreate(
            ['name' => 'end_user'],
            ['label' => 'Retail / Pengguna Langsung', 'description' => 'Pengguna retail aplikasi']
        );

        $this->tierAgent = UserTier::firstOrCreate(
            ['name' => 'agent'],
            ['label' => 'Agen', 'description' => 'Agen pulsa & PPOB']
        );

        $this->tierReseller = UserTier::firstOrCreate(
            ['name' => 'reseller'],
            ['label' => 'Reseller', 'description' => 'Reseller master']
        );
    }

    public function test_unauthenticated_user_cannot_access_users_index(): void
    {
        $response = $this->get(route('admin.users.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_users_index_with_kpi_metrics(): void
    {
        $userA = User::create([
            'user_tier_id' => $this->tierRetail->id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $userB = User::create([
            'user_tier_id' => $this->tierAgent->id,
            'name' => 'Siti Aminah',
            'phone' => '085712345678',
            'email' => 'siti@example.com',
            'role' => 'user',
            'is_active' => false,
            'is_verified' => false,
        ]);

        Wallet::create(['user_id' => $userA->id, 'balance_cents' => 5000000]); // Rp 50.000
        Wallet::create(['user_id' => $userB->id, 'balance_cents' => 2500000]); // Rp 25.000

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Pengguna Mobile Apps');
        $response->assertSee('Budi Santoso');
        $response->assertSee('Siti Aminah');
        $response->assertSee('081234567890');
        $response->assertSee('085712345678');
    }

    public function test_admin_can_filter_users_by_search_tier_and_kyc(): void
    {
        $userA = User::create([
            'user_tier_id' => $this->tierRetail->id,
            'name' => 'Andi Wijaya',
            'phone' => '081122334455',
            'email' => 'andi@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $userB = User::create([
            'user_tier_id' => $this->tierAgent->id,
            'name' => 'Citra Dewi',
            'phone' => '087788990011',
            'email' => 'citra@example.com',
            'role' => 'user',
            'is_active' => true,
            'is_verified' => false,
        ]);

        // Filter search Andi
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.index', ['search' => 'Andi']));
        $response->assertOk();
        $response->assertSee('Andi Wijaya');
        $response->assertDontSee('Citra Dewi');

        // Filter tier agent
        $responseTier = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.index', ['user_tier_id' => $this->tierAgent->id]));
        $responseTier->assertOk();
        $responseTier->assertSee('Citra Dewi');
        $responseTier->assertDontSee('Andi Wijaya');
    }

    public function test_admin_can_view_user_detail_page(): void
    {
        $user = User::create([
            'user_tier_id' => $this->tierAgent->id,
            'name' => 'Eko Prasetyo',
            'phone' => '081399887766',
            'email' => 'eko@example.com',
            'pin_hash' => Hash::make('123456'),
            'role' => 'user',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $wallet = Wallet::create(['user_id' => $user->id, 'balance_cents' => 15000000]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users.show', $user));

        $response->assertOk();
        $response->assertSee('Eko Prasetyo');
        $response->assertSee('081399887766');
        $response->assertSee('KYC Terverifikasi');
        $response->assertSee('Agen');
    }

    public function test_admin_can_toggle_user_active_status(): void
    {
        $user = User::create([
            'user_tier_id' => $this->tierRetail->id,
            'name' => 'Fajar Nugraha',
            'phone' => '081511223344',
            'role' => 'user',
            'is_active' => true,
        ]);

        // Suspend
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.toggle', $user), ['reason' => 'Aktivitas mencurigakan']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->is_active);

        // Reactivate
        $responseReactivate = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.toggle', $user));

        $user->refresh();
        $this->assertTrue($user->is_active);
    }

    public function test_admin_can_toggle_user_kyc_status(): void
    {
        $user = User::create([
            'user_tier_id' => $this->tierRetail->id,
            'name' => 'Gita Gutawa',
            'phone' => '081622334455',
            'role' => 'user',
            'is_verified' => false,
        ]);

        // Approve KYC
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.toggle_kyc', $user), ['reason' => 'KTP dan selfie valid']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->is_verified);

        // Revoke KYC
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.toggle_kyc', $user));

        $user->refresh();
        $this->assertFalse($user->is_verified);
    }

    public function test_admin_can_update_user_tier(): void
    {
        $user = User::create([
            'user_tier_id' => $this->tierRetail->id,
            'name' => 'Hendra Setiawan',
            'phone' => '081733445566',
            'role' => 'user',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.tier.update', $user), [
                'user_tier_id' => $this->tierReseller->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals($this->tierReseller->id, $user->user_tier_id);
    }

    public function test_admin_can_reset_user_pin(): void
    {
        $user = User::create([
            'user_tier_id' => $this->tierRetail->id,
            'name' => 'Irfan Bachdim',
            'phone' => '081844556677',
            'pin_hash' => Hash::make('654321'),
            'role' => 'user',
        ]);

        $this->assertTrue($user->hasPin());

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.reset_pin', $user), ['reason' => 'User lupa PIN']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->hasPin());
        $this->assertNull($user->pin_hash);
    }

    public function test_admin_can_adjust_user_balance_credit_and_debit(): void
    {
        $user = User::create([
            'user_tier_id' => $this->tierRetail->id,
            'name' => 'Joko Anwar',
            'phone' => '081955667788',
            'role' => 'user',
        ]);

        Wallet::create(['user_id' => $user->id, 'balance_cents' => 1000000]); // Rp 10.000

        // Credit +Rp 50.000
        $responseCredit = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.adjust_balance', $user), [
                'type' => 'credit',
                'amount' => 50000,
                'note' => 'Bonus promo pendaftaran agen',
            ]);

        $responseCredit->assertRedirect();
        $responseCredit->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals(6000000, $user->wallet->balance()->toCents()); // Rp 60.000

        // Debit -Rp 20.000
        $responseDebit = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.adjust_balance', $user), [
                'type' => 'debit',
                'amount' => 20000,
                'note' => 'Koreksi kelebihan bonus promo',
            ]);

        $responseDebit->assertRedirect();
        $responseDebit->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals(4000000, $user->wallet->balance()->toCents()); // Rp 40.000

        $this->assertDatabaseHas('wallet_mutations', [
            'wallet_id' => $user->wallet->id,
            'type' => 'credit',
            'amount_cents' => 5000000,
        ]);

        $this->assertDatabaseHas('wallet_mutations', [
            'wallet_id' => $user->wallet->id,
            'type' => 'debit',
            'amount_cents' => 2000000,
        ]);
    }
}
