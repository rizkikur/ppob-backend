<?php

namespace Tests\Feature\Admin\Finance;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLedgerMutationViewTest extends TestCase
{
    use RefreshDatabase;

    private Admin $financeAdmin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financeAdmin = Admin::create([
            'name' => 'Finance Officer',
            'email' => 'finance@ppob.test',
            'password' => Hash::make('password123'),
            'role' => AdminRole::FINANCE_ADMIN,
            'is_active' => true,
        ]);

        $tier = \App\Domain\Auth\Models\UserTier::firstOrCreate(
            ['name' => 'regular'],
            ['label' => 'Regular', 'description' => 'Pengguna Regular']
        );

        $this->user = User::create([
            'user_tier_id' => $tier->id,
            'name' => 'Budi Pengguna',
            'email' => 'budi@ppob.test',
            'phone' => '081299887766',
            'password' => Hash::make('secret'),
            'role' => 'user',
            'is_active' => true,
        ]);

        Wallet::create([
            'user_id' => $this->user->id,
            'balance_cents' => 0,
        ]);

        $walletService = app(WalletService::class);
        $walletService->credit(
            user: $this->user,
            amount: Money::fromCents(30000000), // 300.000 IDR
            referenceType: 'topup',
            referenceId: 101,
            note: 'Topup deposit via BCA Virtual Account'
        );
    }

    public function test_finance_admin_can_view_double_entry_ledger_mutations(): void
    {
        $this->actingAs($this->financeAdmin, 'admin');

        $response = $this->get('/admin/finance/ledger');
        $response->assertOk()
            ->assertSee('Budi Pengguna')
            ->assertSee('300.000')
            ->assertSee('topup');
    }

    public function test_finance_admin_can_stream_export_ledger_csv(): void
    {
        $this->actingAs($this->financeAdmin, 'admin');

        $response = $this->get('/admin/finance/ledger/export?type=credit');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Capture streamed content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('ID Mutasi', $content);
        $this->assertStringContainsString('Nama Pengguna', $content);
        $this->assertStringContainsString('Budi Pengguna', $content);
        $this->assertStringContainsString('300000.00', $content);
    }
}
