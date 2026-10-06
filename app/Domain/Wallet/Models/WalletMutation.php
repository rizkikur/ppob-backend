<?php

namespace App\Domain\Wallet\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Shared\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model WalletMutation — ledger append-only untuk semua perubahan saldo.
 *
 * Aturan keras (lihat CLAUDE.md aturan 2 & 3):
 * - APPEND-ONLY: jangan pernah UPDATE atau DELETE baris di tabel ini
 * - Terdapat trigger DB yang menolak UPDATE/DELETE
 * - Hanya WalletService yang boleh membuat record baru di tabel ini
 *
 * Kolom amount_cents dan balance_after_cents di-cast ke Money.
 */
class WalletMutation extends Model
{
    public const UPDATED_AT = null; // Append-only, tidak ada updated_at

    protected $table = 'wallet_mutations';

    protected $fillable = [
        'wallet_id',
        'user_id',
        'type',
        'amount_cents',
        'balance_after_cents',
        'reference_type',
        'reference_id',
        'note',
    ];

    protected $casts = [
        'amount_cents' => MoneyCast::class,
        'balance_after_cents' => MoneyCast::class,
    ];

    // ─── Konstanta tipe mutasi ─────────────────────────────

    public const TYPE_CREDIT = 'credit'; // Masuk

    public const TYPE_DEBIT = 'debit';  // Keluar

    // ─── Konstanta reference type ─────────────────────────

    public const REF_TOPUP = 'topup';

    public const REF_TRANSACTION = 'transaction';

    public const REF_REFUND = 'refund';

    public const REF_ADJUSTMENT = 'adjustment';

    // ─── Relasi ───────────────────────────────────────────

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
