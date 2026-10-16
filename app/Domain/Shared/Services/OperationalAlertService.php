<?php

namespace App\Domain\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OperationalAlertService
{
    protected bool $enabled;
    protected string $channel;
    protected int $cooldownSeconds;
    protected ?string $telegramToken;
    protected ?string $telegramChatId;
    protected ?string $webhookUrl;

    public function __construct()
    {
        $this->enabled = (bool) config('ppob.alerts.enabled', true);
        $this->channel = (string) config('ppob.alerts.channel', 'log');
        $this->cooldownSeconds = (int) config('ppob.alerts.cooldown_seconds', 300);
        $this->telegramToken = config('ppob.alerts.telegram.bot_token');
        $this->telegramChatId = config('ppob.alerts.telegram.chat_id');
        $this->webhookUrl = config('ppob.alerts.webhook.url');
    }

    /**
     * Kirim alert saat Circuit Breaker suatu provider ter-trip (beralih ke OPEN).
     */
    public function notifyCircuitBreakerTripped(string $supplierCode, int $failures = 0, ?string $reason = null): bool
    {
        $codeUpper = strtoupper($supplierCode);
        $title = "🚨 [CIRCUIT BREAKER TRIPPED] {$codeUpper}";
        $msg = "Provider <b>{$codeUpper}</b> mengalami kegagalan beruntun" . ($failures > 0 ? " ({$failures}x)" : "") . ".\n";
        $msg .= "Status: <b>OPEN</b> (Fail-Fast Aktif).\n";
        if ($reason) {
            $msg .= "Alasan: <i>{$reason}</i>\n";
        }
        $msg .= "Sistem secara otomatis mengalihkan transaksi ke rute failover/backup.";

        $dedupKey = "circuit_tripped:{$supplierCode}";

        return $this->sendAlert($title, $msg, 'critical', $dedupKey);
    }

    /**
     * Kirim notifikasi saat Circuit Breaker provider pulih (beralih ke CLOSED).
     */
    public function notifyCircuitBreakerRecovered(string $supplierCode): bool
    {
        $codeUpper = strtoupper($supplierCode);
        $title = "✅ [CIRCUIT BREAKER RESTORED] {$codeUpper}";
        $msg = "Provider <b>{$codeUpper}</b> telah pulih dan berhasil memproses transaksi verifikasi.\n";
        $msg .= "Status: <b>CLOSED</b> (Normal).\n";
        $msg .= "Rute transaksi utama kembali diarahkan ke provider ini.";

        $dedupKey = "circuit_recovered:{$supplierCode}";

        return $this->sendAlert($title, $msg, 'info', $dedupKey);
    }

    /**
     * Kirim peringatan saldo deposit provider upstream menipis di bawah ambang batas (threshold).
     */
    public function notifyLowBalance(string $providerName, string $providerCode, int $balanceRupiah, int $thresholdRupiah): bool
    {
        $codeUpper = strtoupper($providerCode);
        $balanceFormatted = number_format($balanceRupiah, 0, ',', '.');
        $thresholdFormatted = number_format($thresholdRupiah, 0, ',', '.');

        $title = "⚠️ [LOW BALANCE ALERT] {$codeUpper}";
        $msg = "Saldo upstream provider <b>{$providerName} ({$codeUpper})</b> menipis!\n";
        $msg .= "Sisa Saldo: <b>Rp {$balanceFormatted}</b>\n";
        $msg .= "Batas Minimum: <b>Rp {$thresholdFormatted}</b>\n";
        $msg .= "Segera lakukan deposit saldo upstream sebelum stok transaksi terputus.";

        $dedupKey = "low_balance:{$providerCode}";

        return $this->sendAlert($title, $msg, 'warning', $dedupKey);
    }

    /**
     * Kirim peringatan saat semua rute supplier untuk suatu produk gagal / habis (routing exhausted).
     */
    public function notifyRoutingExhausted(string $productSku, string $productName, string $preferredProviderName, string $reason): bool
    {
        $title = "❌ [ROUTING EXHAUSTED] {$productSku}";
        $msg = "Transaksi untuk SKU <b>{$productSku}</b> ({$productName}) tertahan!\n";
        $msg .= "Provider Utama: <b>{$preferredProviderName}</b> tidak tersedia.\n";
        $msg .= "Rute Backup/Failover: <b>Tidak ada supplier alternatif yang memenuhi syarat (habis/nonaktif/margin negatif).</b>\n";
        $msg .= "Keterangan: <i>{$reason}</i>";

        $dedupKey = "routing_exhausted:{$productSku}";

        return $this->sendAlert($title, $msg, 'critical', $dedupKey);
    }

