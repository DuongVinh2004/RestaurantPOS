<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class StaffConversationSummaryResource extends JsonResource
{
    private const OVERDUE_AFTER_MINUTES = 15;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $staffActorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);
        $workflowState = $this->workflowState();
        $workflowAllowedActions = $this->workflowAllowedActions();

        $branch = null;
        if ($this->relationLoaded('branch') && $this->branch !== null) {
            $branch = [
                'branch_id' => (int) $this->branch->branch_id,
                'branch_code' => $this->branch->branch_code,
                'branch_name' => $this->branch->branch_name,
            ];
        }

        $user = null;
        if ($this->relationLoaded('user') && $this->user !== null) {
            $user = [
                'user_id' => (int) $this->user->user_id,
                'full_name' => $this->user->full_name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'role_name' => $this->user->relationLoaded('role') && $this->user->role !== null
                    ? (string) $this->user->role->role_name
                    : null,
            ];
        }

        $linkedReservation = null;
        if ($this->relationLoaded('linkedReservation') && $this->linkedReservation !== null) {
            $linkedReservation = [
                'reservation_id' => (int) $this->linkedReservation->reservation_id,
                'reservation_code' => (string) $this->linkedReservation->reservation_code,
                'branch_id' => (int) $this->linkedReservation->branch_id,
                'status' => $this->linkedReservation->status?->value ?? (string) $this->linkedReservation->status,
                'start_time' => $this->iso($this->linkedReservation->start_time),
                'end_time' => $this->iso($this->linkedReservation->end_time),
                'user' => $this->linkedReservation->relationLoaded('user') && $this->linkedReservation->user !== null
                    ? [
                        'user_id' => (int) $this->linkedReservation->user->user_id,
                        'full_name' => $this->linkedReservation->user->full_name,
                        'phone' => $this->linkedReservation->user->phone,
                        'email' => $this->linkedReservation->user->email,
                    ]
                    : null,
            ];
        }

        $linkedWaitingList = null;
        if ($this->relationLoaded('linkedWaitingList') && $this->linkedWaitingList !== null) {
            $linkedWaitingList = [
                'waiting_id' => (int) $this->linkedWaitingList->waiting_id,
                'branch_id' => (int) $this->linkedWaitingList->branch_id,
                'status' => $this->linkedWaitingList->status?->value ?? (string) $this->linkedWaitingList->status,
                'guest_count' => (int) $this->linkedWaitingList->guest_count,
                'requested_at' => $this->iso($this->linkedWaitingList->requested_at),
                'user' => $this->linkedWaitingList->relationLoaded('user') && $this->linkedWaitingList->user !== null
                    ? [
                        'user_id' => (int) $this->linkedWaitingList->user->user_id,
                        'full_name' => $this->linkedWaitingList->user->full_name,
                        'phone' => $this->linkedWaitingList->user->phone,
                        'email' => $this->linkedWaitingList->user->email,
                    ]
                    : null,
            ];
        }

        $activeAssignment = null;
        if ($this->relationLoaded('activeAssignment') && $this->activeAssignment !== null) {
            $activeAssignment = (new StaffConversationAssignmentResource($this->activeAssignment))->resolve($request);
        }

        $latestMessage = null;
        if ($this->relationLoaded('latestMessage') && $this->latestMessage !== null) {
            $latestMessage = (new StaffConversationMessageResource($this->latestMessage))->resolve($request);
        }

        $latestAnalysis = null;
        if ($this->relationLoaded('latestAnalysis') && $this->latestAnalysis !== null) {
            $latestAnalysis = (new StaffConversationAnalysisResource($this->latestAnalysis))->resolve($request);
        }

        $latestActivity = $this->latest_message_at ?? $this->created_at;
        $latestActivityIso = $this->iso($latestActivity);
        $isOverdue = false;
        if ($latestActivity !== null && ! $workflowState->isQueueTerminal()) {
            $latestActivityUtc = $latestActivity instanceof \DateTimeInterface
                ? Carbon::instance($latestActivity)->utc()
                : Carbon::parse((string) $latestActivity)->utc();
            $isOverdue = $latestActivityUtc->lessThanOrEqualTo(now('UTC')->subMinutes(self::OVERDUE_AFTER_MINUTES));
        }

        return [
            'conversation_id' => (string) $this->conversation_id,
            'branch_id' => (int) $this->branch_id,
            'branch' => $branch,
            'status' => $this->status?->value ?? (string) $this->status,
            'workflow' => [
                'state' => $workflowState->value,
                'state_reason' => $this->workflowStateReasonValue(),
                'state_changed_at' => $this->iso($this->workflowStateChangedAtValue()),
                'first_triaged_at' => $this->iso($this->first_triaged_at),
                'resolved_at' => $this->iso($this->resolved_at),
                'closed_at' => $this->iso($this->closed_at),
                'is_terminal' => $workflowState->isQueueTerminal(),
                'allowed_actions' => $workflowAllowedActions,
            ],
            'channel' => $this->channel?->value ?? (string) $this->channel,
            'intent_detected' => $this->intent_detected,
            'customer_session_id' => $this->customer_session_id,
            'session_id' => $this->session_id,
            'created_at' => $this->iso($this->created_at),
            'closed_at' => $this->iso($this->closed_at),
            'latest_activity_at' => $latestActivityIso,
            'user' => $user,
            'linked_reservation' => $linkedReservation,
            'linked_waiting_list' => $linkedWaitingList,
            'active_assignment' => $activeAssignment,
            'latest_message' => $latestMessage,
            'latest_analysis' => $latestAnalysis,
            'counts' => [
                'messages' => (int) ($this->messages_count ?? 0),
                'internal_notes' => (int) ($this->internal_notes_count ?? 0),
                'events' => (int) ($this->events_count ?? 0),
                'analyses' => (int) ($this->analyses_count ?? 0),
            ],
            'assignment_state' => [
                'is_assigned' => $activeAssignment !== null,
                'is_unassigned' => $activeAssignment === null,
                'is_mine' => $activeAssignment !== null
                    && $staffActorUserId > 0
                    && (int) ($activeAssignment['agent_user_id'] ?? 0) === $staffActorUserId,
            ],
            'operational' => [
                'is_overdue' => $isOverdue,
                'overdue_after_minutes' => self::OVERDUE_AFTER_MINUTES,
                'queue_bucket' => match ($workflowState->value) {
                    'PendingCustomer' => 'waiting_on_customer',
                    'Resolved' => 'resolved',
                    'Closed' => 'closed',
                    default => $activeAssignment === null ? 'unassigned' : 'assigned',
                },
            ],
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }
}
