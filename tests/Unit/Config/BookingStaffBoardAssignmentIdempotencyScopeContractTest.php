<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class BookingStaffBoardAssignmentIdempotencyScopeContractTest extends TestCase
{
    public function test_booking_config_contains_required_staff_board_assignment_idempotency_scopes(): void
    {
        $scopes = config('booking.idempotency_required_scopes', []);

        self::assertContains('staff.reservation-assign-table', $scopes);
        self::assertContains('staff.reservation-assign-best-fit', $scopes);
    }
}
