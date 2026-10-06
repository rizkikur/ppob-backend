<?php

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Eloquent cast untuk kolom bigint (cents) → Money value object.
 *
 * Cara pakai di Model:
 *   protected $casts = [
 *       'balance_cents' => MoneyCast::class,
 *       'amount_cents'  => MoneyCast::class,
 *   ];
 *
 * Setelah di-cast, properti otomatis menjadi Money object:
 *   $wallet->balance_cents->toCents()   // 50000
 *   $wallet->balance_cents->format()    // "Rp 500,00"
 */
class MoneyCast implements CastsAttributes
{
    /**
     * Konversi nilai dari database (integer cents) ke Money object.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::fromCents((int) $value);
    }

    /**
     * Konversi Money object kembali ke integer cents untuk disimpan ke database.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        if ($value instanceof Money) {
            return $value->toCents();
        }

        if (is_int($value)) {
            return $value;
        }

        throw new InvalidArgumentException(
            "Value for {$key} must be a Money instance or integer (cents). Got: ".gettype($value)
        );
    }
}
