<?php

declare(strict_types=1);

namespace Tests\Feature\DataLifecycle;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class DataLifecycleRetentionConsoleTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        Carbon::setTestNow(Carbon::parse('2026-04-05 12:00:00', 'UTC'));
    }

    public function test_retention_command_prunes_only_eligible_artifacts(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $old = $this->nowUtc()->copy()->subDays(120);
        $oldCreatedAt = $old->copy()->subDays(2);
        $recent = $this->nowUtc()->copy()->subDays(2);

        DB::table('customer_access_sessions')->insert([
            'user_id' => $customerId,
            'session_id' => 'old-retention-session',
            'guest_name' => 'Old Session',
            'phone' => '0909000111',
            'token_hash' => hash('sha256', 'retention-old'),
            'token_last_eight' => 'entionld',
            'session_meta_json' => null,
            'expires_at' => $old->copy()->subDay(),
            'last_used_at' => null,
            'revoked_at' => $old,
            'created_ip' => null,
            'user_agent' => 'PHPUnit',
            'row_version' => 1,
            'created_at' => $oldCreatedAt,
            'updated_at' => $old,
        ]);

        DB::table('user_auth_tokens')->insert([
            'user_id' => $customerId,
            'purpose' => 'PasswordReset',
            'channel' => 'Email',
            'recipient' => 'retention@example.test',
            'token_hash' => hash('sha256', 'retention-token'),
            'otp_hash' => hash('sha256', '654321'),
            'attempt_count' => 0,
            'max_attempts' => 5,
            'expires_at' => $old,
            'used_at' => $old,
            'created_ip' => null,
            'user_agent' => 'PHPUnit',
            'created_at' => $oldCreatedAt,
        ]);

        $outboxId = (int) DB::table('notification_outbox')->insertGetId([
            'channel' => 'Email',
            'recipient' => 'retention@example.test',
            'recipient_user_id' => $customerId,
            'template_key' => 'reservation.reminder',
            'idempotency_key' => 'retention-notif-old',
            'dedupe_key' => 'retention-notif-old',
            'payload_json' => json_encode(['email' => 'retention@example.test'], JSON_THROW_ON_ERROR),
            'status' => 'Sent',
            'attempt_count' => 1,
            'created_at' => $old,
            'sent_at' => $old,
        ]);

        DB::table('notification_delivery_attempts')->insert([
            'outbox_id' => $outboxId,
            'channel' => 'Email',
            'provider_key' => 'smtp',
            'attempt_number' => 1,
            'status' => 'Succeeded',
            'recipient' => 'retention@example.test',
            'request_payload_json' => json_encode(['email' => 'retention@example.test'], JSON_THROW_ON_ERROR),
            'response_payload_json' => json_encode(['status' => 'sent'], JSON_THROW_ON_ERROR),
            'attempted_at' => $old,
            'completed_at' => $old,
            'created_at' => $old,
        ]);

        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'status' => 'Closed',
            'closed_at' => $old,
            'created_at' => $old,
        ]);
        $messageId = $this->createConversationMessage([
            'conversation_id' => $conversationId,
            'created_at' => $old,
        ]);
        $this->createConversationAnalysis([
            'conversation_id' => $conversationId,
            'created_at' => $old,
        ]);
        $this->createMessageEntity([
            'message_id' => $messageId,
            'created_at' => $old,
        ]);

        $recentConversationId = $this->createConversation([
            'user_id' => $customerId,
            'status' => 'Closed',
            'closed_at' => $recent,
            'created_at' => $recent,
        ]);
        $recentMessageId = $this->createConversationMessage([
            'conversation_id' => $recentConversationId,
            'created_at' => $recent,
        ]);
        $this->createConversationAnalysis([
            'conversation_id' => $recentConversationId,
            'created_at' => $recent,
        ]);
        $this->createMessageEntity([
            'message_id' => $recentMessageId,
            'created_at' => $recent,
        ]);

        $this->artisan('data-lifecycle:enforce-retention --dry-run')->assertExitCode(0);

        self::assertSame(1, DB::table('customer_access_sessions')->where('session_id', 'old-retention-session')->count());
        self::assertSame(1, DB::table('notification_outbox')->where('outbox_id', $outboxId)->count());
        self::assertSame(1, DB::table('conversation_analyses')->where('conversation_id', $conversationId)->count());

        $this->artisan('data-lifecycle:enforce-retention')->assertExitCode(0);

        self::assertSame(0, DB::table('customer_access_sessions')->where('session_id', 'old-retention-session')->count());
        self::assertSame(0, DB::table('user_auth_tokens')->where('recipient', 'retention@example.test')->count());
        self::assertSame(0, DB::table('notification_outbox')->where('outbox_id', $outboxId)->count());
        self::assertSame(0, DB::table('notification_delivery_attempts')->where('outbox_id', $outboxId)->count());
        self::assertSame(0, DB::table('conversation_analyses')->where('conversation_id', $conversationId)->count());
        self::assertSame(0, DB::table('message_entities')->where('message_id', $messageId)->count());

        self::assertSame(1, DB::table('conversation_analyses')->where('conversation_id', $recentConversationId)->count());
        self::assertSame(1, DB::table('message_entities')->where('message_id', $recentMessageId)->count());

        $this->assertAuditLogRecorded('data_retention.enforced', 'retention_policy', 'default');
    }

    public function test_retention_command_keeps_non_terminal_attempts_and_recent_entities_on_old_messages(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $old = $this->nowUtc()->copy()->subDays(120);
        $recent = $this->nowUtc()->copy()->subDays(2);

        $pendingOutboxId = (int) DB::table('notification_outbox')->insertGetId([
            'channel' => 'Email',
            'recipient' => 'pending-retention@example.test',
            'recipient_user_id' => $customerId,
            'template_key' => 'reservation.reminder',
            'idempotency_key' => 'retention-notif-pending',
            'dedupe_key' => 'retention-notif-pending',
            'payload_json' => json_encode(['email' => 'pending-retention@example.test'], JSON_THROW_ON_ERROR),
            'status' => 'Pending',
            'attempt_count' => 1,
            'created_at' => $old,
            'next_retry_at' => $recent,
        ]);

        $pendingAttemptId = (int) DB::table('notification_delivery_attempts')->insertGetId([
            'outbox_id' => $pendingOutboxId,
            'channel' => 'Email',
            'provider_key' => 'smtp',
            'attempt_number' => 1,
            'status' => 'Failed',
            'recipient' => 'pending-retention@example.test',
            'error_message' => 'temporary_provider_failure',
            'request_payload_json' => json_encode(['email' => 'pending-retention@example.test'], JSON_THROW_ON_ERROR),
            'response_payload_json' => json_encode(['status' => 'retry'], JSON_THROW_ON_ERROR),
            'attempted_at' => $old,
            'completed_at' => $old,
            'created_at' => $old,
        ]);

        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'status' => 'Closed',
            'closed_at' => $old,
            'created_at' => $old,
        ]);
        $messageId = $this->createConversationMessage([
            'conversation_id' => $conversationId,
            'created_at' => $old,
        ]);
        $oldEntityId = $this->createMessageEntity([
            'message_id' => $messageId,
            'created_at' => $old,
        ]);
        $recentEntityId = $this->createMessageEntity([
            'message_id' => $messageId,
            'entity_text' => 'RSV-RECENT-ENTITY',
            'entity_normalized' => 'RSV-RECENT-ENTITY',
            'created_at' => $recent,
        ]);

        $this->artisan('data-lifecycle:enforce-retention')->assertExitCode(0);

        self::assertSame(1, DB::table('notification_outbox')->where('outbox_id', $pendingOutboxId)->count());
        self::assertSame(1, DB::table('notification_delivery_attempts')->where('attempt_id', $pendingAttemptId)->count());
        self::assertSame(0, DB::table('message_entities')->where('message_entity_id', $oldEntityId)->count());
        self::assertSame(1, DB::table('message_entities')->where('message_entity_id', $recentEntityId)->count());
    }

    public function test_retention_command_scrubs_old_payment_webhook_receipt_payload_artifacts_but_keeps_row(): void
    {
        $old = $this->nowUtc()->copy()->subDays(500);

        $receiptId = (int) DB::table('payment_provider_webhook_receipts')->insertGetId([
            'provider_code' => 'simulated',
            'provider_event_code' => 'evt-retention-old-1',
            'provider_session_code' => 'sim-retention-old-1',
            'payment_scope' => 'deposit',
            'event_type' => 'payment.session.updated',
            'delivery_status' => 'Applied',
            'request_signature' => 'raw-signature',
            'request_headers_json' => json_encode(['x-payment-signature' => 'raw-signature'], JSON_THROW_ON_ERROR),
            'request_body' => json_encode(['provider_event_code' => 'evt-retention-old-1', 'secret' => 'provider-secret'], JSON_THROW_ON_ERROR),
            'provider_payload_json' => json_encode(['raw' => ['secret' => 'provider-secret']], JSON_THROW_ON_ERROR),
            'processed_at' => $old,
            'failure_message' => null,
            'created_at' => $old,
            'updated_at' => $old,
            'created_by' => null,
            'updated_by' => null,
            'row_version' => 2,
        ], 'payment_provider_webhook_receipt_id');

        $this->artisan('data-lifecycle:enforce-retention --dry-run')->assertExitCode(0);

        self::assertSame('raw-signature', (string) DB::table('payment_provider_webhook_receipts')
            ->where('payment_provider_webhook_receipt_id', $receiptId)
            ->value('request_signature'));

        $this->artisan('data-lifecycle:enforce-retention')->assertExitCode(0);

        $row = DB::table('payment_provider_webhook_receipts')
            ->where('payment_provider_webhook_receipt_id', $receiptId)
            ->first();

        self::assertNotNull($row);
        self::assertNull($row->request_signature);
        self::assertNull($row->request_headers_json);
        self::assertNull($row->request_body);
        self::assertSame(true, data_get(json_decode((string) $row->provider_payload_json, true, 512, JSON_THROW_ON_ERROR), '_retention.verbose_payload_scrubbed'));
        self::assertSame(3, (int) $row->row_version);
    }
}
