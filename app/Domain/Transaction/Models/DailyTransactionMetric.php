<?php

namespace App\Domain\Transaction\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model DailyTransactionMetric — agregasi metrik transaksi per jam dan hari.
 * Digunakan khusus untuk dashboard charts dan KPI roll-ups agar tidak melakukan
 * pemindaian (table scan) berulang pada tabel transaksi yang terpartisi.
 */
class DailyTransactionMetric extends Model
{
    protected $table = 'daily_transaction_metrics';

    protected $fillable = [
        'metric_date',
        'hour',
        'total_count',
        'success_count',
        'failed_count',
        'pending_count',
        'gmv_cents',
        'cost_cents',
        'profit_cents',
    ];

    protected $casts = [
        'metric_date' => 'string',
        'hour' => 'integer',
        'total_count' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'pending_count' => 'integer',
        'gmv_cents' => 'integer',
        'cost_cents' => 'integer',
        'profit_cents' => 'integer',
    ];

    /**
     * Pastikan format metric_date selalu YYYY-MM-DD.
     */
    public function setMetricDateAttribute($value): void
    {
        $this->attributes['metric_date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : \Carbon\Carbon::parse($value)->format('Y-m-d');
    }

    /**
     * Konversi GMV cents ke Rupiah integer.
     */
    public function getGmvRupiahAttribute(): int
    {
        return (int) ($this->gmv_cents / 100);
    }

    /**
     * Konversi Profit cents ke Rupiah integer.
     */
    public function getProfitRupiahAttribute(): int
    {
        return (int) ($this->profit_cents / 100);
    }
}
