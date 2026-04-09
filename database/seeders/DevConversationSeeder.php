<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DevConversationSeeder extends Seeder
{
    private const CONVERSATION_IDS = [
        'reservation' => '11111111-1111-1111-1111-111111111111',
        'waiting_list' => '22222222-2222-2222-2222-222222222222',
        'closed' => '33333333-3333-3333-3333-333333333333',
    ];

    public function run(): void
    {
        if (! Schema::hasTable('conversations') || ! Schema::hasTable('conversation_messages')) {
            return;
        }

        $now = Carbon::now('UTC');

        DB::transaction(function () use ($now): void {
            $branchId = $this->resolveBranchId();
            $staffId = (int) (DB::table('users')->where('username', 'staff1')->value('user_id') ?? 0);
            $customerId = (int) (DB::table('users')->where('username', 'customer1')->value('user_id') ?? 0);
            $reservationId = (int) (DB::table('reservations')->where('reservation_code', 'RSV-DEV-001')->value('reservation_id') ?? 0);
            $waitingListId = $this->ensureWaitingListEntry($branchId, $customerId, $now);

            if (Schema::hasTable('agent_assignments')) {
                DB::table('agent_assignments')
                    ->whereIn('conversation_id', array_values(self::CONVERSATION_IDS))
                    ->delete();
            }

            DB::table('conversations')
                ->whereIn('conversation_id', array_values(self::CONVERSATION_IDS))
                ->delete();

            $conversations = [
                [
                    'conversation_id' => self::CONVERSATION_IDS['reservation'],
                    'branch_id' => $branchId,
                    'user_id' => $customerId > 0 ? $customerId : null,
                    'customer_session_id' => 'dev-customer-session-001',
                    'session_id' => 'dev-session-001',
                    'channel' => 'WebChat',
                    'status' => 'Open',
                    'intent_detected' => 'reservation_follow_up',
                    'linked_reservation_id' => $reservationId > 0 ? $reservationId : null,
                    'linked_waiting_list_id' => null,
                    'created_at' => $now->copy()->subMinutes(35),
                    'closed_at' => null,
                ],
                [
                    'conversation_id' => self::CONVERSATION_IDS['waiting_list'],
                    'branch_id' => $branchId,
                    'user_id' => $customerId > 0 ? $customerId : null,
                    'customer_session_id' => 'dev-customer-session-002',
                    'session_id' => 'dev-session-002',
                    'channel' => 'Zalo',
                    'status' => 'Pending',
                    'intent_detected' => 'waiting_list_follow_up',
                    'linked_reservation_id' => null,
                    'linked_waiting_list_id' => $waitingListId > 0 ? $waitingListId : null,
                    'created_at' => $now->copy()->subMinutes(25),
                    'closed_at' => null,
                ],
                [
                    'conversation_id' => self::CONVERSATION_IDS['closed'],
                    'branch_id' => $branchId,
                    'user_id' => $customerId > 0 ? $customerId : null,
                    'customer_session_id' => 'dev-customer-session-003',
                    'session_id' => 'dev-session-003',
                    'channel' => 'Facebook',
                    'status' => 'Closed',
                    'intent_detected' => 'general_support',
                    'linked_reservation_id' => null,
                    'linked_waiting_list_id' => null,
                    'created_at' => $now->copy()->subHours(6),
                    'closed_at' => $now->copy()->subHours(5)->subMinutes(20),
                ],
            ];

            DB::table('conversations')->insert($conversations);

            $reservationInboundMessageId = (int) DB::table('conversation_messages')->insertGetId([
                'conversation_id' => self::CONVERSATION_IDS['reservation'],
                'sender' => 'user',
                'sender_id' => $customerId > 0 ? $customerId : null,
                'message_text' => 'Can you confirm my reservation and help update the arrival note?',
                'message_type' => 'text',
                'is_internal_note' => 0,
                'attachment_url' => null,
                'created_at' => $now->copy()->subMinutes(34),
                'is_processed' => 1,
                'processing_status' => 'processed',
                'confidence' => '0.9720',
                'related_reservation_id' => $reservationId > 0 ? $reservationId : null,
                'related_order_id' => null,
            ]);

            $reservationInternalNoteId = (int) DB::table('conversation_messages')->insertGetId([
                'conversation_id' => self::CONVERSATION_IDS['reservation'],
                'sender' => 'agent',
                'sender_id' => $staffId > 0 ? $staffId : null,
                'message_text' => 'Internal note: customer requested window-side preference and deposit follow-up.',
                'message_type' => 'text',
                'is_internal_note' => 1,
                'attachment_url' => null,
                'created_at' => $now->copy()->subMinutes(30),
                'is_processed' => 1,
                'processing_status' => 'reviewed',
                'confidence' => null,
                'related_reservation_id' => $reservationId > 0 ? $reservationId : null,
                'related_order_id' => null,
            ]);

            $waitingInboundMessageId = (int) DB::table('conversation_messages')->insertGetId([
                'conversation_id' => self::CONVERSATION_IDS['waiting_list'],
                'sender' => 'user',
                'sender_id' => $customerId > 0 ? $customerId : null,
                'message_text' => 'We are nearby. Can someone tell me whether the waiting list is moving soon?',
                'message_type' => 'text',
                'is_internal_note' => 0,
                'attachment_url' => 'https://example.com/dev/waiting-list-location.png',
                'created_at' => $now->copy()->subMinutes(23),
                'is_processed' => 0,
                'processing_status' => 'pending',
                'confidence' => '0.8810',
                'related_reservation_id' => null,
                'related_order_id' => null,
            ]);

            $closedConversationMessageId = (int) DB::table('conversation_messages')->insertGetId([
                'conversation_id' => self::CONVERSATION_IDS['closed'],
                'sender' => 'agent',
                'sender_id' => $staffId > 0 ? $staffId : null,
                'message_text' => 'Issue resolved and reservation reminder sent from the call center workflow.',
                'message_type' => 'text',
                'is_internal_note' => 0,
                'attachment_url' => null,
                'created_at' => $now->copy()->subHours(5)->subMinutes(30),
                'is_processed' => 1,
                'processing_status' => 'processed',
                'confidence' => null,
                'related_reservation_id' => null,
                'related_order_id' => null,
            ]);

            DB::table('conversation_files')->insert([
                'message_id' => $waitingInboundMessageId,
                'file_url' => 'https://example.com/dev/waiting-list-location.png',
                'mime_type' => 'image/png',
                'created_at' => $now->copy()->subMinutes(22),
            ]);

            DB::table('message_entities')->insert([
                [
                    'message_id' => $reservationInboundMessageId,
                    'entity_type' => 'reservation_code',
                    'entity_text' => 'RSV-DEV-001',
                    'entity_normalized' => 'RSV-DEV-001',
                    'extra_json' => json_encode(['source' => 'dev_seed'], JSON_THROW_ON_ERROR),
                    'created_at' => $now->copy()->subMinutes(33),
                ],
                [
                    'message_id' => $waitingInboundMessageId,
                    'entity_type' => 'waiting_status_question',
                    'entity_text' => 'waiting list moving soon',
                    'entity_normalized' => 'waiting_list_follow_up',
                    'extra_json' => json_encode(['source' => 'dev_seed'], JSON_THROW_ON_ERROR),
                    'created_at' => $now->copy()->subMinutes(22),
                ],
            ]);

            DB::table('conversation_events')->insert([
                [
                    'conversation_id' => self::CONVERSATION_IDS['reservation'],
                    'event_type' => 'conversation.linked',
                    'event_by_user_id' => $staffId > 0 ? $staffId : null,
                    'event_data' => json_encode([
                        'branch_id' => $branchId,
                        'linked_reservation_id' => $reservationId > 0 ? $reservationId : null,
                        'user_id' => $customerId > 0 ? $customerId : null,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now->copy()->subMinutes(32),
                ],
                [
                    'conversation_id' => self::CONVERSATION_IDS['reservation'],
                    'event_type' => 'assignment.changed',
                    'event_by_user_id' => $staffId > 0 ? $staffId : null,
                    'event_data' => json_encode([
                        'agent_user_id' => $staffId > 0 ? $staffId : null,
                        'mode' => 'self_assign',
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now->copy()->subMinutes(31),
                ],
                [
                    'conversation_id' => self::CONVERSATION_IDS['reservation'],
                    'event_type' => 'internal_note.added',
                    'event_by_user_id' => $staffId > 0 ? $staffId : null,
                    'event_data' => json_encode([
                        'message_id' => $reservationInternalNoteId,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now->copy()->subMinutes(30),
                ],
                [
                    'conversation_id' => self::CONVERSATION_IDS['waiting_list'],
                    'event_type' => 'conversation.linked',
                    'event_by_user_id' => $staffId > 0 ? $staffId : null,
                    'event_data' => json_encode([
                        'branch_id' => $branchId,
                        'linked_waiting_list_id' => $waitingListId > 0 ? $waitingListId : null,
                        'user_id' => $customerId > 0 ? $customerId : null,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now->copy()->subMinutes(24),
                ],
                [
                    'conversation_id' => self::CONVERSATION_IDS['closed'],
                    'event_type' => 'conversation.closed',
                    'event_by_user_id' => $staffId > 0 ? $staffId : null,
                    'event_data' => json_encode([
                        'resolution' => 'resolved_inbound_follow_up',
                        'message_id' => $closedConversationMessageId,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now->copy()->subHours(5)->subMinutes(20),
                ],
            ]);

            DB::table('conversation_analyses')->insert([
                [
                    'conversation_id' => self::CONVERSATION_IDS['reservation'],
                    'analyzer_name' => 'dev_contract_analyzer',
                    'is_spam' => 0,
                    'quality_score' => '0.9600',
                    'extracted_info' => json_encode([
                        'intent' => 'reservation_follow_up',
                        'linked_reservation_id' => $reservationId > 0 ? $reservationId : null,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now->copy()->subMinutes(29),
                ],
                [
                    'conversation_id' => self::CONVERSATION_IDS['waiting_list'],
                    'analyzer_name' => 'dev_contract_analyzer',
                    'is_spam' => 0,
                    'quality_score' => '0.8900',
                    'extracted_info' => json_encode([
                        'intent' => 'waiting_list_follow_up',
                        'linked_waiting_list_id' => $waitingListId > 0 ? $waitingListId : null,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now->copy()->subMinutes(21),
                ],
            ]);

            if ($staffId > 0 && Schema::hasTable('agent_assignments')) {
                DB::table('agent_assignments')->insert([
                    'conversation_id' => self::CONVERSATION_IDS['reservation'],
                    'agent_user_id' => $staffId,
                    'assigned_at' => $now->copy()->subMinutes(31),
                    'released_at' => null,
                    'is_active' => 1,
                    'notes' => 'Self-assigned from staff inbox demo seed.',
                ]);
            }

            if (Schema::hasTable('conversation_aggregates')) {
                DB::table('conversation_aggregates')->updateOrInsert(
                    [
                        'agg_date' => $now->toDateString(),
                        'hour' => (int) $now->format('G'),
                        'channel' => 'WebChat',
                    ],
                    [
                        'total_conversations' => 3,
                        'total_messages' => 4,
                        'total_spam' => 0,
                        'orders_extracted' => 0,
                        'top_items' => json_encode([['item' => 'Reservation follow-up', 'count' => 1]], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                    ]
                );
            }
        });
    }

    private function resolveBranchId(): int
    {
        $branchId = (int) (DB::table('branches')->where('is_default', 1)->value('branch_id') ?? 0);

        if ($branchId > 0) {
            return $branchId;
        }

        return (int) (DB::table('branches')->orderBy('branch_id')->value('branch_id') ?? 1);
    }

    private function ensureWaitingListEntry(int $branchId, int $customerId, Carbon $now): int
    {
        if (! Schema::hasTable('waiting_list')) {
            return 0;
        }

        DB::table('waiting_list')
            ->where('notes', 'Dev conversation waiting list')
            ->delete();

        return (int) DB::table('waiting_list')->insertGetId([
            'branch_id' => $branchId,
            'user_id' => $customerId > 0 ? $customerId : null,
            'customer_session_id' => 'dev-customer-session-002',
            'guest_name' => 'Conversation Waitlist Guest',
            'phone' => '0900000099',
            'guest_count' => 4,
            'requested_at' => $now->copy()->subMinutes(28),
            'status' => 'Notified',
            'priority' => 2,
            'notified_at' => $now->copy()->subMinutes(20),
            'notify_expires_at' => $now->copy()->addMinutes(12),
            'customer_response_status' => null,
            'customer_responded_at' => null,
            'customer_confirmed_arrival_at' => null,
            'notified_by' => null,
            'created_at' => $now->copy()->subMinutes(28),
            'updated_at' => $now->copy()->subMinutes(20),
            'seated_at' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'notes' => 'Dev conversation waiting list',
            'updated_by' => null,
            'row_version' => 1,
        ]);
    }
}
