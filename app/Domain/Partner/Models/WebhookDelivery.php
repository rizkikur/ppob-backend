<?php

namespace App\Domain\Partner\Models;

use App\Domain\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model WebhookDelivery — tracking pengiriman callback ke partner (ADR-008).
 * Mendukung exponential backoff retry hingga 5 attempt.
 */
class WebhookDelivery extends Model
{
    protected $table = 'webhook_deliveries';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_FAILED_PERMANENT = 'failed_permanent';

    public const MAX_ATTEMPTS = 5;

    /**
     * Delay retry per-attempt (dalam detik) sesuai ADR-008:
     * Attempt 1: Langsung (0s)
     * Attempt 2: +1 menit (60s)
     * Attempt 3: +5 menit (300s)
     * Attempt 4: +30 menit (1800s)
     * Attempt 5: +2 jam (7200s)
     */
    public const RETRY_DELAYS = [
        1 => 0,
        2 => 60,
        3 => 300,
        4 => 1800,
        5 => 7200,
    ];

    protected $fillable = [
        'partner_id',
        'transaction_id',
        'attempt',
        'status',
        'callback_url',
        'response_code',
        'response_body',
        'next_retry_at',
        'delivered_at',
    ];

    protected $casts = [
        'attempt' => 'integer',
        'response_code' => 'integer',
        'next_retry_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isFailedPermanent(): bool
    {
        return $this->status === self::STATUS_FAILED_PERMANENT;
    }

    public function scopePendingRetry(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where('next_retry_at', '<=', now());
    }

    public static function getDelayForAttempt(int $attempt): int
    {
        return self::RETRY_DELAYS[$attempt] ?? 7200;
    }
}
