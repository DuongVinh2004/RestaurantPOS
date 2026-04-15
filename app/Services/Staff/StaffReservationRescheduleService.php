<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\ReservationStatus;
use App\Models\MenuItem;
use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Models\ReservationTable;
use App\Support\AvailabilityCacheVersion;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Services\MenuPreorderPolicyService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Support\AuditEvent;
use App\Support\DatabaseWriteConflictMapper;
use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffReservationRescheduleService
{
    private readonly ReservationBranchScopeService $reservationBranchScopeService;
    private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService;

    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly NotificationOutboxService $notificationOutboxService,
        private readonly TableTimeConflictService $tableTimeConflictService,
        private readonly MenuPreorderPolicyService $menuPreorderPolicyService,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
        ?BranchSchedulingPolicyService $branchSchedulingPolicyService = null,
    ) {
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
        $this->branchSchedulingPolicyService = $branchSchedulingPolicyService ?? app(BranchSchedulingPolicyService::class);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function reschedule(int $reservationId, array $payload, ?int $staffUserId = null): Reservation
    {
        $requestedTableIds = $this->normalizeTableIds($payload['table_ids'] ?? null);

        $currentTableIds = ReservationTable::query()
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $lockTableIds = array_values(array_unique(array_merge($currentTableIds, $requestedTableIds)));
        sort($lockTableIds);

        $lockKeys = array_merge([
            config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation') . ':' . $reservationId,
        ], array_map(fn (int $id) => config('booking.reservation_lock_prefix', 'booking:lock:table') . ':' . $id, $lockTableIds));

        try {
            return $this->locks->withLockKeys($lockKeys, function () use ($reservationId, $payload, $staffUserId) {
                return DB::transaction(function () use ($reservationId, $payload, $staffUserId) {
                    /** @var Reservation|null $reservation */
                $reservation = Reservation::query()
                    ->where('reservation_id', $reservationId)
                    ->lockForUpdate()
                    ->first();

                if (! $reservation) {
                    throw new ModelNotFoundException('Reservation not found');
                }

                $status = $reservation->status instanceof ReservationStatus
                    ? $reservation->status
                    : ReservationStatus::from((string) $reservation->getRawOriginal('status'));

                if ($status !== ReservationStatus::Confirmed) {
                    throw ValidationException::withMessages([
                        'status' => ['Only Confirmed reservations can be rescheduled.'],
                    ]);
                }

                if ($reservation->checked_in_at !== null) {
                    throw ValidationException::withMessages([
                        'status' => ['Checked-in reservations cannot be rescheduled. Use move-table/runtime flows instead.'],
                    ]);
                }

                $beforeVersion = (int) ($reservation->row_version ?? 1);
                $expectedRowVersion = (int) ($payload['row_version'] ?? 0);
                if ($expectedRowVersion !== $beforeVersion) {
                    throw ValidationException::withMessages([
                        'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
                    ]);
                }

                $payments = Payment::query()
                    ->where('reservation_id', $reservationId)
                    ->lockForUpdate()
                    ->get();
                $paymentSummary = PaymentSummary::fromPayments($payments);
                if ((float) ($paymentSummary['final_net_amount'] ?? 0.0) > 0.0001) {
                    throw ValidationException::withMessages([
                        'reservation_id' => ['Reservation already has final payments. Reschedule after payment is not allowed.'],
                    ]);
                }

                if ($reservation->billed_at !== null || $reservation->final_bill_amount !== null) {
                    throw ValidationException::withMessages([
                        'reservation_id' => ['Reservation bill has already been closed. Reschedule is not allowed.'],
                    ]);
                }

                $currentTableIds = ReservationTable::query()
                    ->where('reservation_id', $reservationId)
                    ->lockForUpdate()
                    ->orderBy('table_id')
                    ->pluck('table_id')
                    ->map(fn ($value) => (int) $value)
                    ->all();

                $oldStart = Carbon::parse((string) $reservation->start_time)->utc();
                $oldEnd = Carbon::parse((string) $reservation->end_time)->utc();
                $oldGuestCount = (int) $reservation->guest_count;
                $oldNotes = $reservation->notes;
                $oldTableIds = $currentTableIds;
                $oldTableLabels = $this->fetchTableLabels($oldTableIds);

                $durationMinutes = max(1, (int) $oldStart->diffInMinutes($oldEnd));

                $newStart = array_key_exists('start_time', $payload) && $payload['start_time'] !== null
                    ? Carbon::parse((string) $payload['start_time'])->utc()
                    : $oldStart->copy();

                if (array_key_exists('end_time', $payload) && $payload['end_time'] !== null) {
                    $newEnd = Carbon::parse((string) $payload['end_time'])->utc();
                } elseif (array_key_exists('start_time', $payload) && $payload['start_time'] !== null) {
                    $newEnd = $newStart->copy()->addMinutes($durationMinutes);
                } else {
                    $newEnd = $oldEnd->copy();
                }

                if ($newEnd->lessThanOrEqualTo($newStart)) {
                    throw ValidationException::withMessages([
                        'end_time' => ['end_time must be after start_time.'],
                    ]);
                }

                if ($newEnd->lessThanOrEqualTo(Carbon::now('UTC'))) {
                    throw ValidationException::withMessages([
                        'end_time' => ['Rescheduled time range must end in the future.'],
                    ]);
                }

                $timeChanged = ! $newStart->equalTo($oldStart) || ! $newEnd->equalTo($oldEnd);
                if ($timeChanged) {
                    $this->assertExistingPreOrdersStillValidForNewTime(
                        reservationId: $reservationId,
                        newStart: $newStart,
                    );
                }

                $newGuestCount = array_key_exists('guest_count', $payload)
                    ? (int) $payload['guest_count']
                    : $oldGuestCount;

                $newNotes = array_key_exists('notes', $payload)
                    ? $this->normalizeNotes($payload['notes'])
                    : $oldNotes;

                $newTableIds = $this->normalizeTableIds($payload['table_ids'] ?? null);
                if ($newTableIds === []) {
                    $newTableIds = $oldTableIds;
                }
                sort($newTableIds);
                $reservationBranchId = $this->reservationBranchScopeService->resolveEffectiveReservationBranchId($reservation->branch_id);

                if ($newTableIds !== []) {
                    $tables = RestaurantTable::query()
                        ->whereIn('table_id', $newTableIds)
                        ->lockForUpdate()
                        ->get();

                    if ($tables->count() !== count($newTableIds)) {
                        throw ValidationException::withMessages([
                            'table_ids' => ['Some target tables were not found.'],
                        ]);
                    }

                    $deletedTables = $tables->where('is_deleted', true)->pluck('table_id')->map(fn ($value) => (int) $value)->all();
                    if ($deletedTables !== []) {
                        throw ValidationException::withMessages([
                            'table_ids' => ['Some target tables are deleted: ' . implode(',', $deletedTables)],
                        ]);
                    }

                    $reservationBranchId = $this->reservationBranchScopeService->syncReservationBranchOrAssert(
                        $reservation,
                        $tables->pluck('branch_id')->all(),
                        $staffUserId,
                        'Assigned tables must belong to a single branch.',
                        'Reservation branch does not match the target table branch.',
                        'table_ids',
                    );

                    $blocked = $tables->filter(function (RestaurantTable $table): bool {
                        $status = $table->status?->value ?? (string) $table->status;
                        return in_array($status, ['Blocked', 'Maintenance'], true);
                    })->pluck('table_id')->map(fn ($value) => (int) $value)->all();
                    if ($blocked !== []) {
                        throw ValidationException::withMessages([
                            'table_ids' => ['Some target tables are Blocked/Maintenance: ' . implode(',', $blocked)],
                        ]);
                    }

                    $this->assertCapacityEnough($tables, $newGuestCount);

                    $reservationConflictIds = $this->tableTimeConflictService->findReservationConflictTableIds(
                        tableIds: $newTableIds,
                        start: $newStart,
                        end: $newEnd,
                        ignoreReservationId: $reservationId,
                        lock: true,
                    );
                    if ($reservationConflictIds !== []) {
                        throw ValidationException::withMessages([
                            'table_ids' => ['Target tables have overlapping reservations: ' . implode(',', $reservationConflictIds)],
                        ]);
                    }

                    $holdConflictIds = $this->tableTimeConflictService->findHoldConflictTableIds(
                        tableIds: $newTableIds,
                        start: $newStart,
                        end: $newEnd,
                        lock: true,
                    );
                    if ($holdConflictIds !== []) {
                        throw ValidationException::withMessages([
                            'table_ids' => ['Target tables still have active overlapping holds: ' . implode(',', $holdConflictIds)],
                        ]);
                    }
                }

                $this->branchSchedulingPolicyService->assertReservationWindowAllowed(
                    $reservationBranchId,
                    $newStart,
                    $newEnd,
                    'start_time',
                    null,
                    'reservation',
                    false
                );

                $guestChanged = $newGuestCount !== $oldGuestCount;
                $notesChanged = $this->normalizeNotes($oldNotes) !== $newNotes;
                $tableChanged = $oldTableIds !== $newTableIds;

                if (! $timeChanged && ! $guestChanged && ! $notesChanged && ! $tableChanged) {
                    return Reservation::query()
                        ->with(['user', 'tables', 'orders.items.item', 'payments'])
                        ->findOrFail($reservationId);
                }

                if ($tableChanged) {
                    DB::table('reservation_tables')
                        ->where('reservation_id', $reservationId)
                        ->delete();

                    $rows = array_map(fn (int $tableId) => [
                        'reservation_id' => $reservationId,
                        'table_id' => $tableId,
                    ], $newTableIds);
                    DB::table('reservation_tables')->insert($rows);
                }

                $reservation->start_time = $newStart;
                $reservation->end_time = $newEnd;
                $reservation->guest_count = $newGuestCount;
                $reservation->notes = $newNotes;
                $reservation->updated_by = $staffUserId;
                $reservation->save();

                $changeSet = [
                    'previous_start_time_utc' => $oldStart->toIso8601String(),
                    'previous_end_time_utc' => $oldEnd->toIso8601String(),
                    'previous_guest_count' => $oldGuestCount,
                    'previous_notes' => $oldNotes,
                    'previous_table_ids' => $oldTableIds,
                    'previous_table_labels' => $oldTableLabels,
                    'new_start_time_utc' => $newStart->toIso8601String(),
                    'new_end_time_utc' => $newEnd->toIso8601String(),
                    'new_guest_count' => $newGuestCount,
                    'new_notes' => $newNotes,
                    'new_table_ids' => $newTableIds,
                    'new_table_labels' => $this->fetchTableLabels($newTableIds),
                    'reason' => isset($payload['reason']) ? trim((string) $payload['reason']) : null,
                    'changed_fields' => array_values(array_filter([
                        $timeChanged ? 'time' : null,
                        $guestChanged ? 'guest_count' : null,
                        $tableChanged ? 'tables' : null,
                        $notesChanged ? 'notes' : null,
                    ])),
                ];

                $reservation->loadMissing('user', 'tables', 'payments');

                $customerVisibleChange = $timeChanged || $guestChanged || $tableChanged;
                if ($customerVisibleChange) {
                    if ($timeChanged) {
                        $this->notificationOutboxService->enqueueReservationRescheduled($reservation, $changeSet);
                    } else {
                        $this->notificationOutboxService->enqueueReservationUpdated($reservation, $changeSet);
                    }
                }

                AuditEvent::info('staff.reservation.rescheduled', [
                    'reservation_id' => (int) $reservationId,
                    'staff_user_id' => $staffUserId,
                    'before_row_version' => $beforeVersion,
                    'new_row_version' => (int) $reservation->row_version,
                    'status' => $status->value,
                    'time_changed' => $timeChanged,
                    'guest_changed' => $guestChanged,
                    'table_changed' => $tableChanged,
                    'notes_changed' => $notesChanged,
                    'change_set' => $changeSet,
                ]);

                AvailabilityCacheVersion::bump();

                return Reservation::query()
                    ->with(['user', 'tables', 'orders.items.item', 'payments'])
                    ->findOrFail($reservationId);
                });
            });
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    /**
     * @param iterable<int,RestaurantTable> $tables
     */
    private function assertCapacityEnough(iterable $tables, int $guestCount): void
    {
        $tables = collect($tables);
        $nullTemplate = $tables->filter(fn (RestaurantTable $table) => $table->template_id === null)
            ->pluck('table_id')
            ->map(fn ($value) => (int) $value)
            ->all();
        if ($nullTemplate !== []) {
            throw ValidationException::withMessages([
                'table_ids' => ['Some target tables are missing template_id: ' . implode(',', $nullTemplate)],
            ]);
        }

        $templateIds = $tables->pluck('template_id')->unique()->map(fn ($value) => (int) $value)->values()->all();
        $seatsByTemplate = DB::table('table_templates')
            ->whereIn('template_id', $templateIds)
            ->pluck('seats', 'template_id');

        $missingTemplates = [];
        $totalSeats = 0;
        foreach ($tables as $table) {
            $templateId = (int) $table->template_id;
            if (! $seatsByTemplate->has($templateId)) {
                $missingTemplates[] = $templateId;
                continue;
            }
            $totalSeats += (int) $seatsByTemplate->get($templateId);
        }

        if ($missingTemplates !== []) {
            $missingTemplates = array_values(array_unique($missingTemplates));
            throw ValidationException::withMessages([
                'table_ids' => ['Some target table templates are missing: ' . implode(',', $missingTemplates)],
            ]);
        }

        if ($guestCount > $totalSeats) {
            throw ValidationException::withMessages([
                'table_ids' => ["Guest count ({$guestCount}) exceeds target table capacity ({$totalSeats} seats)."],
            ]);
        }
    }

    /**
     * @param mixed $tableIds
     * @return array<int,int>
     */
    private function normalizeTableIds(mixed $tableIds): array
    {
        if (! is_array($tableIds)) {
            return [];
        }

        $normalized = array_values(array_unique(array_map('intval', $tableIds)));
        $normalized = array_values(array_filter($normalized, fn (int $value) => $value > 0));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param array<int,int> $tableIds
     * @return array<int,string>
     */
    private function fetchTableLabels(array $tableIds): array
    {
        if ($tableIds === []) {
            return [];
        }

        return RestaurantTable::query()
            ->whereIn('table_id', $tableIds)
            ->orderBy('table_code')
            ->get(['table_id', 'table_code'])
            ->map(fn (RestaurantTable $table) => (string) ($table->table_code ?? ('#' . $table->table_id)))
            ->values()
            ->all();
    }

    private function normalizeNotes(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $notes = trim((string) $value);

        return $notes === '' ? null : $notes;
    }
    private function assertExistingPreOrdersStillValidForNewTime(int $reservationId, Carbon $newStart): void
    {
        if (! MenuItem::supportsPreorderColumns()) {
            throw ValidationException::withMessages([
                'start_time' => ['Hệ thống chưa được đồng bộ contract pre-order. Vui lòng áp dụng patch database mới nhất rồi thử lại.'],
            ]);
        }

        $this->menuPreorderPolicyService->assertReservationPreordersRemainValid($reservationId, $newStart);
    }

}
