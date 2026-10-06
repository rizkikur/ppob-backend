<?php

namespace App\Domain\Security\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Security\Exceptions\PinInvalidException;
use App\Domain\Security\Exceptions\PinLockedException;
use App\Domain\Security\Exceptions\PinNotSetException;
use App\Domain\Security\Exceptions\PinTokenInvalidException;
use App\Domain\Security\Models\PinVerificationToken;
use App\Domain\Shared\Exceptions\BusinessException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Service untuk manajemen PIN.
 *
 * Alur lengkap (lihat docs/security-design.md bagian 2):
 * 1. challenge() — buat tantangan sementara
 * 2. verify()    — verifikasi PIN, terbitkan pin_verification_token
 * 3. Token dipakai di header X-Pin-Token oleh endpoint transaksi
 *
 * Proteksi brute force: maks 5 percobaan dalam 15 menit → lock sementara.
 */
class PinService
{
    private const TOKEN_EXPIRE_MIN = 5;

    private const MAX_ATTEMPTS = 5;

    private const LOCK_DURATION_MIN = 15;

    /**
     * Set PIN pertama kali.
     *
     * @throws BusinessException jika PIN sudah pernah diset
     */
    public function setPin(User $user, string $pin): void
    {
        if ($user->hasPin()) {
            throw new BusinessException('VALIDATION_ERROR', 'PIN sudah pernah diset, gunakan fitur ganti PIN', 422);
        }

        $user->update(['pin_hash' => Hash::make($pin)]);
    }

    /**
     * Ganti PIN (perlu pin_verification_token dari PIN lama).
     */
    public function changePin(User $user, string $newPin): void
    {
        $user->update(['pin_hash' => Hash::make($newPin)]);
    }

    /**
     * Buat challenge (step 1 dari alur PIN).
     *
     * @return string challenge_id
     *
     * @throws PinLockedException jika akun terkunci
     * @throws PinNotSetException jika PIN belum diatur
     */
    public function challenge(User $user, string $purpose): string
    {
        if (Cache::has("pin:lock:{$user->id}")) {
            throw new PinLockedException;
        }

        if (! $user->hasPin()) {
            throw new PinNotSetException;
        }

        $challengeId = Str::uuid()->toString();

        // Simpan challenge di cache selama 10 menit
        Cache::put(
            "pin:challenge:{$user->id}:{$challengeId}",
            ['user_id' => $user->id, 'purpose' => $purpose],
            now()->addMinutes(10)
        );

        return $challengeId;
    }

    /**
     * Verifikasi PIN dan terbitkan pin_verification_token (step 2 dari alur PIN).
     *
     * @throws PinLockedException jika akun terkunci
     * @throws PinTokenInvalidException jika challenge tidak valid
     * @throws PinInvalidException jika PIN salah
     */
    public function verify(User $user, string $challengeId, string $pin, ?string $purpose = null): PinVerificationToken
    {
        // Cek lock brute force
        $lockKey = "pin:lock:{$user->id}";
        if (Cache::has($lockKey)) {
            throw new PinLockedException;
        }

        // Validasi challenge
        $challengeKey = "pin:challenge:{$user->id}:{$challengeId}";
        $challengeData = Cache::get($challengeKey);

        if (! $challengeData || ($purpose !== null && $challengeData['purpose'] !== $purpose)) {
            throw new PinTokenInvalidException('Challenge tidak valid atau sudah kedaluwarsa.');
        }

        $resolvedPurpose = $challengeData['purpose'];

        // Verifikasi PIN
        if (! Hash::check($pin, $user->pin_hash)) {
            $isLocked = $this->incrementAttempts($user);

            if ($isLocked) {
                throw new PinLockedException;
            }

            throw new PinInvalidException;
        }

        // Reset counter percobaan dan hapus challenge
        Cache::forget("pin:attempts:{$user->id}");
        Cache::forget($challengeKey);

        // Terbitkan token sekali pakai
        return PinVerificationToken::create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(32)),
            'purpose' => $resolvedPurpose,
            'expires_at' => now()->addMinutes(self::TOKEN_EXPIRE_MIN),
        ]);
    }

    // ─── Private helpers ──────────────────────────────────

    private function incrementAttempts(User $user): bool
    {
        $key = "pin:attempts:{$user->id}";
        $attempts = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, now()->addMinutes(self::LOCK_DURATION_MIN));

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::put("pin:lock:{$user->id}", true, now()->addMinutes(self::LOCK_DURATION_MIN));

            return true;
        }

        return false;
    }
}
