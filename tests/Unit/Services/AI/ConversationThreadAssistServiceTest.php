<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI;

use App\Modules\Conversations\Domain\Models\Conversation;
use App\Modules\Conversations\Domain\Models\ConversationAnalysis;
use App\Modules\Conversations\Domain\Models\ConversationMessage;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\WaitingList\Domain\Models\WaitingList;
use App\Services\AI\ConversationThreadAssistService;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class ConversationThreadAssistServiceTest extends TestCase
{
    public function test_it_builds_a_ready_summary_with_actions_and_risk_flags(): void
    {
        $featureFlags = Mockery::mock(FeatureFlagService::class);
        $featureFlags->shouldReceive('resolve')
            ->once()
            ->with('staff.conversation_ai_assist', 5)
            ->andReturn([
                'enabled' => true,
                'message' => 'Feature flag is enabled by config default.',
            ]);

        $service = new ConversationThreadAssistService($featureFlags);
        $conversation = new Conversation([
            'conversation_id' => 'conv-ai-1',
            'branch_id' => 5,
            'status' => 'Pending',
            'channel' => 'WebChat',
            'intent_detected' => 'reservation_follow_up',
        ]);
        $conversation->setRelation('linkedReservation', new Reservation([
            'reservation_id' => 77,
            'reservation_code' => 'RES-77',
        ]));

        $messages = new Collection([
            new ConversationMessage([
                'conversation_id' => 'conv-ai-1',
                'sender' => 'user',
                'message_text' => 'Can you update my arrival note? We will be late by 15 minutes.',
                'message_type' => 'text',
                'is_internal_note' => false,
                'created_at' => Carbon::parse('2026-04-09T08:00:00Z'),
            ]),
            new ConversationMessage([
                'conversation_id' => 'conv-ai-1',
                'sender' => 'agent',
                'message_text' => 'We are checking your reservation.',
                'message_type' => 'text',
                'is_internal_note' => false,
                'created_at' => Carbon::parse('2026-04-09T08:02:00Z'),
            ]),
        ]);

        $analyses = new Collection([
            new ConversationAnalysis([
                'conversation_id' => 'conv-ai-1',
                'analyzer_name' => 'conversation_contract_test',
                'is_spam' => false,
                'quality_score' => '0.7100',
                'extracted_info' => [
                    'intent' => 'reservation_follow_up',
                ],
                'created_at' => Carbon::parse('2026-04-09T08:03:00Z'),
            ]),
        ]);

        $result = $service->buildForConversationDetail($conversation, $messages, $analyses);

        self::assertSame('ready', $result['status']);
        self::assertSame('staff.conversation_ai_assist', $result['feature_key']);
        self::assertSame('local_heuristic', $result['provider']);
        self::assertSame('conversation-summary-v1', $result['model']);
        self::assertSame('high', $result['priority']);
        self::assertStringContainsString('Reservation RES-77 needs follow-up.', (string) $result['summary']);
        self::assertStringContainsString('Latest thread signal: Can you update my arrival note? We will be late by 15 minutes', (string) $result['summary']);
        self::assertSame('review_reservation', $result['suggested_actions'][0]['code']);
        self::assertSame('update_arrival_note', $result['suggested_actions'][1]['code']);
        self::assertSame('time_sensitive', $result['risk_flags'][0]['code']);
        self::assertSame('low_confidence', $result['risk_flags'][1]['code']);
        self::assertSame(2, $result['generated_from']['message_count']);
        self::assertSame(1, $result['generated_from']['customer_message_count']);
        self::assertSame(1, $result['generated_from']['analysis_count']);
    }

    public function test_it_returns_a_disabled_fallback_without_blocking_the_thread(): void
    {
        $featureFlags = Mockery::mock(FeatureFlagService::class);
        $featureFlags->shouldReceive('resolve')
            ->once()
            ->with('staff.conversation_ai_assist', 9)
            ->andReturn([
                'enabled' => false,
                'message' => 'Conversation AI assist is disabled for this rollout. Use the canonical timeline instead.',
            ]);

        $service = new ConversationThreadAssistService($featureFlags);
        $conversation = new Conversation([
            'conversation_id' => 'conv-ai-off',
            'branch_id' => 9,
            'status' => 'Open',
            'channel' => 'WebChat',
        ]);
        $messages = new Collection([
            new ConversationMessage([
                'conversation_id' => 'conv-ai-off',
                'sender' => 'user',
                'message_text' => 'Need help please.',
                'message_type' => 'text',
                'is_internal_note' => false,
            ]),
        ]);

        $result = $service->buildForConversationDetail($conversation, $messages, new Collection);

        self::assertSame('disabled', $result['status']);
        self::assertSame('feature_disabled', $result['fallback_reason_code']);
        self::assertSame('Conversation AI assist is disabled for this rollout. Use the canonical timeline instead.', $result['fallback_reason']);
        self::assertNull($result['summary']);
        self::assertSame([], $result['suggested_actions']);
    }

    public function test_historical_waiting_list_threads_do_not_get_forced_high_priority(): void
    {
        $featureFlags = Mockery::mock(FeatureFlagService::class);
        $featureFlags->shouldReceive('resolve')
            ->once()
            ->with('staff.conversation_ai_assist', 12)
            ->andReturn([
                'enabled' => true,
                'message' => 'Feature flag is enabled by config default.',
            ]);

        $service = new ConversationThreadAssistService($featureFlags);
        $conversation = new Conversation([
            'conversation_id' => 'conv-ai-wl-history',
            'branch_id' => 12,
            'status' => 'Closed',
            'channel' => 'WebChat',
        ]);
        $conversation->setRelation('linkedWaitingList', new WaitingList([
            'waiting_list_id' => 144,
            'status' => 'Seated',
        ]));

        $messages = new Collection([
            new ConversationMessage([
                'conversation_id' => 'conv-ai-wl-history',
                'sender' => 'user',
                'message_text' => 'Thanks for confirming the table.',
                'message_type' => 'text',
                'is_internal_note' => false,
                'created_at' => Carbon::parse('2026-04-09T09:00:00Z'),
            ]),
        ]);

        $result = $service->buildForConversationDetail($conversation, $messages, new Collection);

        self::assertSame('ready', $result['status']);
        self::assertSame('normal', $result['priority']);
        self::assertSame('review_waiting_list', $result['suggested_actions'][0]['code']);
        self::assertSame([], $result['risk_flags']);
        self::assertStringContainsString('Waiting-list-linked thread needs review.', (string) $result['summary']);
    }
}
