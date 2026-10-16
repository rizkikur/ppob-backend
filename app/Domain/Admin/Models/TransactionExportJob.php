<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionExportJob extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'transaction_export_jobs';

    protected $fillable = [
        'admin_id',
        'filename',
        'filters',
        'status',
        'file_path',
        'file_size_bytes',
        'total_rows',
        'processed_rows',
        'error_message',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'file_size_bytes' => 'integer',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getProgressPercentage(): int
    {
        if ($this->isCompleted()) {
            return 100;
        }

        if ($this->total_rows <= 0) {
            return $this->isProcessing() ? 50 : 0;
        }

        return (int) min(99, round(($this->processed_rows / $this->total_rows) * 100));
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size_bytes) {
            return '0 B';
        }

        $bytes = $this->file_size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
