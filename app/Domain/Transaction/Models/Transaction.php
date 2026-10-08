<?php

namespace App\Domain\Transaction\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Inquiry\Models\Inquiry;
use App\Domain\Partner\Models\Partner;
use App\Domain\Partner\Models\WebhookDelivery;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\Provider;
use App\Domain\Shared\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Transaction — transaksi PPOB.
 * Tabel di-partisi per bulan berdasarkan created_at (lihat ERD.md).
 * Aturan keras: idempotency_key wajib ada dan unik per user.
 */
class Transaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'product_id',
        'inquiry_id',
        'customer_number',
        'amount_cents',
        'sell_price_cents',
        'status',
        'idempotency_key',
        'provider_ref',
        'provider_response',
        'failure_reason',
        'supplier_id',
        'original_supplier_id',
        'is_failover',
        'partner_id',
    ];

    protected $casts = [
        'amount_cents' => MoneyCast::class,
        'sell_price_cents' => MoneyCast::class,
        'provider_response' => 'array',
        'is_failover' => 'boolean',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'supplier_id');
    }

    public function originalSupplier(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'original_supplier_id');
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }
}
