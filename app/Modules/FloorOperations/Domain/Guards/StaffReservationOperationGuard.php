<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Domain\Guards;

use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Policies\ReservationStatusTransitionPolicy;
use Illuminate\Validation\ValidationException;

class StaffReservationOperationGuard
{
    private const ROW_VERSION_MESSAGE = 'Data changed (row_version mismatch). Reload and try again.';

    public static function reservationStatusValue(Reservation $reservation): string
    {
        $status = $reservation->status;

        if ($status instanceof ReservationStatus) {
            return $status->value;
        }

        $raw = trim((string) $reservation->getRawOriginal('status'));
        if ($raw !== '') {
            return $raw;
        }

        return trim((string) $status);
    }

    public static function isCheckedInReservation(Reservation $reservation): bool
    {
        return ReservationStatus::isCheckedInDbValue(self::reservationStatusValue($reservation))
            || $reservation->checked_in_at !== null;
    }

    public static function assertExpectedReservationRowVersion(Reservation $reservation, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($reservation->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => [self::ROW_VERSION_MESSAGE],
            ]);
        }
    }

    public static function assertExpectedTableRowVersion(RestaurantTable $table, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($table->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => [self::ROW_VERSION_MESSAGE],
            ]);
        }
    }

    public static function assertCheckInAllowed(Reservation $reservation, ?int $expectedRowVersion): void
    {
        self::assertExpectedReservationRowVersion($reservation, $expectedRowVersion);
        ReservationStatusTransitionPolicy::assertTransitionAllowed(
            self::reservationStatusValue($reservation),
            ReservationStatus::checkedIn(),
        );
    }

    public static function assertRescheduleAllowed(Reservation $reservation, int $expectedRowVersion): void
    {
        if (self::reservationStatusValue($reservation) !== ReservationStatus::Confirmed->value) {
            throw ValidationException::withMessages([
                'status' => ['Only Confirmed reservations can be rescheduled.'],
            ]);
        }

        if (self::isCheckedInReservation($reservation)) {
            throw ValidationException::withMessages([
                'status' => ['Checked-in reservations cannot be rescheduled. Use move-table/runtime flows instead.'],
            ]);
        }

        self::assertExpectedReservationRowVersion($reservation, $expectedRowVersion);
    }

    public static function assertMoveTableAllowed(Reservation $reservation, ?int $expectedRowVersion): void
    {
        if (! self::isCheckedInReservation($reservation)) {
            throw ValidationException::withMessages([
                'status' => ['Only checked-in reservations can move tables.'],
            ]);
        }

        self::assertExpectedReservationRowVersion($reservation, $expectedRowVersion);
    }

    public static function assertTableReleaseAllowed(
        RestaurantTable $table,
        RestaurantTableStateService $tableStateService,
        bool $force
    ): void {
        $status = (string) ($table->status?->value ?? $table->status);

        if ($tableStateService->isOperationallyBlocked($status) && ! $force) {
            throw ValidationException::withMessages([
                'table_id' => ['Table is blocked/maintenance. Operational states are preserved by release flow.'],
            ]);
        }
    }
}
