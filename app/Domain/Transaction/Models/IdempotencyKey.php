<?php

namespace App\Domain\Transaction\Models;

use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model IdempotencyKey — penyimpanan idempotency key per user.
 * Unique constraint: (user_id, key_value).
 * Cleanup: hapus record > 30 hari via scheduled command.
 */
class IdempotencyKey extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'idempotency_keys';

    protected $fillable = ['user_id', 'key_value', 'endpoint', 'response_code', 'response_body'];

    protected $casts = ['response_code' => 'integer', 'response_body' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
