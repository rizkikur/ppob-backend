<?php

namespace App\Domain\Admin\Http\Controllers\AuditLog;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminAuditLog;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    /**
     * Display a paginated list of audit logs with search and filtering.
     */
    public function index(Request $request): View
    {
        $query = AdminAuditLog::query()->with('admin')->latest('id');

        // Filter: Search across reason, action, target_id, ip_address
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('target_id', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // Filter: Admin ID
        if ($adminId = $request->input('admin_id')) {
            $query->where('admin_id', $adminId);
        }

        // Filter: Action
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        // Filter: Target Type
        if ($targetType = $request->input('target_type')) {
            $query->where('target_type', $targetType);
        }

        // Filter: Date Range
        if ($dateFrom = $request->input('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo = $request->input('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $logs = $query->paginate(25)->withQueryString();

        // Distinct filters for dropdowns
        $admins = Admin::orderBy('name')->get(['id', 'name', 'email', 'role']);
        $distinctActions = AdminAuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');
        $distinctTargets = AdminAuditLog::query()
            ->whereNotNull('target_type')
            ->select('target_type')
            ->distinct()
            ->orderBy('target_type')
            ->pluck('target_type');

        return view('admin.audit_logs.index', compact(
            'logs',
            'admins',
            'distinctActions',
            'distinctTargets'
        ));
    }

    /**
     * Display detailed information and JSON diff for a specific audit log.
     */
    public function show(AdminAuditLog $auditLog): View
    {
        $auditLog->load('admin');

        return view('admin.audit_logs.show', compact('auditLog'));
    }
}
