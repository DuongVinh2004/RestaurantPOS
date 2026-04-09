<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class BookingStaffWaitingListIdempotencyScopeContractTest extends TestCase
{
    public function test_booking_config_contains_required_staff_waiting_list_idempotency_scopes(): void
    {
        $scopes = config('booking.idempotency_required_scopes', []);

        self::assertContains('staff.waiting-list-create', $scopes);
        self::assertContains('staff.waiting-list-notify', $scopes);
        self::assertContains('staff.waiting-list-seat', $scopes);
        self::assertContains('staff.waiting-list-cancel', $scopes);
        self::assertContains('staff.waiting-list-advance', $scopes);
    }
}
