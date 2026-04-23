<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Models\ReservationTable;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use App\Support\AvailabilityCacheVersion;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationRescheduleService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly NotificationOutboxService $notificationOutboxService,
        private readonly TableTimeConflictService $tableTimeConflictService,
        private readonly ReservationBranchScopeService $reservationBranchScopeService,
        private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService,
        private readonly RestaurantTableStateService $tableStateService,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $actorContext
     */
    public function reschedule(int $reservationId, array $payload, array $actorContext = []): Reservation
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
            config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation').':'.$reservationId,
        ], array_map(fn (int $id) => config('booking.reservation_lock_prefix', 'booking:lock:table').':'.$id, $lockTableIds));

        $resolvedActor = $this->resolveActorContext($actorContext);
        $actorUserId = $resolvedActor['user_id'];
        $actorType = $resolvedActor['type'];
        $auditEvent = $resolvedActor['audit_event'];
        $auditContext = $resolvedActor['audit_context'];

        try {
            return $this->locks->withLockKeys($lockKeys, function () use ($reservationId, $payload, $actorUserId, $actorType, $auditEvent, $auditContext) {
                return DB::transaction(function () use ($reservationId, $payload, $actorUserId, $actorType, $auditEvent, $auditContext) {
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
                    if (Money::isPositive($paymentSummary['final_net_amount'] ?? 0)) {
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
                    $tableChanged = $newTableIds !== $oldTableIds;
                    $reservationBranchId = $this->reservationBranchScopeService->resolveEffectiveReservationBranchId($reservation->branch_id);

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
                            'table_ids' => ['Some target tables are deleted: '.implode(',', $deletedTables)],
                        ]);
                    }

                    if ($newTableIds !== []) {
                        $reservationBranchId = $this->reservationBranchScopeService->syncReservationBranchOrAssert(
                            $reservation,
                            $tables->pluck('branch_id')->all(),
                            $actorUserId,
                            'Assigned tables must belong to a single branch.',
                            'Reservation branch does not match the target table branch.',
                            'table_ids',
                        );
                    }

                    $blocked = $tables->filter(function (RestaurantTable $table): bool {
                        $status = $table->status?->value ?? (string) $table->status;

                        return in_array($status, ['Blocked', 'Maintenance'], true);
                    })->pluck('table_id')->map(fn ($value) => (int) $value)->all();
                    if ($blocked !== []) {
                        throw ValidationException::withMessages([
                            'table_ids' => ['Some target tables are Blocked/Maintenance: '.implode(',', $blocked)],
                        ]);
                    }

                    if ($tableChanged) {
                        $nonAllocatable = $tables->filter(function (RestaurantTable $table): bool {
                            $status = (string) ($table->status?->value ?? $table->status);

                            return ! $this->tableStateService->isAllocatableForBooking($status);
                        })->pluck('table_id')->map(fn ($value) => (int) $value)->all();
                        if ($nonAllocatable !== []) {
                            throw ValidationException::withMessages([
                                'table_ids' => ['Some target tables are not in Available status: '.implode(',', $nonAllocatable)],
                            ]);
                        }
                    }

                    if ($newTableIds !== []) {
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
                                'table_ids' => ['Target tables have overlapping reservations: '.implode(',', $reservationConflictIds)],
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
                                'table_ids' => ['Target tables still have active overlapping holds: '.implode(',', $holdConflictIds)],
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
                    $notesChanged = $newNotes !== $oldNotes;

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
                    $reservation->updated_by = $actorUserId;
                    $reservation->save();

                    $changeSet = [
                        'actor_type' => $actorType,
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

                    AuditEvent::info($auditEvent, array_merge([
                        'reservation_id' => (int) $reservationId,
                        'before_row_version' => $beforeVersion,
                        'new_row_version' => (int) $reservation->row_version,
                        'status' => $status->value,
                        'time_changed' => $timeChanged,
                        'guest_changed' => $guestChanged,
                        'table_changed' => $tableChanged,
                        'notes_changed' => $notesChanged,
                        'change_set' => $changeSet,
                    ], $auditContext));

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
     * @param  iterable<int,RestaurantTable>  $tables
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
                'table_ids' => ['Some target tables are missing template_id: '.implode(',', $nullTemplate)],
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
                'table_ids' => ['Some target table templates are missing: '.implode(',', $missingTemplates)],
            ]);
        }

        if ($guestCount > $totalSeats) {
            throw ValidationException::withMessages([
                'table_ids' => ["Guest count ({$guestCount}) exceeds target table capacity ({$totalSeats} seats)."],
            ]);
        }
    }

    /**
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
     * @param  array<int,int>  $tableIds
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
            ->map(fn (RestaurantTable $table) => (string) ($table->table_code ?? ('#'.$table->table_id)))
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
        $preOrderRows = DB::table('reservation_order_items as roi')
            ->join('reservation_orders as ro', 'ro.order_id', '=', 'roi.order_id')
            ->where('ro.reservation_id', $reservationId)
            ->where('ro.order_type', ReservationOrderType::PreOrder->value)
            ->whereIn('ro.status', [
                ReservationOrderStatus::Active->value,
                ReservationOrderStatus::Completed->value,
            ])
            ->where('roi.status', '!=', ReservationOrderItemStatus::Cancelled->value)
            ->select('roi.item_id', DB::raw('SUM(roi.quantity) as quantity'))
            ->groupBy('roi.item_id')
            ->get();

        if ($preOrderRows->isEmpty()) {
            return;
        }

        $itemIds = $preOrderRows->pluck('item_id')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        if (! MenuItem::supportsPreorderColumns()) {
            throw ValidationException::withMessages([
                'start_time' => ['Hệ thống chưa được đồng bộ contract pre-order. Vui lòng áp dụng patch database mới nhất rồi thử lại.'],
            ]);
        }

        $menuItems = MenuItem::query()
            ->whereIn('item_id', $itemIds)
            ->get(['item_id', 'name', 'is_preorder_enabled', 'preorder_quota_per_day', 'preorder_cutoff_minutes'])
            ->keyBy('item_id');

        if ($menuItems->count() !== count($itemIds)) {
            throw ValidationException::withMessages([
                'start_time' => ['Existing pre-order items no longer map to valid menu items.'],
            ]);
        }

        $reservationDate = $newStart->copy()->toDateString();
        foreach ($preOrderRows as $row) {
            $itemId = (int) $row->item_id;
            /** @var MenuItem|null $menuItem */
            $menuItem = $menuItems->get($itemId);

            if (! $menuItem) {
                throw ValidationException::withMessages([
                    'start_time' => ['Existing pre-order items no longer map to valid menu items.'],
                ]);
            }

            if ((int) ($menuItem->is_preorder_enabled ?? 0) !== 1) {
                throw ValidationException::withMessages([
                    'start_time' => [sprintf('Cannot reschedule because pre-order item %s no longer supports pre-order.', (string) $menuItem->name)],
                ]);
            }

            $cutoffMinutes = (int) ($menuItem->preorder_cutoff_minutes ?? 0);
            if ($cutoffMinutes > 0 && Carbon::now('UTC')->addMinutes($cutoffMinutes)->gt($newStart)) {
                throw ValidationException::withMessages([
                    'start_time' => [sprintf('Cannot reschedule because pre-order item %s is already past cutoff for the new time.', (string) $menuItem->name)],
                ]);
            }

            $quotaPerDay = (int) ($menuItem->preorder_quota_per_day ?? 0);
            if ($quotaPerDay <= 0) {
                continue;
            }

            $existingQty = (int) DB::table('reservation_order_items as roi')
                ->join('reservation_orders as ro', 'ro.order_id', '=', 'roi.order_id')
                ->join('reservations as r', 'r.reservation_id', '=', 'ro.reservation_id')
                ->where('ro.order_type', ReservationOrderType::PreOrder->value)
                ->where('roi.item_id', $itemId)
                ->whereDate('r.start_time', '=', $reservationDate)
                ->whereIn('r.status', [
                    ReservationStatus::Confirmed->value,
                    ReservationStatus::checkedInDbValue(),
                    ReservationStatus::Completed->value,
                ])
                ->where('r.reservation_id', '!=', $reservationId)
                ->where('roi.status', '!=', ReservationOrderItemStatus::Cancelled->value)
                ->sum('roi.quantity');

            if ($existingQty + (int) $row->quantity > $quotaPerDay) {
                throw ValidationException::withMessages([
                    'start_time' => [sprintf('Cannot reschedule because pre-order item %s would exceed daily quota on %s.', (string) $menuItem->name, $reservationDate)],
                ]);
            }
        }
    }

    /**
     * @param  array<string,mixed>  $actorContext
     * @return array{type:string,user_id:int|null,audit_event:string,audit_actor_key:string}
     */
    private function resolveActorContext(array $actorContext): array
    {
        $type = strtolower(trim((string) ($actorContext['type'] ?? 'staff')));
        $userId = isset($actorContext['user_id']) && $actorContext['user_id'] !== null
            ? (int) $actorContext['user_id']
            : null;
        $sessionId = isset($actorContext['session_id']) ? trim((string) $actorContext['session_id']) : '';

        return match ($type) {
            'customer' => [
                'type' => 'customer',
                'user_id' => $userId,
                'audit_event' => 'customer.reservation.rescheduled',
                'audit_context' => ['customer_user_id' => $userId],
            ],
            'customer_session' => [
                'type' => 'customer_session',
                'user_id' => null,
                'audit_event' => 'customer.session_reservation.rescheduled',
                'audit_context' => ['customer_session_id' => $sessionId !== '' ? $sessionId : null],
            ],
            default => [
                'type' => 'staff',
                'user_id' => $userId,
                'audit_event' => 'staff.reservation.rescheduled',
                'audit_context' => ['staff_user_id' => $userId],
            ],
        };
    }
}
