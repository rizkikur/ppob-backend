<?php

namespace App\Domain\Partner\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'partner_logs';

    protected $fillable = ['partner_id', 'endpoint', 'method', 'request_body', 'response_code', 'duration_ms', 'ip_address'];

    protected $casts = ['request_body' => 'array', 'response_code' => 'integer', 'duration_ms' => 'integer'];
}
