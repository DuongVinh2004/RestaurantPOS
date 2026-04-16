<?php

declare(strict_types=1);

namespace App\Modules\WaitingList\Application\Services;

use App\Enums\TableHoldStatus;
use App\Enums\WaitingListCustomerResponseStatus;
use App\Enums\WaitingListStatus;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\BranchScheduling\Domain\Models\TableHold;
use App\Modules\Reporting\Application\Services\StaffOperationalRealtimeService;
use App\Modules\WaitingList\Domain\Models\WaitingList;
use App\Modules\WaitingList\Domain\State\WaitingListStateMachine;
use App\Support\AuditEvent;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerWaitingListService
{
    private readonly BranchContextService $branchContextService;

    private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService;

    public function __construct(
        ?BranchContextService $branchContextService = null,
        ?BranchSchedulingPolicyService $branchSchedulingPolicyService = null,
    ) {
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
        $this->branchSchedulingPolicyService = $branchSchedulingPolicyService ?? app(BranchSchedulingPolicyService::class);
    }

    public function listOwnerEntries(int $ownerUserId, array $filters = []): Collection
    {
        return WaitingList::query()
            ->where('user_id', $ownerUserId)
            ->when(($filters['active_only'] ?? true) === true, fn ($q) => $q->whereIn('status', [WaitingListStatus::Waiting->value, WaitingListStatus::Notified->value]))
            ->when(isset($filters['branch_id']) && $filters['branch_id'] !== '', fn ($q) => $q->where('branch_id', (int) $filters['branch_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', (string) $filters['status']))
            ->orderByDesc('requested_at')
            ->orderByDesc('waiting_id')
            ->get();
    }

    public function createEntry(int $ownerUserId, array $payload): WaitingList
    {
        return $this->withOwnerActiveEntryLock($ownerUserId, function () use ($ownerUserId, $payload) {
            return DB::transaction(function () use ($ownerUserId, $payload) {
                $hasActiveEntry = WaitingList::query()
                    ->where('user_id', $ownerUserId)
                    ->whereIn('status', [WaitingListStatus::Waiting->value, WaitingListStatus::Notified->value])
                    ->lockForUpdate()
                    ->exists();

                if ($hasActiveEntry) {
                    throw ValidationException::withMessages([
                        'waiting_list' => ['Khách hiện đã có waiting entry còn hiệu lực.'],
                    ]);
                }

                $branchId = $this->branchContextService->resolveBranchId($payload['branch_id'] ?? null);
                $this->branchSchedulingPolicyService->assertWaitingListEligible($branchId, Carbon::now('UTC'), 'branch_id', false);

                $entry = new WaitingList;
                $entry->branch_id = $branchId;
                $entry->user_id = $ownerUserId;
                $entry->guest_name = $payload['guest_name'] ?? null;
                $entry->phone = $payload['phone'] ?? null;
                $entry->guest_count = (int) $payload['guest_count'];
                $entry->requested_at = Carbon::now('UTC');
                $entry->status = WaitingListStatus::Waiting;
                $entry->priority = 0;
                $entry->notes = $payload['notes'] ?? null;
                $entry->updated_by = $ownerUserId;
                $entry->save();

                AuditEvent::info('customer.waiting_list.created', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'owner_user_id' => $ownerUserId,
                ]);

                return $entry->fresh() ?? $entry;
            });
        });
    }

    public function getOwnerEntryOrFail(int $waitingId, int $ownerUserId): WaitingList
    {
        $entry = WaitingList::query()
            ->whereKey($waitingId)
            ->where('user_id', $ownerUserId)
            ->first();

        if ($entry instanceof WaitingList) {
            return $entry;
        }

        $this->throwOwnerMutationEntryNotFound();
    }

    public function acceptEntry(int $waitingId, int $ownerUserId, ?int $expectedRowVersion = null): WaitingList
    {
        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $ownerUserId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $ownerUserId, $expectedRowVersion) {
                $entry = $this->lockOwnerEntryForUpdate($waitingId, $ownerUserId);
                $this->assertRowVersion($entry, $expectedRowVersion);
                $this->assertNotifiedState($entry);
                $this->assertOpenNotifiedWindow($entry);

                $now = Carbon::now('UTC');

                $entry->customer_response_status = WaitingListCustomerResponseStatus::Accepted;
                $entry->customer_responded_at = $entry->customer_responded_at ?? $now;
                $entry->customer_confirmed_arrival_at = null;
                $entry->updated_by = $ownerUserId;
                $entry->save();

                AuditEvent::info('customer.waiting_list.accepted', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'owner_user_id' => $ownerUserId,
                ]);

                app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.customer_accepted', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'notify_expires_at' => $entry->notify_expires_at?->copy()?->utc()?->toIso8601String(),
                ]);
                app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.customer_accepted', [
                    'waiting_id' => (int) $entry->waiting_id,
                ], ['board']);

                return $entry->fresh() ?? $entry;
            });
        });
    }

    public function confirmArrivalEntry(int $waitingId, int $ownerUserId, ?int $expectedRowVersion = null): WaitingList
    {
        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $ownerUserId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $ownerUserId, $expectedRowVersion) {
                $entry = $this->lockOwnerEntryForUpdate($waitingId, $ownerUserId);
                $this->assertRowVersion($entry, $expectedRowVersion);
                $this->assertNotifiedState($entry);
                $this->assertOpenNotifiedWindow($entry);

                $now = Carbon::now('UTC');

                $entry->customer_response_status = WaitingListCustomerResponseStatus::Accepted;
                $entry->customer_responded_at = $entry->customer_responded_at ?? $now;
                $entry->customer_confirmed_arrival_at = $now;
                $entry->updated_by = $ownerUserId;
                $entry->save();

                AuditEvent::info('customer.waiting_list.arrival_confirmed', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'owner_user_id' => $ownerUserId,
                ]);

                app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.customer_arrival_confirmed', [
                    'waiting_id' => (int) $entry->waiting_id,
                ]);
                app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.customer_arrival_confirmed', [
                    'waiting_id' => (int) $entry->waiting_id,
                ], ['board']);

                return $entry->fresh() ?? $entry;
            });
        });
    }

    public function declineEntry(int $waitingId, int $ownerUserId, ?int $expectedRowVersion = null): WaitingList
    {
        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $ownerUserId, $expectedRowVersion) {
            return DB::transaction(function () use ($waitingId, $ownerUserId, $expectedRowVersion) {
                $entry = $this->lockOwnerEntryForUpdate($waitingId, $ownerUserId);
                $this->assertRowVersion($entry, $expectedRowVersion);
                $this->assertNotifiedState($entry);
                $this->assertOpenNotifiedWindow($entry);

                $now = Carbon::now('UTC');

                WaitingListStateMachine::applyCustomerDeclined($entry, $now, $ownerUserId);
                $entry->save();

                $this->cancelExistingNotifyHold($entry, $ownerUserId);

                AuditEvent::info('customer.waiting_list.declined', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'owner_user_id' => $ownerUserId,
                ]);

                app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.customer_declined', [
                    'waiting_id' => (int) $entry->waiting_id,
                ]);
                app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.customer_declined', [
                    'waiting_id' => (int) $entry->waiting_id,
                ], ['board']);

                return $entry->fresh() ?? $entry;
            });
        });
    }

    public function cancelEntry(int $waitingId, int $ownerUserId, ?int $expectedRowVersion = null, ?string $cancelReason = null): WaitingList
    {
        return $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $ownerUserId, $expectedRowVersion, $cancelReason) {
            return DB::transaction(function () use ($waitingId, $ownerUserId, $expectedRowVersion, $cancelReason) {
                $entry = $this->lockOwnerEntryForUpdate($waitingId, $ownerUserId);
                $this->assertRowVersion($entry, $expectedRowVersion);

                if ($entry->status === WaitingListStatus::Seated) {
                    throw ValidationException::withMessages([
                        'status' => ['Entry đã seated, customer không thể cancel.'],
                    ]);
                }

                if ($entry->status === WaitingListStatus::Cancelled) {
                    return $entry;
                }

                if ($entry->status === WaitingListStatus::Notified) {
                    $this->assertOpenNotifiedWindow($entry);
                }

                WaitingListStateMachine::applyCustomerCancelled(
                    $entry,
                    Carbon::now('UTC'),
                    $this->normalizedCancelReason($cancelReason),
                    $ownerUserId
                );
                $entry->save();

                $this->cancelExistingNotifyHold($entry, $ownerUserId);

                AuditEvent::info('customer.waiting_list.cancelled', [
                    'waiting_id' => (int) $entry->waiting_id,
                    'owner_user_id' => $ownerUserId,
                ]);

                app(StaffOperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.customer_cancelled', [
                    'waiting_id' => (int) $entry->waiting_id,
                ]);
                app(StaffOperationalRealtimeService::class)->publishBoardEvent('waiting_list.customer_cancelled', [
                    'waiting_id' => (int) $entry->waiting_id,
                ], ['board']);

                return $entry->fresh() ?? $entry;
            });
        });
    }

    private function lockOwnerEntryForUpdate(int $waitingId, int $ownerUserId): WaitingList
    {
        $entry = WaitingList::query()
            ->whereKey($waitingId)
            ->where('user_id', $ownerUserId)
            ->lockForUpdate()
            ->first();

        if ($entry instanceof WaitingList) {
            return $entry;
        }

        $this->throwOwnerMutationEntryNotFound();
    }

    private function assertRowVersion(WaitingList $entry, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion !== null && (int) ($entry->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }

    private function assertNotifiedState(WaitingList $entry): void
    {
        if ($entry->status !== WaitingListStatus::Notified) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ có entry ở trạng thái Notified mới cho phép customer response này.'],
            ]);
        }
    }

    private function assertOpenNotifiedWindow(WaitingList $entry, ?Carbon $now = null): void
    {
        $now ??= Carbon::now('UTC');
        $expiresAt = $entry->notify_expires_at?->copy()?->utc();

        if ($entry->notified_at === null || $expiresAt === null || ! $now->lt($expiresAt)) {
            throw ValidationException::withMessages([
                'notify_window' => ['Notify window đã hết hạn hoặc không còn hợp lệ cho waiting entry này.'],
            ]);
        }
    }

    private function withWaitingEntryLock(int $waitingId, callable $callback): mixed
    {
        $lockKey = 'booking:lock:waiting-list:'.$waitingId;

        return $this->withDistributedLock($lockKey, $callback);
    }

    private function withOwnerActiveEntryLock(int $ownerUserId, callable $callback): mixed
    {
        $lockKey = 'booking:lock:waiting-list-owner:'.$ownerUserId;

        return $this->withDistributedLock($lockKey, $callback);
    }

    private function withDistributedLock(string $lockKey, callable $callback): mixed
    {
        $ttlSeconds = max(5, (int) config('booking.reservation_lock_ttl_seconds', 60));
        $waitSeconds = max(0, (int) config('booking.reservation_lock_wait_seconds', 10));

        foreach ($this->candidateCacheStores() as $storeName) {
            try {
                $lock = Cache::store($storeName)->lock($lockKey, $ttlSeconds);

                return $lock->block($waitSeconds, $callback);
            } catch (LockTimeoutException $exception) {
                throw $exception;
            } catch (Throwable) {
                continue;
            }
        }

        return $callback();
    }

    private function candidateCacheStores(): array
    {
        $stores = ['redis'];
        $defaultStore = (string) config('cache.default', 'file');

        if (! in_array($defaultStore, $stores, true)) {
            $stores[] = $defaultStore;
        }

        $stores[] = 'array';

        return array_values(array_unique(array_filter($stores)));
    }

    private function cancelExistingNotifyHold(WaitingList $entry, ?int $ownerUserId = null): void
    {
        $now = Carbon::now('UTC');

        TableHold::query()
            ->where('session_id', $this->buildWaitingSessionId((int) $entry->waiting_id))
            ->whereIn('hold_status', [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value, TableHoldStatus::Confirmed->value])
            ->lockForUpdate()
            ->get()
            ->each(function (TableHold $hold) use ($ownerUserId, $now): void {
                $hold->hold_status = TableHoldStatus::Cancelled;
                $hold->updated_by = $ownerUserId;
                $hold->updated_at = $now;
                $hold->save();
            });
    }

    private function buildWaitingSessionId(int $waitingId): string
    {
        return 'waiting-list:'.$waitingId;
    }

    private function throwOwnerMutationEntryNotFound(): never
    {
        $exception = new ModelNotFoundException;
        $exception->setModel(WaitingList::class);

        throw $exception;
    }

    private function normalizedCancelReason(?string $cancelReason): string
    {
        $value = trim((string) $cancelReason);

        return $value !== '' ? $value : 'Cancelled by customer';
    }
}
