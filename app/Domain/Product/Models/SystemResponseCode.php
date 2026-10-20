<?php

namespace App\Domain\Product\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemResponseCode extends Model
{
    use HasFactory;

    protected $table = 'system_response_codes';

    protected $fillable = [
        'rc',
        'slug',
        'name',
        'status_type',
        'default_message',
        'is_retryable',
        'is_system',
    ];

    protected $casts = [
        'is_retryable' => 'boolean',
        'is_system' => 'boolean',
    ];
}
