<?php

namespace App\Domain\Ppob\Services;

use Illuminate\Support\Facades\Cache;

/**
 * CircuitBreakerService — Implementasi Circuit Breaker Pattern per-supplier (ADR-003).
 *
 * State:
 * - CLOSED    : Normal, request diteruskan ke supplier.
 * - OPEN      : Fail-fast, request langsung ditolak (PROVIDER_UNAVAILABLE).
 * - HALF-OPEN : Cooldown selesai, uji coba 1 request untuk verifikasi pemulihan.
 */
class CircuitBreakerService
{
    public const STATE_CLOSED = 'CLOSED';

    public const STATE_OPEN = 'OPEN';

    public const STATE_HALF_OPEN = 'HALF-OPEN';

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 60
    ) {}

    /**
     * Cek apakah supplier sedang tersedia untuk menerima request.
     */
    public function isAvailable(string $supplierCode): bool
    {
        $state = $this->getState($supplierCode);

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            $openedAt = Cache::get($this->openedAtKey($supplierCode));

            if ($openedAt && (now()->timestamp - (int) $openedAt) >= $this->cooldownSeconds) {
                // Cooldown selesai: transisi ke HALF-OPEN
                $this->setState($supplierCode, self::STATE_HALF_OPEN);

                return true;
            }

            return false;
        }

        // HALF-OPEN: izinkan request percobaan
        return true;
    }

    /**
     * Catat request berhasil dari supplier.
     */
    public function recordSuccess(string $supplierCode): void
    {
        Cache::forget($this->failuresKey($supplierCode));
        Cache::forget($this->openedAtKey($supplierCode));
        $this->setState($supplierCode, self::STATE_CLOSED);
    }

    /**
     * Catat kegagalan dari supplier.
     */
    public function recordFailure(string $supplierCode): void
    {
        $state = $this->getState($supplierCode);

        if ($state === self::STATE_HALF_OPEN) {
            // Jika gagal saat status HALF-OPEN, langsung kembali ke OPEN
            $this->trip($supplierCode);

            return;
        }

        $failures = (int) Cache::get($this->failuresKey($supplierCode), 0) + 1;
        Cache::put($this->failuresKey($supplierCode), $failures, 120);

        if ($failures >= $this->failureThreshold) {
            $this->trip($supplierCode);
        }
    }

    /**
     * Paksa circuit berstatus OPEN (trip).
     */
    public function trip(string $supplierCode): void
    {
        $this->setState($supplierCode, self::STATE_OPEN);
        Cache::put($this->openedAtKey($supplierCode), now()->timestamp, $this->cooldownSeconds * 2);
    }

    /**
     * Reset circuit ke status CLOSED.
     */
    public function reset(string $supplierCode): void
    {
        Cache::forget($this->failuresKey($supplierCode));
        Cache::forget($this->openedAtKey($supplierCode));
        $this->setState($supplierCode, self::STATE_CLOSED);
    }

    public function getState(string $supplierCode): string
    {
        return (string) Cache::get($this->stateKey($supplierCode), self::STATE_CLOSED);
    }

    private function setState(string $supplierCode, string $state): void
    {
        Cache::put($this->stateKey($supplierCode), $state, $this->cooldownSeconds * 2);
    }

    private function stateKey(string $supplierCode): string
    {
        return "circuit:{$supplierCode}:state";
    }

    private function failuresKey(string $supplierCode): string
    {
        return "circuit:{$supplierCode}:failures";
    }

    private function openedAtKey(string $supplierCode): string
    {
        return "circuit:{$supplierCode}:opened_at";
    }
}
