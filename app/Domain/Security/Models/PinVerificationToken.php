<?php

namespace App\Domain\Security\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model PinVerificationToken — token sekali pakai pasca verifikasi PIN.
 *
 * Token ini diterbitkan setelah user berhasil memverifikasi PIN di
 * POST /security/pin/verify dan HARUS digunakan di header X-Pin-Token
 * untuk endpoint transaksi.
 *
 * Aturan keras (lihat docs/security-design.md bagian 2):
 * - Sekali pakai: setelah digunakan, used_at di-set dan tidak bisa dipakai lagi
 * - Expire 5 menit sejak dibuat
 * - PIN tidak pernah dikirim ke endpoint transaksi (hanya token ini)
 */
class PinVerificationToken extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pin_verification_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'purpose',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }
}
