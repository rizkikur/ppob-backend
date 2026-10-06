<?php

namespace App\Domain\Wallet\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Wallet — dompet digital user.
 *
 * Aturan keras:
 * - Saldo TIDAK BOLEH negatif (CHECK constraint di DB)
 * - Perubahan saldo HANYA boleh lewat WalletService
 * - Jangan pernah panggil Wallet::update() langsung dari luar WalletService
 *
 * Kolom balance_cents di-cast ke Money value object secara otomatis.
 */
class Wallet extends Model
{
    protected $table = 'wallets';

    protected $fillable = [
        'user_id',
        'balance_cents',
    ];

    protected $casts = [
        'balance_cents' => MoneyCast::class,
    ];

    // ─── Relasi ───────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(WalletMutation::class, 'wallet_id');
    }

    // ─── Helpers ──────────────────────────────────────────

    /** Ambil saldo sebagai Money object */
    public function balance(): Money
    {
        return $this->balance_cents ?? Money::zero();
    }

    /** Apakah saldo mencukupi untuk jumlah tertentu? */
    public function hasSufficientBalance(Money $amount): bool
    {
        return $this->balance()->isGreaterThanOrEqual($amount);
    }
}
