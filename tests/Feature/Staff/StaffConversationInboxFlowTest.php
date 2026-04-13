<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffConversationInboxFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_staff_can_list_conversations_with_operational_filters_and_summary_meta(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $otherStaffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchA = $this->createBranch(['branch_code' => 'CONV-A', 'branch_name' => 'Conversation A']);
        $branchB = $this->createBranch(['branch_code' => 'CONV-B', 'branch_name' => 'Conversation B']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-list');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchA,
            'status' => 'Open',
        ]);

        $reservationId = $this->createReservation([
            'branch_id' => $branchA,
            'user_id' => $customerId,
            'reservation_code' => 'RSV-CONV-LIST-001',
        ]);
        $waitingListId = $this->createWaitingListEntry([
            'branch_id' => $branchB,
            'user_id' => $customerId,
            'status' => 'Notified',
            'notified_at' => $this->nowUtc()->copy()->subMinutes(18),
            'notify_expires_at' => $this->nowUtc()->copy()->addMinutes(12),
        ]);

        $matchConversationId = $this->createConversation([
            'branch_id' => $branchA,
            'user_id' => $customerId,
            'channel' => 'WebChat',
            'status' => 'Open',
            'intent_detected' => 'reservation_follow_up',
            'linked_reservation_id' => $reservationId,
            'created_at' => $this->nowUtc()->copy()->subMinutes(30),
        ]);
        $this->createConversationMessage([
            'conversation_id' => $matchConversationId,
            'sender' => 'user',
            'sender_id' => $customerId,
            'message_text' => 'Need help with RSV-CONV-LIST-001 please.',
            'created_at' => $this->nowUtc()->copy()->subMinutes(29),
            'related_reservation_id' => $reservationId,
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $matchConversationId,
            'agent_user_id' => $staffId,
            'assigned_at' => $this->nowUtc()->copy()->subMinutes(28),
            'is_active' => 1,
        ]);

        $otherConversationId = $this->createConversation([
            'branch_id' => $branchB,
            'user_id' => $customerId,
            'channel' => 'Zalo',
            'status' => 'Pending',
            'intent_detected' => 'waiting_list_follow_up',
            'linked_waiting_list_id' => $waitingListId,
            'created_at' => $this->nowUtc()->copy()->subMinutes(20),
        ]);
        $this->createConversationMessage([
            'conversation_id' => $otherConversationId,
            'sender' => 'user',
            'sender_id' => $customerId,
            'message_text' => 'Any update on the waiting list?',
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $otherConversationId,
            'agent_user_id' => $otherStaffId,
            'assigned_at' => $this->nowUtc()->copy()->subMinutes(19),
            'is_active' => 1,
        ]);

        $response = $this->withHeaders($headers)->getJson(sprintf(
            '/api/v1/staff/conversations?status=Open&channel=WebChat&assigned_agent_user_id=%d&reservation_id=%d&per_page=20',
            $staffId,
            $reservationId,
        ));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.summary.total', 1)
            ->assertJsonPath('meta.summary.assigned', 1)
            ->assertJsonPath('meta.summary.status_counts.Open', 1)
            ->assertJsonPath('meta.filters.status', 'Open')
            ->assertJsonPath('meta.filters.channel', 'WebChat')
            ->assertJsonPath('data.0.conversation_id', $matchConversationId)
            ->assertJsonPath('data.0.linked_reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.0.active_assignment.agent_user_id', $staffId)
            ->assertJsonPath('data.0.assignment_state.is_mine', true);

        self::assertNotSame($matchConversationId, $otherConversationId);
    }

    public function test_staff_can_read_conversation_detail_with_messages_files_entities_events_and_assignment_history(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $previousStaffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch(['branch_code' => 'CONV-D', 'branch_name' => 'Conversation Detail']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-detail');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'reservation_code' => 'RSV-CONV-DETAIL-001',
        ]);

        $conversationId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Pending',
            'intent_detected' => 'reservation_follow_up',
            'linked_reservation_id' => $reservationId,
        ]);

        $firstMessageId = $this->createConversationMessage([
            'conversation_id' => $conversationId,
            'sender' => 'user',
            'sender_id' => $customerId,
            'message_text' => 'Can you update my arrival note?',
            'related_reservation_id' => $reservationId,
            'created_at' => $this->nowUtc()->copy()->subMinutes(18),
        ]);
        $this->createConversationFile([
            'message_id' => $firstMessageId,
            'file_url' => 'https://example.test/conversations/arrival-note.png',
            'mime_type' => 'image/png',
        ]);
        $this->createMessageEntity([
            'message_id' => $firstMessageId,
            'entity_type' => 'reservation_code',
            'entity_text' => 'RSV-CONV-DETAIL-001',
            'entity_normalized' => 'RSV-CONV-DETAIL-001',
        ]);

        $noteMessageId = $this->createConversationMessage([
            'conversation_id' => $conversationId,
            'sender' => 'agent',
            'sender_id' => $staffId,
            'message_text' => 'Internal note: guest will arrive 15 minutes late.',
            'is_internal_note' => 1,
            'processing_status' => 'reviewed',
            'related_reservation_id' => $reservationId,
            'created_at' => $this->nowUtc()->copy()->subMinutes(10),
        ]);

        $this->createConversationEvent([
            'conversation_id' => $conversationId,
            'event_type' => 'conversation.linked',
            'event_by_user_id' => $staffId,
            'event_data' => [
                'linked_reservation_id' => $reservationId,
            ],
        ]);
        $this->createConversationAnalysis([
            'conversation_id' => $conversationId,
            'analyzer_name' => 'conversation_contract_test',
            'is_spam' => 0,
            'quality_score' => '0.9400',
            'extracted_info' => [
                'intent' => 'reservation_follow_up',
            ],
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $conversationId,
            'agent_user_id' => $previousStaffId,
            'assigned_at' => $this->nowUtc()->copy()->subMinutes(16),
            'released_at' => $this->nowUtc()->copy()->subMinutes(12),
            'is_active' => 0,
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $conversationId,
            'agent_user_id' => $staffId,
            'assigned_at' => $this->nowUtc()->copy()->subMinutes(11),
            'is_active' => 1,
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/conversations/'.$conversationId.'?message_limit=10&event_limit=10');

        $response->assertOk()
            ->assertJsonPath('data.conversation.conversation_id', $conversationId)
            ->assertJsonPath('data.conversation.linked_reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.messages.0.message_id', $firstMessageId)
            ->assertJsonPath('data.messages.0.files.0.file_url', 'https://example.test/conversations/arrival-note.png')
            ->assertJsonPath('data.messages.0.entities.0.entity_normalized', 'RSV-CONV-DETAIL-001')
            ->assertJsonPath('data.messages.1.message_id', $noteMessageId)
            ->assertJsonPath('data.messages.1.is_internal_note', true)
            ->assertJsonPath('data.events.0.event_type', 'conversation.linked')
            ->assertJsonPath('data.analyses.0.analyzer_name', 'conversation_contract_test')
            ->assertJsonPath('data.ai_assist.status', 'ready')
            ->assertJsonPath('data.ai_assist.feature_key', 'staff.conversation_ai_assist')
            ->assertJsonPath('data.ai_assist.provider', 'local_heuristic')
            ->assertJsonPath('data.ai_assist.model', 'conversation-summary-v1')
            ->assertJsonPath('data.ai_assist.priority', 'high')
            ->assertJsonPath('data.ai_assist.generated_from.message_count', 2)
            ->assertJsonPath('data.ai_assist.generated_from.customer_message_count', 1)
            ->assertJsonPath('data.ai_assist.generated_from.internal_note_count', 1)
            ->assertJsonPath('data.ai_assist.generated_from.analysis_count', 1)
            ->assertJsonPath('data.ai_assist.suggested_actions.0.code', 'review_reservation')
            ->assertJsonPath('data.ai_assist.suggested_actions.1.code', 'update_arrival_note')
            ->assertJsonPath('data.ai_assist.risk_flags.0.code', 'time_sensitive')
            ->assertJsonPath('data.assignment_history.0.agent_user_id', $staffId)
            ->assertJsonPath('data.capabilities.can_send_outbound_reply', true)
            ->assertJsonPath('data.capabilities.outbound_reply.supported', true)
            ->assertJsonPath('data.capabilities.outbound_reply.channel', 'Email')
            ->assertJsonPath('data.capabilities.outbound_reply.delivery_mode', 'real')
            ->assertJsonPath('meta.returned_counts.messages', 2);

        self::assertStringContainsString('Reservation RSV-CONV-DETAIL-001 needs follow-up.', (string) $response->json('data.ai_assist.summary'));
    }

    public function test_conversation_detail_keeps_ai_assist_optional_when_branch_flag_disables_it(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'CONV-AI-OFF',
            'branch_name' => 'Conversation AI Off',
        ]);
        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-detail-ai-off');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);

        $conversationId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Open',
        ]);
        $this->createConversationMessage([
            'conversation_id' => $conversationId,
            'sender' => 'user',
            'sender_id' => $customerId,
            'message_text' => 'Need help with this booking.',
        ]);

        $this->upsertFeatureFlagOverride(
            'staff.conversation_ai_assist',
            false,
            'testing',
            $branchId,
            ['reason' => 'batch 7 rollout paused'],
        );

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/conversations/'.$conversationId)
            ->assertOk()
            ->assertJsonPath('data.conversation.conversation_id', $conversationId)
            ->assertJsonPath('data.ai_assist.status', 'disabled')
            ->assertJsonPath('data.ai_assist.fallback_reason_code', 'feature_disabled')
            ->assertJsonPath('data.ai_assist.summary', null)
            ->assertJsonPath('data.ai_assist.generated_from.message_count', 1);
    }

    public function test_conversation_detail_capability_marks_outbound_reply_unsupported_for_other_active_assignee(): void
    {
        $assignedStaffId = $this->createUser(['role_name' => 'Staff']);
        $viewerStaffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'email' => 'conversation.assigned.other@example.test',
        ]);
        $branchId = $this->createBranch([
            'branch_code' => 'CONV-CAP',
            'branch_name' => 'Conversation Capability Branch',
        ]);
        $this->createCashierShift([
            'cashier_user_id' => $viewerStaffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);

        $conversationId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Open',
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $conversationId,
            'agent_user_id' => $assignedStaffId,
            'assigned_at' => $this->nowUtc()->copy()->subMinutes(5),
            'is_active' => 1,
        ]);

        $response = $this->withHeaders($this->staffAuthHeaders($viewerStaffId, 'staff-conversation-detail-capability'))
            ->getJson('/api/v1/staff/conversations/'.$conversationId);

        $response->assertOk()
            ->assertJsonPath('data.conversation.conversation_id', $conversationId)
            ->assertJsonPath('data.capabilities.can_send_outbound_reply', false)
            ->assertJsonPath('data.capabilities.outbound_reply.supported', false)
            ->assertJsonPath('data.capabilities.outbound_reply.reason_code', 'assigned_to_other_staff')
            ->assertJsonPath('data.capabilities.outbound_reply.channel', null)
            ->assertJsonPath('data.capabilities.outbound_reply.delivery_mode', null)
            ->assertJsonPath('data.capabilities.outbound_reply.recipient_masked', null);
    }

    public function test_conversation_reads_and_link_mutations_respect_staff_operational_branch_scope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchA = $this->createBranch(['branch_code' => 'CONV-SCOPE-A', 'branch_name' => 'Conversation Scope A']);
        $branchB = $this->createBranch(['branch_code' => 'CONV-SCOPE-B', 'branch_name' => 'Conversation Scope B']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-branch-scope');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchA,
            'status' => 'Open',
        ]);

        $accessibleConversationId = $this->createConversation([
            'branch_id' => $branchA,
            'user_id' => $customerId,
            'status' => 'Open',
        ]);
        $hiddenConversationId = $this->createConversation([
            'branch_id' => $branchB,
            'user_id' => $customerId,
            'status' => 'Open',
        ]);
        $hiddenReservationId = $this->createReservation([
            'branch_id' => $branchB,
            'user_id' => $customerId,
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/conversations?per_page=20')
            ->assertOk()
            ->assertJsonFragment(['conversation_id' => $accessibleConversationId])
            ->assertJsonMissing(['conversation_id' => $hiddenConversationId]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/conversations/'.$hiddenConversationId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        $this->withHeaders($this->withIdempotencyKey($headers, 'staff-conversation-link-hidden-branch'))
            ->postJson('/api/v1/staff/conversations/'.$accessibleConversationId.'/links', [
                'reservation_id' => $hiddenReservationId,
                'customer_user_id' => $customerId,
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        self::assertNull(DB::table('conversations')
            ->where('conversation_id', $accessibleConversationId)
            ->value('linked_reservation_id'));
    }

    public function test_assign_conflict_take_over_and_unassign_flow_is_safe(): void
    {
        $staffAId = $this->createUser(['role_name' => 'Staff']);
        $staffBId = $this->createUser(['role_name' => 'Staff']);
        $headersA = $this->staffAuthHeaders($staffAId, 'staff-conversation-assign-a');
        $headersB = $this->staffAuthHeaders($staffBId, 'staff-conversation-assign-b');

        $conversationId = $this->createConversation([
            'status' => 'Open',
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $conversationId,
            'agent_user_id' => $staffAId,
            'is_active' => 1,
        ]);

        $this->withHeaders($headersB)
            ->withHeader('Idempotency-Key', 'staff-conversation-assign-conflict-1')
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/assign', [
                'agent_user_id' => $staffBId,
                'notes' => 'Reassign attempt',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'conflict');

        $takeOver = $this->withHeaders($headersB)
            ->withHeader('Idempotency-Key', 'staff-conversation-take-over-1')
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/take-over', [
                'notes' => 'Taking ownership from inbox',
            ]);

        $takeOver->assertOk()
            ->assertJsonPath('data.action', 'conversation.taken_over')
            ->assertJsonPath('data.assignment.agent_user_id', $staffBId)
            ->assertJsonPath('data.event.event_type', 'assignment.changed')
            ->assertJsonPath('data.conversation.active_assignment.agent_user_id', $staffBId);

        self::assertSame(1, (int) DB::table('agent_assignments')->where('conversation_id', $conversationId)->where('is_active', 1)->count());

        $unassign = $this->withHeaders($headersB)
            ->withHeader('Idempotency-Key', 'staff-conversation-unassign-1')
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/unassign', [
                'notes' => 'Inbox cleared',
            ]);

        $unassign->assertOk()
            ->assertJsonPath('data.action', 'conversation.unassigned')
            ->assertJsonPath('data.assignment', null)
            ->assertJsonPath('data.event.event_type', 'assignment.cleared')
            ->assertJsonPath('data.conversation.active_assignment', null);

        self::assertSame(0, (int) DB::table('agent_assignments')->where('conversation_id', $conversationId)->where('is_active', 1)->count());
        self::assertSame(2, (int) DB::table('agent_assignments')->where('conversation_id', $conversationId)->count());
        $this->assertNotSame($staffAId, $staffBId);
        $this->assertNotSame($headersA['X-Staff-Key'], $headersB['X-Staff-Key']);
    }

    public function test_staff_can_link_and_unlink_conversation_with_reservation_waiting_list_and_customer(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch(['branch_code' => 'CONV-L', 'branch_name' => 'Conversation Link']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-link');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'reservation_code' => 'RSV-CONV-LINK-001',
        ]);
        $waitingListId = $this->createWaitingListEntry([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Waiting',
        ]);

        $conversationId = $this->createConversation([
            'branch_id' => 1,
            'user_id' => null,
            'linked_reservation_id' => null,
            'linked_waiting_list_id' => null,
        ]);

        $link = $this->withHeaders($headers)
            ->withHeader('Idempotency-Key', 'staff-conversation-link-1')
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/links', [
                'reservation_id' => $reservationId,
                'waiting_list_id' => $waitingListId,
                'customer_user_id' => $customerId,
                'notes' => 'Linked from staff inbox',
            ]);

        $link->assertOk()
            ->assertJsonPath('data.action', 'conversation.linked')
            ->assertJsonPath('data.conversation.branch_id', $branchId)
            ->assertJsonPath('data.conversation.user.user_id', $customerId)
            ->assertJsonPath('data.conversation.linked_reservation.reservation_id', $reservationId)
            ->assertJsonPath('data.conversation.linked_waiting_list.waiting_id', $waitingListId)
            ->assertJsonPath('data.event.event_type', 'conversation.linked');

        $this->deleteJson('/api/v1/staff/conversations/'.$conversationId.'/links/reservation', [], array_merge($headers, [
            'Idempotency-Key' => 'staff-conversation-unlink-reservation-1',
        ]))
            ->assertOk()
            ->assertJsonPath('data.action', 'conversation.reservation_unlinked')
            ->assertJsonPath('data.conversation.linked_reservation', null);

        $this->deleteJson('/api/v1/staff/conversations/'.$conversationId.'/links/waiting-list', [], array_merge($headers, [
            'Idempotency-Key' => 'staff-conversation-unlink-waiting-list-1',
        ]))
            ->assertOk()
            ->assertJsonPath('data.action', 'conversation.waiting_list_unlinked')
            ->assertJsonPath('data.conversation.linked_waiting_list', null);

        self::assertSame($customerId, (int) DB::table('conversations')->where('conversation_id', $conversationId)->value('user_id'));
    }

    public function test_staff_can_add_internal_note_as_operational_reply_foundation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-note');

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'reservation_code' => 'RSV-CONV-NOTE-001',
        ]);
        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'linked_reservation_id' => $reservationId,
        ]);

        $response = $this->withHeaders($headers)
            ->withHeader('Idempotency-Key', 'staff-conversation-internal-note-1')
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/internal-notes', [
                'message_text' => 'Please call guest back if they do not arrive in 10 minutes.',
                'related_reservation_id' => $reservationId,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.action', 'conversation.internal_note_added')
            ->assertJsonPath('data.message.is_internal_note', true)
            ->assertJsonPath('data.message.sender', 'agent')
            ->assertJsonPath('data.message.sender_id', $staffId)
            ->assertJsonPath('data.event.event_type', 'internal_note.added')
            ->assertJsonPath('data.conversation.linked_reservation.reservation_id', $reservationId);

        self::assertSame(1, (int) DB::table('conversation_messages')->where('conversation_id', $conversationId)->where('is_internal_note', 1)->count());
    }

    public function test_conversation_write_routes_require_idempotency_key_and_replay_internal_note_safely(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-idempotency');

        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'status' => 'Open',
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/take-over', [
                'notes' => 'Missing idempotency key should fail.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'idempotency_key_required');

        $payload = [
            'message_text' => 'Operational replay-safe note.',
        ];

        $first = $this->withHeaders(array_merge($headers, [
            'Idempotency-Key' => 'staff-conversation-note-replay-1',
        ]))->postJson('/api/v1/staff/conversations/'.$conversationId.'/internal-notes', $payload);

        $second = $this->withHeaders(array_merge($headers, [
            'Idempotency-Key' => 'staff-conversation-note-replay-1',
        ]))->postJson('/api/v1/staff/conversations/'.$conversationId.'/internal-notes', $payload);

        $first->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.action', 'conversation.internal_note_added');
        $second->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.action', 'conversation.internal_note_added')
            ->assertJsonPath('data.message.message_id', $first->json('data.message.message_id'));

        self::assertSame(1, (int) DB::table('conversation_messages')->where('conversation_id', $conversationId)->where('is_internal_note', 1)->count());
    }

    public function test_staff_can_queue_outbound_reply_when_runtime_email_delivery_is_supported(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Conversation Reply Customer',
            'email' => 'conversation.reply.customer@example.test',
        ]);
        $branchId = $this->createBranch([
            'branch_code' => 'CONV-RPL',
            'branch_name' => 'Conversation Reply Branch',
        ]);
        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-conversation-reply'), 'staff-conversation-reply-1');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'reservation_code' => 'RSV-CONV-REPLY-001',
        ]);
        $conversationId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Open',
            'linked_reservation_id' => $reservationId,
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $conversationId,
            'agent_user_id' => $staffId,
            'assigned_at' => $this->nowUtc()->copy()->subMinutes(4),
            'is_active' => 1,
        ]);

        $response = $this->withHeaders($headers)->postJson('/api/v1/staff/conversations/'.$conversationId.'/outbound-replies', [
            'message_text' => 'Your table is ready. Please check in with the host stand in the next 10 minutes.',
            'related_reservation_id' => $reservationId,
        ]);

        $response->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.action', 'conversation.outbound_reply_queued')
            ->assertJsonPath('data.message.is_internal_note', false)
            ->assertJsonPath('data.message.sender', 'agent')
            ->assertJsonPath('data.message.sender_id', $staffId)
            ->assertJsonPath('data.event.event_type', 'outbound_reply.queued')
            ->assertJsonPath('data.event.event_data.delivery_channel', 'Email')
            ->assertJsonPath('data.conversation.active_assignment.agent_user_id', $staffId);

        $messageId = (int) $response->json('data.message.message_id');
        $outboxId = (int) $response->json('data.event.event_data.outbox_id');

        self::assertSame(1, (int) DB::table('conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('is_internal_note', 0)
            ->count());
        self::assertSame(1, (int) DB::table('notification_outbox')
            ->where('outbox_id', $outboxId)
            ->where('template_key', 'conversation.outbound_reply')
            ->count());

        $payload = json_decode((string) DB::table('notification_outbox')->where('outbox_id', $outboxId)->value('payload_json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame((string) $conversationId, (string) ($payload['conversation_id'] ?? ''));
        self::assertSame('Your table is ready. Please check in with the host stand in the next 10 minutes.', (string) ($payload['message_text'] ?? ''));

        $audit = $this->assertAuditLogRecorded('conversation.outbound_reply_queued', 'conversation', $conversationId);
        $this->assertAuditSubjectRecorded($audit, 'conversation_message', $messageId, 'message');
        $this->assertAuditSubjectRecorded($audit, 'notification_outbox', $outboxId, 'delivery');
    }

    public function test_outbound_reply_route_requires_conversation_manage_capability(): void
    {
        $staffWithoutCapabilityId = $this->createUser(['role_name' => 'Host']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'status' => 'Open',
        ]);

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffWithoutCapabilityId, 'staff-conversation-reply-host'), 'staff-conversation-reply-host-1'))
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/outbound-replies', [
                'message_text' => 'Capability should block this.',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'conversation.manage');
    }

    public function test_outbound_reply_rejects_closed_conversation_state(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'status' => 'Closed',
        ]);

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-conversation-reply-closed'), 'staff-conversation-reply-closed-1'))
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/outbound-replies', [
                'message_text' => 'This should not send for a closed conversation.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['conversation_id']);

        self::assertSame(0, (int) DB::table('conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('is_internal_note', 0)
            ->count());
    }

    public function test_outbound_reply_rejects_active_assignment_owned_by_another_staff(): void
    {
        $staffAId = $this->createUser(['role_name' => 'Staff']);
        $staffBId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'status' => 'Open',
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $conversationId,
            'agent_user_id' => $staffAId,
            'assigned_at' => $this->nowUtc()->copy()->subMinutes(3),
            'is_active' => 1,
        ]);

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffBId, 'staff-conversation-reply-conflict'), 'staff-conversation-reply-conflict-1'))
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/outbound-replies', [
                'message_text' => 'This should conflict with the current assignee.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'conflict');

        self::assertSame(0, (int) DB::table('notification_outbox')->where('template_key', 'conversation.outbound_reply')->count());
    }

    public function test_outbound_reply_replay_is_idempotent_and_does_not_duplicate_message_or_outbox_rows(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'email' => 'conversation.reply.replay@example.test',
        ]);
        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'status' => 'Open',
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $conversationId,
            'agent_user_id' => $staffId,
            'assigned_at' => $this->nowUtc()->copy()->subMinute(),
            'is_active' => 1,
        ]);

        $headers = $this->withIdempotencyKey($this->staffAuthHeaders($staffId, 'staff-conversation-reply-replay'), 'staff-conversation-reply-replay-1');
        $payload = [
            'message_text' => 'Replay-safe outbound follow-up.',
        ];

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/outbound-replies', $payload);
        $second = $this->withHeaders($headers)
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/outbound-replies', $payload);

        $first->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.action', 'conversation.outbound_reply_queued');
        $second->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.action', 'conversation.outbound_reply_queued')
            ->assertJsonPath('data.message.message_id', $first->json('data.message.message_id'));

        self::assertSame(1, (int) DB::table('conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('is_internal_note', 0)
            ->count());
        self::assertSame(1, (int) DB::table('notification_outbox')
            ->where('template_key', 'conversation.outbound_reply')
            ->where('recipient_user_id', $customerId)
            ->count());
    }

    public function test_branch_flag_can_disable_staff_conversation_inbox_for_a_single_branch(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $branchId = $this->createBranch([
            'branch_code' => 'CONV-OFF',
            'branch_name' => 'Conversation Off',
        ]);
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);
        $conversationId = $this->createConversation([
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);

        $this->upsertFeatureFlagOverride(
            'staff.conversation_inbox',
            false,
            'testing',
            $branchId,
            ['reason' => 'branch inbox rollout paused'],
        );

        $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-conversation-flag-off'))
            ->getJson('/api/v1/staff/conversations/'.$conversationId)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.conversation_inbox.0', 'Conversation inbox is disabled for this rollout.');
    }

    public function test_conversation_routes_require_conversation_manage_capability(): void
    {
        $staffWithoutCapabilityId = $this->createUser(['role_name' => 'Host']);
        $headers = $this->staffAuthHeaders($staffWithoutCapabilityId, 'staff-conversation-host');

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/conversations');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'conversation.manage')
            ->assertJsonPath('staff_role_name', 'Host');
    }

    public function test_staff_can_drive_resolve_reopen_and_close_workflow_transitions_safely(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'status' => 'Open',
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $conversationId,
            'agent_user_id' => $staffId,
            'assigned_at' => $this->nowUtc()->copy()->subMinutes(4),
            'is_active' => 1,
        ]);

        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-workflow');

        $resolve = $this->withHeaders($this->withIdempotencyKey($headers, 'staff-conversation-resolve-1'))
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/workflow-state', [
                'workflow_state' => 'Resolved',
                'expected_workflow_state' => 'Assigned',
                'reason' => 'Customer confirmed the issue is resolved.',
            ]);

        $resolve->assertOk()
            ->assertJsonPath('data.action', 'conversation.resolved')
            ->assertJsonPath('data.event.event_type', 'workflow.state_changed')
            ->assertJsonPath('data.conversation.workflow.state', 'Resolved')
            ->assertJsonPath('data.conversation.workflow.state_reason', 'resolved')
            ->assertJsonPath('data.conversation.active_assignment', null);

        $this->withHeaders($this->withIdempotencyKey($headers, 'staff-conversation-assign-after-resolve-1'))
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/assign', [
                'agent_user_id' => $staffId,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['conversation_id']);

        $reopen = $this->withHeaders($this->withIdempotencyKey($headers, 'staff-conversation-reopen-1'))
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/workflow-state', [
                'workflow_state' => 'Triaged',
                'expected_workflow_state' => 'Resolved',
                'reason' => 'Customer sent a new follow-up.',
            ]);

        $reopen->assertOk()
            ->assertJsonPath('data.action', 'conversation.reopened')
            ->assertJsonPath('data.conversation.workflow.state', 'Triaged')
            ->assertJsonPath('data.conversation.workflow.state_reason', 'reopened');

        $close = $this->withHeaders($this->withIdempotencyKey($headers, 'staff-conversation-close-1'))
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/workflow-state', [
                'workflow_state' => 'Closed',
                'expected_workflow_state' => 'Triaged',
                'reason' => 'Staff archived the completed thread.',
            ]);

        $close->assertOk()
            ->assertJsonPath('data.action', 'conversation.closed')
            ->assertJsonPath('data.conversation.workflow.state', 'Closed')
            ->assertJsonPath('data.conversation.status', 'Closed');

        $this->withHeaders($this->withIdempotencyKey($headers, 'staff-conversation-link-after-close-1'))
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/links', [
                'customer_user_id' => $customerId,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['conversation_id']);
    }

    public function test_invalid_workflow_transition_and_expected_state_conflict_are_rejected(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $conversationId = $this->createConversation([
            'status' => 'Open',
            'workflow_state' => 'Open',
            'workflow_state_reason' => 'open',
        ]);

        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-workflow-invalid');

        $this->withHeaders($this->withIdempotencyKey($headers, 'staff-conversation-workflow-invalid-1'))
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/workflow-state', [
                'workflow_state' => 'Resolved',
                'expected_workflow_state' => 'Open',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['workflow_state']);

        $this->withHeaders($this->withIdempotencyKey($headers, 'staff-conversation-workflow-conflict-1'))
            ->postJson('/api/v1/staff/conversations/'.$conversationId.'/workflow-state', [
                'workflow_state' => 'Triaged',
                'expected_workflow_state' => 'Assigned',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'conflict');
    }

    public function test_operational_inbox_views_surface_unassigned_overdue_waiting_on_customer_and_resolved_today(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-views');
        $branchId = $this->createBranch([
            'branch_code' => 'CONV-VIEW',
            'branch_name' => 'Conversation Views',
        ]);
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);

        $unassignedId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'workflow_state' => 'Open',
            'workflow_state_reason' => 'open',
            'workflow_state_changed_at' => $this->nowUtc()->copy()->subMinutes(4),
            'created_at' => $this->nowUtc()->copy()->subMinutes(4),
        ]);

        $overdueId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'workflow_state' => 'Triaged',
            'workflow_state_reason' => 'triaged',
            'workflow_state_changed_at' => $this->nowUtc()->copy()->subMinutes(40),
            'first_triaged_at' => $this->nowUtc()->copy()->subMinutes(40),
            'created_at' => $this->nowUtc()->copy()->subMinutes(40),
        ]);

        $waitingId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Pending',
            'workflow_state' => 'PendingCustomer',
            'workflow_state_reason' => 'waiting_for_customer',
            'workflow_state_changed_at' => $this->nowUtc()->copy()->subMinutes(8),
            'created_at' => $this->nowUtc()->copy()->subMinutes(20),
        ]);
        $this->createAgentAssignment([
            'conversation_id' => $waitingId,
            'agent_user_id' => $staffId,
            'assigned_at' => $this->nowUtc()->copy()->subMinutes(12),
            'is_active' => 1,
        ]);
        DB::table('conversations')
            ->where('conversation_id', $waitingId)
            ->update([
                'status' => 'Pending',
                'workflow_state' => 'PendingCustomer',
                'workflow_state_reason' => 'waiting_for_customer',
                'workflow_state_changed_at' => $this->nowUtc()->copy()->subMinutes(8),
            ]);

        $resolvedId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'workflow_state' => 'Resolved',
            'workflow_state_reason' => 'resolved',
            'workflow_state_changed_at' => $this->nowUtc()->copy()->subMinutes(6),
            'resolved_at' => $this->nowUtc()->copy()->subMinutes(6),
            'created_at' => $this->nowUtc()->copy()->subHours(2),
        ]);

        $summary = $this->withHeaders($headers)
            ->getJson('/api/v1/staff/conversations?branch_id='.$branchId.'&per_page=20');

        $summary->assertOk()
            ->assertJsonPath('meta.summary.views.unassigned', 2)
            ->assertJsonPath('meta.summary.views.overdue', 1)
            ->assertJsonPath('meta.summary.views.waiting_on_customer', 1)
            ->assertJsonPath('meta.summary.views.resolved_today', 1);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/conversations?branch_id='.$branchId.'&inbox_view=overdue')
            ->assertOk()
            ->assertJsonPath('data.0.conversation_id', $overdueId)
            ->assertJsonPath('data.0.operational.is_overdue', true);

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/conversations?branch_id='.$branchId.'&inbox_view=waiting_on_customer')
            ->assertOk()
            ->assertJsonPath('data.0.conversation_id', $waitingId)
            ->assertJsonPath('data.0.workflow.state', 'PendingCustomer');

        $this->withHeaders($headers)
            ->getJson('/api/v1/staff/conversations?branch_id='.$branchId.'&inbox_view=resolved_today')
            ->assertOk()
            ->assertJsonPath('data.0.conversation_id', $resolvedId)
            ->assertJsonPath('data.0.workflow.state', 'Resolved');

        self::assertNotSame($unassignedId, $resolvedId);
    }

    public function test_unlinking_reservation_preserves_remaining_waiting_list_customer_and_branch_scope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $branchId = $this->createBranch([
            'branch_code' => 'CONV-RELINK',
            'branch_name' => 'Conversation Relink',
        ]);
        $headers = $this->staffAuthHeaders($staffId, 'staff-conversation-relink');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);

        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
        ]);
        $waitingListId = $this->createWaitingListEntry([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Waiting',
        ]);

        $conversationId = $this->createConversation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'linked_reservation_id' => $reservationId,
            'linked_waiting_list_id' => $waitingListId,
            'workflow_state' => 'Triaged',
            'workflow_state_reason' => 'triaged',
            'first_triaged_at' => $this->nowUtc()->copy()->subMinutes(5),
            'workflow_state_changed_at' => $this->nowUtc()->copy()->subMinutes(5),
        ]);

        $this->deleteJson('/api/v1/staff/conversations/'.$conversationId.'/links/reservation', [], array_merge($headers, [
            'Idempotency-Key' => 'staff-conversation-unlink-preserve-1',
        ]))
            ->assertOk()
            ->assertJsonPath('data.conversation.branch_id', $branchId)
            ->assertJsonPath('data.conversation.user.user_id', $customerId)
            ->assertJsonPath('data.conversation.linked_reservation', null)
            ->assertJsonPath('data.conversation.linked_waiting_list.waiting_id', $waitingListId);
    }
}
