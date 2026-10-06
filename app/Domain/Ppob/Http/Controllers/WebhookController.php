<?php

namespace App\Domain\Ppob\Http\Controllers;

use App\Domain\Ppob\Models\ProcessedWebhookEvent;
use App\Domain\Ppob\Services\PpobService;
use App\Domain\Shared\Http\ApiController;
use App\Domain\Shared\Http\ApiResponse;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransactionService;
use App\Domain\Wallet\Http\Resources\TopupResource;
use App\Domain\Wallet\Services\TopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller untuk menerima callback/webhook dari provider PPOB dan payment gateway wallet.
 *
 * Mengimplementasikan validasi berlapis sesuai docs/security-design.md bagian 4:
 * 1. X-Signature (HMAC-SHA256) -> 401 WEBHOOK_SIGNATURE_INVALID
 * 2. X-Timestamp (toleransi 300 detik) -> 422 WEBHOOK_TIMESTAMP_INVALID
 * 3. Idempotency event_id -> 409 WEBHOOK_DUPLICATE
 */
class WebhookController extends ApiController
{
    public function __construct(
        private readonly ?TopupService $topupService = null,
        private readonly ?PpobService $ppobService = null,
        private readonly ?TransactionService $transactionService = null
    ) {}

    public function ppobCallback(Request $request): JsonResponse
    {
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $eventId = $request->header('X-Event-Id') ?? $request->input('event_id');
        $source = $request->header('X-Provider') ?? $request->input('source') ?? $request->input('provider') ?? 'pln';

        // ── 1. Validasi X-Signature (HMAC-SHA256) ──────────────────────────────
        if (! $signature) {
            return ApiResponse::error(
                'WEBHOOK_SIGNATURE_INVALID',
                'Header X-Signature wajib disertakan',
                401
            );
        }

        $ppob = $this->ppobService ?? app(PpobService::class);
        $rawBody = $request->getContent();

        if (! $ppob->verifyWebhook((string) $source, $rawBody, $signature)) {
            return ApiResponse::error(
                'WEBHOOK_SIGNATURE_INVALID',
                'Signature webhook tidak valid',
                401
            );
        }

        // ── 2. Validasi X-Timestamp (toleransi maks 300 detik) ──────────────────
        if (! $timestamp || abs(now()->timestamp - (int) $timestamp) > 300) {
            return ApiResponse::error(
                'WEBHOOK_TIMESTAMP_INVALID',
                'Timestamp webhook di luar toleransi (maks 300 detik)',
                422
            );
        }

        // ── 3. Validasi & Idempotency event_id ──────────────────────────────────
        if (! $eventId) {
            return ApiResponse::validationError(
                ['event_id' => ['Field event_id wajib diisi']],
                'Event ID wajib disertakan'
            );
        }

        if (ProcessedWebhookEvent::where('event_id', $eventId)->exists()) {
            return ApiResponse::conflict(
                'WEBHOOK_DUPLICATE',
                'Event webhook sudah diproses sebelumnya'
            );
        }

        // ── 4. Update status transaksi jika ada referensi ───────────────────────
        $txId = $request->input('transaction_id') ?? $request->input('order_id');
        $providerRef = $request->input('provider_ref');

        $tx = null;
        if ($txId) {
            $tx = Transaction::find($txId);
        } elseif ($providerRef) {
            $tx = Transaction::where('provider_ref', $providerRef)->first();
        }

        if ($tx) {
            $txService = $this->transactionService ?? app(TransactionService::class);
            $status = strtolower((string) ($request->input('status') ?? ''));

            if (in_array($status, ['success', 'paid', 'settlement', 'completed'], true)) {
                $txService->markSuccess($tx, (string) ($providerRef ?? $tx->provider_ref), $request->all());
            } elseif (in_array($status, ['failed', 'failure', 'expired', 'cancelled'], true)) {
                $reason = $request->input('failure_reason') ?? $request->input('message') ?? 'Provider webhook reported failure';
                $txService->failAndRefund($tx, (string) $reason, $request->all());
            }
        }

        // ── 5. Simpan event yang sudah diproses ke database ─────────────────────
        ProcessedWebhookEvent::create([
            'event_id' => $eventId,
            'source' => (string) $source,
            'event_type' => (string) ($request->input('event_type') ?? 'transaction.status_update'),
            'payload' => $request->all(),
            'processed_at' => now(),
        ]);

        return ApiResponse::success(['message' => 'Webhook berhasil diproses']);
    }

    public function walletCallback(Request $request): JsonResponse
    {
        $gateway = $request->input('gateway') ?? $request->header('X-Gateway') ?? 'midtrans';
        $signature = $request->header('X-Signature') ?? (string) $request->input('signature_key', '');
        $payload = $request->all();

        /** @var TopupService $service */
        $service = $this->topupService ?? app(TopupService::class);
        $topup = $service->handleWebhook((string) $gateway, $payload, (string) $signature);

        return ApiResponse::success(new TopupResource($topup), 'Callback berhasil diproses');
    }
}
