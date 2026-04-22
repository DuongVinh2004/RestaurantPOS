<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Application\Services;

use App\Enums\StaffConversationWorkflowState;
use App\Modules\Conversations\Domain\Models\AgentAssignment;
use App\Modules\Conversations\Domain\Models\Conversation;
use App\Modules\Conversations\Domain\Models\ConversationAnalysis;
use App\Modules\Conversations\Domain\Models\ConversationEvent;
use App\Modules\Conversations\Domain\Models\ConversationMessage;
use App\Modules\Conversations\Infrastructure\Internal\ConversationAiAssistBuilder;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

class StaffConversationInboxService
{
    public const OVERDUE_AFTER_MINUTES = 15;

    public function __construct(
        private readonly FeatureFlagService $featureFlags,
        private readonly StaffConversationOutboundReplySupportService $outboundReplySupportService,
        private readonly ConversationAiAssistBuilder $conversationAiAssistBuilder,
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   paginator: LengthAwarePaginator,
     *   summary: array<string, mixed>
     * }
     */
    public function paginate(array $filters = [], ?int $staffActorUserId = null): array
    {
        $filters['accessible_branch_ids'] = $this->branchContextService->branchScopeOrAccessible(
            $staffActorUserId,
            isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
        );

        $this->featureFlags->assertEnabled(
            'staff.conversation_inbox',
            isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
            field: 'conversation_inbox',
        );

        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $sortBy = $this->resolveSortBy((string) ($filters['sort_by'] ?? 'latest_activity'));
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc'));
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $baseQuery = $this->buildFilteredQuery($filters, $staffActorUserId, false);
        $summary = $this->buildSummary($baseQuery, $staffActorUserId);

        $query = $this->applyInboxViewFilter(
            $this->decorateSummaryQuery(clone $baseQuery),
            (string) ($filters['inbox_view'] ?? 'all'),
        );
        $this->applyOrdering($query, $sortBy, $sortDir);

        return [
            'paginator' => $query->paginate($perPage, ['*'], 'page', $page),
            'summary' => $summary,
        ];
    }

