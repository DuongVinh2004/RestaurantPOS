<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\UseCases\Boards;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\FloorOperations\Domain\Guards\TableReleaseGuard;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\AuditEvent;
use App\Support\AvailabilityCacheVersion;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffMoveTableService
{
    private readonly ReservationBranchScopeService $reservationBranchScopeService;

    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly RestaurantTableStateService $tableStateService,
        private readonly TableTimeConflictService $tableTimeConflictService,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
    ) {
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
    }

    public function move(
        int $reservationId,
        int $fromTableId,
        int $toTableId,
        \DateTimeInterface $movedAt,
        ?int $staffUserId = null,
        ?int $expectedRowVersion = null
    ): Reservation {
        if ($fromTableId <= 0 || $toTableId <= 0 || $fromTableId === $toTableId) {
            throw ValidationException::withMessages([
                'table_id' => ['Invalid table ids.'],
            ]);
        }

        $currentTableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->pluck('table_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $lockTableIds = array_values(array_unique(array_merge($currentTableIds, [$fromTableId, $toTableId])));
        sort($lockTableIds);

        $lockKeys = array_merge(
            [
                config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation').':'.$reservationId,
            ],
            array_map(
                fn (int $id) => config('booking.reservation_lock_prefix', 'booking:lock:table').':'.$id,
                $lockTableIds
            )
        );

        try {
            /** @var Reservation $reservation */
            $reservation = $this->locks->withLockKeys($lockKeys, function () use (
                $reservationId,
                $fromTableId,
                $toTableId,
                $movedAt,
                $staffUserId,
                $expectedRowVersion
            ) {
                return DB::transaction(function () use (
                    $reservationId,
                    $fromTableId,
                    $toTableId,
                    $movedAt,
                    $staffUserId,
                    $expectedRowVersion
                ) {
                    /** @var Reservation|null $reservation */
                    $reservation = Reservation::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->first();

                    if (! $reservation) {
                        throw new ModelNotFoundException('Reservation not found');
                    }

                    $this->assertMoveTableReservationIsCheckedIn($reservation);
                    $this->assertReservationRowVersionMatches($reservation, $expectedRowVersion);

                    $currentTableIds = DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->pluck('table_id')
                        ->map(fn ($value) => (int) $value)
                        ->all();

                    if (! in_array($fromTableId, $currentTableIds, true)) {
                        throw ValidationException::withMessages([
                            'from_table_id' => ['Reservation is not assigned to from_table.'],
                        ]);
                    }

                    $tables = RestaurantTable::query()
                        ->whereIn('table_id', array_values(array_unique(array_merge($currentTableIds, [$toTableId]))))
                        ->notDeleted()
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('table_id');

                    if ($tables->count() !== count(array_values(array_unique(array_merge($currentTableIds, [$toTableId]))))) {
                        throw ValidationException::withMessages([
                            'reservation_id' => ['Assigned tables or target table were not found.'],
                        ]);
                    }

                    if (! $tables->has($toTableId)) {
                        throw ValidationException::withMessages([
                            'to_table_id' => ['Target table not found.'],
                        ]);
                    }

                    /** @var RestaurantTable $to */
                    $to = $tables->get($toTableId);
                    $toStatus = (string) ($to->status?->value ?? $to->status);

                    if (! $this->tableStateService->isAllocatableForBooking($toStatus)) {
                        throw ValidationException::withMessages([
                            'to_table_id' => ['Target table is not currently Available.'],
                        ]);
                    }

                    $targetTableIds = array_values(array_unique(array_map(
                        fn (int $id) => $id === $fromTableId ? $toTableId : $id,
                        $currentTableIds
                    )));
                    sort($targetTableIds);

                    $this->reservationBranchScopeService->syncReservationBranchOrAssert(
                        $reservation,
                        array_map(
                            static fn (int $tableId): mixed => $tables->get($tableId)?->branch_id,
                            $targetTableIds,
                        ),
                        $staffUserId,
                        'Assigned tables must belong to a single branch.',
                        'Reservation branch does not match the assigned table branch.',
                        'reservation_id',
                    );

                    $capacity = DB::table('restaurant_tables as rt')
                        ->leftJoin('table_templates as tt', 'tt.template_id', '=', 'rt.template_id')
                        ->whereIn('rt.table_id', $targetTableIds)
                        ->sum(DB::raw('COALESCE(tt.seats, 0)'));

                    if ((int) $capacity < (int) $reservation->guest_count) {
                        throw ValidationException::withMessages([
                            'to_table_id' => ['Target table combination does not have enough seats for this reservation.'],
                        ]);
                    }

                    $reservationConflictIds = $this->tableTimeConflictService->findReservationConflictTableIds(
                        tableIds: [$toTableId],
                        start: $reservation->start_time->copy()->utc(),
                        end: $reservation->end_time->copy()->utc(),
                        ignoreReservationId: $reservationId,
                        lock: true,
                    );

                    if ($reservationConflictIds !== []) {
                        throw ValidationException::withMessages([
                            'to_table_id' => ['Target table already has an overlapping reservation.'],
                        ]);
                    }

                    $holdConflictIds = $this->tableTimeConflictService->findHoldConflictTableIds(
                        tableIds: [$toTableId],
                        start: $reservation->start_time->copy()->utc(),
                        end: $reservation->end_time->copy()->utc(),
                        lock: true,
                    );

                    if ($holdConflictIds !== []) {
                        throw ValidationException::withMessages([
                            'to_table_id' => ['Target table already has an overlapping table hold.'],
                        ]);
                    }

                    DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->where('table_id', $fromTableId)
                        ->delete();

                    $existsTo = DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->where('table_id', $toTableId)
                        ->exists();

                    if (! $existsTo) {
                        DB::table('reservation_tables')->insert([
                            'reservation_id' => $reservationId,
                            'table_id' => $toTableId,
                        ]);
                    }

                    $this->assertFromTableSafeToRelease(
                        tableId: $fromTableId,
                        tableBranchId: (int) ($tables->get($fromTableId)?->branch_id ?? 0),
                        currentReservationId: $reservationId,
                    );

                    $this->tableStateService->releaseTablesSafely(
                        [$fromTableId],
                        null,
                        $staffUserId,
                        [
                            'reservation_id' => $reservationId,
                            'source' => 'staff_move_table',
                            'reason' => 'move_from_table',
                            'counterpart_table_id' => $toTableId,
                        ]
                    );

                    $this->tableStateService->occupyTables(
                        [$toTableId],
                        null,
                        $staffUserId,
                        [
                            'reservation_id' => $reservationId,
                            'source' => 'staff_move_table',
                            'reason' => 'move_to_table',
                            'counterpart_table_id' => $fromTableId,
                        ]
                    );

                    $reservation->updated_by = $staffUserId;
                    $reservation->save();

                    AvailabilityCacheVersion::bump();

                    AuditEvent::info('staff.reservation.table_moved', [
                        'reservation_id' => $reservationId,
                        'from_table_id' => $fromTableId,
                        'to_table_id' => $toTableId,
                        'moved_at' => $movedAt->format(DATE_ATOM),
                        'staff_user_id' => $staffUserId,
                        'table_ids_after' => $targetTableIds,
                    ]);

                    return $reservation;
                });
            });

            app(OperationalRealtimeService::class)->publishBoardEvent(
                'reservation.table_moved',
                [
                    'reservation_id' => (int) $reservationId,
                    'from_table_id' => (int) $fromTableId,
                    'to_table_id' => (int) $toTableId,
                ],
                ['board', 'timeline'],
            );

            return $reservation;
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    private function assertMoveTableReservationIsCheckedIn(Reservation $reservation): void
    {
        if (! $this->isCheckedInReservation($reservation)) {
            throw ValidationException::withMessages([
                'status' => ['Only checked-in reservations can move tables.'],
            ]);
        }
    }

    private function assertReservationRowVersionMatches(Reservation $reservation, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        $currentRowVersion = (int) ($reservation->row_version ?? 1);

        if ($currentRowVersion !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['DÃ¡Â»Â¯ liÃ¡Â»â€¡u Ã„â€˜ÃƒÂ£ thay Ã„â€˜Ã¡Â»â€¢i (row_version mismatch). HÃƒÂ£y reload rÃ¡Â»â€œi thÃ¡Â»Â­ lÃ¡ÂºÂ¡i.'],
            ]);
        }
    }

    private function isCheckedInReservation(Reservation $reservation): bool
    {
        if ($reservation->checked_in_at !== null) {
            return true;
        }

        $rawStatus = $reservation->status instanceof ReservationStatus
            ? $reservation->status->value
            : (string) $reservation->getRawOriginal('status');

        return ReservationStatus::isCheckedInDbValue($rawStatus);
    }

    private function assertFromTableSafeToRelease(int $tableId, int $tableBranchId, int $currentReservationId): void
    {
        $remainingActiveReservations = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->where('rt.table_id', $tableId)
            ->where('rt.reservation_id', '!=', $currentReservationId)
            ->whereIn('r.status', ReservationStatus::activeDbValues())
            ->select('r.reservation_id', 'r.status', 'r.checked_in_at', 'r.branch_id', 'r.start_time', 'r.end_time')
            ->lockForUpdate()
            ->get();

        foreach ($remainingActiveReservations as $reservation) {
            $this->reservationBranchScopeService->assertReservationMatchesTableBranch(
                $reservation->branch_id ?? null,
                $tableBranchId,
                'Reservation branch does not match the source table branch being released.',
                'from_table_id',
            );
        }

        $blockingReservationIds = TableReleaseGuard::blockingReservationIds($remainingActiveReservations, now('UTC'));
        if ($blockingReservationIds !== []) {
            throw ValidationException::withMessages([
                'from_table_id' => [
                    'Original table still has another active service context: '
                    .implode(',', $blockingReservationIds)
                    .'. Resolve that reservation before retrying the move.',
                ],
            ]);
        }

        $activeOrderExists = DB::table('reservation_orders as ro')
            ->join('reservation_tables as rt', 'rt.reservation_id', '=', 'ro.reservation_id')
            ->where('rt.table_id', $tableId)
            ->where('rt.reservation_id', '!=', $currentReservationId)
            ->where('ro.status', ReservationOrderStatus::Active->value)
            ->lockForUpdate()
            ->exists();

        if ($activeOrderExists) {
            throw ValidationException::withMessages([
                'from_table_id' => ['Original table still has another live order context and cannot be released.'],
            ]);
        }
    }
}


