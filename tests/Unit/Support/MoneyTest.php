<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\SharedKernel\Money\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_sums_minor_units_without_float_drift(): void
    {
        self::assertSame(30, Money::sumMinor([10, 20]));
        self::assertSame('0', Money::formatMinor(Money::sumMinor(['0', '0'])));
    }

    public function test_it_rounds_decimal_input_to_minor_units_once(): void
    {
        self::assertSame(1, Money::minorUnits('1.4'));
        self::assertSame(2, Money::minorUnits('1.5'));
        self::assertSame('1', Money::format('1.4'));
        self::assertSame('0', Money::format('-1', true));
    }
}
