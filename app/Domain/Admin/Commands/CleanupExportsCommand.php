<?php

namespace App\Domain\Admin\Commands;

use App\Domain\Admin\Models\TransactionExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExportsCommand extends Command
{
    protected $signature = 'exports:cleanup {--hours=24 : Hapus file export yang lebih lama dari X jam}';

    protected $description = 'Bersihkan file CSV hasil export yang telah kedaluwarsa dari storage server';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        $this->info("Mencari file export transaksi yang dibuat sebelum {$cutoff->toDateTimeString()}...");

        $expiredJobs = TransactionExportJob::where(function ($q) use ($cutoff) {
            $q->where('expires_at', '<', now())
                ->orWhere('created_at', '<', $cutoff);
        })->get();

        $disk = Storage::disk('local');
        $deletedFiles = 0;

        foreach ($expiredJobs as $job) {
            if ($job->file_path && $disk->exists($job->file_path)) {
                $disk->delete($job->file_path);
                $deletedFiles++;
            }
            $job->delete();
        }

        $this->info("Berhasil membersihkan {$deletedFiles} file export fisik dan {$expiredJobs->count()} record job.");

        return self::SUCCESS;
    }
}
