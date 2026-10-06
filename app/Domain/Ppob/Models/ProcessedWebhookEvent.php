<?php

namespace App\Domain\Ppob\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model ProcessedWebhookEvent — log event webhook yang sudah diproses.
 * event_id bersifat UNIQUE untuk mencegah pemrosesan duplikat.
 * Aturan keras (lihat CLAUDE.md aturan 7): cek event_id sebelum proses,
 * tolak duplikat dengan HTTP 409.
 */
class ProcessedWebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'processed_webhook_events';

    protected $fillable = ['event_id', 'source', 'event_type', 'payload', 'processed_at'];

    protected $casts = ['payload' => 'array', 'processed_at' => 'datetime'];
}
