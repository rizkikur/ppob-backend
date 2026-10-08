<?php

namespace App\Domain\Partner\Jobs;

use App\Domain\Partner\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DeliverWebhookJob — Mengirim callback hasil transaksi ke mitra (ADR-007 & ADR-008).
 * Menjalankan exponential backoff retry hingga 5 attempt jika callback URL mitra gagal.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Retry dikontrol secara terstruktur via database & delay

    public int $timeout = 30;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        /** @var WebhookDelivery|null $delivery */
        $delivery = WebhookDelivery::with(['partner', 'transaction.product'])->find($this->deliveryId);

        if (! $delivery || $delivery->isDelivered() || $delivery->isFailedPermanent()) {
            return;
        }

        $partner = $delivery->partner;
        $tx = $delivery->transaction;

        if (! $partner || ! $tx || ! $delivery->callback_url) {
            $delivery->update(['status' => WebhookDelivery::STATUS_FAILED_PERMANENT]);

            return;
        }

        $timestamp = now()->timestamp;
        $payload = [
            'event' => 'transaction.updated',
            'data' => [
                'transaction_id' => $tx->id,
                'idempotency_key' => $tx->idempotency_key,
                'status' => $tx->status,
                'sku_code' => $tx->product?->sku_code,
                'customer_number' => $tx->customer_number,
                'amount' => $tx->amount_cents?->toCents(),
                'sell_price' => $tx->sell_price_cents?->toCents(),
                'sn' => $tx->provider_ref,
                'failure_reason' => $tx->failure_reason,
                'created_at' => $tx->created_at?->toIso8601String(),
                'updated_at' => $tx->updated_at?->toIso8601String(),
            ],
            'timestamp' => $timestamp,
        ];

        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $secret = $partner->getCallbackSecretDecrypted() ?: $partner->getSecretDecrypted();
        $signature = hash_hmac('sha256', $jsonBody, $secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Signature' => $signature,
                    'X-Timestamp' => (string) $timestamp,
                    'X-Event-Id' => (string) $delivery->id,
                ])
                ->post($delivery->callback_url, $payload);

            if ($response->successful()) {
                $delivery->update([
                    'status' => WebhookDelivery::STATUS_DELIVERED,
                    'response_code' => $response->status(),
                    'response_body' => mb_substr($response->body(), 0, 1000),
                    'delivered_at' => now(),
                    'next_retry_at' => null,
                ]);

                return;
            }

            $statusCode = $response->status();
            $responseBody = mb_substr($response->body(), 0, 1000);
        } catch (\Throwable $e) {
            $statusCode = 0;
            $responseBody = $e->getMessage();
        }

        // Penanganan kegagalan & evaluasi retry (ADR-008)
        if ($delivery->attempt >= WebhookDelivery::MAX_ATTEMPTS) {
            $delivery->update([
                'status' => WebhookDelivery::STATUS_FAILED_PERMANENT,
                'response_code' => $statusCode,
                'response_body' => $responseBody,
                'next_retry_at' => null,
            ]);

            Log::critical("Webhook delivery permanently failed for partner {$partner->id}, transaction {$tx->id}");
        } else {
            $nextAttempt = $delivery->attempt + 1;
            $delay = WebhookDelivery::getDelayForAttempt($nextAttempt);

            $delivery->update([
                'attempt' => $nextAttempt,
                'status' => WebhookDelivery::STATUS_FAILED,
                'response_code' => $statusCode,
                'response_body' => $responseBody,
                'next_retry_at' => now()->addSeconds($delay),
            ]);

            self::dispatch($delivery->id)->delay(now()->addSeconds($delay));
        }
    }
}