    /**
     * Eksekusi pengiriman alert dengan proteksi anti-spam (throttling deduplication).
     */
    public function sendAlert(string $title, string $message, string $level = 'warning', ?string $dedupKey = null): bool
    {
        if (! $this->enabled) {
            return false;
        }

        // Anti-spam throttling check
        if ($dedupKey && $this->isThrottled($dedupKey)) {
            Log::info("Operational alert suppressed due to throttling cooldown: [{$dedupKey}]");
            return false;
        }

        $success = match ($this->channel) {
            'telegram' => $this->sendToTelegram($title, $message),
            'webhook' => $this->sendToWebhook($title, $message, $level),
            default => $this->sendToLog($title, $message, $level),
        };

        if ($success && $dedupKey) {
            $this->applyThrottle($dedupKey);
        }

        return $success;
    }

    /**
     * Kirim notifikasi via Telegram Bot API.
     */
    protected function sendToTelegram(string $title, string $message): bool
    {
        if (! $this->telegramToken || ! $this->telegramChatId) {
            Log::warning("Telegram alert skipped: TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID is missing.");
            return false;
        }

        $text = "<b>{$title}</b>\n\n{$message}\n\n<i>Waktu: " . now()->format('Y-m-d H:i:s T') . "</i>";

        try {
            $url = "https://api.telegram.org/bot{$this->telegramToken}/sendMessage";
            $res = Http::timeout(5)->post($url, [
                'chat_id' => $this->telegramChatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            return $res->successful();
        } catch (\Throwable $e) {
            Log::error("Failed to send Telegram operational alert: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kirim notifikasi via Generic Webhook (Discord / Slack / Internal Dashboard).
     */
    protected function sendToWebhook(string $title, string $message, string $level): bool
    {
        if (! $this->webhookUrl) {
            Log::warning("Webhook alert skipped: ALERT_WEBHOOK_URL is missing.");
            return false;
        }

        $cleanText = strip_tags(str_replace(['<b>', '</b>', '<i>', '</i>', '<br>'], ['', '', '', '', "\n"], $message));

        try {
            // Kompatibilitas Discord webhook embeds
            $payload = [
                'content' => "**{$title}**\n{$cleanText}",
                'embeds' => [
                    [
                        'title' => $title,
                        'description' => $cleanText,
                        'color' => match ($level) {
                            'critical' => 15158332, // Red
                            'warning' => 15105570,  // Yellow/Orange
                            'info' => 3066993,     // Green
                            default => 3447003,    // Blue
                        },
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
            ];

            $res = Http::timeout(5)->post($this->webhookUrl, $payload);
            return $res->successful();
        } catch (\Throwable $e) {
            Log::error("Failed to send Webhook operational alert: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Catat alert ke log sistem Laravel.
     */
    protected function sendToLog(string $title, string $message, string $level): bool
    {
        $clean = strip_tags(str_replace(['<b>', '</b>', '<i>', '</i>', '<br>'], ['', '', '', '', ' '], $message));
        $logMessage = "[OPERATIONAL ALERT] {$title} — {$clean}";

        match ($level) {
            'critical' => Log::critical($logMessage),
            'warning' => Log::warning($logMessage),
            default => Log::info($logMessage),
        };

        return true;
    }

    /**
     * Cek apakah alert ini sedang dalam masa cooldown anti-spam.
     */
    public function isThrottled(string $dedupKey): bool
    {
        return Cache::has("alert:throttle:{$dedupKey}");
    }

    /**
     * Terapkan masa cooldown untuk kunci alert tertentu.
     */
    public function applyThrottle(string $dedupKey): void
    {
        Cache::put("alert:throttle:{$dedupKey}", now()->toIso8601String(), $this->cooldownSeconds);
    }

    /**
     * Reset masa cooldown untuk keperluan testing / paksa kirim.
     */
    public function resetThrottle(string $dedupKey): void
    {
        Cache::forget("alert:throttle:{$dedupKey}");
    }
}
