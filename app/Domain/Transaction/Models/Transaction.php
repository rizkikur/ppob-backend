<?php

namespace App\Domain\Transaction\Models;

use App\Domain\Shared\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Transaction — transaksi PPOB.
 * Tabel di-partisi per bulan berdasarkan created_at (lihat ERD.md).
 * Aturan keras: idempotency_key wajib ada dan unik per user.
 */
class Transaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = ['user_id', 'product_id', 'inquiry_id', 'customer_number', 'amount_cents', 'sell_price_cents', 'status', 'idempotency_key', 'provider_ref', 'provider_response', 'failure_reason'];

    protected $casts = ['amount_cents' => MoneyCast::class, 'sell_price_cents' => MoneyCast::class, 'provider_response' => 'array'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';
}
