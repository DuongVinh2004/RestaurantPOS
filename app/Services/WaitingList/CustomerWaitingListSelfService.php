<?php

declare(strict_types=1);

namespace App\Services\WaitingList;

/**
 * @deprecated Legacy guest-session waiting-list self-service residue.
 *
 * Patch 4-5 established the owner-only customer waiting-list runtime contract via
 * App\Http\Controllers\Api\CustomerWaitingListController + App\Services\CustomerWaitingListService.
 *
 * This class currently has no route wiring, controller wiring, or valid test-backed public contract
 * in the repository snapshot. It is retained only because there is not yet enough evidence to delete it
 * as a backward-compatibility break.
 */

use App\Enums\TableHoldStatus;
use App\Enums\WaitingListCustomerResponseStatus;
use App\Enums\WaitingListStatus;
use App\Models\TableHold;
use App\Models\User;
use App\Models\WaitingList;
use App\Support\AuditEvent;
use App\Services\Staff\StaffOperationalRealtimeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerWaitingListSelfService
{
    public function paginateForUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        return $this->paginateQuery($this->ownedQuery($userId), $filters);
    }

    public function paginateForSession(string $sessionId, array $filters = []): LengthAwarePaginator
    {
        return $this->paginateQuery($this->sessionQuery($sessionId), $filters);
    }

    public function showForUser(int $waitingId, int $userId): WaitingList
    {
        return $this->showFromQuery($this->ownedQuery($userId), $waitingId);
    }

    public function showForSession(int $waitingId, string $sessionId): WaitingList
    {
        return $this->showFromQuery($this->sessionQuery($sessionId), $waitingId);
    }

    public function createForUser(User $user, array $payload, ?string $sessionId = null): WaitingList
    {
        $userId = (int) $user->user_id;

        return $this->withCustomerUserLock($userId, function () use ($user, $payload, $userId, $sessionId) {
            return DB::transaction(function () use ($user, $payload, $userId, $sessionId) {
                $this->assertNoActiveEntryConflict($userId);

                $guestName = trim((string) ($payload['guest_name'] ?? $user->full_name ?? ''));
                $phone = trim((string) ($payload['phone'] ?? $user->phone ?? ''));
                $notes = trim((string) ($payload['notes'] ?? ''));

                if ($guestName === '') {
                    throw ValidationException::withMessages([
                        'guest_name' => ['Thiếu guest_name. Hãy bổ sung họ tên hoặc cập nhật hồ sơ khách hàng trước khi vào waiting list.'],
                    ]);
                }

                if ($phone === '') {
                    throw ValidationException::withMessages([
                        'phone' => ['Thiếu phone. Hãy bổ sung số điện thoại để nhà hàng có thể liên hệ khi đến lượt.'],
                    ]);
                }

                $entry = new WaitingList();
                $entry->user_id = $userId;
                $entry->customer_session_id = $this->normalizeSessionId($sessionId);
                $entry->guest_name = $guestName;
                $entry->phone = $phone;
                $entry->guest_count = (int) $payload['guest_count'];
                $entry->requested_at = Carbon::now('UTC');
                $entry->status = WaitingListStatus::Waiting;
                $entry->priority = 0;
                $entry->notes = $notes !== '' ? $notes : null;
                $entry->updated_by = $userId;
                $entry->save();

                AuditEvent::info('customer.waiting_list.created', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'user_id' => $userId,
                    'customer_session_id' => $entry->customer_session_id,
                    'guest_count' => (int) $entry->guest_count,
                ]);

                app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.created', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'status' => WaitingListStatus::Waiting->value,
                    'guest_count' => (int) $entry->guest_count,
                ]);

                return $entry->fresh();
            });
        });
    }

    public function createForGuestSession(string $sessionId, array $payload): WaitingList
    {
        $sessionId = $this->normalizeSessionId($sessionId);
        if ($sessionId === '') {
            throw ValidationException::withMessages([
                'session_id' => ['Session id là bắt buộc cho guest waiting-list flow.'],
            ]);
        }

        return $this->withGuestSessionLock($sessionId, function () use ($sessionId, $payload) {
            return DB::transaction(function () use ($sessionId, $payload) {
                $this->assertNoActiveEntryConflictForSession($sessionId);

                $guestName = trim((string) ($payload['guest_name'] ?? ''));
                $phone = trim((string) ($payload['phone'] ?? ''));
                $notes = trim((string) ($payload['notes'] ?? ''));

                if ($guestName === '') {
                    throw ValidationException::withMessages([
                        'guest_name' => ['Thiếu guest_name cho guest waiting-list flow.'],
                    ]);
                }

                if ($phone === '') {
                    throw ValidationException::withMessages([
                        'phone' => ['Thiếu phone cho guest waiting-list flow.'],
                    ]);
                }

                $entry = new WaitingList();
                $entry->user_id = null;
                $entry->customer_session_id = $sessionId;
                $entry->guest_name = $guestName;
                $entry->phone = $phone;
                $entry->guest_count = (int) $payload['guest_count'];
                $entry->requested_at = Carbon::now('UTC');
                $entry->status = WaitingListStatus::Waiting;
                $entry->priority = 0;
                $entry->notes = $notes !== '' ? $notes : null;
                $entry->updated_by = null;
                $entry->save();

                AuditEvent::info('customer.waiting_list.created', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'customer_session_id' => $sessionId,
                    'guest_count' => (int) $entry->guest_count,
                ]);

                app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.created', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'status' => WaitingListStatus::Waiting->value,
                    'guest_count' => (int) $entry->guest_count,
                ]);

                return $entry->fresh();
            });
        });
    }

    public function cancelForUser(int $waitingId, int $userId, string $cancelReason = '', ?int $expectedRowVersion = null): WaitingList
    {
        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $userId, $cancelReason, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $userId, $cancelReason, $expectedRowVersion) {
                $entry = $this->loadOwnedWaitingEntryForUpdate($waitingId, $userId);
                return $this->cancelEntry($entry, $cancelReason, $expectedRowVersion, $userId, null);
            });
        });
    }

    public function cancelForSession(int $waitingId, string $sessionId, string $cancelReason = '', ?int $expectedRowVersion = null): WaitingList
    {
        $sessionId = $this->normalizeSessionId($sessionId);

        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $sessionId, $cancelReason, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $sessionId, $cancelReason, $expectedRowVersion) {
                $entry = $this->loadSessionWaitingEntryForUpdate($waitingId, $sessionId);
                return $this->cancelEntry($entry, $cancelReason, $expectedRowVersion, null, $sessionId);
            });
        });
    }

    public function acceptInviteForUser(int $waitingId, int $userId, ?int $expectedRowVersion = null): WaitingList
    {
        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $userId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $userId, $expectedRowVersion) {
                $entry = $this->loadOwnedWaitingEntryForUpdate($waitingId, $userId);
                return $this->acceptEntry($entry, $expectedRowVersion, $userId, null);
            });
        });
    }

    public function acceptInviteForSession(int $waitingId, string $sessionId, ?int $expectedRowVersion = null): WaitingList
    {
        $sessionId = $this->normalizeSessionId($sessionId);

        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $sessionId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $sessionId, $expectedRowVersion) {
                $entry = $this->loadSessionWaitingEntryForUpdate($waitingId, $sessionId);
                return $this->acceptEntry($entry, $expectedRowVersion, null, $sessionId);
            });
        });
    }

    public function declineInviteForUser(int $waitingId, int $userId, ?int $expectedRowVersion = null): WaitingList
    {
        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $userId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $userId, $expectedRowVersion) {
                $entry = $this->loadOwnedWaitingEntryForUpdate($waitingId, $userId);
                return $this->declineEntry($entry, $expectedRowVersion, $userId, null);
            });
        });
    }

    public function declineInviteForSession(int $waitingId, string $sessionId, ?int $expectedRowVersion = null): WaitingList
    {
        $sessionId = $this->normalizeSessionId($sessionId);

        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $sessionId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $sessionId, $expectedRowVersion) {
                $entry = $this->loadSessionWaitingEntryForUpdate($waitingId, $sessionId);
                return $this->declineEntry($entry, $expectedRowVersion, null, $sessionId);
            });
        });
    }

    public function confirmArrivalForUser(int $waitingId, int $userId, ?int $expectedRowVersion = null): WaitingList
    {
        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $userId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $userId, $expectedRowVersion) {
                $entry = $this->loadOwnedWaitingEntryForUpdate($waitingId, $userId);
                return $this->confirmArrivalEntry($entry, $expectedRowVersion, $userId, null);
            });
        });
    }

    public function confirmArrivalForSession(int $waitingId, string $sessionId, ?int $expectedRowVersion = null): WaitingList
    {
        $sessionId = $this->normalizeSessionId($sessionId);

        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $sessionId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $sessionId, $expectedRowVersion) {
                $entry = $this->loadSessionWaitingEntryForUpdate($waitingId, $sessionId);
                return $this->confirmArrivalEntry($entry, $expectedRowVersion, null, $sessionId);
            });
        });
    }

    private function paginateQuery(Builder $query, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min(
            (int) ($filters['per_page'] ?? config('booking.customer_waiting_list_page_default', 20)),
            max(1, (int) config('booking.customer_waiting_list_page_max', 100))
        ));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $query
            ->tap(fn (Builder $builder) => $this->applyBucket($builder, (string) ($filters['bucket'] ?? 'active')))
            ->when(! empty($filters['status']), fn (Builder $builder) => $builder->where('status', (string) $filters['status']))
            ->orderByDesc('priority')
            ->orderByDesc('requested_at')
            ->orderByDesc('waiting_id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    private function showFromQuery(Builder $query, int $waitingId): WaitingList
    {
        $entry = $query->whereKey($waitingId)->first();
        if (! $entry) {
            throw ValidationException::withMessages([
                'waiting_id' => ['Waiting entry không tồn tại hoặc không thuộc về customer/session hiện tại.'],
            ]);
        }

        return $entry;
    }

    private function ownedQuery(int $userId): Builder
    {
        return WaitingList::query()->where('user_id', $userId);
    }

    private function sessionQuery(string $sessionId): Builder
    {
        return WaitingList::query()
            ->whereNull('user_id')
            ->where('customer_session_id', $this->normalizeSessionId($sessionId));
    }

    private function applyBucket(Builder $query, string $bucket): void
    {
        $normalized = trim(strtolower($bucket));
        if ($normalized === '' || $normalized === 'all') {
            return;
        }

        if ($normalized === 'history') {
            $query->whereIn('status', [WaitingListStatus::Seated->value, WaitingListStatus::Cancelled->value]);
            return;
        }

        $query->whereIn('status', [WaitingListStatus::Waiting->value, WaitingListStatus::Notified->value]);
    }

    private function assertNoActiveEntryConflict(int $userId): void
    {
        $activeEntry = WaitingList::query()
            ->where('user_id', $userId)
            ->whereIn('status', [WaitingListStatus::Waiting->value, WaitingListStatus::Notified->value])
            ->orderByDesc('requested_at')
            ->orderByDesc('waiting_id')
            ->first(['waiting_id', 'status']);

        if (! $activeEntry) {
            return;
        }

        throw ValidationException::withMessages([
            'waiting_list' => [sprintf(
                'Customer hiện đã có waiting entry #%d ở trạng thái %s. Hãy huỷ hoặc hoàn tất entry hiện tại trước khi tạo mới.',
                (int) $activeEntry->waiting_id,
                (string) ($activeEntry->status?->value ?? $activeEntry->status)
            )],
        ]);
    }

    private function assertNoActiveEntryConflictForSession(string $sessionId): void
    {
        $activeEntry = WaitingList::query()
            ->whereNull('user_id')
            ->where('customer_session_id', $this->normalizeSessionId($sessionId))
            ->whereIn('status', [WaitingListStatus::Waiting->value, WaitingListStatus::Notified->value])
            ->orderByDesc('requested_at')
            ->orderByDesc('waiting_id')
            ->first(['waiting_id', 'status']);

        if (! $activeEntry) {
            return;
        }

        throw ValidationException::withMessages([
            'waiting_list' => [sprintf(
                'Session hiện đã có waiting entry #%d ở trạng thái %s. Hãy huỷ hoặc hoàn tất entry hiện tại trước khi tạo mới.',
                (int) $activeEntry->waiting_id,
                (string) ($activeEntry->status?->value ?? $activeEntry->status)
            )],
        ]);
    }

    private function loadOwnedWaitingEntryForUpdate(int $waitingId, int $userId): WaitingList
    {
        $entry = WaitingList::query()
            ->whereKey($waitingId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if (! $entry) {
            throw ValidationException::withMessages([
                'waiting_id' => ['Waiting entry không tồn tại hoặc không thuộc về customer hiện tại.'],
            ]);
        }

        return $entry;
    }

    private function loadSessionWaitingEntryForUpdate(int $waitingId, string $sessionId): WaitingList
    {
        $entry = WaitingList::query()
            ->whereKey($waitingId)
            ->whereNull('user_id')
            ->where('customer_session_id', $this->normalizeSessionId($sessionId))
            ->lockForUpdate()
            ->first();

        if (! $entry) {
            throw ValidationException::withMessages([
                'waiting_id' => ['Waiting entry không tồn tại hoặc không thuộc về session hiện tại.'],
            ]);
        }

        return $entry;
    }

    private function cancelEntry(WaitingList $entry, string $cancelReason, ?int $expectedRowVersion, ?int $actorUserId, ?string $sessionId): WaitingList
    {
        if ($entry->status === WaitingListStatus::Cancelled) {
            AuditEvent::info('customer.waiting_list.cancel_noop', [
                'waiting_id' => (int) $entry->waiting_id,
                'user_id' => $actorUserId,
                'customer_session_id' => $sessionId,
                'cancel_reason' => $entry->cancel_reason,
            ]);

            return $entry;
        }

        $this->assertExpectedRowVersion($entry, $expectedRowVersion);

        if ($entry->status === WaitingListStatus::Seated) {
            throw ValidationException::withMessages([
                'status' => ['Entry đã được seat, không thể customer-cancel.'],
            ]);
        }

        $wasNotified = $entry->status === WaitingListStatus::Notified;

        $entry->status = WaitingListStatus::Cancelled;
        $entry->cancelled_at = Carbon::now('UTC');
        $entry->cancel_reason = trim($cancelReason) !== '' ? trim($cancelReason) : 'Cancelled by customer';
        $entry->notified_at = null;
        $entry->notify_expires_at = null;
        $entry->customer_response_status = null;
        $entry->customer_responded_at = null;
        $entry->customer_confirmed_arrival_at = null;
        $entry->notified_by = null;
        $entry->updated_by = $actorUserId;
        $entry->save();

        $this->cancelExistingNotifyHold((int) $entry->waiting_id, $actorUserId);

        AuditEvent::info('customer.waiting_list.cancelled', [
            'waiting_id' => (int) $entry->waiting_id,
            'user_id' => $actorUserId,
            'customer_session_id' => $sessionId,
            'cancel_reason' => $entry->cancel_reason,
        ]);

        app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.customer_cancelled', [
            'waiting_id' => (int) $entry->waiting_id,
            'cancel_reason' => (string) $entry->cancel_reason,
        ]);

        if ($wasNotified) {
            app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.customer_cancelled', [
                'waiting_id' => (int) $entry->waiting_id,
                'cancel_reason' => (string) $entry->cancel_reason,
            ], ['board']);
        }

        return $entry->fresh();
    }

    private function acceptEntry(WaitingList $entry, ?int $expectedRowVersion, ?int $actorUserId, ?string $sessionId): WaitingList
    {
        if ($this->isAcceptedState($entry)) {
            AuditEvent::info('customer.waiting_list.accept_noop', [
                'waiting_id' => (int) $entry->waiting_id,
                'user_id' => $actorUserId,
                'customer_session_id' => $sessionId,
            ]);

            return $entry;
        }

        $this->assertExpectedRowVersion($entry, $expectedRowVersion);
        $this->assertInviteActionable($entry, 'accept');

        $now = Carbon::now('UTC');
        $entry->forceFill([
            'customer_response_status' => WaitingListCustomerResponseStatus::Accepted->value,
            'customer_responded_at' => $entry->customer_responded_at ?? $now,
            'customer_confirmed_arrival_at' => null,
            'updated_by' => $actorUserId,
            'row_version' => max(1, (int) ($entry->row_version ?? 1)) + 1,
        ]);
        $entry->save();

        AuditEvent::info('customer.waiting_list.accepted', [
            'waiting_id' => (int) $entry->waiting_id,
            'user_id' => $actorUserId,
            'customer_session_id' => $sessionId,
            'notify_expires_at' => optional($entry->notify_expires_at)?->utc()->toIso8601String(),
        ]);

        app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.customer_accepted', [
            'waiting_id' => (int) $entry->waiting_id,
            'notify_expires_at' => optional($entry->notify_expires_at)?->utc()->toIso8601String(),
        ]);

        app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.customer_accepted', [
            'waiting_id' => (int) $entry->waiting_id,
        ], ['board']);

        return $entry->fresh();
    }

    private function declineEntry(WaitingList $entry, ?int $expectedRowVersion, ?int $actorUserId, ?string $sessionId): WaitingList
    {
        if ($this->isDeclinedState($entry)) {
            AuditEvent::info('customer.waiting_list.decline_noop', [
                'waiting_id' => (int) $entry->waiting_id,
                'user_id' => $actorUserId,
                'customer_session_id' => $sessionId,
            ]);

            return $entry;
        }

        $this->assertExpectedRowVersion($entry, $expectedRowVersion);
        $this->assertInviteActionable($entry, 'decline');

        $now = Carbon::now('UTC');
        $entry->forceFill([
            'status' => WaitingListStatus::Cancelled->value,
            'cancelled_at' => $now,
            'cancel_reason' => 'Declined by customer',
            'customer_response_status' => WaitingListCustomerResponseStatus::Declined->value,
            'customer_responded_at' => $now,
            'customer_confirmed_arrival_at' => null,
            'notified_at' => null,
            'notify_expires_at' => null,
            'notified_by' => null,
            'updated_by' => $actorUserId,
            'row_version' => max(1, (int) ($entry->row_version ?? 1)) + 1,
        ]);
        $entry->save();

        $this->cancelExistingNotifyHold((int) $entry->waiting_id, $actorUserId);

        AuditEvent::info('customer.waiting_list.declined', [
            'waiting_id' => (int) $entry->waiting_id,
            'user_id' => $actorUserId,
            'customer_session_id' => $sessionId,
        ]);

        app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.customer_declined', [
            'waiting_id' => (int) $entry->waiting_id,
        ]);

        app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.customer_declined', [
            'waiting_id' => (int) $entry->waiting_id,
        ], ['board']);

        return $entry->fresh();
    }

    private function confirmArrivalEntry(WaitingList $entry, ?int $expectedRowVersion, ?int $actorUserId, ?string $sessionId): WaitingList
    {
        if ($this->isArrivalConfirmedState($entry)) {
            AuditEvent::info('customer.waiting_list.confirm_arrival_noop', [
                'waiting_id' => (int) $entry->waiting_id,
                'user_id' => $actorUserId,
                'customer_session_id' => $sessionId,
            ]);

            return $entry;
        }

        $this->assertExpectedRowVersion($entry, $expectedRowVersion);
        $this->assertInviteActionable($entry, 'confirm_arrival');

        $now = Carbon::now('UTC');
        $entry->forceFill([
            'customer_response_status' => WaitingListCustomerResponseStatus::Accepted->value,
            'customer_responded_at' => $entry->customer_responded_at ?? $now,
            'customer_confirmed_arrival_at' => $now,
            'updated_by' => $actorUserId,
            'row_version' => max(1, (int) ($entry->row_version ?? 1)) + 1,
        ]);
        $entry->save();

        AuditEvent::info('customer.waiting_list.arrival_confirmed', [
            'waiting_id' => (int) $entry->waiting_id,
            'user_id' => $actorUserId,
            'customer_session_id' => $sessionId,
        ]);

        app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.customer_arrival_confirmed', [
            'waiting_id' => (int) $entry->waiting_id,
        ]);

        app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.customer_arrival_confirmed', [
            'waiting_id' => (int) $entry->waiting_id,
        ], ['board']);

        return $entry->fresh();
    }

    private function assertExpectedRowVersion(WaitingList $entry, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion !== null && (int) ($entry->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }

    private function assertInviteActionable(WaitingList $entry, string $action): void
    {
        if ($entry->status !== WaitingListStatus::Notified) {
            throw ValidationException::withMessages([
                'status' => [sprintf('Chỉ có entry ở trạng thái Notified mới có thể %s invite của customer.', $action)],
            ]);
        }

        $expiresAt = $entry->notify_expires_at?->copy()->utc();
        if (! $expiresAt) {
            throw ValidationException::withMessages([
                'notify_window' => ['Invite window hiện không hợp lệ hoặc đã bị reset.'],
            ]);
        }

        if (! $expiresAt->isFuture()) {
            throw ValidationException::withMessages([
                'notify_window' => ['Invite window đã hết hạn. Hãy chờ staff gọi lại nếu vẫn còn trong hàng chờ.'],
            ]);
        }
    }

    private function isAcceptedState(WaitingList $entry): bool
    {
        return $entry->status === WaitingListStatus::Notified
            && $entry->customer_response_status === WaitingListCustomerResponseStatus::Accepted;
    }

    private function isDeclinedState(WaitingList $entry): bool
    {
        return $entry->status === WaitingListStatus::Cancelled
            && $entry->customer_response_status === WaitingListCustomerResponseStatus::Declined
            && trim((string) ($entry->cancel_reason ?? '')) === 'Declined by customer';
    }

    private function isArrivalConfirmedState(WaitingList $entry): bool
    {
        return $entry->status === WaitingListStatus::Notified
            && $entry->customer_response_status === WaitingListCustomerResponseStatus::Accepted
            && $entry->customer_confirmed_arrival_at !== null;
    }

    private function cancelExistingNotifyHold(int $waitingId, ?int $updatedBy = null): void
    {
        TableHold::query()
            ->where('session_id', $this->buildWaitingSessionId($waitingId))
            ->whereIn('hold_status', [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value, TableHoldStatus::Confirmed->value])
            ->lockForUpdate()
            ->get()
            ->each(function (TableHold $hold) use ($updatedBy): void {
                $hold->hold_status = TableHoldStatus::Cancelled;
                $hold->updated_by = $updatedBy;
                $hold->updated_at = Carbon::now('UTC');
                $hold->save();
            });
    }

    private function buildWaitingSessionId(int $waitingId): string
    {
        return 'waiting-list:' . $waitingId;
    }

    private function normalizeSessionId(?string $sessionId): string
    {
        return trim((string) $sessionId);
    }

    private function withCustomerUserLock(int $userId, callable $callback): mixed
    {
        $lockKey = 'booking:lock:customer-waiting-list-user:' . $userId;
        $lock = Cache::store('redis')->lock($lockKey, 10);

        return $lock->block(5, function () use ($callback, $lock) {
            try {
                return $callback();
            } finally {
                optional($lock)->release();
            }
        });
    }

    private function withGuestSessionLock(string $sessionId, callable $callback): mixed
    {
        $lockKey = 'booking:lock:customer-waiting-list-session:' . sha1($this->normalizeSessionId($sessionId));
        $lock = Cache::store('redis')->lock($lockKey, 10);

        return $lock->block(5, function () use ($callback, $lock) {
            try {
                return $callback();
            } finally {
                optional($lock)->release();
            }
        });
    }

    private function withWaitingEntryLock(int $waitingId, callable $callback): mixed
    {
        $lockKey = 'booking:lock:waiting-list:' . $waitingId;
        $ttlSeconds = max(5, (int) config('booking.reservation_lock_ttl_seconds', 60));
        $waitSeconds = max(0, (int) config('booking.reservation_lock_wait_seconds', 10));

        $lock = Cache::store('redis')->lock($lockKey, $ttlSeconds);

        return $lock->block($waitSeconds, $callback);
    }
}
