<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\Admin;
use App\Domain\Auth\Models\User;
use App\Domain\Shared\Exceptions\BusinessException;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Wallet\Models\TopupRequest;
use App\Domain\Wallet\Models\WalletMutation;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFinanceService
{
    public function __construct(
        protected AdminAuditLogService $auditLogService,
        protected WalletService $walletService
    ) {}

    /**
     * Topup Summary Metrics.
     *
     * @return array{pending_count: int, pending_amount_cents: int, today_approved_amount_cents: int}
     */
    public function getTopupSummaryMetrics(): array
    {
        $pendingCount = TopupRequest::where('status', TopupRequest::STATUS_PENDING)->count();
        $pendingAmountCents = (int) TopupRequest::where('status', TopupRequest::STATUS_PENDING)->sum('amount_cents');
        $todayApprovedCents = (int) TopupRequest::where('status', TopupRequest::STATUS_PAID)
            ->whereDate('confirmed_at', today())
            ->sum('amount_cents');

        return [
            'pending_count' => $pendingCount,
            'pending_amount_cents' => $pendingAmountCents,
            'today_approved_amount_cents' => $todayApprovedCents,
        ];
    }

    /**
     * Get paginated topup requests with filters.
     *
     * @param  array{status?: string, search?: string}  $filters
     */
    public function getTopupRequests(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = TopupRequest::query()->with(['user.wallet']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('gateway_ref', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Approve manual topup request and credit user wallet with row lock.
     */
    public function approveTopup(TopupRequest $topup, Admin $admin, ?string $notes = null): TopupRequest
    {
        return DB::transaction(function () use ($topup, $admin, $notes) {
            /** @var TopupRequest $locked */
            $locked = TopupRequest::where('id', $topup->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isPaid()) {
                return $locked;
            }

            if ($locked->status !== TopupRequest::STATUS_PENDING) {
                throw new BusinessException('TOPUP_NOT_PENDING', "Tiket topup #{$locked->id} sudah berstatus {$locked->status} dan tidak dapat disetujui.");
            }

            // Credit wallet via WalletService
            $this->walletService->credit(
                user: $locked->user,
                amount: $locked->amount_cents,
                referenceType: WalletMutation::REF_TOPUP,
                referenceId: $locked->id,
                note: "Manual topup disetujui oleh {$admin->name}. ".($notes ?? 'Bukti transfer valid.')
            );

            $locked->update([
                'status' => TopupRequest::STATUS_PAID,
                'paid_at' => now(),
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
            ]);

            $this->auditLogService->log(
                admin: $admin,
                action: 'finance.topup_approved',
                target: $locked,
                changes: [
                    'amount_cents' => $locked->amount_cents->toCents(),
                    'user_id' => $locked->user_id,
                    'notes' => $notes,
                ],
                reason: "Approval tiket topup #{$locked->id} sebesar Rp ".number_format($locked->amount_cents->toCents() / 100, 0, ',', '.')
            );

            return $locked->fresh();
        });
    }

    /**
     * Reject manual topup request.
     */
    public function rejectTopup(TopupRequest $topup, Admin $admin, string $reason): TopupRequest
    {
        return DB::transaction(function () use ($topup, $admin, $reason) {
            /** @var TopupRequest $locked */
            $locked = TopupRequest::where('id', $topup->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== TopupRequest::STATUS_PENDING) {
                throw new BusinessException('TOPUP_NOT_PENDING', "Tiket topup #{$locked->id} sudah berstatus {$locked->status} dan tidak dapat ditolak.");
            }

            $locked->update([
                'status' => TopupRequest::STATUS_FAILED,
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
            ]);

            $this->auditLogService->log(
                admin: $admin,
                action: 'finance.topup_rejected',
                target: $locked,
                changes: [
                    'status' => 'failed',
                    'reason' => $reason,
                ],
                reason: "Penolakan tiket topup #{$locked->id}: {$reason}"
            );

            return $locked->fresh();
        });
    }

    /**
     * Execute manual balance adjustment (Credit / Debit) with mandatory audit reason.
     */
    public function processAdjustment(User $user, string $type, int $amountCents, Admin $admin, string $reason): WalletMutation
    {
        $normalizedType = strtolower($type);
        $amount = Money::fromCents($amountCents);

        if ($normalizedType === 'credit') {
            $mutation = $this->walletService->credit(
                user: $user,
                amount: $amount,
                referenceType: 'adjustment',
                referenceId: null,
                note: "Manual Adjustment Credit oleh {$admin->name}: {$reason}"
            );
        } else {
            $mutation = $this->walletService->debit(
                user: $user,
                amount: $amount,
                referenceType: 'adjustment',
                referenceId: null,
                note: "Manual Adjustment Debit oleh {$admin->name}: {$reason}"
            );
        }

        $this->auditLogService->log(
            admin: $admin,
            action: 'finance.balance_adjusted',
            target: $user,
            changes: [
                'type' => $normalizedType,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $mutation->balance_after_cents,
            ],
            reason: "Penyesuaian saldo pengguna {$user->name}: {$reason}"
        );

        return $mutation;
    }

    /**
     * Get paginated wallet mutations for ledger viewer.
     *
     * @param  array{type?: string, reference_type?: string, search?: string, start_date?: string, end_date?: string}  $filters
     */
    public function getLedgerMutations(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = WalletMutation::query()->with(['user.wallet']);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->whereHas('user', function ($uq) use ($search) {
                $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Stream CSV export of wallet ledger mutations.
     *
     * @param  array{type?: string, reference_type?: string, search?: string, start_date?: string, end_date?: string}  $filters
     */
    public function streamExportLedgerCsv(array $filters = []): StreamedResponse
    {
        $query = WalletMutation::query()->with(['user'])->latest('id');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->whereHas('user', function ($uq) use ($search) {
                $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ledger_mutations_'.now()->format('Ymd_His').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header
            fputcsv($handle, [
                'ID Mutasi',
                'Waktu (WIB)',
                'User ID',
                'Nama Pengguna',
                'Email / No HP',
                'Jenis (Credit/Debit)',
                'Nominal (Rp)',
                'Saldo Akhir (Rp)',
                'Tipe Referensi',
                'ID Referensi',
                'Catatan / Alasan',
            ]);

            // Cursor stream to keep memory minimal
            foreach ($query->cursor() as $row) {
                $amountCents = $row->amount_cents instanceof Money
                    ? $row->amount_cents->toCents()
                    : (int) $row->amount_cents;

                $balanceAfterCents = $row->balance_after_cents instanceof Money
                    ? $row->balance_after_cents->toCents()
                    : (int) $row->balance_after_cents;

                fputcsv($handle, [
                    $row->id,
                    $row->created_at?->format('Y-m-d H:i:s'),
                    $row->user_id,
                    $row->user?->name ?? 'User Terhapus',
                    $row->user?->email ?: $row->user?->phone,
                    strtoupper($row->type),
                    number_format($amountCents / 100, 2, '.', ''),
                    number_format($balanceAfterCents / 100, 2, '.', ''),
                    $row->reference_type,
                    $row->reference_id ?? '-',
                    $row->note ?? '-',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
