<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_sums_minor_units_without_float_drift(): void
    {
        self::assertSame(30, Money::sumMinor([0.10, 0.20]));
        self::assertSame('0.30', Money::formatMinor(Money::sumMinor(['0.10', '0.20'])));
    }

    public function test_it_rounds_decimal_input_to_minor_units_once(): void
    {
        self::assertSame(101, Money::minorUnits('1.005'));
        self::assertSame('1.01', Money::format('1.005'));
        self::assertSame('0.00', Money::format('-1.00', true));
    }
}
