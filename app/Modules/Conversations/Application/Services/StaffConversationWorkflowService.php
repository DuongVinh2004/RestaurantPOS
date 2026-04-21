<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Application\Services;

use App\Enums\ConversationStatus;
use App\Enums\StaffConversationWorkflowState;
use App\Modules\Conversations\Domain\Models\AgentAssignment;
use App\Modules\Conversations\Domain\Models\Conversation;
use App\Modules\Conversations\Domain\Models\ConversationEvent;
use App\Modules\Conversations\Domain\Models\ConversationMessage;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Waitlist\Domain\Models\WaitlistEntry;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use App\Support\AuditEvent;
use App\Modules\IdentityAccess\Application\Queries\StaffCapabilityResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StaffConversationWorkflowService
{
    public function __construct(
        private readonly StaffConversationInboxService $inboxService,
        private readonly StaffCapabilityResolver $staffCapabilityResolver,
        private readonly FeatureFlagService $featureFlags,
        private readonly StaffConversationOutboundReplySupportService $outboundReplySupportService,
        private readonly NotificationOutboxService $notificationOutboxService,
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assign(string $conversationId, int $agentUserId, int $actingStaffUserId, ?string $notes = null): array
    {
        $agent = $this->resolveAssignableAgent($agentUserId);

        $mutation = DB::transaction(function () use ($conversationId, $agent, $actingStaffUserId, $notes): array {
            $conversation = $this->lockConversationOrFail($conversationId);
            $this->assertConversationInboxEnabled($conversation);
            $this->assertConversationOperationalBranchAccessible($conversation, $actingStaffUserId);
            $activeAssignment = $this->lockActiveAssignment($conversationId);
            $currentState = $this->resolveWorkflowState($conversation, $activeAssignment);
            $this->assertAssignmentAllowed($currentState);

            if ($activeAssignment instanceof AgentAssignment) {
                if ((int) $activeAssignment->agent_user_id !== (int) $agent->user_id) {
                    throw new ConflictHttpException('Conversation is already assigned to another staff actor. Use take-over to replace the current assignee.');
                }

                if ($notes !== null && $notes !== '') {
                    $activeAssignment->notes = $notes;
                    $activeAssignment->save();
                }

                $this->syncWorkflowState(
                    $conversation,
                    StaffConversationWorkflowState::Assigned,
                    'assigned',
                );

                return [
                    'action' => 'conversation.assigned',
                    'assignment_id' => (int) $activeAssignment->assignment_id,
                    'event_id' => null,
                    'message_id' => null,
                ];
            }

            $assignment = AgentAssignment::query()->create([
                'conversation_id' => $conversation->conversation_id,
                'agent_user_id' => (int) $agent->user_id,
                'assigned_at' => now('UTC'),
                'released_at' => null,
                'is_active' => true,
                'notes' => $notes,
            ]);

            $this->syncWorkflowState(
                $conversation,
                StaffConversationWorkflowState::Assigned,
                'assigned',
            );

            $event = $this->recordEvent(
                $conversation->conversation_id,
                'assignment.changed',
                $actingStaffUserId,
                [
                    'mode' => 'assign',
                    'agent_user_id' => (int) $agent->user_id,
                    'assignment_id' => (int) $assignment->assignment_id,
                    'workflow_state' => StaffConversationWorkflowState::Assigned->value,
                    'state_reason' => 'assigned',
                    'notes' => $notes,
                ]
            );

            $this->auditConversationChange(
                'staff.conversation.assigned',
                $conversation,
                $actingStaffUserId,
                after: [
                    'assignment_id' => (int) $assignment->assignment_id,
                    'agent_user_id' => (int) $agent->user_id,
                    'workflow_state' => StaffConversationWorkflowState::Assigned->value,
                ],
                summary: [
                    'mode' => 'assign',
                    'notes_present' => $notes !== null && $notes !== '',
                ],
                subjects: [
                    [
                        'type' => 'agent_assignment',
                        'id' => (string) $assignment->assignment_id,
                        'role' => 'assignment',
                    ],
                    [
                        'type' => 'user',
                        'id' => (string) $agent->user_id,
                        'role' => 'assignee',
                    ],
                ],
            );

            return [
                'action' => 'conversation.assigned',
                'assignment_id' => (int) $assignment->assignment_id,
                'event_id' => (int) $event->event_id,
                'message_id' => null,
            ];
        });

        return $this->hydrateMutationPayload($conversationId, $mutation);
    }

    /**
     * @return array<string, mixed>
     */
    public function takeOver(string $conversationId, int $actingStaffUserId, ?string $notes = null): array
    {
        $agent = $this->resolveAssignableAgent($actingStaffUserId);

        $mutation = DB::transaction(function () use ($conversationId, $actingStaffUserId, $agent, $notes): array {
            $conversation = $this->lockConversationOrFail($conversationId);
            $this->assertConversationInboxEnabled($conversation);
            $this->assertConversationOperationalBranchAccessible($conversation, $actingStaffUserId);
            $activeAssignment = $this->lockActiveAssignment($conversationId);
            $currentState = $this->resolveWorkflowState($conversation, $activeAssignment);
            $this->assertAssignmentAllowed($currentState);

            if ($activeAssignment instanceof AgentAssignment && (int) $activeAssignment->agent_user_id === (int) $actingStaffUserId) {
                if ($notes !== null && $notes !== '') {
                    $activeAssignment->notes = $notes;
                    $activeAssignment->save();
                }

                $this->syncWorkflowState(
                    $conversation,
                    StaffConversationWorkflowState::Assigned,
                    'assigned',
                );

                return [
                    'action' => 'conversation.taken_over',
                    'assignment_id' => (int) $activeAssignment->assignment_id,
                    'event_id' => null,
                    'message_id' => null,
                ];
            }

            $previousAssignmentId = $activeAssignment?->assignment_id;
            $previousAgentUserId = $activeAssignment?->agent_user_id;

            if ($activeAssignment instanceof AgentAssignment) {
                $activeAssignment->forceFill([
                    'is_active' => false,
                    'released_at' => now('UTC'),
                ])->save();
            }

            $assignment = AgentAssignment::query()->create([
                'conversation_id' => $conversation->conversation_id,
                'agent_user_id' => (int) $agent->user_id,
                'assigned_at' => now('UTC'),
                'released_at' => null,
                'is_active' => true,
                'notes' => $notes,
            ]);

            $this->syncWorkflowState(
                $conversation,
                StaffConversationWorkflowState::Assigned,
                'assigned',
            );

            $event = $this->recordEvent(
                $conversation->conversation_id,
                'assignment.changed',
                $actingStaffUserId,
                [
                    'mode' => 'take_over',
                    'previous_assignment_id' => $previousAssignmentId !== null ? (int) $previousAssignmentId : null,
                    'previous_agent_user_id' => $previousAgentUserId !== null ? (int) $previousAgentUserId : null,
                    'agent_user_id' => (int) $agent->user_id,
                    'assignment_id' => (int) $assignment->assignment_id,
                    'workflow_state' => StaffConversationWorkflowState::Assigned->value,
                    'state_reason' => 'assigned',
                    'notes' => $notes,
                ]
            );

            $this->auditConversationChange(
                'staff.conversation.reassigned',
                $conversation,
                $actingStaffUserId,
                before: [
                    'previous_assignment_id' => $previousAssignmentId !== null ? (int) $previousAssignmentId : null,
                    'previous_agent_user_id' => $previousAgentUserId !== null ? (int) $previousAgentUserId : null,
                ],
                after: [
                    'assignment_id' => (int) $assignment->assignment_id,
                    'agent_user_id' => (int) $agent->user_id,
                    'workflow_state' => StaffConversationWorkflowState::Assigned->value,
                ],
                summary: [
                    'mode' => 'take_over',
                    'notes_present' => $notes !== null && $notes !== '',
                ],
                subjects: array_values(array_filter([
                    $previousAssignmentId !== null ? [
                        'type' => 'agent_assignment',
                        'id' => (string) $previousAssignmentId,
                        'role' => 'previous_assignment',
                    ] : null,
                    [
                        'type' => 'agent_assignment',
                        'id' => (string) $assignment->assignment_id,
                        'role' => 'assignment',
                    ],
                    $previousAgentUserId !== null ? [
                        'type' => 'user',
                        'id' => (string) $previousAgentUserId,
                        'role' => 'previous_assignee',
                    ] : null,
                    [
                        'type' => 'user',
                        'id' => (string) $agent->user_id,
                        'role' => 'assignee',
                    ],
                ])),
            );

            return [
                'action' => 'conversation.taken_over',
                'assignment_id' => (int) $assignment->assignment_id,
                'event_id' => (int) $event->event_id,
                'message_id' => null,
            ];
        });

        return $this->hydrateMutationPayload($conversationId, $mutation);
    }

    /**
     * @return array<string, mixed>
     */
    public function unassign(string $conversationId, int $actingStaffUserId, ?string $notes = null): array
    {
        $mutation = DB::transaction(function () use ($conversationId, $actingStaffUserId, $notes): array {
            $conversation = $this->lockConversationOrFail($conversationId);
            $this->assertConversationInboxEnabled($conversation);
            $this->assertConversationOperationalBranchAccessible($conversation, $actingStaffUserId);
            $activeAssignment = $this->lockActiveAssignment($conversationId);
            $currentState = $this->resolveWorkflowState($conversation, $activeAssignment);

            if (! $activeAssignment instanceof AgentAssignment) {
                if ($currentState === StaffConversationWorkflowState::Assigned) {
                    $this->syncWorkflowState(
                        $conversation,
                        StaffConversationWorkflowState::Triaged,
                        'assignment_cleared',
                    );
                }

                return [
                    'action' => 'conversation.unassigned',
                    'assignment_id' => null,
                    'event_id' => null,
                    'message_id' => null,
                ];
            }

            $activeAssignment->forceFill([
                'is_active' => false,
                'released_at' => now('UTC'),
                'notes' => $notes !== null && $notes !== '' ? $notes : $activeAssignment->notes,
            ])->save();

            $this->syncWorkflowState(
                $conversation,
                StaffConversationWorkflowState::Triaged,
                'assignment_cleared',
            );

            $event = $this->recordEvent(
                $conversation->conversation_id,
                'assignment.cleared',
                $actingStaffUserId,
                [
                    'assignment_id' => (int) $activeAssignment->assignment_id,
                    'agent_user_id' => (int) $activeAssignment->agent_user_id,
                    'workflow_state' => StaffConversationWorkflowState::Triaged->value,
                    'state_reason' => 'assignment_cleared',
                    'notes' => $notes,
                ]
            );

            $this->auditConversationChange(
                'staff.conversation.unassigned',
                $conversation,
                $actingStaffUserId,
                after: [
                    'released_assignment_id' => (int) $activeAssignment->assignment_id,
                    'workflow_state' => StaffConversationWorkflowState::Triaged->value,
                ],
                summary: [
                    'notes_present' => $notes !== null && $notes !== '',
                ],
                subjects: [
                    [
                        'type' => 'agent_assignment',
                        'id' => (string) $activeAssignment->assignment_id,
                        'role' => 'released_assignment',
                    ],
                    [
                        'type' => 'user',
                        'id' => (string) $activeAssignment->agent_user_id,
                        'role' => 'released_assignee',
                    ],
                ],
            );

            return [
                'action' => 'conversation.unassigned',
                'assignment_id' => null,
                'event_id' => (int) $event->event_id,
                'message_id' => null,
            ];
        });

        return $this->hydrateMutationPayload($conversationId, $mutation);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateWorkflowState(string $conversationId, array $payload, int $actingStaffUserId): array
    {
        $targetState = StaffConversationWorkflowState::tryFromInput((string) ($payload['workflow_state'] ?? ''));
        if (! $targetState instanceof StaffConversationWorkflowState) {
            throw ValidationException::withMessages([
                'workflow_state' => ['Unsupported conversation workflow state.'],
            ]);
        }

        $expectedState = null;
        if (! empty($payload['expected_workflow_state'])) {
            $expectedState = StaffConversationWorkflowState::tryFromInput((string) $payload['expected_workflow_state']);
        }

        $reasonNote = isset($payload['reason']) ? trim((string) $payload['reason']) : null;

        $mutation = DB::transaction(function () use (
            $conversationId,
            $actingStaffUserId,
            $targetState,
            $expectedState,
            $reasonNote,
        ): array {
            $conversation = $this->lockConversationOrFail($conversationId);
            $this->assertConversationInboxEnabled($conversation);
            $this->assertConversationOperationalBranchAccessible($conversation, $actingStaffUserId);
            $activeAssignment = $this->lockActiveAssignment($conversationId);
            $currentState = $this->resolveWorkflowState($conversation, $activeAssignment);

            if ($expectedState instanceof StaffConversationWorkflowState && $currentState !== $expectedState) {
                throw new ConflictHttpException(sprintf(
                    'Conversation workflow state changed from %s to %s before this request could be applied.',
                    $expectedState->value,
                    $currentState->value,
                ));
            }

            if ($targetState === StaffConversationWorkflowState::Assigned) {
                throw ValidationException::withMessages([
                    'workflow_state' => ['Use assign or take-over to move a conversation into the assigned workflow state.'],
                ]);
            }

            $this->assertWorkflowTransitionAllowed($currentState, $targetState);

            if ($currentState === $targetState) {
                return [
                    'action' => $this->workflowActionName($currentState, $targetState),
                    'assignment_id' => $activeAssignment instanceof AgentAssignment ? (int) $activeAssignment->assignment_id : null,
                    'event_id' => null,
                    'message_id' => null,
                ];
            }

            $releasedAssignment = null;
            if ($targetState->isQueueTerminal() && $activeAssignment instanceof AgentAssignment) {
                $activeAssignment->forceFill([
                    'is_active' => false,
                    'released_at' => now('UTC'),
                ])->save();
                $releasedAssignment = $activeAssignment;
                $activeAssignment = null;
            }

            $stateReason = $this->workflowStateReasonForTransition($currentState, $targetState);
            $this->syncWorkflowState($conversation, $targetState, $stateReason);

            $event = $this->recordEvent(
                $conversation->conversation_id,
                'workflow.state_changed',
                $actingStaffUserId,
                [
                    'previous_state' => $currentState->value,
                    'current_state' => $targetState->value,
                    'state_reason' => $stateReason,
                    'reason_note' => $reasonNote,
                    'released_assignment_id' => $releasedAssignment?->assignment_id !== null
                        ? (int) $releasedAssignment->assignment_id
                        : null,
                ]
            );

            $this->auditConversationChange(
                $this->workflowAuditEventName($currentState, $targetState),
                $conversation,
                $actingStaffUserId,
                before: [
                    'workflow_state' => $currentState->value,
                ],
                after: [
                    'workflow_state' => $targetState->value,
                    'state_reason' => $stateReason,
                    'released_assignment_id' => $releasedAssignment?->assignment_id !== null
                        ? (int) $releasedAssignment->assignment_id
                        : null,
                ],
                summary: [
                    'reason_note_present' => $reasonNote !== null && $reasonNote !== '',
                ],
                subjects: array_values(array_filter([
                    $releasedAssignment?->assignment_id !== null ? [
                        'type' => 'agent_assignment',
                        'id' => (string) $releasedAssignment->assignment_id,
                        'role' => 'released_assignment',
                    ] : null,
                ])),
            );

            return [
                'action' => $this->workflowActionName($currentState, $targetState),
                'assignment_id' => $activeAssignment instanceof AgentAssignment ? (int) $activeAssignment->assignment_id : null,
                'event_id' => (int) $event->event_id,
                'message_id' => null,
            ];
        });

        return $this->hydrateMutationPayload($conversationId, $mutation);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function link(string $conversationId, array $payload, int $actingStaffUserId): array
    {
        $mutation = DB::transaction(function () use ($conversationId, $payload, $actingStaffUserId): array {
            $conversation = $this->lockConversationOrFail($conversationId);
            $this->assertConversationInboxEnabled($conversation);
            $this->assertConversationOperationalBranchAccessible($conversation, $actingStaffUserId);
            $currentState = $this->resolveWorkflowState($conversation, $this->lockActiveAssignment($conversationId));
            $this->assertClosedStateDisallowsMutation($currentState, 'Conversation links cannot be changed after the conversation is closed.');
            $linkState = $this->resolveLinkState($conversation, $payload);
            $this->assertConversationInboxEnabledForBranch((int) $linkState['branch_id']);
            $this->assertOperationalBranchAccessible((int) $linkState['branch_id'], $actingStaffUserId);

            $previousState = $this->conversationLinkSnapshot($conversation);

            $conversation->forceFill($linkState)->save();

            $event = $this->recordEvent(
                $conversation->conversation_id,
                'conversation.linked',
                $actingStaffUserId,
                [
                    'previous' => $previousState,
                    'current' => $linkState,
                    'notes' => $payload['notes'] ?? null,
                ]
            );

            $this->auditConversationChange(
                'staff.conversation.link_updated',
                $conversation,
                $actingStaffUserId,
                before: $previousState,
                after: $linkState,
                summary: [
                    'notes_present' => ! empty($payload['notes']),
                ],
                subjects: array_values(array_filter([
                    isset($linkState['linked_reservation_id']) && $linkState['linked_reservation_id'] !== null ? [
                        'type' => 'reservation',
                        'id' => (string) $linkState['linked_reservation_id'],
                        'role' => 'reservation',
                    ] : null,
                    isset($linkState['linked_waiting_list_id']) && $linkState['linked_waiting_list_id'] !== null ? [
                        'type' => 'waiting_list',
                        'id' => (string) $linkState['linked_waiting_list_id'],
                        'role' => 'waiting_list',
                    ] : null,
                    isset($linkState['user_id']) && $linkState['user_id'] !== null ? [
                        'type' => 'user',
                        'id' => (string) $linkState['user_id'],
                        'role' => 'customer',
                    ] : null,
                ])),
            );

            return [
                'action' => 'conversation.linked',
                'assignment_id' => null,
                'event_id' => (int) $event->event_id,
                'message_id' => null,
            ];
        });

        return $this->hydrateMutationPayload($conversationId, $mutation);
    }

    /**
     * @return array<string, mixed>
     */
    public function unlinkReservation(string $conversationId, int $actingStaffUserId): array
    {
        $mutation = DB::transaction(function () use ($conversationId, $actingStaffUserId): array {
            $conversation = $this->lockConversationOrFail($conversationId);
            $this->assertConversationInboxEnabled($conversation);
            $this->assertConversationOperationalBranchAccessible($conversation, $actingStaffUserId);
            $currentState = $this->resolveWorkflowState($conversation, $this->lockActiveAssignment($conversationId));
            $this->assertClosedStateDisallowsMutation($currentState, 'Conversation links cannot be changed after the conversation is closed.');
            $previousState = $this->conversationLinkSnapshot($conversation);

            $linkState = $this->resolveLinkState($conversation, [
                'waiting_list_id' => $conversation->linked_waiting_list_id,
                'customer_user_id' => $conversation->user_id,
            ]);
            $linkState['linked_reservation_id'] = null;
            $this->assertConversationInboxEnabledForBranch((int) $linkState['branch_id']);
            $this->assertOperationalBranchAccessible((int) $linkState['branch_id'], $actingStaffUserId);

            $conversation->forceFill($linkState)->save();

            $event = $this->recordEvent(
                $conversation->conversation_id,
                'conversation.unlinked',
                $actingStaffUserId,
                [
                    'target' => 'reservation',
                    'previous' => $previousState,
                    'current' => $linkState,
                ]
            );

            $this->auditConversationChange(
                'staff.conversation.reservation_unlinked',
                $conversation,
                $actingStaffUserId,
                before: $previousState,
                after: $linkState,
                subjects: array_values(array_filter([
                    $previousState['linked_reservation_id'] !== null ? [
                        'type' => 'reservation',
                        'id' => (string) $previousState['linked_reservation_id'],
                        'role' => 'removed_reservation',
                    ] : null,
                ])),
            );

            return [
                'action' => 'conversation.reservation_unlinked',
                'assignment_id' => null,
                'event_id' => (int) $event->event_id,
                'message_id' => null,
            ];
        });

        return $this->hydrateMutationPayload($conversationId, $mutation);
    }

    /**
     * @return array<string, mixed>
     */
    public function unlinkWaitingList(string $conversationId, int $actingStaffUserId): array
    {
        $mutation = DB::transaction(function () use ($conversationId, $actingStaffUserId): array {
            $conversation = $this->lockConversationOrFail($conversationId);
            $this->assertConversationInboxEnabled($conversation);
            $this->assertConversationOperationalBranchAccessible($conversation, $actingStaffUserId);
            $currentState = $this->resolveWorkflowState($conversation, $this->lockActiveAssignment($conversationId));
            $this->assertClosedStateDisallowsMutation($currentState, 'Conversation links cannot be changed after the conversation is closed.');
            $previousState = $this->conversationLinkSnapshot($conversation);

            $linkState = $this->resolveLinkState($conversation, [
                'reservation_id' => $conversation->linked_reservation_id,
                'customer_user_id' => $conversation->user_id,
            ]);
            $linkState['linked_waiting_list_id'] = null;
            $this->assertConversationInboxEnabledForBranch((int) $linkState['branch_id']);
            $this->assertOperationalBranchAccessible((int) $linkState['branch_id'], $actingStaffUserId);

            $conversation->forceFill($linkState)->save();

            $event = $this->recordEvent(
                $conversation->conversation_id,
                'conversation.unlinked',
                $actingStaffUserId,
                [
                    'target' => 'waiting_list',
                    'previous' => $previousState,
                    'current' => $linkState,
                ]
            );

            $this->auditConversationChange(
                'staff.conversation.waiting_list_unlinked',
                $conversation,
                $actingStaffUserId,
                before: $previousState,
                after: $linkState,
                subjects: array_values(array_filter([
                    $previousState['linked_waiting_list_id'] !== null ? [
                        'type' => 'waiting_list',
                        'id' => (string) $previousState['linked_waiting_list_id'],
                        'role' => 'removed_waiting_list',
                    ] : null,
                ])),
            );

            return [
                'action' => 'conversation.waiting_list_unlinked',
                'assignment_id' => null,
                'event_id' => (int) $event->event_id,
                'message_id' => null,
            ];
        });

        return $this->hydrateMutationPayload($conversationId, $mutation);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function addInternalNote(string $conversationId, array $payload, int $actingStaffUserId): array
    {
        $mutation = DB::transaction(function () use ($conversationId, $payload, $actingStaffUserId): array {
            $conversation = $this->lockConversationOrFail($conversationId);
            $this->assertConversationInboxEnabled($conversation);
            $this->assertConversationOperationalBranchAccessible($conversation, $actingStaffUserId);
            $currentState = $this->resolveWorkflowState($conversation, $this->lockActiveAssignment($conversationId));
            $this->assertClosedStateDisallowsMutation($currentState, 'Internal notes cannot be added after the conversation is closed.');

            $message = ConversationMessage::query()->create([
                'conversation_id' => $conversation->conversation_id,
                'sender' => 'agent',
                'sender_id' => $actingStaffUserId,
                'message_text' => (string) $payload['message_text'],
                'message_type' => 'text',
                'is_internal_note' => true,
                'attachment_url' => null,
                'is_processed' => true,
                'processing_status' => 'reviewed',
                'confidence' => null,
                'related_reservation_id' => $payload['related_reservation_id'] ?? $conversation->linked_reservation_id,
                'related_order_id' => $payload['related_order_id'] ?? null,
            ]);

            $event = $this->recordEvent(
                $conversation->conversation_id,
                'internal_note.added',
                $actingStaffUserId,
                [
                    'message_id' => (int) $message->message_id,
                    'related_reservation_id' => $message->related_reservation_id !== null ? (int) $message->related_reservation_id : null,
                    'related_order_id' => $message->related_order_id !== null ? (int) $message->related_order_id : null,
                ]
            );

            return [
                'action' => 'conversation.internal_note_added',
                'assignment_id' => null,
                'event_id' => (int) $event->event_id,
                'message_id' => (int) $message->message_id,
            ];
        });

        return $this->hydrateMutationPayload($conversationId, $mutation);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendOutboundReply(string $conversationId, array $payload, int $actingStaffUserId): array
    {
        $mutation = DB::transaction(function () use ($conversationId, $payload, $actingStaffUserId): array {
            $conversation = $this->lockConversationOrFail($conversationId);
            $this->assertConversationInboxEnabled($conversation);
            $this->assertConversationOperationalBranchAccessible($conversation, $actingStaffUserId);
            $activeAssignment = $this->lockActiveAssignment($conversationId);
            $support = $this->outboundReplySupportService->describe($conversation, $actingStaffUserId, $activeAssignment);
            $this->assertOutboundReplySupported($support);

            $message = ConversationMessage::query()->create([
                'conversation_id' => $conversation->conversation_id,
                'sender' => 'agent',
                'sender_id' => $actingStaffUserId,
                'message_text' => (string) $payload['message_text'],
                'message_type' => 'text',
                'is_internal_note' => false,
                'attachment_url' => null,
                'is_processed' => true,
                'processing_status' => 'processed',
                'confidence' => null,
                'related_reservation_id' => $payload['related_reservation_id'] ?? $conversation->linked_reservation_id,
                'related_order_id' => $payload['related_order_id'] ?? null,
            ]);

            $conversation->loadMissing([
                'branch',
                'user',
                'linkedReservation.user',
                'linkedWaitingList.user',
            ]);

            $outbox = $this->notificationOutboxService->enqueueMessage([
                'channel' => (string) ($support['channel'] ?? 'Email'),
                'recipient' => (string) ($support['recipient'] ?? ''),
                'recipient_user_id' => $support['recipient_user_id'] ?? null,
                'template_key' => 'conversation.outbound_reply',
                'event_key' => 'conversation.outbound_reply',
                'idempotency_key' => sprintf(
                    'conversation:%s:outbound-reply:%d',
                    $conversation->conversation_id,
                    (int) $message->message_id,
                ),
                'dedupe_key' => null,
                'payload' => $this->buildOutboundReplyNotificationPayload($conversation, $message),
                'related_reservation_id' => $message->related_reservation_id !== null ? (int) $message->related_reservation_id : null,
                'preferred_timezone' => $conversation->branch !== null ? ($conversation->branch->timezone ?? null) : null,
                'missing_recipient_audit_context' => [
                    'conversation_id' => $conversation->conversation_id,
                    'message_id' => (int) $message->message_id,
                ],
            ]);

            if ($outbox === null) {
                throw ValidationException::withMessages([
                    'conversation_id' => ['Outbound reply could not be queued because the supported delivery channel is no longer available.'],
                ]);
            }

            $event = $this->recordEvent(
                $conversation->conversation_id,
                'outbound_reply.queued',
                $actingStaffUserId,
                [
                    'message_id' => (int) $message->message_id,
                    'outbox_id' => (int) $outbox->outbox_id,
                    'delivery_channel' => (string) ($support['channel'] ?? 'Email'),
                    'delivery_mode' => (string) ($support['delivery_mode'] ?? ''),
                    'recipient_masked' => $support['recipient_masked'] ?? null,
                    'related_reservation_id' => $message->related_reservation_id !== null ? (int) $message->related_reservation_id : null,
                    'related_order_id' => $message->related_order_id !== null ? (int) $message->related_order_id : null,
                    'quiet_until_utc' => $support['quiet_until_utc'] ?? null,
                ]
            );

            AuditEvent::info('staff.conversation.outbound_reply_queued', [
                '_audit' => [
                    'action' => 'conversation.outbound_reply_queued',
                    'entity_type' => 'conversation',
                    'entity_id' => (string) $conversation->conversation_id,
                    'subjects' => array_values(array_filter([
                        [
                            'type' => 'conversation_message',
                            'id' => (string) $message->message_id,
                            'role' => 'message',
                        ],
                        [
                            'type' => 'notification_outbox',
                            'id' => (string) $outbox->outbox_id,
                            'role' => 'delivery',
                        ],
                        $message->related_reservation_id !== null ? [
                            'type' => 'reservation',
                            'id' => (string) $message->related_reservation_id,
                            'role' => 'reservation',
                        ] : null,
                        ($support['recipient_user_id'] ?? null) !== null ? [
                            'type' => 'user',
                            'id' => (string) $support['recipient_user_id'],
                            'role' => 'customer',
                        ] : null,
                    ])),
                    'after' => [
                        'message_id' => (int) $message->message_id,
                        'outbox_id' => (int) $outbox->outbox_id,
                        'delivery_channel' => (string) ($support['channel'] ?? 'Email'),
                        'delivery_mode' => (string) ($support['delivery_mode'] ?? ''),
                        'related_reservation_id' => $message->related_reservation_id !== null ? (int) $message->related_reservation_id : null,
                        'related_order_id' => $message->related_order_id !== null ? (int) $message->related_order_id : null,
                    ],
                    'summary' => [
                        'delivery_channel' => (string) ($support['channel'] ?? 'Email'),
                        'delivery_mode' => (string) ($support['delivery_mode'] ?? ''),
                        'message_length' => mb_strlen((string) $message->message_text),
                        'quiet_until_utc' => $support['quiet_until_utc'] ?? null,
                    ],
                    'actor' => [
                        'type' => 'staff_user',
                        'user_id' => $actingStaffUserId,
                    ],
                ],
                'conversation_id' => (string) $conversation->conversation_id,
                'message_id' => (int) $message->message_id,
                'outbox_id' => (int) $outbox->outbox_id,
                'delivery_channel' => (string) ($support['channel'] ?? 'Email'),
                'delivery_mode' => (string) ($support['delivery_mode'] ?? ''),
                'recipient_masked' => $support['recipient_masked'] ?? null,
            ]);

            return [
                'action' => 'conversation.outbound_reply_queued',
                'assignment_id' => $activeAssignment instanceof AgentAssignment ? (int) $activeAssignment->assignment_id : null,
                'event_id' => (int) $event->event_id,
                'message_id' => (int) $message->message_id,
            ];
        });

        return $this->hydrateMutationPayload($conversationId, $mutation);
    }

    private function lockConversationOrFail(string $conversationId): Conversation
    {
        $conversation = Conversation::query()
            ->where('conversation_id', $conversationId)
            ->lockForUpdate()
            ->first();

        if (! $conversation instanceof Conversation) {
            throw (new ModelNotFoundException())->setModel(Conversation::class, [$conversationId]);
        }

        return $conversation;
    }

    private function lockActiveAssignment(string $conversationId): ?AgentAssignment
    {
        return AgentAssignment::query()
            ->where('conversation_id', $conversationId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->latest('assignment_id')
            ->first();
    }

    private function recordEvent(string $conversationId, string $eventType, int $actingStaffUserId, array $eventData): ConversationEvent
    {
        return ConversationEvent::query()->create([
            'conversation_id' => $conversationId,
            'event_type' => $eventType,
            'event_by_user_id' => $actingStaffUserId,
            'event_data' => $eventData,
        ]);
    }

    private function resolveAssignableAgent(int $userId): User
    {
        $user = User::query()->with('role')->find($userId);

        if (! $user instanceof User) {
            throw (new ModelNotFoundException())->setModel(User::class, [$userId]);
        }

        $roleName = $user->relationLoaded('role') && $user->role !== null
            ? (string) $user->role->role_name
            : '';

        $resolved = $this->staffCapabilityResolver->resolveForActor((int) ($user->role_id ?? 0), $roleName);
        $capabilities = (array) ($resolved['capabilities'] ?? []);

        if (! in_array('*', $capabilities, true) && ! in_array('conversation.manage', $capabilities, true)) {
            throw ValidationException::withMessages([
                'agent_user_id' => ['Selected user is not allowed to own staff conversations.'],
            ]);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int|null>
     */
    private function resolveLinkState(Conversation $conversation, array $payload): array
    {
        $reservation = null;
        if (! empty($payload['reservation_id'])) {
            $reservation = Reservation::query()->find((int) $payload['reservation_id']);
            if (! $reservation instanceof Reservation) {
                throw (new ModelNotFoundException())->setModel(Reservation::class, [(int) $payload['reservation_id']]);
            }
        }

        $waitingList = null;
        if (! empty($payload['waiting_list_id'])) {
            $waitingList = WaitlistEntry::query()->find((int) $payload['waiting_list_id']);
            if (! $waitingList instanceof WaitlistEntry) {
                throw (new ModelNotFoundException())->setModel(WaitlistEntry::class, [(int) $payload['waiting_list_id']]);
            }
        }

        $customer = null;
        if (! empty($payload['customer_user_id'])) {
            $customer = User::query()->find((int) $payload['customer_user_id']);
            if (! $customer instanceof User) {
                throw (new ModelNotFoundException())->setModel(User::class, [(int) $payload['customer_user_id']]);
            }
        }

        if ($reservation instanceof Reservation && $waitingList instanceof WaitlistEntry && (int) $reservation->branch_id !== (int) $waitingList->branch_id) {
            throw ValidationException::withMessages([
                'waiting_list_id' => ['Waiting-list entry must belong to the same branch as the linked reservation.'],
            ]);
        }

        $candidateUserIds = array_values(array_filter([
            $customer?->user_id,
            $reservation?->user_id,
            $waitingList?->user_id,
        ], static fn (mixed $value): bool => $value !== null && (int) $value > 0));

        if (count(array_unique(array_map('intval', $candidateUserIds))) > 1) {
            throw ValidationException::withMessages([
                'customer_user_id' => ['Customer, reservation, and waiting-list links must resolve to the same user.'],
            ]);
        }

        $branchId = (int) ($conversation->branch_id ?? 0);
        if ($reservation instanceof Reservation) {
            $branchId = (int) $reservation->branch_id;
        } elseif ($waitingList instanceof WaitlistEntry) {
            $branchId = (int) $waitingList->branch_id;
        }

        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'branch_id' => ['Conversation branch scope could not be resolved from the requested links.'],
            ]);
        }

        $userId = null;
        if ($candidateUserIds !== []) {
            $userId = (int) $candidateUserIds[0];
        } elseif ($conversation->user_id !== null && (int) $conversation->user_id > 0) {
            $userId = (int) $conversation->user_id;
        }

        return [
            'branch_id' => $branchId,
            'user_id' => $userId,
            'linked_reservation_id' => $reservation instanceof Reservation
                ? (int) $reservation->reservation_id
                : ($conversation->linked_reservation_id !== null ? (int) $conversation->linked_reservation_id : null),
            'linked_waiting_list_id' => $waitingList instanceof WaitlistEntry
                ? (int) $waitingList->waiting_id
                : ($conversation->linked_waiting_list_id !== null ? (int) $conversation->linked_waiting_list_id : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $mutation
     * @return array<string, mixed>
     */
    private function hydrateMutationPayload(string $conversationId, array $mutation): array
    {
        $assignment = null;
        if (! empty($mutation['assignment_id'])) {
            $assignment = AgentAssignment::query()
                ->with(['agent.role'])
                ->find((int) $mutation['assignment_id']);
        }

        $event = null;
        if (! empty($mutation['event_id'])) {
            $event = ConversationEvent::query()
                ->with(['byUser.role'])
                ->find((int) $mutation['event_id']);
        }

        $message = null;
        if (! empty($mutation['message_id'])) {
            $message = ConversationMessage::query()
                ->with(['senderUser.role', 'files', 'entities'])
                ->find((int) $mutation['message_id']);
        }

        return [
            'action' => (string) ($mutation['action'] ?? 'conversation.updated'),
            'conversation' => $this->inboxService->findSummaryOrFail($conversationId),
            'assignment' => $assignment,
            'event' => $event,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $support
     */
    private function assertOutboundReplySupported(array $support): void
    {
        if (($support['supported'] ?? false) === true) {
            return;
        }

        $reasonCode = (string) ($support['reason_code'] ?? '');
        $reason = (string) ($support['reason'] ?? 'Outbound reply is unavailable for this conversation.');

        if ($reasonCode === 'assigned_to_other_staff') {
            throw new ConflictHttpException($reason);
        }

        throw ValidationException::withMessages([
            'conversation_id' => [$reason],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOutboundReplyNotificationPayload(Conversation $conversation, ConversationMessage $message): array
    {
        $recipientUser = $conversation->user
            ?? $conversation->linkedReservation?->user
            ?? $conversation->linkedWaitingList?->user;

        return [
            'conversation_id' => (string) $conversation->conversation_id,
            'conversation_channel' => $conversation->channel?->value ?? (string) $conversation->channel,
            'customer_name' => (string) ($recipientUser?->full_name ?? 'Customer'),
            'message_text' => (string) $message->message_text,
            'branch_name' => (string) ($conversation->branch?->branch_name ?? config('app.name', 'RestaurantPOS')),
            'reservation_code' => (string) ($conversation->linkedReservation?->reservation_code ?? ''),
            'related_reservation_id' => $message->related_reservation_id !== null ? (int) $message->related_reservation_id : null,
            'related_order_id' => $message->related_order_id !== null ? (int) $message->related_order_id : null,
            'restaurant_name' => (string) config('app.name', 'RestaurantPOS'),
        ];
    }

    private function resolveWorkflowState(Conversation $conversation, ?AgentAssignment $activeAssignment): StaffConversationWorkflowState
    {
        $conversation->setRelation('activeAssignment', $activeAssignment);

        return $conversation->workflowState();
    }

    private function assertAssignmentAllowed(StaffConversationWorkflowState $currentState): void
    {
        if (in_array($currentState, [StaffConversationWorkflowState::Resolved, StaffConversationWorkflowState::Closed], true)) {
            throw ValidationException::withMessages([
                'conversation_id' => ['Resolved or closed conversations must be reopened before they can be assigned.'],
            ]);
        }
    }

    private function assertClosedStateDisallowsMutation(StaffConversationWorkflowState $currentState, string $message): void
    {
        if ($currentState === StaffConversationWorkflowState::Closed) {
            throw ValidationException::withMessages([
                'conversation_id' => [$message],
            ]);
        }
    }

    private function assertWorkflowTransitionAllowed(
        StaffConversationWorkflowState $currentState,
        StaffConversationWorkflowState $targetState,
    ): void {
        $allowed = match ($currentState) {
            StaffConversationWorkflowState::Open => [
                StaffConversationWorkflowState::Open,
                StaffConversationWorkflowState::Triaged,
                StaffConversationWorkflowState::PendingCustomer,
                StaffConversationWorkflowState::Closed,
            ],
            StaffConversationWorkflowState::Triaged => [
                StaffConversationWorkflowState::Triaged,
                StaffConversationWorkflowState::Open,
                StaffConversationWorkflowState::PendingCustomer,
                StaffConversationWorkflowState::Resolved,
                StaffConversationWorkflowState::Closed,
            ],
            StaffConversationWorkflowState::Assigned => [
                StaffConversationWorkflowState::Assigned,
                StaffConversationWorkflowState::PendingCustomer,
                StaffConversationWorkflowState::Resolved,
                StaffConversationWorkflowState::Closed,
            ],
            StaffConversationWorkflowState::PendingCustomer => [
                StaffConversationWorkflowState::PendingCustomer,
                StaffConversationWorkflowState::Triaged,
                StaffConversationWorkflowState::Resolved,
                StaffConversationWorkflowState::Closed,
            ],
            StaffConversationWorkflowState::Resolved => [
                StaffConversationWorkflowState::Resolved,
                StaffConversationWorkflowState::Triaged,
                StaffConversationWorkflowState::Closed,
            ],
            StaffConversationWorkflowState::Closed => [
                StaffConversationWorkflowState::Closed,
                StaffConversationWorkflowState::Triaged,
            ],
        };

        if (! in_array($targetState, $allowed, true)) {
            throw ValidationException::withMessages([
                'workflow_state' => [sprintf(
                    'Conversation workflow cannot transition from %s to %s.',
                    $currentState->value,
                    $targetState->value,
                )],
            ]);
        }
    }

    private function syncWorkflowState(
        Conversation $conversation,
        StaffConversationWorkflowState $targetState,
        string $stateReason,
    ): void {
        $currentState = $conversation->workflowState();
        $now = now('UTC');
        $currentResolvedAt = $conversation->resolved_at;

        $conversation->forceFill([
            'workflow_state' => $targetState->value,
            'workflow_state_reason' => $stateReason,
            'workflow_state_changed_at' => $now,
            'first_triaged_at' => $targetState === StaffConversationWorkflowState::Open
                ? $conversation->first_triaged_at
                : ($conversation->first_triaged_at ?? $now),
            'resolved_at' => match ($targetState) {
                StaffConversationWorkflowState::Resolved => $now,
                StaffConversationWorkflowState::Closed => $currentState === StaffConversationWorkflowState::Resolved
                    ? ($currentResolvedAt ?? $now)
                    : $currentResolvedAt,
                default => null,
            },
            'closed_at' => $targetState === StaffConversationWorkflowState::Closed ? $now : null,
            'status' => match ($targetState) {
                StaffConversationWorkflowState::PendingCustomer => ConversationStatus::Pending->value,
                StaffConversationWorkflowState::Closed => ConversationStatus::Closed->value,
                default => ConversationStatus::Open->value,
            },
        ])->save();
    }

    /**
     * @return array<string, int|null>
     */
    private function conversationLinkSnapshot(Conversation $conversation): array
    {
        return [
            'branch_id' => $conversation->branch_id !== null ? (int) $conversation->branch_id : null,
            'user_id' => $conversation->user_id !== null ? (int) $conversation->user_id : null,
            'linked_reservation_id' => $conversation->linked_reservation_id !== null ? (int) $conversation->linked_reservation_id : null,
            'linked_waiting_list_id' => $conversation->linked_waiting_list_id !== null ? (int) $conversation->linked_waiting_list_id : null,
        ];
    }

    private function workflowStateReasonForTransition(
        StaffConversationWorkflowState $currentState,
        StaffConversationWorkflowState $targetState,
    ): string {
        if ($targetState === StaffConversationWorkflowState::Triaged && in_array($currentState, [StaffConversationWorkflowState::Resolved, StaffConversationWorkflowState::Closed], true)) {
            return 'reopened';
        }

        return match ($targetState) {
            StaffConversationWorkflowState::Open => 'open',
            StaffConversationWorkflowState::Triaged => 'triaged',
            StaffConversationWorkflowState::PendingCustomer => 'waiting_for_customer',
            StaffConversationWorkflowState::Resolved => 'resolved',
            StaffConversationWorkflowState::Closed => 'closed',
            default => 'workflow_updated',
        };
    }

    private function workflowActionName(
        StaffConversationWorkflowState $currentState,
        StaffConversationWorkflowState $targetState,
    ): string {
        if ($targetState === StaffConversationWorkflowState::Triaged && in_array($currentState, [StaffConversationWorkflowState::Resolved, StaffConversationWorkflowState::Closed], true)) {
            return 'conversation.reopened';
        }

        return match ($targetState) {
            StaffConversationWorkflowState::Triaged => 'conversation.triaged',
            StaffConversationWorkflowState::PendingCustomer => 'conversation.pending_customer',
            StaffConversationWorkflowState::Resolved => 'conversation.resolved',
            StaffConversationWorkflowState::Closed => 'conversation.closed',
            default => 'conversation.workflow_updated',
        };
    }

    private function workflowAuditEventName(
        StaffConversationWorkflowState $currentState,
        StaffConversationWorkflowState $targetState,
    ): string {
        if ($targetState === StaffConversationWorkflowState::Triaged && in_array($currentState, [StaffConversationWorkflowState::Resolved, StaffConversationWorkflowState::Closed], true)) {
            return 'staff.conversation.reopened';
        }

        return match ($targetState) {
            StaffConversationWorkflowState::Triaged => 'staff.conversation.triaged',
            StaffConversationWorkflowState::PendingCustomer => 'staff.conversation.waiting_for_customer',
            StaffConversationWorkflowState::Resolved => 'staff.conversation.resolved',
            StaffConversationWorkflowState::Closed => 'staff.conversation.closed',
            default => 'staff.conversation.workflow_updated',
        };
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, string>>  $subjects
     */
    private function auditConversationChange(
        string $eventName,
        Conversation $conversation,
        int $actingStaffUserId,
        array $before = [],
        array $after = [],
        array $summary = [],
        array $subjects = [],
    ): void {
        AuditEvent::info($eventName, [
            '_audit' => [
                'action' => $eventName,
                'entity_type' => 'conversation',
                'entity_id' => (string) $conversation->conversation_id,
                'before' => $before === [] ? null : $before,
                'after' => $after === [] ? null : $after,
                'summary' => $summary === [] ? null : $summary,
                'subjects' => $subjects,
                'actor' => [
                    'type' => 'staff_user',
                    'user_id' => $actingStaffUserId,
                ],
            ],
            'conversation_id' => (string) $conversation->conversation_id,
        ]);
    }

    private function assertConversationInboxEnabled(Conversation $conversation): void
    {
        $this->assertConversationInboxEnabledForBranch(
            $conversation->branch_id !== null ? (int) $conversation->branch_id : null,
        );
    }

    private function assertConversationInboxEnabledForBranch(?int $branchId): void
    {
        $this->featureFlags->assertEnabled(
            'staff.conversation_inbox',
            $branchId,
            field: 'conversation_inbox',
        );
    }

    private function assertConversationOperationalBranchAccessible(Conversation $conversation, ?int $staffActorUserId): void
    {
        $this->assertOperationalBranchAccessible((int) $conversation->branch_id, $staffActorUserId);
    }

    private function assertOperationalBranchAccessible(int $branchId, ?int $staffActorUserId): void
    {
        $this->branchContextService->assertAccessibleBranch($staffActorUserId, $branchId);
    }
}

