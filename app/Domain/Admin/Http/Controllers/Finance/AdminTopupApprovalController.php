<?php

namespace App\Domain\Admin\Http\Controllers\Finance;

use App\Domain\Admin\Http\Requests\ApproveTopupRequest;
use App\Domain\Admin\Http\Requests\RejectTopupRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminFinanceService;
use App\Domain\Wallet\Models\TopupRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTopupApprovalController extends Controller
{
    public function __construct(
        protected AdminFinanceService $financeService
    ) {}

    /**
     * List all Top-Up Requests with status filter & metrics.
     */
    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->input('status', TopupRequest::STATUS_PENDING),
            'search' => $request->input('search'),
        ];

        $topups = $this->financeService->getTopupRequests($filters, 15);
        $metrics = $this->financeService->getTopupSummaryMetrics();

        return view('admin.finance.topups.index', [
            'topups' => $topups,
            'metrics' => $metrics,
            'filters' => $filters,
        ]);
    }

    /**
     * Show detail of a topup request.
     */
    public function show(TopupRequest $topup): View
    {
        $topup->loadMissing(['user.wallet.mutations' => fn ($q) => $q->latest()->limit(10)]);

        return view('admin.finance.topups.show', [
            'topup' => $topup,
        ]);
    }

    /**
     * Approve topup request and credit user wallet.
     */
    public function approve(ApproveTopupRequest $request, TopupRequest $topup): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->financeService->approveTopup(
            topup: $topup,
            admin: $admin,
            notes: $request->validated('notes')
        );

        return redirect()
            ->route('admin.finance.topups.index')
            ->with('success', "Tiket top-up #{$topup->id} berhasil disetujui. Saldo telah dikreditkan ke dompet user.");
    }

    /**
     * Reject topup request with mandatory reason.
     */
    public function reject(RejectTopupRequest $request, TopupRequest $topup): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->financeService->rejectTopup(
            topup: $topup,
            admin: $admin,
            reason: $request->validated('reason')
        );

        return redirect()
            ->route('admin.finance.topups.index')
            ->with('success', "Tiket top-up #{$topup->id} telah ditolak.");
    }
}
