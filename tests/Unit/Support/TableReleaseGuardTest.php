<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TableReleaseGuard;
use PHPUnit\Framework\TestCase;

final class TableReleaseGuardTest extends TestCase
{
    public function test_reserved_reservation_blocks_release(): void
    {
        self::assertTrue(TableReleaseGuard::isReservationBlockingRelease('Reserved', null));
    }

    public function test_future_confirmed_reservation_without_checkin_does_not_block_release(): void
    {
        self::assertFalse(TableReleaseGuard::isReservationBlockingRelease('Confirmed', null));
    }

    public function test_confirmed_reservation_with_checkin_blocks_release(): void
    {
        self::assertTrue(TableReleaseGuard::isReservationBlockingRelease('Confirmed', '2026-03-15 10:00:00'));
    }

    public function test_it_extracts_only_blocking_reservation_ids(): void
    {
        $ids = TableReleaseGuard::blockingReservationIds([
            ['reservation_id' => 10, 'status' => 'Confirmed', 'checked_in_at' => null],
            ['reservation_id' => 11, 'status' => 'Reserved', 'checked_in_at' => null],
            ['reservation_id' => 12, 'status' => 'Confirmed', 'checked_in_at' => '2026-03-15 10:00:00'],
        ]);

        self::assertSame([11, 12], $ids);
    }
}
