<?php

namespace App\Domain\Admin\Http\Controllers\User;

use App\Domain\Admin\Http\Requests\AdjustUserBalanceRequest;
use App\Domain\Admin\Http\Requests\UpdateUserTierRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AdminUserManagementService;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(
        protected AdminUserManagementService $userService
    ) {}

    /**
     * Display a paginated list of mobile app users with search & filters.
     */
    public function index(Request $request): View
    {
        $filters = [
            'search' => $request->input('search'),
            'user_tier_id' => $request->input('user_tier_id'),
            'is_active' => $request->input('is_active'),
            'is_verified' => $request->input('is_verified'),
        ];

        $users = $this->userService->getUsersWithMetrics($filters, 15);
        $metrics = $this->userService->getUserSummaryMetrics();
        $tiers = UserTier::orderBy('id')->get();

        return view('admin.users.index', [
            'users' => $users,
            'metrics' => $metrics,
            'tiers' => $tiers,
            'filters' => $filters,
        ]);
    }

    /**
     * Display detailed profile, wallet, mutations, and transactions of a user.
     */
    public function show(User $user): View
    {
        $user->loadMissing([
            'tier',
            'wallet.mutations' => fn ($q) => $q->latest()->limit(15),
            'transactions' => fn ($q) => $q->with('product')->latest()->limit(15),
        ]);

        $tiers = UserTier::orderBy('id')->get();

        return view('admin.users.show', [
            'user' => $user,
            'tiers' => $tiers,
        ]);
    }

    /**
     * Toggle active/suspended status of a user.
     */
    public function toggleStatus(User $user, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $newStatus = $this->userService->toggleUserStatus(
            user: $user,
            admin: $admin,
            reason: $request->input('reason')
        );

        $statusText = $newStatus ? 'diaktifkan kembali' : 'ditangguhkan (SUSPENDED)';

        return redirect()
            ->back()
            ->with('success', "Akun pengguna {$user->name} berhasil {$statusText}.");
    }

    /**
     * Toggle KYC / identity verification status of a user.
     */
    public function toggleKyc(User $user, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $newStatus = $this->userService->toggleKycStatus(
            user: $user,
            admin: $admin,
            reason: $request->input('reason')
        );

        $statusText = $newStatus ? 'TERVERIFIKASI (KYC Disetujui)' : 'dibatalkan status verifikasinya';

        return redirect()
            ->back()
            ->with('success', "Status KYC pengguna {$user->name} berhasil diubah menjadi {$statusText}.");
    }

    /**
     * Update user tier / price level.
     */
    public function updateTier(User $user, UpdateUserTierRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->userService->updateUserTier(
            user: $user,
            tierId: (int) $request->validated('user_tier_id'),
            admin: $admin
        );

        return redirect()
            ->back()
            ->with('success', "Level tier pengguna {$user->name} berhasil diperbarui.");
    }

    /**
     * Reset transaction PIN.
     */
    public function resetPin(User $user, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->userService->resetPin(
            user: $user,
            admin: $admin,
            reason: $request->input('reason')
        );

        return redirect()
            ->back()
            ->with('success', "PIN transaksi pengguna {$user->name} berhasil direset. Pengguna akan diminta membuat PIN baru.");
    }

    /**
     * Adjust user wallet balance manually (Credit / Debit).
     */
    public function adjustBalance(User $user, AdjustUserBalanceRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $type = $request->validated('type');
        $amountCents = (int) round((float) $request->validated('amount') * 100);
        $note = $request->validated('note');

        try {
            $this->userService->adjustBalance(
                user: $user,
                type: $type,
                amountCents: $amountCents,
                admin: $admin,
                note: $note
            );

            $typeLabel = $type === 'credit' ? 'Penambahan' : 'Pemotongan';

            return redirect()
                ->back()
                ->with('success', "{$typeLabel} saldo pengguna {$user->name} sebesar Rp ".number_format($amountCents / 100, 0, ',', '.').' berhasil diproses.');
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withErrors(['amount' => $e->getMessage()]);
        }
    }
}
