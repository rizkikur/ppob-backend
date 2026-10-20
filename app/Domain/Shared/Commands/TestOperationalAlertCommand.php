<?php

namespace App\Domain\Shared\Commands;

use App\Domain\Shared\Services\OperationalAlertService;
use Illuminate\Console\Command;

class TestOperationalAlertCommand extends Command
{
    protected $signature = 'alert:test
                            {--channel= : Kanal alert yang ingin diuji (telegram, webhook, log)}
                            {--message= : Pesan kustom yang ingin dikirimkan}';

    protected $description = 'Uji coba pengiriman bot notifikasi operasional (Telegram / Webhook / Log)';

    public function handle(OperationalAlertService $alertService): int
    {
        $channel = $this->option('channel') ?: config('ppob.alerts.channel', 'log');
        $customMessage = $this->option('message') ?: 'Ini adalah pesan uji coba bot notifikasi operasional PPOB.';

        $this->info("=== Menguji Bot Notifikasi Operasional PPOB ===");
        $this->line("Kanal Aktif: <fg=yellow>{$channel}</>");

        if ($channel === 'telegram') {
            $token = config('ppob.alerts.telegram.bot_token');
            $chatId = config('ppob.alerts.telegram.chat_id');
            $this->line("Telegram Bot Token: " . ($token ? substr($token, 0, 6) . '...' : '<fg=red>Belum disetel di .env (TELEGRAM_BOT_TOKEN)</>'));
            $this->line("Telegram Chat ID: " . ($chatId ?: '<fg=red>Belum disetel di .env (TELEGRAM_CHAT_ID)</>'));
        } elseif ($channel === 'webhook') {
            $url = config('ppob.alerts.webhook.url');
            $this->line("Webhook URL: " . ($url ?: '<fg=red>Belum disetel di .env (ALERT_WEBHOOK_URL)</>'));
        }

        $this->info("Mengirimkan alert pengujian...");

        $title = "🔔 [TEST ALERT] Pengujian Sistem Notifikasi Operasional";
        $message = "Pesan uji coba berhasil diterima dari environment <b>" . app()->environment() . "</b>.\n";
        $message .= "Isi pesan: <i>{$customMessage}</i>\n";
        $message .= "Status sistem: <b>NORMAL</b>.";

        // Bypass throttling untuk command test
        $dedupKey = "test_alert_" . time();

        $success = $alertService->sendAlert($title, $message, 'info', $dedupKey);

        if ($success) {
            $this->info("✓ Berhasil! Notifikasi operasional berhasil dikirimkan via kanal [{$channel}].");
            return self::SUCCESS;
        }

        $this->error("✗ Gagal mengirimkan notifikasi. Silakan periksa kredensial .env atau log aplikasi.");
        return self::FAILURE;
    }
}
