<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\UseCases\Boards;

use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\FloorOperations\Application\Queries\StaffTableBoardService;
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

class StaffReservationBoardAssignmentService
{
    private readonly ReservationBranchScopeService $reservationBranchScopeService;

    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly RestaurantTableStateService $tableStateService,
        private readonly TableTimeConflictService $tableTimeConflictService,
        private readonly StaffTableBoardService $boardService,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
    ) {
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
    }

    /**
     * @return array{reservation:Reservation,assignment:array<string,mixed>}
     */
    public function assignSuggestedTable(
        int $reservationId,
        int $tableId,
        ?int $staffUserId = null,
        ?int $expectedRowVersion = null,
        ?string $zone = null,
        ?\DateTimeInterface $boardFrom = null,
        ?\DateTimeInterface $boardTo = null,
        bool $includeSlotOnlyCandidates = true,
    ): array
    {
        if ($tableId <= 0) {
            throw ValidationException::withMessages([
                'table_id' => ['table_id must be a positive integer.'],
            ]);
        }

        $reservation = Reservation::query()->find($reservationId);
        if (! $reservation) {
            throw new ModelNotFoundException('Reservation not found');
        }

        $assignedTableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        if ($assignedTableIds !== []) {
            if ($assignedTableIds === [$tableId]
                && ($expectedRowVersion === null || (int) ($reservation->row_version ?? 1) === $expectedRowVersion)
                && ($reservation->status?->value ?? (string) $reservation->status) === ReservationStatus::Confirmed->value
                && $reservation->checked_in_at === null) {
                $reservation->load(['tables', 'user']);

                return [
                    'reservation' => $reservation,
                    'assignment' => $this->buildAssignmentMeta(
                        'suggested_table',
                        $this->fallbackCandidateFromTableId($tableId),
                        true,
                    ),
                ];
            }

            $assignmentResult = $this->commitAssignment(
                reservationId: $reservationId,
                tableId: $tableId,
                staffUserId: $staffUserId,
                expectedRowVersion: $expectedRowVersion,
            );

            return [
                'reservation' => $assignmentResult['reservation'],
                'assignment' => $this->buildAssignmentMeta('suggested_table', $this->fallbackCandidateFromTableId($tableId), false),
            ];
        }

        $candidateMap = collect($this->resolveCandidateTables(
            reservation: $reservation,
            zone: $zone,
            boardFrom: $boardFrom,
            boardTo: $boardTo,
            includeSlotOnlyCandidates: $includeSlotOnlyCandidates,
        ))->keyBy(
            static fn (array $candidate): int => (int) ($candidate['table_id'] ?? 0)
        );

        if (! $candidateMap->has($tableId)) {
            throw ValidationException::withMessages([
                'table_id' => ['Target table is not in the current board suggestion set for this reservation. Refresh the board and try again.'],
            ]);
        }

        $candidate = (array) $candidateMap->get($tableId);
        $assignmentResult = $this->commitAssignment(
            reservationId: $reservationId,
            tableId: $tableId,
            staffUserId: $staffUserId,
            expectedRowVersion: $expectedRowVersion,
        );

        return [
            'reservation' => $assignmentResult['reservation'],
            'assignment' => $this->buildAssignmentMeta('suggested_table', $candidate, false),
        ];
    }

    /**
     * @return array{reservation:Reservation,assignment:array<string,mixed>}
     */
    public function assignBestFit(
        int $reservationId,
        ?int $staffUserId = null,
        ?int $expectedRowVersion = null,
        ?string $zone = null,
        ?\DateTimeInterface $boardFrom = null,
        ?\DateTimeInterface $boardTo = null,
        bool $includeSlotOnlyCandidates = true,
    ): array
    {
        $reservation = Reservation::query()->find($reservationId);
        if (! $reservation) {
            throw new ModelNotFoundException('Reservation not found');
        }

        $candidates = $this->sortCandidateTables($this->resolveCandidateTables(
            reservation: $reservation,
            zone: $zone,
            boardFrom: $boardFrom,
            boardTo: $boardTo,
            includeSlotOnlyCandidates: $includeSlotOnlyCandidates,
        ));
        if ($candidates === []) {
            throw ValidationException::withMessages([
                'reservation_id' => ['No board candidate tables are currently available for this reservation.'],
            ]);
        }

        foreach ($candidates as $candidate) {
            $tableId = (int) ($candidate['table_id'] ?? 0);
            if ($tableId <= 0) {
                continue;
            }

            try {
                $assignmentResult = $this->commitAssignment(
                    reservationId: $reservationId,
                    tableId: $tableId,
                    staffUserId: $staffUserId,
                    expectedRowVersion: $expectedRowVersion,
                );

                return [
                    'reservation' => $assignmentResult['reservation'],
                    'assignment' => $this->buildAssignmentMeta('best_fit', (array) $candidate, false),
                ];
            } catch (ValidationException $e) {
                $errors = $e->errors();
                if (array_key_exists('table_id', $errors)) {
                    continue;
                }

                throw $e;
            }
        }

        throw ValidationException::withMessages([
            'reservation_id' => ['No board candidate tables are currently available for this reservation.'],
        ]);
    }

    /**
     * @return array{reservation:Reservation,mutated:bool}
     */
    private function commitAssignment(int $reservationId, int $tableId, ?int $staffUserId, ?int $expectedRowVersion): array
    {
        $lockKeys = [
            config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation') . ':' . $reservationId,
            config('booking.reservation_lock_prefix', 'booking:lock:table') . ':' . $tableId,
        ];

        try {
            $result = $this->locks->withLockKeys($lockKeys, function () use ($reservationId, $tableId, $staffUserId, $expectedRowVersion) {
                return DB::transaction(function () use ($reservationId, $tableId, $staffUserId, $expectedRowVersion) {
                    /** @var Reservation|null $reservation */
                    $reservation = Reservation::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->first();

                    if ($reservation === null) {
                        throw new ModelNotFoundException('Reservation not found');
                    }

                    if ($expectedRowVersion !== null && (int) ($reservation->row_version ?? 1) !== $expectedRowVersion) {
                        throw ValidationException::withMessages([
                            'row_version' => ['DÃ¡Â»Â¯ liÃ¡Â»â€¡u Ã„â€˜ÃƒÂ£ thay Ã„â€˜Ã¡Â»â€¢i (row_version mismatch). HÃƒÂ£y reload rÃ¡Â»â€œi thÃ¡Â»Â­ lÃ¡ÂºÂ¡i.'],
                        ]);
                    }

                    if (($reservation->status?->value ?? (string) $reservation->status) !== ReservationStatus::Confirmed->value) {
                        throw ValidationException::withMessages([
                            'status' => ['Only Confirmed reservations can be assigned from the board.'],
                        ]);
                    }

                    if ($reservation->checked_in_at !== null) {
                        throw ValidationException::withMessages([
                            'status' => ['Checked-in reservations cannot use board assignment. Use move-table flow instead.'],
                        ]);
                    }

                    $assignedTableIds = DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->orderBy('table_id')
                        ->pluck('table_id')
                        ->map(static fn ($value): int => (int) $value)
                        ->all();

                    if ($assignedTableIds !== []) {
                        if ($assignedTableIds === [$tableId]) {
                            return [
                                'reservation' => $reservation->load(['tables', 'user']),
                                'mutated' => false,
                            ];
                        }

                        throw ValidationException::withMessages([
                            'reservation_id' => ['Reservation already has assigned tables. Use reschedule or move-table flow for reassignment.'],
                        ]);
                    }

                    /** @var RestaurantTable|null $table */
                    $table = RestaurantTable::query()
                        ->where('table_id', $tableId)
                        ->notDeleted()
                        ->with('template')
                        ->lockForUpdate()
                        ->first();

                    if ($table === null) {
                        throw ValidationException::withMessages([
                            'table_id' => ['Target table not found.'],
                        ]);
                    }

                    $this->reservationBranchScopeService->syncReservationBranchOrAssert(
                        $reservation,
                        [$table->branch_id],
                        $staffUserId,
                        'Assigned tables must belong to a single branch.',
                        'Reservation branch does not match the target table branch.',
                        'table_id',
                    );

                    $tableStatus = (string) ($table->status?->value ?? $table->status);
                    if (! $this->tableStateService->isAllocatableForBooking($tableStatus)) {
                        throw ValidationException::withMessages([
                            'table_id' => ['Target table is not currently available for board assignment.'],
                        ]);
                    }

                    $seats = (int) ($table->template->seats ?? 0);
                    if ($seats < (int) $reservation->guest_count) {
                        throw ValidationException::withMessages([
                            'table_id' => ['Target table does not have enough seats for this reservation.'],
                        ]);
                    }

                    $start = $reservation->start_time->copy()->utc();
                    $end = $reservation->end_time->copy()->utc();

                    $reservationConflicts = $this->tableTimeConflictService->findReservationConflictTableIds(
                        tableIds: [$tableId],
                        start: $start,
                        end: $end,
                        ignoreReservationId: $reservationId,
                        lock: true,
                    );
                    if ($reservationConflicts !== []) {
                        throw ValidationException::withMessages([
                            'table_id' => ['Target table already has an overlapping reservation.'],
                        ]);
                    }

                    $holdConflicts = $this->tableTimeConflictService->findHoldConflictTableIds(
                        tableIds: [$tableId],
                        start: $start,
                        end: $end,
                        lock: true,
                        ignoreConfirmedReservationId: $reservationId,
                    );
                    if ($holdConflicts !== []) {
                        throw ValidationException::withMessages([
                            'table_id' => ['Target table already has an overlapping active hold.'],
                        ]);
                    }

                    DB::table('reservation_tables')->insert([
                        'reservation_id' => $reservationId,
                        'table_id' => $tableId,
                    ]);

                    $reservation->updated_by = $staffUserId;
                    $reservation->save();

                    AvailabilityCacheVersion::bump();

                    AuditEvent::info('staff.reservation.board_assigned', [
                        'reservation_id' => (int) $reservationId,
                        'table_id' => (int) $tableId,
                        'staff_user_id' => $staffUserId,
                        'source' => 'staff_table_board',
                    ]);

                    return [
                        'reservation' => $reservation->load(['tables', 'user']),
                        'mutated' => true,
                    ];
                });
            });

            if (($result['mutated'] ?? false) === true) {
                app(OperationalRealtimeService::class)->publishBoardEvent(
                    'reservation.board_assignment_committed',
                    [
                        'reservation_id' => (int) $reservationId,
                        'table_id' => (int) $tableId,
                    ],
                    ['board', 'timeline'],
                );
            }

            return $result;
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $candidate
     * @return array<string,mixed>
     */


    /**
     * @param list<array<string,mixed>> $candidates
     * @return list<array<string,mixed>>
     */
    private function sortCandidateTables(array $candidates): array
    {
        usort($candidates, static function (array $left, array $right): int {
            $leftRank = (int) data_get($left, 'rank', PHP_INT_MAX);
            $rightRank = (int) data_get($right, 'rank', PHP_INT_MAX);
            $leftFit = match ((string) data_get($left, 'fit.status', '')) {
                'exact_fit' => 0,
                'close_fit' => 1,
                'slot_only_fit' => 2,
                default => 3,
            };
            $rightFit = match ((string) data_get($right, 'fit.status', '')) {
                'exact_fit' => 0,
                'close_fit' => 1,
                'slot_only_fit' => 2,
                default => 3,
            };
            $leftScore = is_numeric(data_get($left, 'score')) ? (float) data_get($left, 'score') : -INF;
            $rightScore = is_numeric(data_get($right, 'score')) ? (float) data_get($right, 'score') : -INF;

            return [
                $leftRank,
                $leftFit,
                -$leftScore,
                -1 * (int) ($left['table_id'] ?? 0),
                (string) ($left['table_code'] ?? ''),
            ] <=> [
                $rightRank,
                $rightFit,
                -$rightScore,
                -1 * (int) ($right['table_id'] ?? 0),
                (string) ($right['table_code'] ?? ''),
            ];
        });

        return array_values($candidates);
    }

    private function buildAssignmentMeta(string $mode, array $candidate, bool $idempotentReplay): array
    {
        return [
            'mode' => $mode,
            'idempotent_replay' => $idempotentReplay,
            'assigned_table' => [
                'table_id' => (int) ($candidate['table_id'] ?? 0),
                'table_code' => (string) ($candidate['table_code'] ?? ''),
                'zone' => $candidate['zone'] ?? null,
                'board_state' => $candidate['board_state'] ?? null,
            ],
            'rank' => $candidate['rank'] ?? null,
            'fit' => $candidate['fit'] ?? null,
            'score' => $candidate['score'] ?? null,
            'reason_codes' => $candidate['reason_codes'] ?? [],
            'policy_flags' => $candidate['policy_flags'] ?? [],
            'assignment_window' => $candidate['assignment_window'] ?? null,
            'assignment_request_context' => $candidate['assignment_request_context'] ?? null,
            'source' => 'staff_table_board',
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function resolveCandidateTables(
        Reservation $reservation,
        ?string $zone,
        ?\DateTimeInterface $boardFrom,
        ?\DateTimeInterface $boardTo,
        bool $includeSlotOnlyCandidates,
    ): array {
        return $this->boardService->getCandidateTablesForReservation(
            reservation: $reservation,
            zone: $zone,
            includeSlotOnly: $includeSlotOnlyCandidates,
            boardFrom: $boardFrom,
            boardTo: $boardTo,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function fallbackCandidateFromTableId(int $tableId): array
    {
        $table = RestaurantTable::query()
            ->where('table_id', $tableId)
            ->notDeleted()
            ->first();

        return [
            'table_id' => $tableId,
            'table_code' => (string) ($table?->table_code ?? ''),
            'zone' => $table?->zone,
            'board_state' => $table !== null ? strtolower((string) ($table->status?->value ?? $table->status)) : null,
        ];
    }
}


