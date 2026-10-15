<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserTier;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminUserManagementService
{
    public function __construct(
        protected AdminAuditLogService $auditLogService,
        protected WalletService $walletService
    ) {}

    /**
     * Get paginated mobile app users with search, tier, and status filters.
     *
     * @param array<string, mixed> $filters
     */
    public function getUsersWithMetrics(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', 'user')
            ->where(function ($q) {
                $q->whereNull('email')
                    ->orWhere('email', 'not like', '%@partner.internal');
            })
            ->with(['tier', 'wallet'])
            ->withCount('transactions');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['user_tier_id'])) {
            $query->where('user_tier_id', (int) $filters['user_tier_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['is_verified']) && $filters['is_verified'] !== '') {
            $query->where('is_verified', filter_var($filters['is_verified'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Get summary KPI metrics for mobile app users.
     *
     * @return array{total_users: int, active_users: int, verified_users: int, total_balance_cents: int}
     */
    public function getUserSummaryMetrics(): array
    {
        $baseQuery = User::query()
            ->where('role', 'user')
            ->where(function ($q) {
                $q->whereNull('email')
                    ->orWhere('email', 'not like', '%@partner.internal');
            });

        $totalUsers = (clone $baseQuery)->count();
        $activeUsers = (clone $baseQuery)->where('is_active', true)->count();
        $verifiedUsers = (clone $baseQuery)->where('is_verified', true)->count();

        $userIds = (clone $baseQuery)->pluck('id');
        $totalBalanceCents = (int) Wallet::whereIn('user_id', $userIds)->sum('balance_cents');

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'verified_users' => $verifiedUsers,
            'total_balance_cents' => $totalBalanceCents,
        ];
    }

    /**
     * Toggle active/suspended status of a user.
     */
    public function toggleUserStatus(User $user, Admin $admin, ?string $reason = null): bool
    {
        $newStatus = ! $user->is_active;

        $user->update([
            'is_active' => $newStatus,
        ]);

        $this->auditLogService->log(
            admin: $admin,
            action: $newStatus ? 'user.activated' : 'user.suspended',
            target: $user,
            changes: ['is_active' => $newStatus],
            reason: $reason ?? ($newStatus ? 'Buka suspend akun pengguna' : 'Suspend akun pengguna')
        );

        return $newStatus;
    }

    /**
     * Toggle KYC / verification status of a user.
     */
    public function toggleKycStatus(User $user, Admin $admin, ?string $reason = null): bool
    {
        $newStatus = ! $user->is_verified;

        $user->update([
            'is_verified' => $newStatus,
        ]);

        $this->auditLogService->log(
            admin: $admin,
            action: $newStatus ? 'user.kyc_approved' : 'user.kyc_revoked',
            target: $user,
            changes: ['is_verified' => $newStatus],
            reason: $reason ?? ($newStatus ? 'Verifikasi identitas (KYC) disetujui' : 'Status verifikasi identitas (KYC) dicabut')
        );

        return $newStatus;
    }

    /**
     * Update user tier level.
     */
    public function updateUserTier(User $user, int $tierId, Admin $admin): User
    {
        $oldTierId = $user->user_tier_id;
        $tier = UserTier::findOrFail($tierId);

        $user->update([
            'user_tier_id' => $tier->id,
        ]);

        $this->auditLogService->log(
            admin: $admin,
            action: 'user.tier_updated',
            target: $user,
            changes: [
                'old_tier_id' => $oldTierId,
                'new_tier_id' => $tier->id,
                'new_tier_label' => $tier->label,
            ],
            reason: "Ubah level pengguna {$user->name} menjadi {$tier->label}"
        );

        return $user;
    }

    /**
     * Reset transaction PIN.
     */
    public function resetPin(User $user, Admin $admin, ?string $reason = null): void
    {
        $user->update([
            'pin_hash' => null,
        ]);

        $this->auditLogService->log(
            admin: $admin,
            action: 'user.pin_reset',
            target: $user,
            changes: ['pin_hash' => null],
            reason: $reason ?? "Reset PIN transaksi pengguna {$user->name}"
        );
    }

    /**
     * Adjust user wallet balance manually (Credit / Debit).
     */
    public function adjustBalance(User $user, string $type, int $amountCents, Admin $admin, ?string $note = null): WalletMutation
    {
        $amount = Money::fromCents($amountCents);
        $referenceNote = $note ?: "Manual adjustment oleh admin {$admin->name}";

        if ($type === 'credit') {
            $mutation = $this->walletService->credit(
                user: $user,
                amount: $amount,
                referenceType: 'admin_adjustment',
                referenceId: $admin->id,
                note: $referenceNote
            );
        } else {
            $mutation = $this->walletService->debit(
                user: $user,
                amount: $amount,
                referenceType: 'admin_adjustment',
                referenceId: $admin->id,
                note: $referenceNote
            );
        }

        $this->auditLogService->log(
            admin: $admin,
            action: 'user.balance_adjusted',
            target: $user,
            changes: [
                'type' => $type,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $mutation->balance_after_cents,
            ],
            reason: "Penyesuaian saldo pengguna {$user->name}: {$referenceNote}"
        );

        return $mutation;
    }
}
