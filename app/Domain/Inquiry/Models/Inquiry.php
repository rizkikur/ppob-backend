<?php

namespace App\Domain\Inquiry\Models;

use App\Domain\Shared\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Inquiry — hasil inquiry tagihan postpaid sebelum pembayaran.
 * Hanya untuk produk product_type=postpaid.
 * Expire setelah 10 menit — transaksi harus dilakukan sebelum kedaluwarsa.
 */
class Inquiry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'inquiries';

    protected $fillable = ['user_id', 'product_id', 'customer_number', 'amount_cents', 'admin_fee_cents', 'inquiry_ref', 'provider_response', 'status', 'expires_at'];

    protected $casts = ['amount_cents' => MoneyCast::class, 'admin_fee_cents' => MoneyCast::class, 'provider_response' => 'array', 'expires_at' => 'datetime'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_SUCCESS && ! $this->isExpired();
    }
}
