<?php

namespace App\Domain\Security\Commands;

use App\Domain\Auth\Models\OtpCode;
use App\Domain\Security\Models\PinVerificationToken;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupExpiredTokensCommand extends Command
{
    protected $signature = 'ppob:cleanup:tokens {--hours=24 : Masa retensi kadaluarsa dalam jam (default 24 jam)}';

    protected $description = 'Bersihkan kode OTP dan PIN verification token yang sudah kadaluarsa atau terpakai';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        if ($hours <= 0) {
            $hours = 24;
        }

        $cutoff = Carbon::now()->subHours($hours);

        $this->info("Menghapus OTP dan PIN Token kadaluarsa sebelum {$cutoff->toDateTimeString()}...");

        // Bersihkan OTP yang sudah expire sebelum cutoff ATAU sudah verified sebelum cutoff
        $deletedOtpCount = OtpCode::where(function ($query) use ($cutoff) {
            $query->where('expires_at', '<', $cutoff)
                ->orWhere(function ($q) use ($cutoff) {
                    $q->whereNotNull('verified_at')
                        ->where('verified_at', '<', $cutoff);
                });
        })->delete();

        // Bersihkan PIN verification token yang expire sebelum cutoff ATAU used sebelum cutoff
        $deletedPinTokenCount = PinVerificationToken::where(function ($query) use ($cutoff) {
            $query->where('expires_at', '<', $cutoff)
                ->orWhere(function ($q) use ($cutoff) {
                    $q->whereNotNull('used_at')
                        ->where('used_at', '<', $cutoff);
                });
        })->delete();

        $this->info("Berhasil membersihkan {$deletedOtpCount} record OTP dan {$deletedPinTokenCount} record PIN Token.");

        return self::SUCCESS;
    }
}
