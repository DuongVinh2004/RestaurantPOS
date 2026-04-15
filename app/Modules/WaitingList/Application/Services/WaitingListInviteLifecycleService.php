<?php

declare(strict_types=1);

namespace App\Modules\WaitingList\Application\Services;

use App\Enums\TableHoldStatus;
use App\Enums\WaitingListCustomerResponseStatus;
use App\Enums\WaitingListStatus;
use App\Modules\BranchScheduling\Domain\Models\TableHold;
use App\Modules\WaitingList\Domain\Models\WaitingList;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WaitingListInviteLifecycleService
{
    public function hydrateCollection(EloquentCollection $entries): EloquentCollection
    {
        $this->attachInviteLifecycleContext($entries);

        return $entries;
    }

    public function hydrateEntry(WaitingList $entry): WaitingList
    {
        $this->attachInviteLifecycleContext(new EloquentCollection([$entry]));

        return $entry;
    }

    private function attachInviteLifecycleContext(EloquentCollection $entries): void
    {
        if ($entries->isEmpty()) {
            return;
        }

        $waitingIds = $entries
            ->pluck('waiting_id')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (int) $value)
            ->values();

        if ($waitingIds->isEmpty()) {
            return;
        }

        $sessionIdByWaitingId = $waitingIds
            ->mapWithKeys(fn (int $waitingId) => [$waitingId => $this->buildWaitingSessionId($waitingId)]);

        $holdsBySession = TableHold::query()
            ->whereIn('session_id', $sessionIdByWaitingId->values()->all())
            ->with('tables:table_id')
            ->orderByDesc('created_at')
            ->orderByDesc('hold_id')
            ->get()
            ->groupBy('session_id');

        $now = Carbon::now('UTC');

        foreach ($entries as $entry) {
            if (! $entry instanceof WaitingList) {
                continue;
            }

            $waitingId = (int) $entry->waiting_id;
            $sessionId = (string) ($sessionIdByWaitingId[$waitingId] ?? $this->buildWaitingSessionId($waitingId));
            $sessionHolds = $holdsBySession->get($sessionId, new Collection());
            $latestHold = $sessionHolds->first();
            $activeHold = $sessionHolds->first(fn (TableHold $hold) => $this->isActiveHold($hold, $now));

            $entry->setAttribute('invite_lifecycle_context', $this->buildContext($entry, $sessionId, $activeHold, $latestHold, $now));
        }
    }

    private function buildContext(WaitingList $entry, string $sessionId, ?TableHold $activeHold, ?TableHold $latestHold, Carbon $now): array
    {
        $status = $entry->status instanceof WaitingListStatus
            ? $entry->status
            : WaitingListStatus::tryFrom((string) $entry->status);
        $responseStatus = $entry->customer_response_status instanceof WaitingListCustomerResponseStatus
            ? $entry->customer_response_status
            : WaitingListCustomerResponseStatus::tryFrom((string) $entry->customer_response_status);

        $notifiedAt = $entry->notified_at?->copy()->utc();
        $expiresAt = $entry->notify_expires_at?->copy()->utc();
        $windowActive = $status === WaitingListStatus::Notified && $expiresAt !== null && $expiresAt->isFuture();
        $windowExpired = $status === WaitingListStatus::Notified && $expiresAt !== null && ! $expiresAt->isFuture();
        $hasArrivalConfirmation = $entry->customer_confirmed_arrival_at !== null;
        $hasAcceptedResponse = $responseStatus === WaitingListCustomerResponseStatus::Accepted
            || ($status === WaitingListStatus::Notified && $entry->customer_responded_at !== null);
        $hasDeclinedResponse = $responseStatus === WaitingListCustomerResponseStatus::Declined
            || (
                $status === WaitingListStatus::Waiting
                && $entry->customer_responded_at !== null
                && $entry->customer_confirmed_arrival_at === null
                && $entry->notified_at === null
                && $entry->notify_expires_at === null
            )
            || (
                $status === WaitingListStatus::Cancelled
                && trim((string) ($entry->cancel_reason ?? '')) === 'Declined by customer'
            );

        $currentResponseState = match (true) {
            $windowExpired => 'invite_expired',
            $status === WaitingListStatus::Notified && $hasArrivalConfirmation => 'arrival_confirmed',
            $status === WaitingListStatus::Notified && $hasAcceptedResponse => 'accepted',
            $status === WaitingListStatus::Notified => 'pending',
            $hasDeclinedResponse => 'declined',
            default => 'none',
        };

        $hasActiveHold = $activeHold !== null;
        $seatReadiness = match (true) {
            $status !== WaitingListStatus::Notified => 'not_notified',
            $windowExpired => 'invite_expired',
            ! $hasActiveHold => 'notify_hold_missing',
            $hasArrivalConfirmation => 'ready_to_seat',
            $responseStatus === WaitingListCustomerResponseStatus::Accepted => 'customer_accepted',
            default => 'awaiting_customer_response',
        };

        $customerNextStep = match ($currentResponseState) {
            'pending' => 'respond_to_invite',
            'accepted', 'arrival_confirmed' => 'wait_for_staff_seat',
            'declined', 'invite_expired' => 'wait_for_next_invite',
            default => $status === WaitingListStatus::Waiting ? 'wait_to_be_called' : 'none',
        };

        $staffNextStep = match ($seatReadiness) {
            'ready_to_seat' => 'seat_customer_when_ready',
            'customer_accepted' => 'verify_arrival_then_seat',
            'awaiting_customer_response' => 'wait_for_customer_response_or_expiry',
            'invite_expired' => 'expire_or_renotify',
            'notify_hold_missing' => 'investigate_or_renotify',
            default => $status === WaitingListStatus::Waiting ? 'wait_in_queue_or_renotify_later' : 'none',
        };

        return [
            'waiting_session_id' => $sessionId,
            'current_response_state' => $currentResponseState,
            'invite_window' => [
                'notified_at' => $notifiedAt?->toIso8601String(),
                'expires_at' => $expiresAt?->toIso8601String(),
                'is_active' => $windowActive,
                'is_expired' => $windowExpired,
                'seconds_remaining' => $windowActive && $expiresAt !== null ? max(0, $now->diffInSeconds($expiresAt, false)) : 0,
            ],
            'hold' => [
                'has_active_hold' => $hasActiveHold,
                'active' => $this->summarizeHold($activeHold),
                'latest' => $this->summarizeHold($latestHold),
            ],
            'semantics' => [
                'requires_explicit_staff_seat' => true,
                'auto_convert_to_reservation' => false,
                'can_staff_seat_now' => $status === WaitingListStatus::Notified && $windowActive && $hasActiveHold,
                'seat_readiness' => $seatReadiness,
                'customer_next_step' => $customerNextStep,
                'staff_next_step' => $staffNextStep,
            ],
        ];
    }

    private function summarizeHold(?TableHold $hold): ?array
    {
        if (! $hold instanceof TableHold) {
            return null;
        }

        return [
            'hold_id' => (string) $hold->hold_id,
            'status' => $hold->hold_status?->value ?? (string) $hold->hold_status,
            'session_id' => (string) $hold->session_id,
            'expires_at' => $hold->expire_at?->copy()->utc()->toIso8601String(),
            'confirmed_reservation_id' => $hold->confirmed_reservation_id !== null ? (int) $hold->confirmed_reservation_id : null,
            'table_ids' => $hold->relationLoaded('tables')
                ? $hold->tables->pluck('table_id')->map(fn ($tableId) => (int) $tableId)->values()->all()
                : [],
        ];
    }

    private function isActiveHold(TableHold $hold, Carbon $now): bool
    {
        $holdStatus = $hold->hold_status instanceof TableHoldStatus
            ? $hold->hold_status
            : TableHoldStatus::tryFrom((string) $hold->hold_status);

        return in_array($holdStatus, [TableHoldStatus::Holding, TableHoldStatus::Pending, TableHoldStatus::Confirmed], true)
            && ($hold->expire_at === null || $hold->expire_at->copy()->utc()->isFuture());
    }

    private function buildWaitingSessionId(int $waitingId): string
    {
        return 'waiting-list:' . $waitingId;
    }
}
