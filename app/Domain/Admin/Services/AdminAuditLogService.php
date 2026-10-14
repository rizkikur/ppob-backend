<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;

class AdminAuditLogService
{
    /**
     * Record an audit log entry for an admin action.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public function log(
        Admin $admin,
        string $action,
        ?Model $target = null,
        ?array $changes = null,
        ?string $reason = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target ? (string) $target->getKey() : null,
            'changes' => $changes,
            'reason' => $reason,
            'ip_address' => $ipAddress ?? request()->ip() ?? '127.0.0.1',
            'user_agent' => $userAgent ?? request()->userAgent() ?? 'System',
            'created_at' => now(),
        ]);
    }
}
