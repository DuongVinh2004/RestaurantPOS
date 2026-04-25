<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\UseCases\CheckIn;

use App\Enums\ReservationStatus;
use App\Enums\TableHoldStatus;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\FloorOperations\Application\Queries\StaffCheckInReadinessService;
use App\Modules\FloorOperations\Domain\Guards\StaffReservationOperationGuard;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\AuditEvent;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffCheckInService
{
    private readonly StaffCheckInReadinessService $checkInReadinessService;

    private readonly ?StaffBranchContextService $staffBranchContextService;

    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly NotificationOutboxService $notificationOutboxService,
        private readonly RestaurantTableStateService $tableStateService,
        mixed $checkInReadinessServiceOrConflicts = null,
        ?RuntimeSettingService $runtimeSettings = null,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
        ?StaffBranchContextService $staffBranchContextService = null,
    ) {
        $this->staffBranchContextService = $staffBranchContextService;

        if ($checkInReadinessServiceOrConflicts instanceof StaffCheckInReadinessService) {
            $this->checkInReadinessService = $checkInReadinessServiceOrConflicts;

            return;
        }

        $tableTimeConflictService = $checkInReadinessServiceOrConflicts instanceof TableTimeConflictService
            ? $checkInReadinessServiceOrConflicts
            : app(TableTimeConflictService::class);

        $this->checkInReadinessService = new StaffCheckInReadinessService(
            $this->tableStateService,
            $tableTimeConflictService,
            $runtimeSettings ?? app(RuntimeSettingService::class),
            $reservationBranchScopeService ?? app(ReservationBranchScopeService::class),
        );
    }

    public function checkIn(int $reservationId, ?array $tableIds, \DateTimeInterface $checkedInAt, ?int $staffUserId = null, array $ignoredHoldIds = [], bool $skipLocking = false, ?int $expectedRowVersion = null): Reservation
    {
        $requestedTableIds = $tableIds === null ? [] : array_values(array_unique(array_map('intval', $tableIds)));
        sort($requestedTableIds);
        $ignoredHoldIds = array_values(array_unique(array_filter(array_map('strval', $ignoredHoldIds), static fn (string $value) => $value !== '')));

        $runner = function () use ($reservationId, $requestedTableIds, $checkedInAt, $staffUserId, $ignoredHoldIds, $expectedRowVersion) {
            return DB::transaction(function () use ($reservationId, $requestedTableIds, $checkedInAt, $staffUserId, $ignoredHoldIds, $expectedRowVersion) {
                /** @var Reservation $reservation */
                $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->first();
                if (! $reservation) {
                    throw new ModelNotFoundException('Reservation not found');
                }
                $assignedTableIds = DB::table('reservation_tables')
                    ->where('reservation_id', $reservationId)
                    ->lockForUpdate()
                    ->orderBy('reservation_table_id')
                    ->pluck('table_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $tables = RestaurantTable::query()
                    ->whereIn('table_id', $assignedTableIds)
                    ->notDeleted()
                    ->lockForUpdate()
                    ->get();
                $this->assertOperationalBranchAccessible(
                    $this->resolveOperationalBranchId($reservation, $tables),
                    $staffUserId,
                );

                if (StaffReservationOperationGuard::isCheckedInReservation($reservation)) {
                    return [
                        'reservation' => $reservation,
                        'mutated' => false,
                        'table_ids' => [],
                    ];
                }

                StaffReservationOperationGuard::assertCheckInAllowed($reservation, $expectedRowVersion);
                $checked = Carbon::instance(\DateTimeImmutable::createFromInterface($checkedInAt));

                if (count($assignedTableIds) === 0) {
                    throw ValidationException::withMessages(['reservation_id' => 'Reservation has no assigned tables to check in.']);
                }

                if ($requestedTableIds !== []) {
                    $sortedAssignedTableIds = $assignedTableIds;
                    sort($sortedAssignedTableIds);
                    if ($requestedTableIds !== $sortedAssignedTableIds) {
                        throw ValidationException::withMessages(['table_ids' => 'Check-in cannot change assigned tables. Use move-table flow for reassignment.']);
                    }
                }

                $tableIds = $assignedTableIds;
                $ignoredHoldIdsForReservation = array_values(array_unique(array_merge(
                    $ignoredHoldIds,
                    $this->resolveConfirmedHoldIdsForReservation($reservation, $tableIds, true),
                )));
                $this->checkInReadinessService->assertReadyForWrite(
                    $reservation,
                    $checked,
                    $tableIds,
                    $tables,
                    ignoredHoldIds: $ignoredHoldIdsForReservation,
                    lock: true,
                    updatedBy: $staffUserId,
                );

                $reservation->status = ReservationStatus::checkedIn();
                $reservation->checked_in_at = $checkedInAt;
                $reservation->updated_by = $staffUserId;
                $reservation->save();

                $this->tableStateService->occupyTables(
                    $tableIds,
                    Carbon::instance(\DateTimeImmutable::createFromInterface($checkedInAt))->utc(),
                    $staffUserId,
                    [
                        'reservation_id' => (int) $reservationId,
                        'source' => 'staff_check_in',
                        'reason' => 'reservation_check_in',
                    ]
                );

                $reservation->loadMissing('user', 'tables', 'payments');
                $this->notificationOutboxService->enqueueReservationCheckedIn($reservation);

                AuditEvent::info('staff.reservation.checked_in', [
                    'reservation_id' => (int) $reservationId,
                    'table_ids' => $tableIds,
                    'checked_in_at' => $checkedInAt->format(DATE_ATOM),
                    'staff_user_id' => $staffUserId,
                ]);

                return [
                    'reservation' => $reservation,
                    'mutated' => true,
                    'table_ids' => $tableIds,
                ];
            });
        };

        try {
            if ($skipLocking) {
                $result = $runner();
            } else {
                $lockTableIds = $requestedTableIds;
                if ($lockTableIds === []) {
                    $lockTableIds = DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->orderBy('table_id')
                        ->pluck('table_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                }
                sort($lockTableIds);

                $lockKeys = array_merge([
                    config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation').':'.$reservationId,
                ], array_map(fn (int $id) => config('booking.reservation_lock_prefix', 'booking:lock:table').':'.$id, $lockTableIds));

                $result = $this->locks->withLockKeys($lockKeys, $runner);
            }

            /** @var Reservation $reservation */
            $reservation = $result['reservation'];
            if (($result['mutated'] ?? false) === true) {
                app(OperationalRealtimeService::class)->publishBoardEvent(
                    'reservation.checked_in',
                    [
                        'reservation_id' => (int) $reservationId,
                        'table_ids' => array_values(array_map('intval', (array) ($result['table_ids'] ?? []))),
                        'checked_in_at' => Carbon::instance(\DateTimeImmutable::createFromInterface($checkedInAt))->utc()->toIso8601String(),
                    ],
                    ['board', 'timeline'],
                );
            }

            return $reservation;
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    /**
     * @param  array<int,int>  $tableIds
     * @return array<int,string>
     */
    private function resolveConfirmedHoldIdsForReservation(Reservation $reservation, array $tableIds, bool $lock = false): array
    {
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        sort($tableIds);

        if ($tableIds === []) {
            return [];
        }

        $startUtc = Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->start_time))->utc();
        $endUtc = Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->end_time))->utc();

        $query = DB::table('table_hold_details as thd')
            ->join('table_holds as th', 'th.hold_id', '=', 'thd.hold_id')
            ->whereIn('thd.table_id', $tableIds)
            ->where('th.confirmed_reservation_id', (int) $reservation->reservation_id)
            ->where('th.hold_status', TableHoldStatus::Confirmed->value)
            ->where('th.start_time', '<', $endUtc)
            ->where('th.end_time', '>', $startUtc);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->distinct()
            ->pluck('th.hold_id')
            ->map(static fn ($holdId): string => (string) $holdId)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RestaurantTable>  $tables
     */
    private function resolveOperationalBranchId(Reservation $reservation, Collection $tables): ?int
    {
        $reservationBranchId = (int) ($reservation->branch_id ?? 0);
        if ($reservationBranchId > 0) {
            return $reservationBranchId;
        }

        $tableBranchIds = $tables
            ->pluck('branch_id')
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->filter(static fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->values();

        if ($tableBranchIds->count() !== 1) {
            return null;
        }

        return (int) $tableBranchIds->first();
    }

    private function assertOperationalBranchAccessible(?int $branchId, ?int $staffUserId): void
    {
        if ($staffUserId === null || $staffUserId <= 0 || $branchId === null || $branchId <= 0) {
            return;
        }

        $this->staffBranchContextService()->assertAccessibleBranch($staffUserId, $branchId);
    }

    private function staffBranchContextService(): StaffBranchContextService
    {
        return $this->staffBranchContextService ?? app(StaffBranchContextService::class);
    }
}
