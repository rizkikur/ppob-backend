<?php

namespace App\Domain\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operator extends Model
{
    protected $table = 'operators';

    protected $fillable = [
        'category_id',
        'name',
        'code',
        'icon_url',
        'prefix_rules',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'prefix_rules' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'operator_id');
    }

    /**
     * Deteksi operator secara otomatis berdasarkan awalan nomor telepon tujuan.
     * Contoh: '08123456789' atau '+628123456789' -> Operator Telkomsel.
     */
    public static function detectByDestinationNumber(string $destinationNumber): ?self
    {
        // Normalisasi format: hilangkan spasi, tanda minus, dan ubah +62 / 62 menjadi 0
        $cleanNumber = preg_replace('/[^0-9]/', '', $destinationNumber);
        if (str_starts_with($cleanNumber, '62')) {
            $cleanNumber = '0' . substr($cleanNumber, 2);
        }

        if (strlen($cleanNumber) < 4) {
            return null;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, self> $operators */
        $operators = self::query()
            ->active()
            ->whereNotNull('prefix_rules')
            ->orderBy('sort_order', 'asc')
            ->get();

        foreach ($operators as $operator) {
            $prefixes = (array) ($operator->prefix_rules ?? []);
            foreach ($prefixes as $prefix) {
                $trimmedPrefix = trim((string) $prefix);
                if ($trimmedPrefix !== '' && str_starts_with($cleanNumber, $trimmedPrefix)) {
                    return $operator;
                }
            }
        }

        return null;
    }
}
