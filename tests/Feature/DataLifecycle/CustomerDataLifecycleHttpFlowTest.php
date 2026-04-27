<?php

declare(strict_types=1);

namespace Tests\Feature\DataLifecycle;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class CustomerDataLifecycleHttpFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        Carbon::setTestNow(Carbon::parse('2026-04-05 10:00:00', 'UTC'));
    }

    public function test_customer_can_export_own_data_and_create_privacy_request(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer', 'full_name' => 'Privacy Guest', 'email' => 'privacy@example.test', 'phone' => '0909111222']);
        $sessionId = 'sess-privacy-export';
        $headers = $this->customerAuthHeaders($customerId, $sessionId, [
            'session_id' => $sessionId,
            'guest_name' => 'Privacy Guest',
            'phone' => '0909111222',
        ]);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'checked_out_at' => $this->nowUtc(),
            'notes' => 'Window seat please',
        ]);
        $paymentId = $this->createPayment([
            'reservation_id' => $reservationId,
            'amount' => '450000.00',
            'status' => 'Success',
            'payment_type' => 'Final',
            'transaction_code' => 'PAY-PRIV-001',
            'idempotency_key' => 'payment-export-idem-001',
            'provider_response_json' => ['provider_reference' => 'secret-ref-001'],
        ]);
        self::assertGreaterThan(0, $paymentId);

        $this->createWaitingListEntry([
            'user_id' => $customerId,
            'customer_session_id' => $sessionId,
            'status' => 'Cancelled',
            'cancelled_at' => $this->nowUtc(),
            'guest_name' => 'Privacy Guest',
            'phone' => '0909111222',
        ]);

        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'session_id' => $sessionId,
            'status' => 'Closed',
            'closed_at' => $this->nowUtc(),
        ]);
        $messageId = $this->createConversationMessage([
            'conversation_id' => $conversationId,
            'message_text' => 'Need invoice support',
        ]);
        $this->createConversationFile(['message_id' => $messageId]);
        $this->createMessageEntity(['message_id' => $messageId, 'entity_text' => 'privacy@example.test']);

        $outboxId = (int) DB::table('notification_outbox')->insertGetId([
            'channel' => 'Email',
            'recipient' => 'privacy@example.test',
            'recipient_user_id' => $customerId,
            'template_key' => 'reservation.updated',
            'idempotency_key' => 'notif-privacy-export',
            'dedupe_key' => 'notif-privacy-export',
            'payload_json' => json_encode(['email' => 'privacy@example.test'], JSON_THROW_ON_ERROR),
            'status' => 'Sent',
            'processing_token' => 'processing-token-export',
            'locked_until' => $this->nowUtc()->copy()->addMinutes(5),
            'locked_by' => 'worker-1',
            'attempt_count' => 1,
            'last_attempted_at' => $this->nowUtc(),
            'last_error' => 'transient-provider-error',
            'created_at' => $this->nowUtc(),
            'sent_at' => $this->nowUtc(),
        ]);

        DB::table('notification_delivery_attempts')->insert([
            'outbox_id' => $outboxId,
            'channel' => 'Email',
            'provider_key' => 'smtp',
            'attempt_number' => 1,
            'status' => 'Succeeded',
            'recipient' => 'privacy@example.test',
            'provider_message_id' => 'msg-privacy-export-1',
            'provider_status' => 'delivered',
            'error_code' => null,
            'error_message' => 'provider internal note',
            'request_payload_json' => json_encode(['email' => 'privacy@example.test'], JSON_THROW_ON_ERROR),
            'response_payload_json' => json_encode(['status' => 'queued'], JSON_THROW_ON_ERROR),
            'attempted_at' => $this->nowUtc(),
            'completed_at' => $this->nowUtc(),
            'created_at' => $this->nowUtc(),
        ]);

        DB::table('bank_accounts')->insert([
            'user_id' => $customerId,
            'bank_account_number' => '123456789',
            'bank_name' => 'ACB',
            'account_holder_name' => 'Privacy Guest',
            'is_default' => 1,
            'created_at' => $this->nowUtc(),
        ]);

        DB::table('user_auth_tokens')->insert([
            'user_id' => $customerId,
            'purpose' => 'PasswordReset',
            'channel' => 'Email',
            'recipient' => 'privacy@example.test',
            'token_hash' => hash('sha256', 'privacy-export-token'),
            'otp_hash' => hash('sha256', '123456'),
            'attempt_count' => 1,
            'max_attempts' => 5,
            'expires_at' => $this->nowUtc()->copy()->addHour(),
            'used_at' => null,
            'created_ip' => null,
            'user_agent' => 'PHPUnit',
            'created_at' => $this->nowUtc(),
        ]);

        DB::table('reservation_deposit_payment_sessions')->insert([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'linked_payment_id' => $paymentId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'dep-export-session-001',
            'provider_payment_code' => 'dep-export-payment-001',
            'payment_method' => 'Card',
            'amount' => '200000.00',
            'currency' => 'VND',
            'session_status' => 'Confirmed',
            'settlement_status' => 'Applied',
            'provider_payload_json' => json_encode(['checkout_url' => 'https://provider.test/secret'], JSON_THROW_ON_ERROR),
            'idempotency_key' => 'dep-export-idem-001',
            'provider_expires_at' => $this->nowUtc()->copy()->addMinutes(30),
            'confirmed_at' => $this->nowUtc(),
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        DB::table('reservation_bill_payment_sessions')->insert([
            'reservation_id' => $reservationId,
            'order_id' => null,
            'customer_user_id' => $customerId,
            'linked_payment_id' => $paymentId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'bill-export-session-001',
            'provider_payment_code' => 'bill-export-payment-001',
            'payment_method' => 'Card',
            'amount' => '250000.00',
            'currency' => 'VND',
            'session_status' => 'Created',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => json_encode(['checkout_url' => 'https://provider.test/bill-secret'], JSON_THROW_ON_ERROR),
            'idempotency_key' => 'bill-export-idem-001',
            'provider_expires_at' => $this->nowUtc()->copy()->addMinutes(30),
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        $export = $this->withHeaders($headers)->getJson('/api/v1/me/data-export');

        $export->assertOk()
            ->assertJsonPath('meta.action', 'customer_data_export')
            ->assertJsonPath('data.customer.user.user_id', $customerId)
            ->assertJsonPath('data.summary.reservation_count', 1)
            ->assertJsonCount(1, 'data.tables.reservations')
            ->assertJsonCount(1, 'data.tables.waiting_list')
            ->assertJsonCount(1, 'data.tables.conversations')
            ->assertJsonCount(1, 'data.tables.notification_outbox');

        $exportedData = $export->json('data');
        self::assertSame('PAY-PRIV-001', data_get($exportedData, 'tables.payments.0.transaction_code'));
        self::assertSame('Confirmed', data_get($exportedData, 'tables.reservation_deposit_payment_sessions.0.session_status'));
        self::assertSame('Need invoice support', data_get($exportedData, 'tables.conversation_messages.0.message_text'));
        self::assertSame('ACB', data_get($exportedData, 'tables.bank_accounts.0.bank_name'));
        $this->assertSanitizedCustomerExport($exportedData);

        $this->assertAuditLogRecorded('customer_data.exported', 'user', $customerId);

        $request = $this->withHeaders($this->withIdempotencyKey($headers, 'privacy-request-create'))
            ->postJson('/api/v1/me/privacy-requests', [
                'request_type' => 'anonymize',
                'reason' => 'Close my account and redact personal data.',
            ]);

        $request->assertCreated()
            ->assertJsonPath('meta.action', 'customer_privacy_request_created')
            ->assertJsonPath('data.request.user_id', $customerId)
            ->assertJsonPath('data.request.status', 'Requested');

        $requestId = (int) $request->json('data.request.customer_privacy_request_id');
        $this->assertAuditLogRecorded('customer_privacy_request.created', 'customer_privacy_request', $requestId);
    }

    public function test_admin_customer_export_uses_the_same_sanitized_shape(): void
    {
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Admin Export Customer',
            'email' => 'admin-export@example.test',
            'phone' => '0909555666',
        ]);
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $adminHeaders = $this->staffAuthHeaders($adminId, 'privacy-admin-export');

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'checked_out_at' => $this->nowUtc(),
            'notes' => 'Admin export reservation note',
        ]);
        $paymentId = $this->createPayment([
            'reservation_id' => $reservationId,
            'branch_id' => 1,
            'amount' => '320000.00',
            'status' => 'Success',
            'payment_type' => 'Final',
            'transaction_code' => 'PAY-PRIV-ADMIN-001',
            'idempotency_key' => 'payment-admin-export-idem-001',
            'provider_response_json' => ['provider_reference' => 'admin-secret-ref-001'],
        ]);

        DB::table('user_auth_tokens')->insert([
            'user_id' => $customerId,
            'purpose' => 'VerifyEmail',
            'channel' => 'Email',
            'recipient' => 'admin-export@example.test',
            'token_hash' => hash('sha256', 'admin-export-token'),
            'otp_hash' => hash('sha256', '654321'),
            'attempt_count' => 0,
            'max_attempts' => 5,
            'expires_at' => $this->nowUtc()->copy()->addHour(),
            'created_ip' => null,
            'user_agent' => 'PHPUnit',
            'created_at' => $this->nowUtc(),
        ]);

        $outboxId = (int) DB::table('notification_outbox')->insertGetId([
            'channel' => 'Email',
            'recipient' => 'admin-export@example.test',
            'recipient_user_id' => $customerId,
            'template_key' => 'reservation.receipt',
            'idempotency_key' => 'notif-admin-export',
            'dedupe_key' => 'notif-admin-export',
            'payload_json' => json_encode(['email' => 'admin-export@example.test'], JSON_THROW_ON_ERROR),
            'status' => 'Sent',
            'processing_token' => 'processing-token-admin-export',
            'attempt_count' => 1,
            'created_at' => $this->nowUtc(),
            'sent_at' => $this->nowUtc(),
        ]);

        DB::table('notification_delivery_attempts')->insert([
            'outbox_id' => $outboxId,
            'channel' => 'Email',
            'provider_key' => 'smtp',
            'attempt_number' => 1,
            'status' => 'Succeeded',
            'recipient' => 'admin-export@example.test',
            'provider_message_id' => 'msg-admin-export-1',
            'provider_status' => 'delivered',
            'error_code' => null,
            'error_message' => 'provider secret error',
            'request_payload_json' => json_encode(['email' => 'admin-export@example.test'], JSON_THROW_ON_ERROR),
            'response_payload_json' => json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR),
            'attempted_at' => $this->nowUtc(),
            'completed_at' => $this->nowUtc(),
            'created_at' => $this->nowUtc(),
        ]);

        DB::table('reservation_deposit_payment_sessions')->insert([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'linked_payment_id' => $paymentId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'dep-admin-export-session-001',
            'provider_payment_code' => 'dep-admin-export-payment-001',
            'payment_method' => 'Card',
            'amount' => '320000.00',
            'currency' => 'VND',
            'session_status' => 'Confirmed',
            'settlement_status' => 'Applied',
            'provider_payload_json' => json_encode(['checkout_url' => 'https://provider.test/admin-secret'], JSON_THROW_ON_ERROR),
            'idempotency_key' => 'dep-admin-export-idem-001',
            'confirmed_at' => $this->nowUtc(),
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        $export = $this->withHeaders($adminHeaders)->getJson("/api/v1/admin/privacy/customers/{$customerId}/data-export");

        $export->assertOk()
            ->assertJsonPath('meta.action', 'admin_customer_data_export')
            ->assertJsonPath('data.customer.user.user_id', $customerId)
            ->assertJsonPath('data.tables.reservations.0.reservation_id', $reservationId)
            ->assertJsonPath('data.tables.payments.0.transaction_code', 'PAY-PRIV-ADMIN-001');

        $this->assertSanitizedCustomerExport($export->json('data'));
    }

    public function test_admin_dry_run_review_reports_blockers_for_active_customer_state(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $sessionId = 'sess-privacy-blocker';
        $customerHeaders = $this->customerAuthHeaders($customerId, $sessionId, ['session_id' => $sessionId]);
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $adminHeaders = $this->staffAuthHeaders($adminId, 'privacy-admin-dry-run');

        $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'notes' => 'Still upcoming',
        ]);

        $createRequest = $this->withHeaders($this->withIdempotencyKey($customerHeaders, 'privacy-request-blocker'))
            ->postJson('/api/v1/me/privacy-requests', [
                'request_type' => 'anonymize',
            ]);

        $requestId = (int) $createRequest->json('data.request.customer_privacy_request_id');

        $review = $this->withHeaders($this->withIdempotencyKey($adminHeaders, 'privacy-review-dry-run'))
            ->postJson("/api/v1/admin/privacy/requests/{$requestId}/review", [
                'decision' => 'approve',
                'mode' => 'dry_run',
            ]);

        $review->assertOk()
            ->assertJsonPath('meta.action', 'admin_customer_privacy_request_review')
            ->assertJsonPath('data.mode', 'dry_run')
            ->assertJsonPath('data.can_commit', false)
            ->assertJsonPath('data.preview.blockers.0.code', 'active_reservations');
    }

    public function test_admin_commit_anonymization_redacts_identity_and_keeps_financial_history(): void
    {
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Delete Me',
            'email' => 'deleteme@example.test',
            'phone' => '0909777666',
        ]);
        $sessionId = 'sess-privacy-commit';
        $customerHeaders = $this->customerAuthHeaders($customerId, $sessionId, [
            'session_id' => $sessionId,
            'guest_name' => 'Delete Me',
            'phone' => '0909777666',
        ]);
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $adminHeaders = $this->staffAuthHeaders($adminId, 'privacy-admin-commit');

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'checked_out_at' => $this->nowUtc(),
            'notes' => 'Contains private note',
            'final_bill_amount' => '650000.00',
            'billed_at' => $this->nowUtc(),
        ]);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'amount' => '650000.00',
            'status' => 'Success',
            'payment_type' => 'Final',
            'transaction_code' => 'PAY-PRIV-002',
        ]);

        $waitingId = $this->createWaitingListEntry([
            'user_id' => $customerId,
            'customer_session_id' => $sessionId,
            'status' => 'Cancelled',
            'cancelled_at' => $this->nowUtc(),
            'guest_name' => 'Delete Me',
            'phone' => '0909777666',
            'notes' => 'Private waiting note',
        ]);
        self::assertGreaterThan(0, $waitingId);

        $conversationId = $this->createConversation([
            'user_id' => $customerId,
            'session_id' => $sessionId,
            'customer_session_id' => $sessionId,
            'status' => 'Closed',
            'closed_at' => $this->nowUtc(),
        ]);
        $messageId = $this->createConversationMessage([
            'conversation_id' => $conversationId,
            'message_text' => 'My personal message',
        ]);
        $this->createConversationFile(['message_id' => $messageId, 'file_url' => 'https://example.test/private.jpg']);
        $this->createConversationEvent(['conversation_id' => $conversationId, 'event_data' => ['phone' => '0909777666']]);
        $this->createConversationAnalysis(['conversation_id' => $conversationId, 'extracted_info' => ['email' => 'deleteme@example.test']]);
        $this->createMessageEntity(['message_id' => $messageId, 'entity_text' => 'deleteme@example.test']);

        $outboxId = (int) DB::table('notification_outbox')->insertGetId([
            'channel' => 'Email',
            'recipient' => 'deleteme@example.test',
            'recipient_user_id' => $customerId,
            'template_key' => 'checkout.completed',
            'idempotency_key' => 'notif-privacy-commit',
            'dedupe_key' => 'notif-privacy-commit',
            'payload_json' => json_encode(['email' => 'deleteme@example.test'], JSON_THROW_ON_ERROR),
            'status' => 'Sent',
            'attempt_count' => 1,
            'created_at' => $this->nowUtc(),
            'sent_at' => $this->nowUtc(),
        ]);

        DB::table('notification_delivery_attempts')->insert([
            'outbox_id' => $outboxId,
            'channel' => 'Email',
            'provider_key' => 'smtp',
            'attempt_number' => 1,
            'status' => 'Succeeded',
            'recipient' => 'deleteme@example.test',
            'request_payload_json' => json_encode(['email' => 'deleteme@example.test'], JSON_THROW_ON_ERROR),
            'response_payload_json' => json_encode(['status' => 'sent'], JSON_THROW_ON_ERROR),
            'attempted_at' => $this->nowUtc(),
            'completed_at' => $this->nowUtc(),
            'created_at' => $this->nowUtc(),
        ]);

        DB::table('bank_accounts')->insert([
            'user_id' => $customerId,
            'bank_account_number' => '9988776655',
            'bank_name' => 'VCB',
            'account_holder_name' => 'Delete Me',
            'is_default' => 1,
            'created_at' => $this->nowUtc(),
        ]);

        DB::table('user_auth_tokens')->insert([
            'user_id' => $customerId,
            'purpose' => 'PasswordReset',
            'channel' => 'Email',
            'recipient' => 'deleteme@example.test',
            'token_hash' => hash('sha256', 'privacy-token'),
            'otp_hash' => hash('sha256', '123456'),
            'attempt_count' => 0,
            'max_attempts' => 5,
            'expires_at' => $this->nowUtc()->addDay(),
            'used_at' => null,
            'created_ip' => null,
            'user_agent' => 'PHPUnit',
            'created_at' => $this->nowUtc(),
        ]);

        DB::table('notification_preferences')->insert([
            'user_id' => $customerId,
            'channel' => 'Email',
            'is_enabled' => 1,
            'quiet_hours_start_minute' => null,
            'quiet_hours_end_minute' => null,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        $request = $this->withHeaders($this->withIdempotencyKey($customerHeaders, 'privacy-request-commit'))
            ->postJson('/api/v1/me/privacy-requests', ['request_type' => 'anonymize']);
        $requestId = (int) $request->json('data.request.customer_privacy_request_id');

        $review = $this->withHeaders($this->withIdempotencyKey($adminHeaders, 'privacy-review-commit'))
            ->postJson("/api/v1/admin/privacy/requests/{$requestId}/review", [
                'decision' => 'approve',
                'mode' => 'commit',
                'notes' => 'Approved after ops check.',
            ]);

        $review->assertOk()
            ->assertJsonPath('data.request.status', 'Completed')
            ->assertJsonPath('data.summary.updated.users', 1);

        $userRow = DB::table('users')->where('user_id', $customerId)->first();
        self::assertNotNull($userRow);
        self::assertStringStartsWith('Deleted Customer #', (string) $userRow->full_name);
        self::assertSame(1, (int) $userRow->is_deleted);
        self::assertNull($userRow->email);
        self::assertNull($userRow->phone);
        self::assertNotNull($userRow->privacy_anonymized_at);

        self::assertSame($customerId, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('user_id'));
        self::assertNull(DB::table('reservations')->where('reservation_id', $reservationId)->value('notes'));
        self::assertSame('650000.00', number_format((float) DB::table('payments')->where('reservation_id', $reservationId)->value('amount'), 2, '.', ''));

        self::assertStringStartsWith('Deleted Customer #', (string) DB::table('waiting_list')->where('waiting_id', $waitingId)->value('guest_name'));
        self::assertNull(DB::table('waiting_list')->where('waiting_id', $waitingId)->value('phone'));

        self::assertSame('[redacted after privacy anonymization]', (string) DB::table('conversation_messages')->where('message_id', $messageId)->value('message_text'));
        self::assertSame('redacted://privacy/file', (string) DB::table('conversation_files')->where('message_id', $messageId)->value('file_url'));
        self::assertSame('[redacted after privacy anonymization]', (string) DB::table('message_entities')->where('message_id', $messageId)->value('entity_text'));

        $eventData = json_decode((string) DB::table('conversation_events')->where('conversation_id', $conversationId)->value('event_data'), true);
        self::assertTrue((bool) ($eventData['redacted'] ?? false));
        $analysisData = json_decode((string) DB::table('conversation_analyses')->where('conversation_id', $conversationId)->value('extracted_info'), true);
        self::assertTrue((bool) ($analysisData['redacted'] ?? false));

        $outboxPayload = json_decode((string) DB::table('notification_outbox')->where('outbox_id', $outboxId)->value('payload_json'), true);
        self::assertSame('redacted://privacy/recipient', (string) DB::table('notification_outbox')->where('outbox_id', $outboxId)->value('recipient'));
        self::assertTrue((bool) ($outboxPayload['redacted'] ?? false));
        self::assertSame('redacted://privacy/recipient', (string) DB::table('notification_delivery_attempts')->where('outbox_id', $outboxId)->value('recipient'));

        self::assertSame(0, DB::table('customer_access_sessions')->where('user_id', $customerId)->count());
        self::assertSame(0, DB::table('bank_accounts')->where('user_id', $customerId)->count());
        self::assertSame(0, DB::table('user_auth_tokens')->where('user_id', $customerId)->count());
        self::assertSame(0, DB::table('notification_preferences')->where('user_id', $customerId)->count());

        $this->assertAuditLogRecorded('customer_privacy_request.completed', 'customer_privacy_request', $requestId);
    }

    /**
     * @param  array<string,mixed>  $exportedData
     */
    private function assertSanitizedCustomerExport(array $exportedData): void
    {
        self::assertArrayNotHasKey('password_hash', (array) data_get($exportedData, 'customer.user', []));
        self::assertArrayNotHasKey('bank_account_number', (array) data_get($exportedData, 'tables.bank_accounts.0', []));
        self::assertArrayNotHasKey('session_id', (array) data_get($exportedData, 'tables.customer_access_sessions.0', []));
        self::assertArrayNotHasKey('token_hash', (array) data_get($exportedData, 'tables.customer_access_sessions.0', []));
        self::assertArrayNotHasKey('token_last_eight', (array) data_get($exportedData, 'tables.customer_access_sessions.0', []));
        self::assertArrayNotHasKey('session_meta_json', (array) data_get($exportedData, 'tables.customer_access_sessions.0', []));
        self::assertArrayNotHasKey('token_hash', (array) data_get($exportedData, 'tables.user_auth_tokens.0', []));
        self::assertArrayNotHasKey('otp_hash', (array) data_get($exportedData, 'tables.user_auth_tokens.0', []));
        self::assertArrayNotHasKey('created_ip', (array) data_get($exportedData, 'tables.user_auth_tokens.0', []));
        self::assertArrayNotHasKey('user_agent', (array) data_get($exportedData, 'tables.user_auth_tokens.0', []));
        self::assertArrayNotHasKey('provider_response_json', (array) data_get($exportedData, 'tables.payments.0', []));
        self::assertArrayNotHasKey('idempotency_key', (array) data_get($exportedData, 'tables.payments.0', []));
        self::assertArrayNotHasKey('provider_payload_json', (array) data_get($exportedData, 'tables.reservation_deposit_payment_sessions.0', []));
        self::assertArrayNotHasKey('idempotency_key', (array) data_get($exportedData, 'tables.reservation_deposit_payment_sessions.0', []));
        self::assertArrayNotHasKey('provider_payload_json', (array) data_get($exportedData, 'tables.reservation_bill_payment_sessions.0', []));
        self::assertArrayNotHasKey('idempotency_key', (array) data_get($exportedData, 'tables.reservation_bill_payment_sessions.0', []));
        self::assertArrayNotHasKey('payload_json', (array) data_get($exportedData, 'tables.notification_outbox.0', []));
        self::assertArrayNotHasKey('processing_token', (array) data_get($exportedData, 'tables.notification_outbox.0', []));
        self::assertArrayNotHasKey('locked_until', (array) data_get($exportedData, 'tables.notification_outbox.0', []));
        self::assertArrayNotHasKey('locked_by', (array) data_get($exportedData, 'tables.notification_outbox.0', []));
        self::assertArrayNotHasKey('last_error', (array) data_get($exportedData, 'tables.notification_outbox.0', []));
        self::assertArrayNotHasKey('request_payload_json', (array) data_get($exportedData, 'tables.notification_delivery_attempts.0', []));
        self::assertArrayNotHasKey('response_payload_json', (array) data_get($exportedData, 'tables.notification_delivery_attempts.0', []));
        self::assertArrayNotHasKey('error_message', (array) data_get($exportedData, 'tables.notification_delivery_attempts.0', []));
        self::assertArrayNotHasKey('session_id', (array) data_get($exportedData, 'tables.conversations.0', []));
        self::assertArrayNotHasKey('customer_session_id', (array) data_get($exportedData, 'tables.conversations.0', []));
        self::assertArrayNotHasKey('event_data', (array) data_get($exportedData, 'tables.conversation_events.0', []));
        self::assertArrayNotHasKey('extracted_info', (array) data_get($exportedData, 'tables.conversation_analyses.0', []));
    }
}
