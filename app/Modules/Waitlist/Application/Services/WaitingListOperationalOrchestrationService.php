<?php

declare(strict_types=1);

namespace App\Modules\Waitlist\Application\Services;

use App\Enums\RestaurantTableStatus;
use App\Enums\TableHoldStatus;
use App\Enums\WaitingListCustomerResponseStatus;
use App\Enums\WaitingListStatus;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\BranchScheduling\Domain\Models\TableHold;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Modules\Waitlist\Domain\Models\WaitlistEntry;
use App\Modules\Waitlist\Domain\StateMachines\WaitlistInvitationStateMachine;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaitingListOperationalOrchestrationService
{
    public function __construct(
        private readonly WaitingListInviteLifecycleService $inviteLifecycleService,
        private readonly StaffWaitingListService $staffWaitingListService,
        private readonly FeatureFlagService $featureFlags,
    ) {}

    public function hydrateCollection(EloquentCollection $entries): EloquentCollection
    {
        $this->inviteLifecycleService->hydrateCollection($entries);

        if ($entries->isEmpty()) {
            return $entries;
        }

        $tableMap = $this->loadTableContextMap($entries);

        foreach ($entries as $entry) {
            if (! $entry instanceof WaitlistEntry) {
                continue;
            }

            $entry->setAttribute('waiting_orchestration_context', $this->buildOrchestrationContext($entry, $tableMap));
        }

        return $entries;
    }

    public function hydrateEntry(WaitlistEntry $entry): WaitlistEntry
    {
        $this->hydrateCollection(new EloquentCollection([$entry]));

        return $entry;
    }

    /**
     * @return array<string,mixed>
     */
    public function buildSummary(EloquentCollection $entries): array
    {
        $readyToSeat = 0;
        $advanceReady = 0;
        $advanceBlocked = 0;
        $awaitingCustomer = 0;
        $holdInvestigation = 0;

        foreach ($entries as $entry) {
            if (! $entry instanceof WaitlistEntry) {
                continue;
            }

            $context = is_array($entry->getAttribute('waiting_orchestration_context'))
                ? $entry->getAttribute('waiting_orchestration_context')
                : [];

            $actionableState = (string) ($context['actionable_state'] ?? 'none');

            if ($actionableState === 'seat_customer') {
                $readyToSeat++;
                continue;
            }

            if ($actionableState === 'advance_queue') {
                if ((bool) ($context['advance_queue']['can_apply_now'] ?? false)) {
                    $advanceReady++;
                } else {
                    $advanceBlocked++;
                }

                continue;
            }

            if ($actionableState === 'await_customer_response' || $actionableState === 'await_customer_arrival') {
                $awaitingCustomer++;
                continue;
            }

            if ($actionableState === 'investigate_hold_state') {
                $holdInvestigation++;
            }
        }

        return [
            'mode' => 'semi_automated_waiting_list_orchestration',
            'ready_to_seat_count' => $readyToSeat,
            'advance_queue_ready_count' => $advanceReady,
            'advance_queue_blocked_count' => $advanceBlocked,
            'awaiting_customer_follow_up_count' => $awaitingCustomer,
            'hold_investigation_count' => $holdInvestigation,
        ];
    }

    /**
     * @return array{source_waiting_list: WaitlistEntry, advanced_waiting_list: WaitlistEntry|null, automation: array<string,mixed>}
     */
    public function advanceQueueAfterResponse(
        int $waitingId,
        ?int $staffUserId = null,
        ?int $expectedRowVersion = null,
        ?int $holdMinutesOverride = null,
    ): array {
        $sourceOutcome = $this->withWaitingEntryLock($waitingId, function () use ($waitingId, $staffUserId, $expectedRowVersion, $holdMinutesOverride) {
            return DB::transaction(function () use ($waitingId, $staffUserId, $expectedRowVersion, $holdMinutesOverride) {
                $source = WaitlistEntry::query()
                    ->with('user')
                    ->whereKey($waitingId)
                    ->lockForUpdate()
                    ->first();

                if (! $source) {
                    throw ValidationException::withMessages([
                        'waiting_id' => ['Waiting entry khÃ´ng tá»“n táº¡i.'],
                    ]);
                }

                if ($expectedRowVersion !== null && (int) ($source->row_version ?? 1) !== $expectedRowVersion) {
                    throw ValidationException::withMessages([
                        'row_version' => ['Dá»¯ liá»‡u Ä‘Ã£ thay Ä‘á»•i (row_version mismatch). HÃ£y reload rá»“i thá»­ láº¡i.'],
                    ]);
                }

                $this->featureFlags->assertEnabled(
                    'waiting_list.advanced_automation',
                    $source->branch_id !== null ? (int) $source->branch_id : null,
                    field: 'feature_flag',
                );

                $this->inviteLifecycleService->hydrateEntry($source);
                $inviteContext = is_array($source->getAttribute('invite_lifecycle_context'))
                    ? $source->getAttribute('invite_lifecycle_context')
                    : [];

                $currentResponseState = (string) ($inviteContext['current_response_state'] ?? 'none');
                $seatReadiness = (string) data_get($inviteContext, 'semantics.seat_readiness', 'not_notified');

                $isDeclinedCancellation = $source->status === WaitingListStatus::Cancelled
                    && $currentResponseState === 'declined'
                    && trim((string) ($source->cancel_reason ?? '')) === 'Declined by customer';

                if ($source->status === WaitingListStatus::Seated || ($source->status === WaitingListStatus::Cancelled && ! $isDeclinedCancellation)) {
                    throw ValidationException::withMessages([
                        'status' => ['Waiting entry Ä‘Ã£ káº¿t thÃºc, khÃ´ng thá»ƒ orchestration tiáº¿p.'],
                    ]);
                }

                if ($currentResponseState === 'arrival_confirmed' || $seatReadiness === 'ready_to_seat') {
                    throw ValidationException::withMessages([
                        'status' => ['Customer Ä‘Ã£ xÃ¡c nháº­n Ä‘áº¿n nÆ¡i; hÃ£y dÃ¹ng flow seat hiá»‡n cÃ³ thay vÃ¬ advance queue.'],
                    ]);
                }

                if ($currentResponseState === 'accepted' || $seatReadiness === 'customer_accepted') {
                    throw ValidationException::withMessages([
                        'status' => ['Customer Ä‘Ã£ accept invite; hÃ£y xÃ¡c minh arrival hoáº·c dÃ¹ng flow seat hiá»‡n cÃ³ thay vÃ¬ advance queue.'],
                    ]);
                }

                if (! in_array($currentResponseState, ['declined', 'invite_expired'], true)) {
                    throw ValidationException::withMessages([
                        'status' => ['Chá»‰ entry vá»«a decline hoáº·c invite Ä‘Ã£ háº¿t háº¡n má»›i cÃ³ thá»ƒ advance queue tá»± Ä‘á»™ng.'],
                    ]);
                }

                $latestHold = $this->loadLatestHoldForUpdate($waitingId);
                $releasedTable = $latestHold ? $this->loadPrimaryTableSummary($latestHold) : null;

                if ($releasedTable === null) {
                    throw ValidationException::withMessages([
                        'waiting_id' => ['KhÃ´ng tÃ¬m tháº¥y table context tá»« invite/hold gáº§n nháº¥t Ä‘á»ƒ advance queue an toÃ n.'],
                    ]);
                }

                $sourceTransition = 'declined_entry_acknowledged';
                if ($currentResponseState === 'invite_expired' && $source->status === WaitingListStatus::Notified) {
                    WaitlistInvitationStateMachine::applyExpiredToWaiting($source, $staffUserId);
                    $sourceTransition = 'expired_entry_returned_to_waiting';
                }

                $source->updated_by = $staffUserId;
                $source->updated_at = Carbon::now('UTC');

                // bá» synthetic runtime attribute Ä‘á»ƒ khÃ´ng bá»‹ Eloquent cá»‘ persist xuá»‘ng DB
                $source->offsetUnset('invite_lifecycle_context');
                $source->offsetUnset('waiting_orchestration_context');

                $source->save();

                $this->cancelOpenSourceHolds((int) $source->waiting_id, $staffUserId);

                $candidate = null;
                $selectionReason = 'released_table_unavailable';
                if (($releasedTable['status'] ?? null) === RestaurantTableStatus::Available->value && (int) ($releasedTable['seats'] ?? 0) > 0) {
                    $candidate = WaitlistEntry::query()
                        ->with('user')
                        ->where('status', WaitingListStatus::Waiting->value)
                        ->where('waiting_id', '!=', (int) $source->waiting_id)
                        ->where('guest_count', '<=', (int) $releasedTable['seats'])
                        ->orderByDesc('priority')
                        ->orderBy('requested_at')
                        ->orderBy('waiting_id')
                        ->lockForUpdate()
                        ->first();

                    $selectionReason = $candidate instanceof WaitlistEntry
                        ? 'next_queue_candidate_selected'
                        : 'no_matching_waiting_candidate';
                }

                $holdMinutes = max(1, min(60, (int) ($holdMinutesOverride ?? $latestHold?->duration_minutes ?? config('booking.waiting_list_notify_hold_minutes', 10))));

                return [
                    'source' => $source->fresh(['user']),
                    'candidate' => $candidate,
                    'released_table' => $releasedTable,
                    'hold_minutes' => $holdMinutes,
                    'source_transition' => $sourceTransition,
                    'selection_reason' => $selectionReason,
                ];
            });
        });

        $advanced = null;
        $automationResult = (string) ($sourceOutcome['selection_reason'] ?? 'no_matching_waiting_candidate');
        $advanceFailure = null;

        $candidate = $sourceOutcome['candidate'] ?? null;
        $releasedTable = $sourceOutcome['released_table'] ?? null;

        if ($candidate instanceof WaitlistEntry && is_array($releasedTable)) {
            try {
                $advanced = $this->staffWaitingListService->notifyEntry(
                    (int) $candidate->waiting_id,
                    [
                        'table_id' => (int) $releasedTable['table_id'],
                        'hold_minutes' => (int) ($sourceOutcome['hold_minutes'] ?? config('booking.waiting_list_notify_hold_minutes', 10)),
                    ],
                    $staffUserId,
                    (int) ($candidate->row_version ?? 1),
                )->load('user');

                $automationResult = 'notified_next_candidate';
            } catch (ValidationException $e) {
                $advanceFailure = $e->errors();
                $automationResult = 'notify_next_candidate_failed';
            }
        }

        $source = WaitlistEntry::query()->with('user')->whereKey($waitingId)->firstOrFail();
        $source = $this->hydrateEntry($source);

        if ((string) ($sourceOutcome['source_transition'] ?? '') === 'declined_entry_acknowledged') {
            $inviteContext = is_array($source->getAttribute('invite_lifecycle_context'))
                ? $source->getAttribute('invite_lifecycle_context')
                : [];

            if (($inviteContext['current_response_state'] ?? 'none') === 'none') {
                $inviteContext['current_response_state'] = 'declined';
                $source->setAttribute('invite_lifecycle_context', $inviteContext);
            }
        }

        app(OperationalRealtimeService::class)->publishWaitingListEvent('waiting_list.queue_advanced', [
            'source_waiting_id' => (int) $source->waiting_id,
            'advanced_waiting_id' => $advanced ? (int) $advanced->waiting_id : null,
            'source_transition' => (string) ($sourceOutcome['source_transition'] ?? 'unknown'),
            'result' => $automationResult,
        ]);

        app(OperationalRealtimeService::class)->publishBoardEvent('waiting_list.queue_advanced', [
            'source_waiting_id' => (int) $source->waiting_id,
            'advanced_waiting_id' => $advanced ? (int) $advanced->waiting_id : null,
            'source_transition' => (string) ($sourceOutcome['source_transition'] ?? 'unknown'),
            'result' => $automationResult,
            'released_table_id' => is_array($releasedTable) ? (int) ($releasedTable['table_id'] ?? 0) : null,
        ], ['board']);

        return [
            'source_waiting_list' => $source,
            'advanced_waiting_list' => $advanced ? $this->hydrateEntry($advanced) : null,
            'automation' => [
                'mode' => 'semi_automated_response_orchestration',
                'source_transition' => (string) ($sourceOutcome['source_transition'] ?? 'unknown'),
                'result' => $automationResult,
                'released_table' => $releasedTable,
                'hold_minutes' => (int) ($sourceOutcome['hold_minutes'] ?? config('booking.waiting_list_notify_hold_minutes', 10)),
                'selected_candidate' => $candidate instanceof WaitlistEntry
                    ? $this->summarizeCandidatePreview($candidate, (int) ($releasedTable['seats'] ?? 0))
                    : null,
                'failure' => $advanceFailure,
                'reuses_canonical_notify_flow' => true,
                'reuses_canonical_seat_flow' => true,
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $tableMap
     * @return array<string,mixed>
     */
    private function buildOrchestrationContext(WaitlistEntry $entry, array $tableMap): array
    {
        $inviteContext = is_array($entry->getAttribute('invite_lifecycle_context'))
            ? $entry->getAttribute('invite_lifecycle_context')
            : [];

        $currentResponseState = (string) ($inviteContext['current_response_state'] ?? 'none');
        $seatReadiness = (string) data_get($inviteContext, 'semantics.seat_readiness', 'not_notified');
        $canStaffSeatNow = (bool) data_get($inviteContext, 'semantics.can_staff_seat_now', false);
        $latestHold = is_array(data_get($inviteContext, 'hold.latest')) ? data_get($inviteContext, 'hold.latest') : null;
        $releasedTable = $this->resolveReleasedTableContext($latestHold, $tableMap);
        $automationFeature = $this->featureFlags->resolve(
            'waiting_list.advanced_automation',
            $entry->branch_id !== null ? (int) $entry->branch_id : null,
        );
        $automationEnabled = (bool) ($automationFeature['enabled'] ?? false);
        $automationDisabledReason = $automationEnabled ? null : (string) ($automationFeature['message'] ?? '');

        $actionableState = match (true) {
            $currentResponseState === 'arrival_confirmed' || $seatReadiness === 'ready_to_seat' => 'seat_customer',
            $currentResponseState === 'accepted' || $seatReadiness === 'customer_accepted' => 'await_customer_arrival',
            $currentResponseState === 'declined',
            $currentResponseState === 'invite_expired' => 'advance_queue',
            $entry->status === WaitingListStatus::Cancelled || $entry->status === WaitingListStatus::Seated => 'closed',
            $seatReadiness === 'notify_hold_missing' => 'investigate_hold_state',
            $currentResponseState === 'pending' => 'await_customer_response',
            $entry->status === WaitingListStatus::Waiting => 'wait_in_queue',
            default => 'none',
        };

        $nextCandidate = null;
        if (
            $actionableState === 'advance_queue'
            && $automationEnabled
            && $releasedTable !== null
            && ($releasedTable['status'] ?? null) === RestaurantTableStatus::Available->value
            && (int) ($releasedTable['seats'] ?? 0) > 0
        ) {
            $candidate = WaitlistEntry::query()
                ->with('user')
                ->where('status', WaitingListStatus::Waiting->value)
                ->where('waiting_id', '!=', (int) $entry->waiting_id)
                ->where('guest_count', '<=', (int) $releasedTable['seats'])
                ->orderByDesc('priority')
                ->orderBy('requested_at')
                ->orderBy('waiting_id')
                ->first();

            if ($candidate instanceof WaitlistEntry) {
                $nextCandidate = $this->summarizeCandidatePreview($candidate, (int) $releasedTable['seats']);
            }
        }

        $advanceQueue = [
            'supported' => $actionableState === 'advance_queue' && $releasedTable !== null && $automationEnabled,
            'can_apply_now' => $actionableState === 'advance_queue'
                && $automationEnabled
                && $releasedTable !== null
                && $nextCandidate !== null
                && ($releasedTable['status'] ?? null) === RestaurantTableStatus::Available->value,
            'resulting_action' => $actionableState === 'advance_queue' && $automationEnabled ? 'notify_next_candidate' : 'none',
            'released_table_available' => ($releasedTable['status'] ?? null) === RestaurantTableStatus::Available->value,
            'next_candidate' => $nextCandidate,
            'disabled_reason' => $actionableState === 'advance_queue' && ! $automationEnabled ? $automationDisabledReason : null,
        ];

        $recommendedAction = match ($actionableState) {
            'seat_customer' => 'seat_current_customer',
            'await_customer_arrival' => 'wait_or_verify_arrival_then_seat',
            'advance_queue' => ! $automationEnabled
                ? 'use_canonical_waiting_list_notify_flow'
                : ($advanceQueue['can_apply_now'] ? 'advance_queue_to_next_candidate' : 'review_released_table_before_advancing_queue'),
            'investigate_hold_state' => 'investigate_missing_or_stale_hold',
            'await_customer_response' => 'wait_for_customer_response_or_expiry',
            'wait_in_queue' => 'keep_waiting_in_queue',
            'closed' => 'none',
            default => 'none',
        };

        return [
            'mode' => 'semi_automated_waiting_list_orchestration',
            'actionable_state' => $actionableState,
            'recommended_action' => $recommendedAction,
            'released_table' => $releasedTable,
            'advance_queue' => $advanceQueue,
            'actions' => $this->buildActionHints($entry, $actionableState, $canStaffSeatNow, $advanceQueue, $releasedTable),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $tableMap
     * @param array<string,mixed>|null $latestHold
     * @return array<string,mixed>|null
     */
    private function resolveReleasedTableContext(?array $latestHold, array $tableMap): ?array
    {
        if ($latestHold === null) {
            return null;
        }

        $tableIds = collect((array) ($latestHold['table_ids'] ?? []))
            ->map(fn ($tableId) => (int) $tableId)
            ->filter(fn (int $tableId) => $tableId > 0)
            ->values();

        if ($tableIds->isEmpty()) {
            return null;
        }

        $primaryTableId = (int) $tableIds->first();
        if (! array_key_exists($primaryTableId, $tableMap)) {
            return [
                'table_id' => $primaryTableId,
                'table_ids' => $tableIds->all(),
                'table_code' => null,
                'zone' => null,
                'status' => null,
                'seats' => null,
            ];
        }

        return array_merge($tableMap[$primaryTableId], [
            'table_ids' => $tableIds->all(),
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $tableMap
     * @return list<array<string,mixed>>
     */
    private function buildActionHints(WaitlistEntry $entry, string $actionableState, bool $canStaffSeatNow, array $advanceQueue, ?array $releasedTable): array
    {
        $actions = [];
        $waitingId = (int) $entry->waiting_id;

        $actions[] = [
            'key' => 'seat',
            'method' => 'POST',
            'href' => '/api/v1/staff/waiting-list/' . $waitingId . '/seat',
            'enabled' => $canStaffSeatNow,
            'reason' => $canStaffSeatNow ? 'canonical_staff_seat_flow' : 'seat_not_ready',
        ];

        if ($actionableState === 'advance_queue') {
            $actions[] = [
                'key' => 'advance_queue',
                'method' => 'POST',
                'href' => '/api/v1/staff/waiting-list/' . $waitingId . '/advance',
                'enabled' => (bool) ($advanceQueue['can_apply_now'] ?? false),
                'reason' => ! (bool) ($advanceQueue['supported'] ?? false)
                    ? 'feature_disabled'
                    : ((bool) ($advanceQueue['can_apply_now'] ?? false)
                        ? 'notify_next_candidate_for_released_table'
                        : (($releasedTable['status'] ?? null) !== RestaurantTableStatus::Available->value
                            ? 'released_table_not_available'
                            : 'no_matching_candidate')),
            ];
        }

        return $actions;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadTableContextMap(EloquentCollection $entries): array
    {
        $tableIds = [];

        foreach ($entries as $entry) {
            if (! $entry instanceof WaitlistEntry) {
                continue;
            }

            $inviteContext = is_array($entry->getAttribute('invite_lifecycle_context'))
                ? $entry->getAttribute('invite_lifecycle_context')
                : [];
            $latestHold = is_array(data_get($inviteContext, 'hold.latest')) ? data_get($inviteContext, 'hold.latest') : null;

            foreach ((array) ($latestHold['table_ids'] ?? []) as $tableId) {
                $tableId = (int) $tableId;
                if ($tableId > 0) {
                    $tableIds[$tableId] = $tableId;
                }
            }
        }

        if ($tableIds === []) {
            return [];
        }

        return RestaurantTable::query()
            ->leftJoin('table_templates', 'table_templates.template_id', '=', 'restaurant_tables.template_id')
            ->whereIn('restaurant_tables.table_id', array_values($tableIds))
            ->get([
                'restaurant_tables.table_id',
                'restaurant_tables.table_code',
                'restaurant_tables.zone',
                'restaurant_tables.status',
                'table_templates.seats',
            ])
            ->mapWithKeys(static function ($row): array {
                return [
                    (int) $row->table_id => [[
                        'table_id' => (int) $row->table_id,
                        'table_code' => (string) $row->table_code,
                        'zone' => $row->zone !== null ? (string) $row->zone : null,
                        'status' => self::normalizeTableStatus($row->status),
                        'seats' => $row->seats !== null ? (int) $row->seats : null,
                    ]],
                ];
            })
            ->all();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadPrimaryTableSummary(TableHold $hold): ?array
    {
        $tableId = $hold->tables->pluck('table_id')->map(fn ($value) => (int) $value)->filter(fn (int $value) => $value > 0)->first();
        if (! $tableId) {
            return null;
        }

        $row = RestaurantTable::query()
            ->leftJoin('table_templates', 'table_templates.template_id', '=', 'restaurant_tables.template_id')
            ->where('restaurant_tables.table_id', $tableId)
            ->first([
                'restaurant_tables.table_id',
                'restaurant_tables.table_code',
                'restaurant_tables.zone',
                'restaurant_tables.status',
                'table_templates.seats',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'table_id' => (int) $row->table_id,
            'table_ids' => $hold->tables->pluck('table_id')->map(fn ($value) => (int) $value)->filter(fn (int $value) => $value > 0)->values()->all(),
            'table_code' => (string) $row->table_code,
            'zone' => $row->zone !== null ? (string) $row->zone : null,
            'status' => self::normalizeTableStatus($row->status),
            'seats' => $row->seats !== null ? (int) $row->seats : null,
        ];
    }

    private static function normalizeTableStatus(mixed $status): ?string
    {
        if ($status instanceof RestaurantTableStatus) {
            return $status->value;
        }

        return $status !== null ? (string) $status : null;
    }

    private function loadLatestHoldForUpdate(int $waitingId): ?TableHold
    {
        return TableHold::query()
            ->where('session_id', $this->buildWaitingSessionId($waitingId))
            ->with('tables:table_id')
            ->lockForUpdate()
            ->orderByDesc('created_at')
            ->orderByDesc('hold_id')
            ->first();
    }

    private function cancelOpenSourceHolds(int $waitingId, ?int $staffUserId = null): void
    {
        TableHold::query()
            ->where('session_id', $this->buildWaitingSessionId($waitingId))
            ->whereIn('hold_status', [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value, TableHoldStatus::Confirmed->value])
            ->lockForUpdate()
            ->get()
            ->each(function (TableHold $hold) use ($staffUserId): void {
                $hold->hold_status = TableHoldStatus::Cancelled;
                $hold->updated_by = $staffUserId;
                $hold->updated_at = Carbon::now('UTC');
                $hold->save();
            });
    }

    /**
     * @return array<string,mixed>
     */
    private function summarizeCandidatePreview(WaitlistEntry $candidate, int $tableSeats): array
    {
        return [
            'waiting_id' => (int) $candidate->waiting_id,
            'user_id' => $candidate->user_id !== null ? (int) $candidate->user_id : null,
            'guest_name' => $candidate->guest_name,
            'guest_count' => (int) $candidate->guest_count,
            'priority' => (int) ($candidate->priority ?? 0),
            'requested_at' => optional($candidate->requested_at)->utc()->toIso8601String(),
            'row_version' => (int) ($candidate->row_version ?? 1),
            'capacity_fit' => [
                'table_seats' => $tableSeats,
                'seat_delta' => max(0, $tableSeats - (int) $candidate->guest_count),
            ],
        ];
    }

    private function withWaitingEntryLock(int $waitingId, callable $callback): mixed
    {
        $lockKey = 'booking:lock:waiting-list:' . $waitingId;
        $ttlSeconds = max(5, (int) config('booking.reservation_lock_ttl_seconds', 60));
        $waitSeconds = max(0, (int) config('booking.reservation_lock_wait_seconds', 10));

        $lock = Cache::store('redis')->lock($lockKey, $ttlSeconds);

        return $lock->block($waitSeconds, $callback);
    }

    private function buildWaitingSessionId(int $waitingId): string
    {
        return 'waiting-list:' . $waitingId;
    }
}
