<?php

namespace App\Domain\Auth\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model OtpCode — kode OTP yang dikirim ke WhatsApp user.
 *
 * Skema tabel (otp_codes):
 *   - phone: nomor telepon penerima
 *   - code: 6-digit OTP
 *   - type: purpose (register|login|reset_pin)
 *   - attempts: jumlah percobaan verifikasi
 *   - expires_at: waktu kadaluarsa
 *   - verified_at: waktu diverifikasi (null = belum)
 *
 * Aturan:
 * - Maks 3 percobaan per kode
 * - Expire 5 menit sejak dibuat
 * - Setelah terverifikasi, set verified_at dan tidak dapat dipakai lagi
 */
class OtpCode extends Model
{
    public const UPDATED_AT = null; // Tabel tidak punya updated_at

    protected $table = 'otp_codes';

    protected $fillable = [
        'phone',
        'code',
        'type',
        'attempts',
        'expires_at',
        'verified_at',
        'purpose',
        'attempt_count',
        'used_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    // ─── Relasi ───────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'phone', 'phone');
    }

    // ─── Scopes ───────────────────────────────────────────

    /** OTP yang masih aktif (belum diverifikasi dan belum expire) */
    public function scopeActive($query): Builder
    {
        return $query
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', 3);
    }

    // ─── Helpers (sesuai spec Phase 1) ────────────────────

    public function getPurposeAttribute(): ?string
    {
        return $this->type;
    }

    public function setPurposeAttribute(?string $value): void
    {
        $this->attributes['type'] = $value;
    }

    public function getAttemptCountAttribute(): int
    {
        return (int) ($this->attributes['attempts'] ?? 0);
    }

    public function setAttemptCountAttribute(int $value): void
    {
        $this->attributes['attempts'] = $value;
    }

    public function getUsedAtAttribute(): ?Carbon
    {
        return $this->verified_at ? \Illuminate\Support\Carbon::parse($this->verified_at) : null;
    }

    public function setUsedAtAttribute($value): void
    {
        $this->attributes['verified_at'] = $value;
    }

    /**
     * Apakah OTP sudah kadaluarsa?
     * Spec: isExpired() → now()->gt($this->expires_at)
     */
    public function isExpired(): bool
    {
        return now()->gt($this->expires_at);
    }

    /**
     * Apakah OTP sudah digunakan/diverifikasi?
     * Spec: isUsed() → $this->verified_at !== null
     * (DB column: verified_at, alias used_at di spec)
     */
    public function isUsed(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Apakah OTP sudah melebihi batas percobaan?
     * Spec: isExhausted() → $this->attempt_count >= 3
     * (DB column: attempts, alias attempt_count di spec)
     */
    public function isExhausted(): bool
    {
        return $this->attempt_count >= 3;
    }

    /** @deprecated Gunakan isUsed() — alias untuk kompatibilitas */
    public function isVerified(): bool
    {
        return $this->isUsed();
    }

    /** @deprecated Gunakan isExhausted() — alias untuk kompatibilitas */
    public function isMaxAttempts(): bool
    {
        return $this->isExhausted();
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed() && ! $this->isExhausted();
    }
}
