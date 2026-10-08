<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduled Maintenance Tasks ─────────────────────────────────────────────

// 1. Bersihkan Idempotency Keys yang lebih tua dari 30 hari setiap hari pukul 02:00
Schedule::command('ppob:cleanup:idempotency --days=30')
    ->dailyAt('02:00')
    ->name('ppob-cleanup-idempotency')
    ->withoutOverlapping()
    ->runInBackground();

// 2. Bersihkan expired OTP & expired PIN token setiap jam
Schedule::command('ppob:cleanup:tokens --hours=24')
    ->hourly()
    ->name('ppob-cleanup-tokens')
    ->withoutOverlapping()
    ->runInBackground();

// 3. Health check status provider & circuit breaker setiap 5 menit
Schedule::command('ppob:health:check')
    ->everyFiveMinutes()
    ->name('ppob-health-check')
    ->withoutOverlapping()
    ->runInBackground();

// 4. Re-dispatch callback webhook mitra yang pending retry setiap menit (ADR-008)
Schedule::command('ppob:webhook:retry')
    ->everyMinute()
    ->name('ppob-webhook-retry')
    ->withoutOverlapping()
    ->runInBackground();
