<?php

declare(strict_types=1);

namespace App\Modules\WaitingList\Application\Services;

use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Enums\TableHoldStatus;
use App\Enums\WaitingListStatus;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\BranchScheduling\Domain\Models\TableHold;
use App\Modules\FloorOps\Application\Services\StaffCheckInService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reporting\Application\Services\StaffOperationalRealtimeService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Application\Services\ReservationService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\WaitingList\Domain\Models\WaitingList;
use App\Modules\WaitingList\Domain\State\WaitingListStateMachine;
use App\Models\User;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use App\Support\AuditEvent;
use App\Modules\BranchScheduling\Domain\Guards\HoldConflictScope;
use App\Support\Listing\SafeLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffWaitingListService
{
    private readonly BranchContextService $branchContextService;
    private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService;

    public function __construct(
        private readonly NotificationOutboxService $notificationOutboxService,
        private readonly ReservationLockService $reservationLockService,
        private readonly ReservationService $reservationService,
        private readonly StaffCheckInService $staffCheckInService,
        private readonly RuntimeSettingService $runtimeSettings,
        ?BranchContextService $branchContextService = null,
        ?BranchSchedulingPolicyService $branchSchedulingPolicyService = null,
    ) {
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
        $this->branchSchedulingPolicyService = $branchSchedulingPolicyService ?? app(BranchSchedulingPolicyService::class);
    }

    public function paginateQueue(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $this->baseQueueQuery($filters)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function listQueue(array $filters = []): Collection
    {
        return $this->baseQueueQuery($filters)->get();
    }

    public function createEntry(array $payload, ?int $staffUserId = null): WaitingList
    {
        return DB::transaction(function () use ($payload, $staffUserId) {
            $userId = isset($payload['user_id']) ? (int) $payload['user_id'] : null;
            if ($userId !== null) {
                $this->assertUserExists($userId);
            }

            $branchId = $this->branchContextService->resolveBranchId($payload['branch_id'] ?? null);
            $this->branchSchedulingPolicyService->assertWaitingListEligible($branchId, Carbon::now('UTC'), 'branch_id', false);

            $entry = new WaitingList();
            $entry->branch_id = $branchId;
            $entry->user_id = $userId;
            $entry->guest_name = $payload['guest_name'] ?? null;
            $entry->phone = $payload['phone'] ?? null;
            $entry->guest_count = (int) $payload['guest_count'];
            $entry->requested_at = Carbon::now('UTC');
            $entry->status = WaitingListStatus::Waiting;
            $entry->priority = (int) ($payload['priority'] ?? 0);
            $entry->notes = $payload['notes'] ?? null;
            $entry->updated_by = $staffUserId;
            $entry->save();

            AuditEvent::info('staff.waiting_list.created', [
                'waiting_id' => (int) $entry->waiting_id,
                'user_id' => $entry->user_id,
                'guest_count' => (int) $entry->guest_count,
                'staff_user_id' => $staffUserId,
            ]);

            app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.created', [
                'waiting_id' => (int) $entry->waiting_id,
                'status' => WaitingListStatus::Waiting->value,
                'guest_count' => (int) $entry->guest_count,
            ]);

            return $entry;
        });
    }

    public function notifyEntry(int $waitingId, array $payload, ?int $staffUserId = null, ?int $expectedRowVersion = null): WaitingList
    {
        $tableId = (int) $payload['table_id'];

        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $payload, $tableId, $staffUserId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $payload, $tableId, $staffUserId, $expectedRowVersion) {
                $entry = WaitingList::query()->with('user')->whereKey($waitingId)->lockForUpdate()->first();
                if (! $entry) {
                    throw ValidationException::withMessages(['waiting_id' => ['Waiting entry không tồn tại.']]);
                }
                WaitingListStateMachine::assertExpectedRowVersion($entry, $expectedRowVersion);
                WaitingListStateMachine::assertCanNotify($entry);
                $this->branchSchedulingPolicyService->assertWaitingListEligible((int) $entry->branch_id, Carbon::now('UTC'), 'waiting_id', false);

                $table = RestaurantTable::query()->notDeleted()->whereKey($tableId)->lockForUpdate()->first();
                if (! $table) {
                    throw ValidationException::withMessages(['table_id' => ['Bàn không tồn tại.']]);
                }
                $this->branchContextService->assertSameBranch(
                    $entry->branch_id,
                    $table->branch_id,
                    'Waiting list entry and table must belong to the same branch.',
                    'table_id',
                    false
                );

                $status = $table->status?->value ?? (string) $table->status;
                if ($status !== RestaurantTableStatus::Available->value) {
                    throw ValidationException::withMessages(['table_id' => ['Bàn hiện không ở trạng thái Available.']]);
                }

                $this->assertTableHasEnoughSeats($table, (int) $entry->guest_count);

                $now = Carbon::now('UTC');
                $holdMinutes = isset($payload['hold_minutes'])
                    ? (int) $payload['hold_minutes']
                    : $this->branchSchedulingPolicyService->waitingListNotifyHoldMinutes((int) $entry->branch_id, false);
                $expireAt = $now->copy()->addMinutes(max(1, min(60, $holdMinutes)));

                $this->cancelExistingNotifyHold($entry, $staffUserId);
                $this->assertNoActiveReservationConflict($tableId, $now, $expireAt);
                $this->assertNoActiveHoldConflict($tableId, $now, $expireAt, $this->buildWaitingSessionId((int) $entry->waiting_id));

                $hold = new TableHold();
                $hold->hold_id = (string) Str::uuid();
                $hold->branch_id = (int) $entry->branch_id;
                $hold->session_id = $this->buildWaitingSessionId((int) $entry->waiting_id);
                $hold->user_id = $entry->user_id;
                $hold->start_time = $now;
                $hold->end_time = $expireAt;
                $hold->duration_minutes = (int) $now->diffInMinutes($expireAt);
                $hold->hold_status = TableHoldStatus::Holding;
                $hold->created_at = $now;
                $hold->updated_at = $now;
                $hold->expire_at = $expireAt;
                $hold->save();
                $hold->details()->create(['table_id' => $tableId]);

                WaitingListStateMachine::applyNotified($entry, $now, $expireAt, $staffUserId);
                $entry->save();

                $entry->load('user');
                $this->notificationOutboxService->enqueueWaitingListNotified($entry, $table, $expireAt);

                AuditEvent::info('staff.waiting_list.notified', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'table_id' => $tableId,
                    'hold_id' => (string) $hold->hold_id,
                    'notify_expires_at' => $expireAt->toIso8601String(),
                    'staff_user_id' => $staffUserId,
                ]);

                app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.notified', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'table_id' => $tableId,
                    'hold_id' => (string) $hold->hold_id,
                    'notify_expires_at' => $expireAt->toIso8601String(),
                ]);

                app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.notified', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'table_id' => $tableId,
                    'hold_id' => (string) $hold->hold_id,
                ], ['board']);

                return $entry;
            });
        });
    }

    /** @return array{waiting_list: WaitingList, reservation: Reservation} */
    public function seatEntry(int $waitingId, array $payload, ?int $staffUserId = null, ?int $expectedRowVersion = null): array
    {
        $checkedInAt = isset($payload['checked_in_at']) ? Carbon::parse((string) $payload['checked_in_at'])->utc() : Carbon::now('UTC');

        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $payload, $staffUserId, $checkedInAt, $expectedRowVersion) {
            [$tableIds, $userId, $notes, $serviceMinutes] = DB::transaction(function () use ($waitingId, $payload, $expectedRowVersion) {
                $entry = WaitingList::query()->with('user')->whereKey($waitingId)->lockForUpdate()->first();
                if (! $entry) {
                    throw ValidationException::withMessages(['waiting_id' => ['Waiting entry không tồn tại.']]);
                }
                WaitingListStateMachine::assertExpectedRowVersion($entry, $expectedRowVersion);
                if ($entry->status !== WaitingListStatus::Notified) {
                    throw ValidationException::withMessages(['status' => ['Chỉ có entry ở trạng thái Notified mới có thể seat.']]);
                }
                if ($entry->notify_expires_at === null || Carbon::parse((string) $entry->notify_expires_at)->lessThanOrEqualTo(Carbon::now('UTC'))) {
                    throw ValidationException::withMessages(['notify_window' => ['Notify window đã hết hạn. Hãy expire hoặc notify lại entry này trước khi seat.']]);
                }

                $hold = $this->findActiveNotifyHoldForUpdate((int) $entry->waiting_id);
                if (! $hold) {
                    throw ValidationException::withMessages(['waiting_id' => ['Không tìm thấy hold còn hiệu lực cho waiting entry này.']]);
                }

                $tableIds = $hold->tables->pluck('table_id')->map(fn ($id) => (int) $id)->all();
                if (count($tableIds) === 0) {
                    throw ValidationException::withMessages(['waiting_id' => ['Hold không có bàn hợp lệ để seat.']]);
                }

                $userId = isset($payload['user_id']) ? (int) $payload['user_id'] : (int) ($entry->user_id ?? 0);
                if ($userId <= 0) {
                    throw ValidationException::withMessages(['user_id' => ['Cần user_id để convert waiting entry sang reservation offline.']]);
                }
                $this->assertUserExists($userId);

                $notes = ! empty($payload['notes']) ? trim((string) $payload['notes']) : (string) ($entry->notes ?? '');
                $serviceMinutes = isset($payload['service_minutes'])
                    ? (int) $payload['service_minutes']
                    : $this->branchSchedulingPolicyService->waitingListServiceMinutes((int) $entry->branch_id, false);
                $serviceMinutes = max(30, min(480, $serviceMinutes));

                return [$tableIds, $userId, $notes, $serviceMinutes];
            });

            return $this->reservationLockService->withTableLocks($tableIds, function () use ($waitingId, $checkedInAt, $serviceMinutes, $tableIds, $userId, $notes, $staffUserId) {
                return DB::transaction(function () use ($waitingId, $checkedInAt, $serviceMinutes, $tableIds, $userId, $notes, $staffUserId) {
                    $entry = WaitingList::query()->with('user')->whereKey($waitingId)->lockForUpdate()->firstOrFail();
                    WaitingListStateMachine::assertCanSeat($entry, Carbon::now('UTC'));

                    $hold = $this->findActiveNotifyHoldForUpdate((int) $entry->waiting_id);
                    if (! $hold) {
                        throw ValidationException::withMessages(['waiting_id' => ['Không tìm thấy hold còn hiệu lực cho waiting entry này.']]);
                    }
                    $this->branchContextService->assertSameBranch(
                        $entry->branch_id,
                        $hold->branch_id,
                        'Waiting list hold branch does not match the entry branch.',
                        'waiting_id',
                        false
                    );

                    $resolvedTableIds = $hold->tables->pluck('table_id')->map(fn ($id) => (int) $id)->values()->all();
                    sort($resolvedTableIds);
                    $expectedTableIds = $tableIds;
                    sort($expectedTableIds);
                    if ($resolvedTableIds === [] || $resolvedTableIds !== $expectedTableIds) {
                        throw ValidationException::withMessages(['table_ids' => ['Notify hold đã thay đổi bàn trong lúc seat.']]);
                    }

                    $this->assertTablesServiceableForSeat($resolvedTableIds);

                    $reservation = $this->reservationService->createReservation([
                        'branch_id' => (int) $entry->branch_id,
                        'user_id' => $userId,
                        'session_id' => (string) $hold->session_id,
                        'table_ids' => $resolvedTableIds,
                        'start_time' => $checkedInAt->copy()->toIso8601String(),
                        'end_time' => $checkedInAt->copy()->addMinutes($serviceMinutes)->toIso8601String(),
                        'guest_count' => (int) $entry->guest_count,
                        'notes' => $notes,
                    ], $staffUserId, [
                        'skip_locking' => true,
                        'policy_now_utc' => $checkedInAt,
                        'trusted_hold_ids' => [(string) $hold->hold_id],
                    ]);

                    $reservation = $this->staffCheckInService->checkIn(
                        reservationId: (int) $reservation->reservation_id,
                        tableIds: $resolvedTableIds,
                        checkedInAt: $checkedInAt,
                        staffUserId: $staffUserId,
                        ignoredHoldIds: [(string) $hold->hold_id],
                        skipLocking: true,
                    );

                    $hold->hold_status = TableHoldStatus::Cancelled;
                    $hold->updated_by = $staffUserId;
                    $hold->updated_at = Carbon::now('UTC');
                    $hold->save();

                    WaitingListStateMachine::applySeated($entry, $checkedInAt, $staffUserId, $userId, $notes);
                    $entry->save();

                    AuditEvent::info('staff.waiting_list.seated', [
                        'waiting_id' => (int) $entry->waiting_id,
                        'hold_id' => (string) $hold->hold_id,
                        'table_ids' => $resolvedTableIds,
                        'staff_user_id' => $staffUserId,
                        'reservation_user_id' => $userId,
                        'reservation_id' => (int) $reservation->reservation_id,
                    ]);

                    app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.seated', [
                        'waiting_id' => (int) $entry->waiting_id,
                        'reservation_id' => (int) $reservation->reservation_id,
                        'table_ids' => $resolvedTableIds,
                    ]);

                    app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.seated', [
                        'waiting_id' => (int) $entry->waiting_id,
                        'reservation_id' => (int) $reservation->reservation_id,
                        'table_ids' => $resolvedTableIds,
                    ], ['board', 'timeline']);

                    return [
                        'waiting_list' => $entry->fresh(['user']),
                        'reservation' => $reservation,
                    ];
                });
            });
        });
    }

    public function cancelEntry(int $waitingId, string $cancelReason = '', ?int $staffUserId = null, ?int $expectedRowVersion = null): WaitingList
    {
        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $cancelReason, $staffUserId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $cancelReason, $staffUserId, $expectedRowVersion) {
                $entry = WaitingList::query()->with('user')->whereKey($waitingId)->lockForUpdate()->first();
                if (! $entry) {
                    throw ValidationException::withMessages(['waiting_id' => ['Waiting entry không tồn tại.']]);
                }
                WaitingListStateMachine::assertExpectedRowVersion($entry, $expectedRowVersion);
                WaitingListStateMachine::assertCanCancel($entry);

                if ($entry->status === WaitingListStatus::Cancelled) {
                    AuditEvent::info('staff.waiting_list.cancel_noop', [
                        'waiting_id' => (int) $entry->waiting_id,
                        'staff_user_id' => $staffUserId,
                        'cancel_reason' => $entry->cancel_reason,
                    ]);

                    return $entry;
                }

                $wasNotified = $entry->status === WaitingListStatus::Notified;

                WaitingListStateMachine::applyCancelled($entry, Carbon::now('UTC'), $cancelReason, $staffUserId);
                $entry->save();

                $this->cancelExistingNotifyHold($entry, $staffUserId);

                AuditEvent::info('staff.waiting_list.cancelled', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'staff_user_id' => $staffUserId,
                    'cancel_reason' => $entry->cancel_reason,
                ]);

                app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.cancelled', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'cancel_reason' => (string) $entry->cancel_reason,
                ]);

                if ($wasNotified) {
                    app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.cancelled', [
                        'waiting_id' => (int) $entry->waiting_id,
                        'cancel_reason' => (string) $entry->cancel_reason,
                    ], ['board']);
                }

                return $entry;
            });
        });
    }

    public function expireNotifiedEntries(?Carbon $now = null): int
    {
        $now ??= Carbon::now('UTC');

        $entries = WaitingList::query()
            ->where('status', WaitingListStatus::Notified->value)
            ->whereNotNull('notify_expires_at')
            ->where('notify_expires_at', '<=', $now)
            ->orderBy('waiting_id')
            ->get();

        $count = 0;
        $expiredWaitingIds = [];
        foreach ($entries as $entry) {
            try {
                $changed = $this->withWaitingEntryLock((int) $entry->waiting_id, function () use ($entry, $now) {
                    return DB::transaction(function () use ($entry, $now) {
                        $lockedEntry = WaitingList::query()->whereKey((int) $entry->waiting_id)->lockForUpdate()->first();
                        if (! $lockedEntry || $lockedEntry->status !== WaitingListStatus::Notified || $lockedEntry->notify_expires_at === null || Carbon::parse((string) $lockedEntry->notify_expires_at)->gt($now)) {
                            return false;
                        }

                        WaitingListStateMachine::applyExpiredToWaiting($lockedEntry, null);
                        $lockedEntry->save();

                        $this->cancelExistingNotifyHold($lockedEntry);
                        return true;
                    });
                });
            } catch (\Throwable $e) {
                AuditEvent::warning('staff.waiting_list.notify_expire_failed', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'message' => $e->getMessage(),
                ]);
                continue;
            }

            if ($changed) {
                $count++;
                $expiredWaitingIds[] = (int) $entry->waiting_id;

                app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.notify_expired', [
                    'waiting_id' => (int) $entry->waiting_id,
                ]);

                app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.notify_expired', [
                    'waiting_id' => (int) $entry->waiting_id,
                ], ['board']);
            }
        }

        if ($count > 0) {
            AuditEvent::info('staff.waiting_list.notify_expired', [
                'count' => $count,
                'waiting_ids' => $expiredWaitingIds,
            ]);
        }

        return $count;
    }

    private function assertUserExists(int $userId): void
    {
        $exists = User::query()->where('user_id', $userId)->where('is_deleted', 0)->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['user_id' => ['User không tồn tại hoặc đã bị xoá.']]);
        }
    }

    private function assertTableHasEnoughSeats(RestaurantTable $table, int $guestCount): void
    {
        if ($table->template_id === null) {
            throw ValidationException::withMessages(['table_id' => ['Bàn thiếu template_id nên không thể notify waiting entry an toàn.']]);
        }

        $template = DB::table('table_templates')
            ->where('template_id', (int) $table->template_id)
            ->first(['template_id', 'seats']);

        if (! $template) {
            throw ValidationException::withMessages(['table_id' => ['Template của bàn không tồn tại nên không thể notify waiting entry.']]);
        }

        $seats = (int) ($template->seats ?? 0);
        if ($seats < $guestCount) {
            throw ValidationException::withMessages(['table_id' => ['Sức chứa bàn không đủ cho số khách trong waiting entry.']]);
        }
    }

    private function assertNoActiveReservationConflict(int $tableId, Carbon $start, Carbon $end): void
    {
        $exists = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->where('rt.table_id', $tableId)
            ->whereIn('r.status', ReservationStatus::activeDbValues())
            ->where('r.start_time', '<', $end)
            ->where('r.end_time', '>', $start)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['table_id' => ['Bàn đang bị overlap với reservation khác trong thời gian notify.']]);
        }
    }

    private function assertNoActiveHoldConflict(int $tableId, Carbon $start, Carbon $end, string $sessionId): void
    {
        $holdQuery = DB::table('table_hold_details as thd')
            ->join('table_holds as th', 'th.hold_id', '=', 'thd.hold_id')
            ->where('thd.table_id', $tableId)
            ->where('th.session_id', '!=', $sessionId)
            ->where('th.start_time', '<', $end)
            ->where('th.end_time', '>', $start);

        HoldConflictScope::apply($holdQuery, 'th', Carbon::now('UTC'));

        $exists = $holdQuery->exists();

        if ($exists) {
            throw ValidationException::withMessages(['table_id' => ['Bàn đang bị giữ bởi hold khác.']]);
        }
    }

    private function assertTablesServiceableForSeat(array $tableIds): void
    {
        $tables = RestaurantTable::query()
            ->notDeleted()
            ->whereIn('table_id', $tableIds)
            ->lockForUpdate()
            ->get();

        if ($tables->count() !== count($tableIds)) {
            throw ValidationException::withMessages(['table_ids' => ['Có bàn không tồn tại hoặc đã bị xoá trong lúc seat waiting entry.']]);
        }

        foreach ($tables as $table) {
            $status = $table->status?->value ?? (string) $table->status;
            if (in_array($status, [
                RestaurantTableStatus::Blocked->value,
                RestaurantTableStatus::Maintenance->value,
                RestaurantTableStatus::Occupied->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'table_ids' => [sprintf('Bàn %d hiện không sẵn sàng để seat waiting entry.', (int) $table->table_id)],
                ]);
            }
        }
    }

    private function withWaitingEntryLock(int $waitingId, callable $callback): mixed
    {
        $lockKey = 'booking:lock:waiting-list:' . $waitingId;
        $ttlSeconds = max(5, (int) config('booking.reservation_lock_ttl_seconds', 60));
        $waitSeconds = max(0, (int) config('booking.reservation_lock_wait_seconds', 10));

        $lock = Cache::store('redis')->lock($lockKey, $ttlSeconds);

        return $lock->block($waitSeconds, $callback);
    }

    private function cancelExistingNotifyHold(WaitingList $entry, ?int $staffUserId = null): void
    {
        $now = Carbon::now('UTC');

        TableHold::query()
            ->where('session_id', $this->buildWaitingSessionId((int) $entry->waiting_id))
            ->whereIn('hold_status', [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value, TableHoldStatus::Confirmed->value])
            ->lockForUpdate()
            ->get()
            ->each(function (TableHold $hold) use ($staffUserId, $now): void {
                $hold->hold_status = TableHoldStatus::Cancelled;
                $hold->updated_by = $staffUserId;
                $hold->updated_at = $now;
                $hold->save();
            });
    }

    private function findActiveNotifyHoldForUpdate(int $waitingId): ?TableHold
    {
        return TableHold::query()
            ->where('session_id', $this->buildWaitingSessionId($waitingId))
            ->whereIn('hold_status', [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value, TableHoldStatus::Confirmed->value])
            ->where('expire_at', '>', Carbon::now('UTC'))
            ->with('tables')
            ->lockForUpdate()
            ->orderByDesc('created_at')
            ->first();
    }

    private function buildWaitingSessionId(int $waitingId): string
    {
        return 'waiting-list:' . $waitingId;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function baseQueueQuery(array $filters): Builder
    {
        $query = WaitingList::query()->with('user');

        $this->applyQueueFilters($query, $filters);
        $this->applyQueueOrdering(
            $query,
            (string) ($filters['sort_by'] ?? 'priority'),
            (string) ($filters['sort_dir'] ?? 'desc'),
        );

        return $query;
    }

    /**
     * @param Builder<WaitingList> $query
     * @param array<string, mixed> $filters
     */
    private function applyQueueFilters(Builder $query, array $filters): void
    {
        $query
            ->when(($filters['active_only'] ?? true) === true, fn ($builder) => $builder->whereIn('status', [WaitingListStatus::Waiting->value, WaitingListStatus::Notified->value]))
            ->when(isset($filters['branch_id']) && $filters['branch_id'] !== '', fn ($builder) => $builder->where('branch_id', (int) $filters['branch_id']))
            ->when(! empty($filters['status']), fn ($builder) => $builder->where('status', (string) $filters['status']))
            ->when(! empty($filters['phone']), fn ($builder) => $builder->where('phone', 'like', SafeLike::contains(trim((string) $filters['phone']))))
            ->when(! empty($filters['guest_name']), fn ($builder) => $builder->where('guest_name', 'like', SafeLike::contains(trim((string) $filters['guest_name']))));
    }

    /**
     * @param Builder<WaitingList> $query
     */
    private function applyQueueOrdering(Builder $query, string $sortBy, string $sortDir): void
    {
        $direction = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';
        $allowed = [
            'priority',
            'requested_at',
            'notified_at',
            'guest_name',
            'guest_count',
            'waiting_id',
        ];
        $sortBy = in_array($sortBy, $allowed, true) ? $sortBy : 'priority';

        if ($sortBy === 'priority') {
            $query->orderBy('priority', $direction);
            $query->orderBy('requested_at', 'asc');
            $query->orderBy('waiting_id', 'asc');

            return;
        }

        $query->orderBy($sortBy, $direction);
        $query->orderByDesc('priority');

        if ($sortBy !== 'requested_at') {
            $query->orderBy('requested_at', 'asc');
        }

        if ($sortBy !== 'waiting_id') {
            $query->orderBy('waiting_id', 'asc');
        }
    }
}
