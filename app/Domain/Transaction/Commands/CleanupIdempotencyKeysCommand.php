<?php

namespace App\Domain\Transaction\Commands;

use App\Domain\Transaction\Models\IdempotencyKey;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupIdempotencyKeysCommand extends Command
{
    protected $signature = 'ppob:cleanup:idempotency {--days=30 : Masa retensi dalam hari (default 30 hari)}';

    protected $description = 'Bersihkan record Idempotency Key yang lebih lama dari masa retensi';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 30;
        }

        $cutoff = Carbon::now()->subDays($days);

        $this->info("Menghapus data Idempotency Key yang dibuat sebelum {$cutoff->toDateTimeString()} ({$days} hari lalu)...");

        $deletedCount = IdempotencyKey::where('created_at', '<', $cutoff)->delete();

        $this->info("Berhasil membersihkan {$deletedCount} record Idempotency Key.");

        return self::SUCCESS;
    }
}