    public function findSummaryOrFail(string $conversationId, ?int $staffActorUserId = null): Conversation
    {
        $query = Conversation::query()->where('conversation_id', $conversationId);

        if ($staffActorUserId !== null && $staffActorUserId > 0) {
            $accessibleBranchIds = $this->branchContextService->accessibleBranchIds($staffActorUserId);
            if ($accessibleBranchIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('conversations.branch_id', $accessibleBranchIds);
            }
        }

        $conversation = $this->decorateSummaryQuery($query)->first();

        if (! $conversation instanceof Conversation) {
            throw (new ModelNotFoundException)->setModel(Conversation::class, [$conversationId]);
        }

        $this->featureFlags->assertEnabled(
            'staff.conversation_inbox',
            $conversation->branch_id !== null ? (int) $conversation->branch_id : null,
            field: 'conversation_inbox',
        );

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function detail(string $conversationId, array $options = [], ?int $staffActorUserId = null): array
    {
        $messageLimit = max(1, min((int) ($options['message_limit'] ?? 50), 200));
        $eventLimit = max(1, min((int) ($options['event_limit'] ?? 50), 200));
        $includeClosedAssignments = (bool) ($options['include_closed_assignments'] ?? true);

        $conversation = $this->findSummaryOrFail($conversationId, $staffActorUserId);
        $outboundReply = $this->outboundReplySupportService->describe($conversation, $staffActorUserId);

        $messages = ConversationMessage::query()
            ->with(['senderUser.role', 'files', 'entities'])
            ->where('conversation_id', $conversationId)
            ->latest('created_at')
            ->latest('message_id')
            ->limit($messageLimit)
            ->get()
            ->sortBy([
                ['created_at', 'asc'],
                ['message_id', 'asc'],
            ])
            ->values();

        $events = ConversationEvent::query()
            ->with(['byUser.role'])
            ->where('conversation_id', $conversationId)
            ->latest('created_at')
            ->latest('event_id')
            ->limit($eventLimit)
            ->get();

        $analyses = ConversationAnalysis::query()
            ->where('conversation_id', $conversationId)
            ->latest('created_at')
            ->latest('analysis_id')
            ->get();

        $assignmentHistoryQuery = AgentAssignment::query()
            ->with(['agent.role'])
            ->where('conversation_id', $conversationId)
            ->latest('assigned_at')
            ->latest('assignment_id');

        if (! $includeClosedAssignments) {
            $assignmentHistoryQuery->where('is_active', true);
        }

        return [
            'conversation' => $conversation,
            'messages' => $messages,
            'events' => $events,
            'analyses' => $analyses,
            'ai_assist' => $this->conversationAiAssistBuilder->buildForConversationDetail($conversation, $messages, $analyses),
            'assignment_history' => $assignmentHistoryQuery->get(),
            'capabilities' => [
                'can_assign' => true,
                'can_take_over' => true,
                'can_unassign' => true,
                'can_link' => true,
                'can_update_workflow_state' => true,
                'can_add_internal_note' => true,
                'can_send_outbound_reply' => (bool) ($outboundReply['supported'] ?? false),
                'outbound_reply' => [
                    'supported' => (bool) ($outboundReply['supported'] ?? false),
                    'channel' => $outboundReply['channel'] ?? null,
                    'delivery_mode' => $outboundReply['delivery_mode'] ?? null,
                    'recipient_masked' => $outboundReply['recipient_masked'] ?? null,
                    'reason_code' => $outboundReply['reason_code'] ?? null,
                    'reason' => $outboundReply['reason'] ?? null,
                    'quiet_until_utc' => $outboundReply['quiet_until_utc'] ?? null,
                ],
            ],
            'message_limit' => $messageLimit,
            'event_limit' => $eventLimit,
            'include_closed_assignments' => $includeClosedAssignments,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildFilteredQuery(array $filters, ?int $staffActorUserId, bool $applyInboxView = true): Builder
    {
        $query = Conversation::query()
            ->select('conversations.*')
            ->selectSub(
                ConversationMessage::query()
                    ->select('created_at')
                    ->whereColumn('conversation_id', 'conversations.conversation_id')
                    ->latest('created_at')
                    ->latest('message_id')
                    ->limit(1),
                'latest_message_at'
            );

        if (! empty($filters['status'])) {
            $query->where('conversations.status', (string) $filters['status']);
        }

        if (array_key_exists('accessible_branch_ids', $filters)) {
            $accessibleBranchIds = $this->accessibleBranchIdsFromFilters($filters);
            if ($accessibleBranchIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('conversations.branch_id', $accessibleBranchIds);
            }
        }

        if (! empty($filters['workflow_state'])) {
            $query->where('conversations.workflow_state', (string) $filters['workflow_state']);
        }

        if (! empty($filters['channel'])) {
            $query->where('conversations.channel', (string) $filters['channel']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('conversations.branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['reservation_id'])) {
            $query->where('conversations.linked_reservation_id', (int) $filters['reservation_id']);
        }

        if (! empty($filters['waiting_list_id'])) {
            $query->where('conversations.linked_waiting_list_id', (int) $filters['waiting_list_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('conversations.user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['assigned_agent_user_id'])) {
            $assignedAgentUserId = (int) $filters['assigned_agent_user_id'];
            $query->whereHas('activeAssignment', static function (Builder $assignmentQuery) use ($assignedAgentUserId): void {
                $assignmentQuery->where('agent_user_id', $assignedAgentUserId);
            });
        }

        $assignmentState = strtolower((string) ($filters['assignment_state'] ?? 'all'));
        if ($assignmentState === 'assigned') {
            $query->whereHas('activeAssignment');
        } elseif ($assignmentState === 'unassigned') {
            $query->whereDoesntHave('activeAssignment');
        } elseif ($assignmentState === 'mine' && $staffActorUserId !== null && $staffActorUserId > 0) {
            $query->whereHas('activeAssignment', static function (Builder $assignmentQuery) use ($staffActorUserId): void {
                $assignmentQuery->where('agent_user_id', $staffActorUserId);
            });
        }

        if (! empty($filters['created_from'])) {
            $query->where('conversations.created_at', '>=', Carbon::parse((string) $filters['created_from'])->utc());
        }

        if (! empty($filters['created_to'])) {
            $query->where('conversations.created_at', '<=', Carbon::parse((string) $filters['created_to'])->utc());
        }

        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery
                    ->where('conversations.conversation_id', 'like', '%'.$term.'%')
                    ->orWhere('conversations.session_id', 'like', '%'.$term.'%')
                    ->orWhere('conversations.customer_session_id', 'like', '%'.$term.'%')
                    ->orWhere('conversations.intent_detected', 'like', '%'.$term.'%')
                    ->orWhereHas('user', static function (Builder $userQuery) use ($term): void {
                        $userQuery
                            ->where('full_name', 'like', '%'.$term.'%')
                            ->orWhere('phone', 'like', '%'.$term.'%')
                            ->orWhere('email', 'like', '%'.$term.'%');
                    })
                    ->orWhereHas('messages', static function (Builder $messageQuery) use ($term): void {
                        $messageQuery->where('message_text', 'like', '%'.$term.'%');
                    })
                    ->orWhereHas('linkedReservation', static function (Builder $reservationQuery) use ($term): void {
                        $reservationQuery->where('reservation_code', 'like', '%'.$term.'%');
                    });
            });
        }

        return $applyInboxView
            ? $this->applyInboxViewFilter($query, (string) ($filters['inbox_view'] ?? 'all'))
            : $query;
    }

    private function decorateSummaryQuery(Builder $query): Builder
    {
        return $query
            ->with([
                'branch',
                'user.role',
                'linkedReservation.user',
                'linkedWaitingList.user',
                'activeAssignment.agent.role',
                'latestMessage.senderUser.role',
                'latestMessage.files',
                'latestMessage.entities',
                'latestAnalysis',
            ])
            ->withCount([
                'messages',
                'events',
                'analyses',
                'messages as internal_notes_count' => static function (Builder $messageQuery): void {
                    $messageQuery->where('is_internal_note', true);
                },
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(Builder $query, ?int $staffActorUserId): array
    {
        $statusCounts = (clone $query)
            ->select('conversations.status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('conversations.status')
            ->pluck('aggregate', 'conversations.status')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        $workflowCounts = (clone $query)
            ->select('conversations.workflow_state')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('conversations.workflow_state')
            ->pluck('aggregate', 'conversations.workflow_state')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        return [
            'total' => (clone $query)->count(),
            'assigned' => (clone $query)->whereHas('activeAssignment')->count(),
            'unassigned' => (clone $query)->whereDoesntHave('activeAssignment')->count(),
            'mine' => $staffActorUserId !== null && $staffActorUserId > 0
                ? (clone $query)->whereHas('activeAssignment', static function (Builder $assignmentQuery) use ($staffActorUserId): void {
                    $assignmentQuery->where('agent_user_id', $staffActorUserId);
                })->count()
                : 0,
            'status_counts' => $statusCounts,
            'workflow_state_counts' => $workflowCounts,
            'views' => [
                'unassigned' => (clone $query)
                    ->whereDoesntHave('activeAssignment')
                    ->whereNotIn('conversations.workflow_state', [
                        StaffConversationWorkflowState::Resolved->value,
                        StaffConversationWorkflowState::Closed->value,
                    ])
                    ->count(),
                'overdue' => $this->applyInboxViewFilter(clone $query, 'overdue')->count(),
                'waiting_on_customer' => (clone $query)
                    ->where('conversations.workflow_state', StaffConversationWorkflowState::PendingCustomer->value)
                    ->count(),
                'resolved_today' => $this->applyInboxViewFilter(clone $query, 'resolved_today')->count(),
            ],
            'sla_minutes' => [
                'overdue' => self::OVERDUE_AFTER_MINUTES,
            ],
        ];
    }

    private function applyOrdering(Builder $query, string $sortBy, string $sortDir): void
    {
        if ($sortBy === 'message_count') {
            $query->orderBy('messages_count', $sortDir);
        } elseif ($sortBy === 'created_at') {
            $query->orderBy('conversations.created_at', $sortDir);
        } else {
            $query->orderByRaw('COALESCE(latest_message_at, conversations.created_at) '.strtoupper($sortDir));
        }

        $query->orderBy('conversations.conversation_id', $sortDir);
    }

    private function resolveSortBy(string $sortBy): string
    {
        return match ($sortBy) {
            'created_at', 'message_count' => $sortBy,
            default => 'latest_activity',
        };
    }

    private function applyInboxViewFilter(Builder $query, string $inboxView): Builder
    {
        $normalizedView = strtolower(trim($inboxView));

        return match ($normalizedView) {
            'unassigned' => $query
                ->whereDoesntHave('activeAssignment')
                ->whereNotIn('conversations.workflow_state', [
                    StaffConversationWorkflowState::Resolved->value,
                    StaffConversationWorkflowState::Closed->value,
                ]),
            'waiting_on_customer' => $query
                ->where('conversations.workflow_state', StaffConversationWorkflowState::PendingCustomer->value),
            'resolved_today' => $query
                ->where('conversations.workflow_state', StaffConversationWorkflowState::Resolved->value)
                ->whereBetween('conversations.resolved_at', [
                    now('UTC')->startOfDay(),
                    now('UTC')->endOfDay(),
                ]),
            'overdue' => $query
                ->whereNotIn('conversations.workflow_state', [
                    StaffConversationWorkflowState::Resolved->value,
                    StaffConversationWorkflowState::Closed->value,
                ])
                ->whereRaw(
                    'COALESCE((
                        SELECT cm.created_at
                        FROM conversation_messages cm
                        WHERE cm.conversation_id = conversations.conversation_id
                        ORDER BY cm.created_at DESC, cm.message_id DESC
                        LIMIT 1
                    ), conversations.workflow_state_changed_at, conversations.created_at) <= ?',
                    [now('UTC')->subMinutes(self::OVERDUE_AFTER_MINUTES)->toDateTimeString()]
                ),
            default => $query,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    private function accessibleBranchIdsFromFilters(array $filters): array
    {
        $branchIds = [];

        foreach ((array) ($filters['accessible_branch_ids'] ?? []) as $branchId) {
            $normalizedBranchId = (int) $branchId;
            if ($normalizedBranchId > 0) {
                $branchIds[] = $normalizedBranchId;
            }
        }

        $branchIds = array_values(array_unique($branchIds));
        sort($branchIds);

        return $branchIds;
    }
}
