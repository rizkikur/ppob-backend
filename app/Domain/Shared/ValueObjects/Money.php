<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Money value object — representasi uang dalam satuan cents (sen).
 *
 * Aturan keras: semua nilai uang di aplikasi ini WAJIB menggunakan class ini.
 * Tidak boleh menggunakan float, decimal, atau integer mentah untuk menyimpan uang.
 *
 * Penyimpanan di database: bigint dalam satuan cents.
 * Tampilan: format rupiah (Rp x.xxx,xx).
 */
final class Money implements JsonSerializable, Stringable
{
    private function __construct(
        private readonly int $amount
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException("Money amount cannot be negative: {$amount}");
        }
    }

    /** Buat instance dari nilai cents (integer) */
    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /** Buat instance dari nilai rupiah (otomatis dikali 100 untuk ke cents) */
    public static function fromRupiah(int|float $rupiah): self
    {
        return new self((int) round($rupiah * 100));
    }

    /** Buat instance bernilai nol */
    public static function zero(): self
    {
        return new self(0);
    }

    /** Nilai dalam satuan cents */
    public function toCents(): int
    {
        return $this->amount;
    }

    /** Nilai dalam satuan rupiah (float) */
    public function toRupiah(): float
    {
        return $this->amount / 100;
    }

    /** Format tampilan rupiah: "Rp 10.000,00" */
    public function format(): string
    {
        return 'Rp '.number_format($this->toRupiah(), 2, ',', '.');
    }

    /** Tambah dengan Money lain */
    public function add(Money $other): self
    {
        return new self($this->amount + $other->amount);
    }

    /** Kurang dengan Money lain — hasilnya tidak boleh negatif */
    public function subtract(Money $other): self
    {
        if ($other->amount > $this->amount) {
            throw new InvalidArgumentException(
                "Cannot subtract {$other->format()} from {$this->format()}: result would be negative"
            );
        }

        return new self($this->amount - $other->amount);
    }

    /** Perbandingan */
    public function isGreaterThan(Money $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function isGreaterThanOrEqual(Money $other): bool
    {
        return $this->amount >= $other->amount;
    }

    public function isLessThan(Money $other): bool
    {
        return $this->amount < $other->amount;
    }

    public function isLessThanOrEqual(Money $other): bool
    {
        return $this->amount <= $other->amount;
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    /** {@inheritDoc} */
    public function jsonSerialize(): int
    {
        return $this->amount;
    }

    /** {@inheritDoc} */
    public function __toString(): string
    {
        return $this->format();
    }
}
