<?php

namespace App\Domain\Wallet\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Shared\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model TopupRequest — permintaan top-up saldo dompet.
 *
 * Mendukung dua metode:
 * - payment_gateway: otomatis via Midtrans/Xendit, konfirmasi via webhook
 * - manual_transfer: transfer bank manual, dikonfirmasi admin
 */
class TopupRequest extends Model
{
    protected $table = 'topup_requests';

    protected $fillable = [
        'user_id',
        'amount_cents',
        'method',
        'payment_gateway',
        'gateway_ref',
        'gateway_payload',
        'status',
        'idempotency_key',
        'paid_at',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'amount_cents' => MoneyCast::class,
        'gateway_payload' => 'array',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    // ─── Konstanta status ─────────────────────────────────

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    // ─── Relasi ───────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // ─── Helpers ──────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getPaymentUrl(): ?string
    {
        return $this->gateway_payload['payment_url'] ?? null;
    }
}
