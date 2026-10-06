<?php

namespace App\Domain\Ppob\Http\Controllers;

use App\Domain\Ppob\Models\ProcessedWebhookEvent;
use App\Domain\Shared\Http\ApiController;
use App\Domain\Wallet\Http\Resources\TopupResource;
use App\Domain\Wallet\Services\TopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller untuk menerima callback/webhook dari provider PPOB dan payment gateway wallet.
 */
class WebhookController extends ApiController
{
    public function __construct(
        private readonly ?TopupService $topupService = null
    ) {}

    public function ppobCallback(Request $request): JsonResponse
    {
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        $eventId = $request->header('X-Event-Id') ?? $request->input('event_id');

        // Validasi timestamp
        if (! $timestamp || abs(now()->timestamp - (int) $timestamp) > 300) {
            return $this->error('WEBHOOK_TIMESTAMP_INVALID', 'Timestamp di luar toleransi', 422);
        }

        // Cek duplikat event_id
        if (ProcessedWebhookEvent::where('event_id', $eventId)->exists()) {
            return $this->conflict('WEBHOOK_DUPLICATE', 'Event sudah diproses sebelumnya');
        }

        // Simpan event yang sudah diproses
        ProcessedWebhookEvent::create([
            'event_id' => $eventId,
            'source' => $request->input('source', 'unknown'),
            'event_type' => $request->input('event_type', 'unknown'),
            'payload' => $request->all(),
            'processed_at' => now(),
        ]);

        return $this->success(['message' => 'Webhook diterima']);
    }

    public function walletCallback(Request $request): JsonResponse
    {
        $gateway = $request->input('gateway') ?? $request->header('X-Gateway') ?? 'midtrans';
        $signature = $request->header('X-Signature') ?? (string) $request->input('signature_key', '');
        $payload = $request->all();

        /** @var TopupService $service */
        $service = $this->topupService ?? app(TopupService::class);
        $topup = $service->handleWebhook((string) $gateway, $payload, (string) $signature);

        return $this->success(new TopupResource($topup), 'Callback berhasil diproses');
    }
}
