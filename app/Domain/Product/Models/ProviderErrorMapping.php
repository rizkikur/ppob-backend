<?php

namespace App\Domain\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderErrorMapping extends Model
{
    protected $table = 'provider_error_mappings';

    protected $fillable = [
        'provider_id',
        'upstream_code',
        'internal_code',
        'custom_message',
        'is_retryable',
    ];

    protected $casts = [
        'is_retryable' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }
}
