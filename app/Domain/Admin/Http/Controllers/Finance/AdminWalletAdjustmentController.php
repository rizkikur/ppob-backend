<?php

namespace App\Domain\Admin\Http\Controllers\Finance;

use App\Domain\Admin\Http\Requests\WalletAdjustmentRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminFinanceService;
use App\Domain\Auth\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminWalletAdjustmentController extends Controller
{
    public function __construct(
        protected AdminFinanceService $financeService
    ) {}

    /**
     * Show form to manually adjust user/partner balance.
     */
    public function create(Request $request): View
    {
        $selectedUserId = $request->input('user_id');
        $selectedUser = $selectedUserId ? User::with('wallet')->find($selectedUserId) : null;

        // Recent users / partners for quick select
        $users = User::with('wallet')
            ->latest('id')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'role']);

        return view('admin.finance.adjustments.create', [
            'selectedUser' => $selectedUser,
            'users' => $users,
        ]);
    }

    /**
     * Store and execute manual balance adjustment.
     */
    public function store(WalletAdjustmentRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        /** @var User $user */
        $user = User::findOrFail($request->validated('user_id'));

        $type = $request->validated('type');
        $amountCents = (int) round((float) $request->validated('amount') * 100);
        $reason = $request->validated('reason');

        try {
            $mutation = $this->financeService->processAdjustment(
                user: $user,
                type: $type,
                amountCents: $amountCents,
                admin: $admin,
                reason: $reason
            );

            $typeLabel = strtolower($type) === 'credit' ? 'Penambahan (Credit)' : 'Pemotongan (Debit)';

            return redirect()
                ->route('admin.finance.ledger.index', ['search' => $user->phone ?: $user->email])
                ->with('success', "{$typeLabel} saldo sebesar Rp ".number_format($amountCents / 100, 0, ',', '.')." untuk pengguna {$user->name} berhasil dicatat di mutasi ledger.");
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['amount' => $e->getMessage()]);
        }
    }
}
