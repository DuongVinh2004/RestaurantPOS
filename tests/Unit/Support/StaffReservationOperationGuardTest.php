<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\RestaurantTableStateService;
use App\Support\StaffReservationOperationGuard;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class StaffReservationOperationGuardTest extends TestCase
{
    public function test_it_detects_checked_in_reservation_from_status_or_checked_in_at(): void
    {
        $checkedInByStatus = new Reservation([
            'status' => ReservationStatus::checkedIn(),
            'checked_in_at' => null,
        ]);
        $checkedInByTimestamp = new Reservation([
            'status' => ReservationStatus::Confirmed,
            'checked_in_at' => now(),
        ]);
        $confirmed = new Reservation([
            'status' => ReservationStatus::Confirmed,
            'checked_in_at' => null,
        ]);

        self::assertTrue(StaffReservationOperationGuard::isCheckedInReservation($checkedInByStatus));
        self::assertTrue(StaffReservationOperationGuard::isCheckedInReservation($checkedInByTimestamp));
        self::assertFalse(StaffReservationOperationGuard::isCheckedInReservation($confirmed));
    }

    public function test_reschedule_guard_rejects_checked_in_reservation_before_row_version_check(): void
    {
        $reservation = new Reservation([
            'status' => ReservationStatus::Confirmed,
            'checked_in_at' => now(),
            'row_version' => 9,
        ]);

        try {
            StaffReservationOperationGuard::assertRescheduleAllowed($reservation, 1);
            self::fail('Expected validation exception was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('status', $e->errors());
            self::assertArrayNotHasKey('row_version', $e->errors());
        }
    }

    public function test_move_table_guard_requires_checked_in_reservation(): void
    {
        $reservation = new Reservation([
            'status' => ReservationStatus::Confirmed,
            'checked_in_at' => null,
            'row_version' => 3,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only checked-in reservations can move tables.');

        StaffReservationOperationGuard::assertMoveTableAllowed($reservation, 3);
    }

    public function test_table_release_guard_preserves_blocked_operational_state_without_force(): void
    {
        $table = new RestaurantTable([
            'status' => RestaurantTableStatus::Blocked,
            'row_version' => 2,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Table is blocked/maintenance. Operational states are preserved by release flow.');

        StaffReservationOperationGuard::assertTableReleaseAllowed($table, new RestaurantTableStateService(), false);
    }

    public function test_table_row_version_guard_uses_shared_message(): void
    {
        $table = new RestaurantTable([
            'status' => RestaurantTableStatus::Available,
            'row_version' => 4,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('row_version mismatch');

        StaffReservationOperationGuard::assertExpectedTableRowVersion($table, 3);
    }
}
