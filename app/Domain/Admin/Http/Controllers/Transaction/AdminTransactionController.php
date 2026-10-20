<?php

namespace App\Domain\Admin\Http\Controllers\Transaction;

use App\Domain\Admin\Http\Requests\ManualStatusOverrideRequest;
use App\Domain\Admin\Http\Requests\TransactionFilterRequest;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminAuditLog;
use App\Domain\Admin\Services\AdminTransactionManagementService;
use App\Domain\Partner\Models\Partner;
use App\Domain\Product\Models\Provider;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Wallet\Models\WalletMutation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminTransactionController extends Controller
{
    public function __construct(
        protected AdminTransactionManagementService $transactionService
    ) {}

    /**
     * Display a listing of transactions with filters and search.
     */
    public function index(TransactionFilterRequest $request)
    {
        $filters = $request->validated();

        // Default setting: rentang tanggal hari ini jika belum ditentukan
        if (! $request->has('date_start') && ! $request->has('date_end')) {
            $today = now()->toDateString();
            $filters['date_start'] = $today;
            $filters['date_end'] = $today;
        } elseif (! empty($filters['date_start']) && ! empty($filters['date_end'])) {
            // Sanitasi: jika tanggal awal > tanggal akhir, swap agar query aman
            if ($filters['date_start'] > $filters['date_end']) {
                $temp = $filters['date_start'];
                $filters['date_start'] = $filters['date_end'];
                $filters['date_end'] = $temp;
            }
        }

        if ($request->validated('export') === 'csv') {
            return $this->transactionService->exportTransactionsCsv($filters);
        }

        $transactions = $this->transactionService->filterTransactions($filters);

        // Render partial table only for Live Auto-Refresh or AJAX
        if ($request->ajax() || $request->header('X-Live-Refresh') === '1' || $request->query('live') === '1') {
            return view('admin.transactions.components._transaction_table', [
                'transactions' => $transactions,
            ]);
        }

        $providers = Provider::query()->orderBy('name')->get();
        $partners = Partner::query()->orderBy('name')->get();

        return view('admin.transactions.index', [
            'transactions' => $transactions,
            'providers' => $providers,
            'partners' => $partners,
            'filters' => $filters,
        ]);
    }

    /**
     * Server-Sent Events (SSE) stream for real-time filtered transaction updates.
     */
    public function stream(TransactionFilterRequest $request, \App\Domain\Transaction\Services\TransactionEventStreamer $streamer): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = $request->validated();
        $maxDuration = max(1, min(60, (int) $request->query('duration', 25)));

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($filters, $streamer, $maxDuration) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $lastSeq = (int) \Illuminate\Support\Facades\Cache::get('sse:event:seq', 0);
            $startTime = time();
            $lastPing = time();

            echo "event: connected\n";
            echo "data: " . json_encode(['status' => 'connected', 'filters' => $filters]) . "\n\n";

            if (function_exists('flush')) {
                flush();
            }

            while (time() - $startTime < $maxDuration) {
                if (connection_aborted()) {
                    break;
                }

                $currentSeq = (int) \Illuminate\Support\Facades\Cache::get('sse:event:seq', 0);
                if ($currentSeq > $lastSeq) {
                    $lastSeq = $currentSeq;
                    $latestEvent = \Illuminate\Support\Facades\Cache::get('sse:event:latest');

                    if ($latestEvent && $streamer->matchesFilter($latestEvent, $filters)) {
                        echo "event: transaction\n";
                        echo "data: " . json_encode($latestEvent) . "\n\n";

                        if (function_exists('flush')) {
                            flush();
                        }
                    }
                }

                if (time() - $lastPing >= 10) {
                    echo "event: ping\n";
                    echo "data: {\"time\":" . time() . "}\n\n";
                    if (function_exists('flush')) {
                        flush();
                    }
                    $lastPing = time();
                }

                usleep(250000);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * Display detailed transaction information.
     */
    public function show(Transaction $transaction): View
    {
        $transaction->loadMissing([
            'user.wallet',
            'user.userTier',
            'product.provider',
            'product.category',
            'partner',
            'supplier',
            'originalSupplier',
            'inquiry',
            'webhookDeliveries.partner',
        ]);

        $walletMutations = WalletMutation::query()
            ->where('user_id', $transaction->user_id)
            ->where('reference_id', $transaction->id)
            ->latest('id')
            ->get();

        $auditLogs = AdminAuditLog::query()
            ->with('admin')
            ->whereIn('target_type', [class_basename($transaction), Transaction::class])
            ->where('target_id', (string) $transaction->id)
            ->latest('created_at')
            ->get();

        $maskedProviderResponse = $this->transactionService->maskSensitivePayload(
            $transaction->provider_response
        );

        return view('admin.transactions.show', [
            'transaction' => $transaction,
            'walletMutations' => $walletMutations,
            'auditLogs' => $auditLogs,
            'maskedProviderResponse' => $maskedProviderResponse,
        ]);
    }

    /**
     * Manually override transaction status.
     */
    public function overrideStatus(Transaction $transaction, ManualStatusOverrideRequest $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $this->transactionService->overrideStatus(
            transaction: $transaction,
            admin: $admin,
            newStatus: $request->validated('status'),
            reason: $request->validated('reason')
        );

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('success', "Status transaksi #{$transaction->id} berhasil diubah ke {$request->validated('status')}.");
    }

    /**
     * Re-dispatch webhook callback to B2B partner for this transaction.
     */
    public function retryWebhook(Transaction $transaction): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        try {
            $this->transactionService->retryWebhook($transaction, $admin);

            return redirect()
                ->route('admin.transactions.show', $transaction)
                ->with('success', "Webhook callback untuk transaksi #{$transaction->id} berhasil di-dispatch ulang ke mitra.");
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.transactions.show', $transaction)
                ->with('error', "Gagal me-retry webhook: {$e->getMessage()}");
        }
    }
}
