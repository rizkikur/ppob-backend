<?php

namespace Tests\Feature\Admin\AuditLog;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminAuditLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    private Admin $financeAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@ppob.test',
            'password' => bcrypt('password123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->financeAdmin = Admin::create([
            'name' => 'Finance Admin',
            'email' => 'finance@ppob.test',
            'password' => bcrypt('password123'),
            'role' => AdminRole::FINANCE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function createAuditLog(array $attributes = []): AdminAuditLog
    {
        return AdminAuditLog::create(array_merge([
            'admin_id' => $this->superAdmin->id,
            'action' => 'test.action',
            'target_type' => null,
            'target_id' => null,
            'changes' => null,
            'reason' => 'Test reason',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit/Test',
            'created_at' => now(),
        ], $attributes));
    }

    public function test_unauthenticated_user_cannot_access_audit_logs(): void
    {
        $response = $this->get(route('admin.audit_logs.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_audit_logs_list(): void
    {
        $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'provider.circuit_breaker.reset',
            'target_type' => 'Provider',
            'target_id' => '1',
            'changes' => ['state' => 'CLOSED'],
            'reason' => 'Pemulihan upstream gateway',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.audit_logs.index'));

        $response->assertOk();
        $response->assertSee('Audit Trail');
        $response->assertSee('provider.circuit_breaker.reset');
        $response->assertSee('Pemulihan upstream gateway');
        $response->assertSee('Super Admin');
    }

    public function test_can_filter_audit_logs_by_admin(): void
    {
        $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'partner.rotate_key',
            'reason' => 'Rotasi periodik',
        ]);

        $this->createAuditLog([
            'admin_id' => $this->financeAdmin->id,
            'action' => 'topup.approve',
            'reason' => 'Mutasi bank valid',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.audit_logs.index', ['admin_id' => $this->financeAdmin->id]));

        $response->assertOk();
        $response->assertSee('topup.approve');
        $response->assertSee('Mutasi bank valid');
        $response->assertDontSee('Rotasi periodik');
    }

    public function test_can_filter_audit_logs_by_action(): void
    {
        $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'transaction.refund',
            'reason' => 'Refund transaksi gagal',
        ]);

        $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'product.bulk_update',
            'reason' => 'Kenaikan margin tier',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.audit_logs.index', ['action' => 'transaction.refund']));

        $response->assertOk();
        $response->assertSee('Refund transaksi gagal');
        $response->assertDontSee('Kenaikan margin tier');
    }

    public function test_can_filter_audit_logs_by_target_type(): void
    {
        $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'partner.ip.add',
            'target_type' => 'Partner',
            'target_id' => '42',
            'reason' => 'Whitelist IP kantor mitra',
        ]);

        $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'wallet.adjustment',
            'target_type' => 'Wallet',
            'target_id' => '100',
            'reason' => 'Koreksi pembukuan',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.audit_logs.index', ['target_type' => 'Partner']));

        $response->assertOk();
        $response->assertSee('Whitelist IP kantor mitra');
        $response->assertDontSee('Koreksi pembukuan');
    }

    public function test_can_filter_audit_logs_by_search_query(): void
    {
        $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'admin.login',
            'reason' => 'Login reguler shift pagi',
            'ip_address' => '192.168.1.55',
        ]);

        $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'admin.login',
            'reason' => 'Login reguler shift malam',
            'ip_address' => '10.0.0.99',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.audit_logs.index', ['search' => '192.168.1.55']));

        $response->assertOk();
        $response->assertSee('shift pagi');
        $response->assertDontSee('shift malam');
    }

    public function test_can_filter_audit_logs_by_date_range(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');

        $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'old.action',
            'reason' => 'Aksi minggu lalu',
            'created_at' => now()->subDays(7),
        ]);

        $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'today.action',
            'reason' => 'Aksi hari ini',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.audit_logs.index', [
                'date_from' => '2026-10-15',
                'date_to' => '2026-10-15',
            ]));

        $response->assertOk();
        $response->assertSee('Aksi hari ini');
        $response->assertDontSee('Aksi minggu lalu');

        Carbon::setTestNow();
    }

    public function test_can_view_single_audit_log_detail(): void
    {
        $log = $this->createAuditLog([
            'admin_id' => $this->superAdmin->id,
            'action' => 'wallet.adjustment',
            'target_type' => 'Wallet',
            'target_id' => '10',
            'changes' => [
                'type' => 'credit',
                'amount_cents' => 5000000,
                'balance_before' => 0,
                'balance_after' => 5000000,
            ],
            'reason' => 'Kompensasi saldo terpotong ganda pada sistem sebelumnya',
            'ip_address' => '10.0.1.20',
            'user_agent' => 'Mozilla/5.0 Test Suite',
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.audit_logs.show', $log));

        $response->assertOk();
        $response->assertSee("Detail Log Audit #{$log->id}");
        $response->assertSee('wallet.adjustment');
        $response->assertSee('Kompensasi saldo terpotong ganda pada sistem sebelumnya');
        $response->assertSee('10.0.1.20');
        $response->assertSee('Mozilla/5.0 Test Suite');
        $response->assertSee('5000000');
    }
}
