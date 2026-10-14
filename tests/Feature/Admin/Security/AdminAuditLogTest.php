<?php

namespace Tests\Feature\Admin\Security;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminAuditLog;
use App\Domain\Admin\Services\AdminAuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Security Auditor',
            'email' => 'auditor@ppob.test',
            'password' => Hash::make('password123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_audit_log_recorded_on_admin_login_and_logout(): void
    {
        $this->post('/admin/login', [
            'email' => 'auditor@ppob.test',
            'password' => 'password123',
        ]);

        $loginLog = AdminAuditLog::where('action', 'admin.login')
            ->where('admin_id', $this->admin->id)
            ->first();

        $this->assertNotNull($loginLog);
        $this->assertEquals('Admin', $loginLog->target_type);
        $this->assertEquals((string) $this->admin->id, $loginLog->target_id);

        $this->post('/admin/logout');

        $logoutLog = AdminAuditLog::where('action', 'admin.logout')
            ->where('admin_id', $this->admin->id)
            ->first();

        $this->assertNotNull($logoutLog);
    }

    public function test_audit_log_captures_correct_ip_and_user_agent(): void
    {
        $service = app(AdminAuditLogService::class);

        $log = $service->log(
            admin: $this->admin,
            action: 'test.action',
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0 AuditBot'
        );

        $this->assertEquals('192.168.1.100', $log->ip_address);
        $this->assertEquals('Mozilla/5.0 AuditBot', $log->user_agent);
        $this->assertEquals('test.action', $log->action);
    }

    public function test_audit_log_table_is_append_only(): void
    {
        $log = AdminAuditLog::create([
            'admin_id' => $this->admin->id,
            'action' => 'immutable.record',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Console',
            'created_at' => now(),
        ]);

        $this->assertNotNull($log->created_at);
        $this->assertNull($log->updated_at);
    }
}
