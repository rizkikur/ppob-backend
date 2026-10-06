<?php

namespace Tests\Unit;

use App\Domain\Shared\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_can_create_from_cents(): void
    {
        $money = Money::fromCents(10000);
        $this->assertEquals(10000, $money->toCents());
    }

    public function test_can_add(): void
    {
        $a = Money::fromCents(5000);
        $b = Money::fromCents(3000);
        $this->assertEquals(8000, $a->add($b)->toCents());
    }

    public function test_can_subtract(): void
    {
        $a = Money::fromCents(5000);
        $b = Money::fromCents(3000);
        $this->assertEquals(2000, $a->subtract($b)->toCents());
    }

    public function test_subtract_throws_when_result_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromCents(1000)->subtract(Money::fromCents(2000));
    }

    public function test_cannot_create_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromCents(-1);
    }

    public function test_formats_rupiah(): void
    {
        $money = Money::fromCents(500000);
        $this->assertEquals('Rp 5.000,00', $money->format());
    }

    public function test_comparison(): void
    {
        $big = Money::fromCents(10000);
        $small = Money::fromCents(5000);
        $this->assertTrue($big->isGreaterThan($small));
        $this->assertFalse($small->isGreaterThan($big));
        $this->assertTrue($small->isLessThan($big));
    }
}
