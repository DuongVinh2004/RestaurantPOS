<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Policies;

use App\Enums\ReservationStatus;
use App\Modules\Reservations\Domain\Policies\ReservationStatusTransitionPolicy;
use Tests\TestCase;

final class ReservationStatusTransitionPolicyTest extends TestCase
{
    public function test_reservation_transition_matrix_covers_valid_and_invalid_paths(): void
    {
        $cases = [
            [ReservationStatus::Confirmed, ReservationStatus::checkedIn(), false, true],
            [ReservationStatus::Confirmed, ReservationStatus::Cancelled, false, true],
            [ReservationStatus::Confirmed, ReservationStatus::Expired, false, true],
            [ReservationStatus::Confirmed, ReservationStatus::NoShow, false, true],
            [ReservationStatus::Confirmed, ReservationStatus::Completed, false, false],
            [ReservationStatus::checkedIn(), ReservationStatus::Completed, false, true],
            [ReservationStatus::checkedIn(), ReservationStatus::Cancelled, false, false],
            [ReservationStatus::checkedIn(), ReservationStatus::Cancelled, true, true],
            [ReservationStatus::checkedIn(), ReservationStatus::Expired, false, false],
            [ReservationStatus::Completed, ReservationStatus::Cancelled, false, false],
            [ReservationStatus::Cancelled, ReservationStatus::checkedIn(), false, false],
            [ReservationStatus::NoShow, ReservationStatus::Confirmed, false, false],
        ];

        foreach ($cases as [$from, $to, $forceCancel, $expected]) {
            self::assertSame(
                $expected,
                ReservationStatusTransitionPolicy::canTransition($from, $to, $forceCancel),
                sprintf('Unexpected reservation transition result for %s -> %s.', $from->value, $to->value),
            );
        }
    }
}
