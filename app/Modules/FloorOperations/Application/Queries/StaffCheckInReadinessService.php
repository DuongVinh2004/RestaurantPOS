<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\Queries;

use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\FloorOperations\Domain\Guards\StaffReservationOperationGuard;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StaffCheckInReadinessService
{
    private readonly ReservationBranchScopeService $reservationBranchScopeService;

    public function __construct(
        private readonly RestaurantTableStateService $tableStateService,
        private readonly TableTimeConflictService $tableTimeConflictService,
        private readonly RuntimeSettingService $runtimeSettings,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
    ) {
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
    }

    /**
     * @param  array<int,int>  $assignedTableIds
     * @param  iterable<mixed>  $tables
     * @param  array<int,int>|null  $reservationConflictTableIds
     * @param  array<int,int>|null  $holdConflictTableIds
     * @param  array<int,string>  $ignoredHoldIds
     * @return array<string,mixed>
     */
    public function describe(
        Reservation $reservation,
        Carbon $checkInAt,
        array $assignedTableIds,
        iterable $tables,
        ?array $reservationConflictTableIds = null,
        ?array $holdConflictTableIds = null,
        array $ignoredHoldIds = [],
        bool $lock = false,
        bool $syncReservationBranch = false,
        ?int $updatedBy = null,
    ): array {
        $assignedTableIds = $this->normalizeIds($assignedTableIds);
        $tables = Collection::make($tables)->values();
        $loadedTableIds = $tables
            ->map(static fn (mixed $table): int => (int) data_get($table, 'table_id', 0))
            ->filter(static fn (int $tableId): bool => $tableId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $missingAssignedTableIds = array_values(array_diff($assignedTableIds, $loadedTableIds));
        $status = (string) ($reservation->status?->value ?? $reservation->status ?? '');
        $isTerminal = in_array($status, [
            ReservationStatus::Cancelled->value,
            ReservationStatus::Completed->value,
            ReservationStatus::Expired->value,
            ReservationStatus::NoShow->value,
        ], true);
        $alreadyCheckedIn = StaffReservationOperationGuard::isCheckedInReservation($reservation);
        $window = $this->buildCheckInWindow($reservation, $checkInAt);

        $branchConsistent = false;
        $branchValidation = null;
        if ($assignedTableIds !== [] && $missingAssignedTableIds === []) {
            try {
                $tableBranchIds = $tables
                    ->map(static fn (mixed $table): mixed => data_get($table, 'branch_id'))
                    ->all();

                if ($syncReservationBranch) {
                    $this->reservationBranchScopeService->syncReservationBranchOrAssert(
                        $reservation,
                        $tableBranchIds,
                        $updatedBy,
                        'Assigned tables must belong to a single branch.',
                        'Reservation branch does not match its assigned tables.',
                        'reservation_id',
                    );
                } else {
                    $this->reservationBranchScopeService->assertReservationMatchesTableBranchesInMemory(
                        $reservation->branch_id,
                        $tableBranchIds,
                        'Assigned tables must belong to a single branch.',
                        'Reservation branch does not match its assigned tables.',
                        'reservation_id',
                    );
                }

                $branchConsistent = true;
            } catch (ValidationException $exception) {
                $branchValidation = $exception;
            }
        }

        $nonServiceReadyTableIds = $tables
            ->filter(function (mixed $table): bool {
                $status = $this->resolveTableStatus($table);

                return $this->tableStateService->isOperationallyBlocked($status)
                    || $status === RestaurantTableStatus::Occupied->value;
            })
            ->map(static fn (mixed $table): int => (int) data_get($table, 'table_id', 0))
            ->filter(static fn (int $tableId): bool => $tableId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $reservationConflictTableIds = $reservationConflictTableIds !== null
            ? $this->normalizeIds($reservationConflictTableIds)
            : $this->tableTimeConflictService->findReservationConflictTableIds(
                tableIds: $assignedTableIds,
                start: Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->start_time))->utc(),
                end: Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->end_time))->utc(),
                ignoreReservationId: (int) $reservation->reservation_id,
                lock: $lock,
            );

        $holdConflictTableIds = $holdConflictTableIds !== null
            ? $this->normalizeIds($holdConflictTableIds)
            : $this->tableTimeConflictService->findHoldConflictTableIds(
                tableIds: $assignedTableIds,
                start: Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->start_time))->utc(),
                end: Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->end_time))->utc(),
                ignoredHoldIds: $ignoredHoldIds,
                lock: $lock,
            );

        $available = ! $isTerminal
            && $status === ReservationStatus::Confirmed->value
            && ! $alreadyCheckedIn
            && $assignedTableIds !== []
            && $missingAssignedTableIds === []
            && $branchConsistent
            && $nonServiceReadyTableIds === []
            && $reservationConflictTableIds === []
            && $holdConflictTableIds === []
            && $window['within_window'];

        return [
            'available' => $available,
            'blocked_reason_code' => $available ? null : $this->resolveBlockedReason(
                $status,
                $isTerminal,
                $alreadyCheckedIn,
                $assignedTableIds,
                $missingAssignedTableIds,
                $branchConsistent,
                $nonServiceReadyTableIds,
                $reservationConflictTableIds,
                $holdConflictTableIds,
                $checkInAt,
                $window['start_utc'],
                $window['end_utc'],
            ),
            'status' => $status,
            'assigned_table_ids' => $assignedTableIds,
            'missing_assigned_table_ids' => $missingAssignedTableIds,
            'window' => $window,
            'checks' => [
                'status_confirmed' => $status === ReservationStatus::Confirmed->value,
                'is_terminal' => $isTerminal,
                'already_checked_in' => $alreadyCheckedIn,
                'has_assigned_tables' => $assignedTableIds !== [],
                'assigned_tables_loaded' => $missingAssignedTableIds === [],
                'within_check_in_window' => $window['within_window'],
                'branch_consistent' => $branchConsistent,
                'all_assigned_tables_available' => $nonServiceReadyTableIds === [],
                'has_assigned_table_hold_conflict' => $holdConflictTableIds !== [],
                'has_assigned_table_reservation_conflict' => $reservationConflictTableIds !== [],
            ],
            'non_service_ready_table_ids' => $nonServiceReadyTableIds,
            'reservation_conflict_table_ids' => $reservationConflictTableIds,
            'hold_conflict_table_ids' => $holdConflictTableIds,
            'branch_validation' => $branchValidation,
        ];
    }

    /**
     * @param  array<int,int>  $assignedTableIds
     * @param  iterable<mixed>  $tables
     * @param  array<int,string>  $ignoredHoldIds
     * @return array<string,mixed>
     */
    public function assertReadyForWrite(
        Reservation $reservation,
        Carbon $checkedInAt,
        array $assignedTableIds,
        iterable $tables,
        array $ignoredHoldIds = [],
        bool $lock = false,
        ?int $updatedBy = null,
    ): array {
        $readiness = $this->describe(
            reservation: $reservation,
            checkInAt: $checkedInAt,
            assignedTableIds: $assignedTableIds,
            tables: $tables,
            reservationConflictTableIds: null,
            holdConflictTableIds: null,
            ignoredHoldIds: $ignoredHoldIds,
            lock: $lock,
            syncReservationBranch: true,
            updatedBy: $updatedBy,
        );

        if ($readiness['checks']['within_check_in_window'] !== true) {
            throw ValidationException::withMessages([
                'checked_in_at' => [
                    sprintf(
                        'Outside check-in grace window. Allowed between %s and %s UTC.',
                        $readiness['window']['start_utc']->toIso8601String(),
                        $readiness['window']['end_utc']->toIso8601String(),
                    ),
                ],
            ]);
        }

        if ($readiness['checks']['has_assigned_tables'] !== true) {
            throw ValidationException::withMessages(['reservation_id' => 'Reservation has no assigned tables to check in.']);
        }

        if ($readiness['checks']['assigned_tables_loaded'] !== true) {
            throw ValidationException::withMessages(['table_ids' => 'Some assigned tables were not found.']);
        }

        if ($readiness['branch_validation'] instanceof ValidationException) {
            throw $readiness['branch_validation'];
        }

        if ($readiness['non_service_ready_table_ids'] !== []) {
            $tableId = (int) ($readiness['non_service_ready_table_ids'][0] ?? 0);
            $table = Collection::make($tables)->first(
                static fn (mixed $row): bool => (int) data_get($row, 'table_id', 0) === $tableId
            );
            $status = $this->resolveTableStatus($table);

            if ($status === RestaurantTableStatus::Occupied->value) {
                throw ValidationException::withMessages(['table_ids' => "Table {$tableId} is already occupied."]);
            }

            throw ValidationException::withMessages(['table_ids' => "Table {$tableId} is not available for service."]);
        }

        if ($readiness['reservation_conflict_table_ids'] !== []) {
            throw ValidationException::withMessages([
                'table_ids' => [
                    'Assigned tables have overlapping reservations at check-in time: '
                    .implode(',', $readiness['reservation_conflict_table_ids']).'.',
                ],
            ]);
        }

        if ($readiness['hold_conflict_table_ids'] !== []) {
            throw ValidationException::withMessages([
                'table_ids' => [
                    'Assigned tables still have active overlapping holds: '
                    .implode(',', $readiness['hold_conflict_table_ids'])
                    .'. Release or wait for the hold to expire first.',
                ],
            ]);
        }

        return $readiness;
    }

    /**
     * @return array{start_utc:Carbon,end_utc:Carbon,within_window:bool}
     */
    private function buildCheckInWindow(Reservation $reservation, Carbon $checkInAt): array
    {
        $graceMinutes = $this->resolveCheckInGraceMinutes();
        $startUtc = Carbon::instance(\DateTimeImmutable::createFromInterface($reservation->start_time))->utc();
        $windowStartUtc = $startUtc->copy()->subMinutes($graceMinutes);
        $windowEndUtc = $startUtc->copy()->addMinutes($graceMinutes);

        return [
            'start_utc' => $windowStartUtc,
            'end_utc' => $windowEndUtc,
            'within_window' => $checkInAt->betweenIncluded($windowStartUtc, $windowEndUtc),
        ];
    }

    /**
     * @param  array<int,int>  $assignedTableIds
     * @param  array<int,int>  $missingAssignedTableIds
     * @param  array<int,int>  $nonServiceReadyTableIds
     * @param  array<int,int>  $reservationConflictTableIds
     * @param  array<int,int>  $holdConflictTableIds
     */
    private function resolveBlockedReason(
        string $status,
        bool $isTerminal,
        bool $alreadyCheckedIn,
        array $assignedTableIds,
        array $missingAssignedTableIds,
        bool $branchConsistent,
        array $nonServiceReadyTableIds,
        array $reservationConflictTableIds,
        array $holdConflictTableIds,
        Carbon $checkInAt,
        Carbon $windowStartUtc,
        Carbon $windowEndUtc,
    ): string {
        if ($isTerminal) {
            return 'terminal_status';
        }

        if ($alreadyCheckedIn) {
            return 'already_checked_in';
        }

        if ($assignedTableIds === []) {
            return 'assignment_required';
        }

        if ($missingAssignedTableIds !== []) {
            return 'assigned_table_missing';
        }

        if ($status !== ReservationStatus::Confirmed->value) {
            return 'status_not_confirmed';
        }

        if (! $branchConsistent) {
            return 'branch_mismatch';
        }

        if ($nonServiceReadyTableIds !== []) {
            return 'assigned_table_not_service_ready';
        }

        if ($holdConflictTableIds !== []) {
            return 'assigned_table_hold_conflict';
        }

        if ($reservationConflictTableIds !== []) {
            return 'assigned_table_reservation_conflict';
        }

        if ($checkInAt->lt($windowStartUtc)) {
            return 'check_in_window_not_open';
        }

        if ($checkInAt->gt($windowEndUtc)) {
            return 'check_in_window_closed';
        }

        return 'check_in_not_available';
    }

    /**
     * @param  array<int,int>  $ids
     * @return array<int,int>
     */
    private function normalizeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $value): bool => $value > 0)));
        sort($ids);

        return $ids;
    }

    private function resolveTableStatus(mixed $table): string
    {
        if (is_object($table)) {
            $status = $table->status ?? null;

            return is_object($status) && property_exists($status, 'value')
                ? (string) $status->value
                : (string) ($status ?? '');
        }

        $status = data_get($table, 'status');
        if (is_object($status) && property_exists($status, 'value')) {
            return (string) $status->value;
        }

        return (string) ($status ?? '');
    }

    private function resolveCheckInGraceMinutes(): int
    {
        return max(0, $this->runtimeSettings->int(
            'checkin.grace_minutes',
            $this->runtimeSettings->int('booking.check_in_grace_minutes', (int) config('booking.check_in_grace_minutes', 15))
        ));
    }
}
