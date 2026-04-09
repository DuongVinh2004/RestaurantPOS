<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ReservationStatus;
use PHPUnit\Framework\TestCase;

class ReservationStatusSemanticsTest extends TestCase
{
    public function test_checked_in_helpers_keep_backward_compatible_reserved_value_explicit(): void
    {
        self::assertSame(ReservationStatus::Reserved, ReservationStatus::checkedIn());
        self::assertSame('Reserved', ReservationStatus::checkedInDbValue());
        self::assertTrue(ReservationStatus::isCheckedInDbValue('Reserved'));
        self::assertFalse(ReservationStatus::isCheckedInDbValue('Confirmed'));
        self::assertSame(['Confirmed', 'Reserved'], ReservationStatus::activeDbValues());
        self::assertSame(['Confirmed', 'Reserved'], ReservationStatus::cancellableDbValues());
        self::assertTrue(ReservationStatus::isActiveDbValue('Confirmed'));
        self::assertTrue(ReservationStatus::isActiveDbValue('Reserved'));
        self::assertFalse(ReservationStatus::isActiveDbValue('Completed'));
    }
}
