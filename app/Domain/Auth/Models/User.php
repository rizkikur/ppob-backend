<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Model User — entitas utama pengguna aplikasi.
 *
 * Semua relasi dan akses data user dipusatkan di sini.
 * Jangan taruh User model di app/Models (sudah dihapus/dikosongkan).
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'user_tier_id',
        'name',
        'phone',
        'email',
        'pin_hash',
        'role',
        'fcm_token',
        'is_active',
        'is_verified',
    ];

    protected $hidden = [
        'pin_hash',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
    ];

    // ─── Relasi ───────────────────────────────────────────

    public function tier(): BelongsTo
    {
        return $this->belongsTo(UserTier::class, 'user_tier_id');
    }

    /**
     * Forward declaration — Wallet domain belum ada, gunakan string class.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne('App\Domain\Wallet\Models\Wallet', 'user_id');
    }

    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class, 'phone', 'phone');
    }

    // ─── Helpers ──────────────────────────────────────────

    /** Apakah user sudah mengatur PIN? */
    public function hasPin(): bool
    {
        return ! empty($this->pin_hash);
    }
}
