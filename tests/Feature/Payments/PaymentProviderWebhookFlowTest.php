<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Modules\CheckoutPayments\Domain\Models\ReservationBillPaymentSession;
use App\Modules\CheckoutPayments\Domain\Models\ReservationDepositPaymentSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class PaymentProviderWebhookFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    private const SIMULATED_WEBHOOK_SECRET = 'simulated-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        config()->set('booking.realtime.enabled', true);
        config()->set('booking.realtime.cache_store', 'array');
        config()->set('booking.realtime.recent_event_limit', 50);
        config()->set('booking.realtime.poll_hint_ms', 1500);
        config()->set('booking.payment_providers.providers.simulated.webhook_secret', self::SIMULATED_WEBHOOK_SECRET);
        config()->set('booking.payment_providers.providers.simulated.webhook.secret', self::SIMULATED_WEBHOOK_SECRET);
        config()->set('booking.payment_providers.providers.simulated.enforce_signature', true);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
        Cache::store('array')->flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_deposit_webhook_applies_payment_once_and_duplicate_delivery_is_idempotent(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Reserved']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $session = ReservationDepositPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-dep-webhook-1',
            'payment_method' => 'Online',
            'amount' => '100000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'deposit'],
            'row_version' => 1,
        ]);

        $payload = [
            'provider_event_code' => 'evt-dep-1',
            'provider_session_code' => 'sim-dep-webhook-1',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ];
        $reservationRowVersionBefore = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');
        $beforeVersion = (int) $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes')
            ->assertOk()
            ->json('data.current_version');

        $first = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $first->assertStatus(202)
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.payment_scope', 'deposit')
            ->assertJsonPath('data.delivery_status', 'Applied');

        $firstChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $beforeVersion);

        $firstChanges->assertOk()->assertJsonPath('data.has_changes', true);
        $event = collect($firstChanges->json('data.events'))->firstWhere('type', 'reservation.deposit_paid');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame([$tableId], array_values((array) data_get($event, 'payload.table_ids', [])));
        self::assertSame('Paid', (string) data_get($event, 'payload.deposit_status'));
        $afterFirstVersion = (int) $firstChanges->json('data.current_version');

        $second = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $second->assertStatus(202)
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.payment_scope', 'deposit');

        $replayChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version=' . $afterFirstVersion);

        $replayChanges->assertOk()
            ->assertJsonPath('data.has_changes', false)
            ->assertJsonCount(0, 'data.events');

        $session->refresh();
        self::assertSame('Succeeded', (string) ($session->session_status?->value ?? $session->session_status));
        self::assertSame('Applied', (string) ($session->settlement_status?->value ?? $session->settlement_status));
        self::assertNotNull($session->linked_payment_id);
        self::assertSame(1, DB::table('payments')->where('reservation_id', $reservationId)->count());
        self::assertSame(1, DB::table('payment_provider_webhook_receipts')->where('provider_event_code', 'evt-dep-1')->count());
        self::assertSame('Paid', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('deposit_status'));
        self::assertSame(100000.0, (float) DB::table('reservations')->where('reservation_id', $reservationId)->value('deposit_paid_amount'));
        self::assertGreaterThan(
            $reservationRowVersionBefore,
            (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version')
        );

        $receiptId = (int) DB::table('payment_provider_webhook_receipts')
            ->where('provider_event_code', 'evt-dep-1')
            ->value('payment_provider_webhook_receipt_id');
        $this->assertAuditLogCount(1, 'payment.webhook.processed', 'payment_provider_webhook_receipt', $receiptId);

        $log = $this->assertAuditLogRecorded('payment.webhook.processed', 'payment_provider_webhook_receipt', $receiptId);
        self::assertSame('webhook_provider', $log->actor_type);
        self::assertSame('webhook_provider:simulated', $log->actor_key);
        self::assertSame('Applied', (string) data_get($log->summary_json, 'delivery_status'));
        $this->assertAuditSubjectRecorded($log, 'reservation', $reservationId, 'reservation');
        $this->assertAuditSubjectRecorded($log, 'deposit_payment_session', $session->deposit_payment_session_id, 'payment_session');
    }

    public function test_duplicate_delivery_resumes_incomplete_received_receipt_and_applies_payment_once(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '90000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        ReservationDepositPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-dep-received-retry-1',
            'payment_method' => 'Online',
            'amount' => '90000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'deposit'],
            'row_version' => 1,
        ]);

        $payload = [
            'provider_event_code' => 'evt-dep-received-retry-1',
            'provider_session_code' => 'sim-dep-received-retry-1',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, self::SIMULATED_WEBHOOK_SECRET);

        DB::table('payment_provider_webhook_receipts')->insert([
            'provider_code' => 'simulated',
            'provider_event_code' => 'evt-dep-received-retry-1',
            'provider_session_code' => 'sim-dep-received-retry-1',
            'payment_scope' => 'deposit',
            'event_type' => 'payment.session.updated',
            'delivery_status' => 'Received',
            'request_signature' => $signature,
            'request_headers_json' => json_encode(['x-payment-signature' => $signature], JSON_THROW_ON_ERROR),
            'request_body' => $body,
            'provider_payload_json' => json_encode(['mode' => 'simulated', 'webhook' => true], JSON_THROW_ON_ERROR),
            'processed_at' => null,
            'failure_message' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'created_by' => null,
            'updated_by' => null,
            'row_version' => 1,
        ]);

        $response = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $response->assertStatus(202)
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.resumed_incomplete_delivery', true)
            ->assertJsonPath('data.payment_scope', 'deposit')
            ->assertJsonPath('data.delivery_status', 'Applied');

        self::assertSame(1, DB::table('payment_provider_webhook_receipts')->where('provider_event_code', 'evt-dep-received-retry-1')->count());
        self::assertSame(
            'Applied',
            (string) DB::table('payment_provider_webhook_receipts')
                ->where('provider_event_code', 'evt-dep-received-retry-1')
                ->value('delivery_status')
        );
        self::assertSame('Succeeded', (string) DB::table('reservation_deposit_payment_sessions')
            ->where('provider_code', 'simulated')
            ->where('provider_session_code', 'sim-dep-received-retry-1')
            ->value('session_status'));
        self::assertSame(1, DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Deposit')->count());
    }

    public function test_webhook_receipt_redacts_sensitive_request_artifacts_and_raw_provider_payloads(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '91000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        ReservationDepositPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-dep-redacted-receipt-1',
            'payment_method' => 'Online',
            'amount' => '91000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'deposit'],
            'row_version' => 1,
        ]);

        $payload = [
            'provider_event_code' => 'evt-dep-redacted-receipt-1',
            'provider_session_code' => 'sim-dep-redacted-receipt-1',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, self::SIMULATED_WEBHOOK_SECRET);

        $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload))
            ->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Applied');

        $receipt = DB::table('payment_provider_webhook_receipts')
            ->where('provider_event_code', 'evt-dep-redacted-receipt-1')
            ->first();

        self::assertNotNull($receipt);

        $storedHeaders = json_decode((string) $receipt->request_headers_json, true, 512, JSON_THROW_ON_ERROR);
        $storedBody = json_decode((string) $receipt->request_body, true, 512, JSON_THROW_ON_ERROR);
        $storedPayload = json_decode((string) $receipt->provider_payload_json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('sha256:'.hash('sha256', $signature), (string) $receipt->request_signature);
        self::assertSame('[redacted]', $storedHeaders['x-payment-signature']);
        self::assertSame('evt-dep-redacted-receipt-1', $storedBody['provider_event_code']);
        self::assertSame('sim-dep-redacted-receipt-1', $storedBody['provider_session_code']);
        self::assertArrayHasKey('body_fingerprint', $storedBody);
        self::assertStringNotContainsString('simulation_outcome', (string) $receipt->request_body);
        self::assertSame('simulated', $storedPayload['mode']);
        self::assertTrue((bool) $storedPayload['webhook']);
        self::assertArrayNotHasKey('received_headers', $storedPayload);
        self::assertArrayHasKey('_receipt', $storedPayload);
        self::assertSame($storedBody['body_fingerprint'], $storedPayload['_receipt']['body_fingerprint']);
    }

    public function test_client_confirm_then_webhook_for_same_deposit_session_does_not_double_apply_payment(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '85000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        $session = ReservationDepositPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-dep-confirm-then-webhook-1',
            'payment_method' => 'Online',
            'amount' => '85000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'deposit'],
            'row_version' => 1,
        ]);

        $customer = \App\Models\User::query()->findOrFail($customerId);
        $confirm = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-confirm-then-webhook-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$session->deposit_payment_session_id.'/confirm', [
            'row_version' => 1,
            'simulation_outcome' => 'succeeded',
        ]);

        $confirm->assertOk()
            ->assertJsonPath('data.payment_session.session_status', 'Succeeded')
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied');

        $linkedPaymentId = (int) $confirm->json('data.payment_session.linked_payment_id');

        $payload = [
            'provider_event_code' => 'evt-dep-confirm-then-webhook-1',
            'provider_session_code' => 'sim-dep-confirm-then-webhook-1',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ];

        $webhook = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $webhook->assertStatus(202)
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.payment_scope', 'deposit')
            ->assertJsonPath('data.delivery_status', 'Applied');

        $session->refresh();
        self::assertSame($linkedPaymentId, (int) $session->linked_payment_id);
        self::assertSame('Applied', (string) ($session->settlement_status?->value ?? $session->settlement_status));
        self::assertSame(1, DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Deposit')->count());
        self::assertSame(1, DB::table('payment_provider_webhook_receipts')->where('provider_event_code', 'evt-dep-confirm-then-webhook-1')->count());
    }

    public function test_duplicate_event_code_with_changed_payload_is_rejected_without_mutating_existing_receipt(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '95000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        ReservationDepositPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-dep-duplicate-mismatch-1',
            'payment_method' => 'Online',
            'amount' => '95000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'deposit'],
            'row_version' => 1,
        ]);

        $appliedPayload = [
            'provider_event_code' => 'evt-dep-duplicate-mismatch-1',
            'provider_session_code' => 'sim-dep-duplicate-mismatch-1',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ];

        $this->postJson('/api/v1/payments/providers/simulated/webhooks', $appliedPayload, $this->webhookHeaders($appliedPayload))
            ->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Applied');

        $conflictingPayload = [
            'provider_event_code' => 'evt-dep-duplicate-mismatch-1',
            'provider_session_code' => 'sim-dep-duplicate-mismatch-1',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'failed',
        ];

        $response = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $conflictingPayload, $this->webhookHeaders($conflictingPayload));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath(
                'details.errors.provider_event_code.0',
                'Webhook provider_event_code is already bound to a different webhook payload.'
            );

        self::assertSame(1, DB::table('payment_provider_webhook_receipts')
            ->where('provider_event_code', 'evt-dep-duplicate-mismatch-1')
            ->count());
        self::assertSame(
            'Applied',
            (string) DB::table('payment_provider_webhook_receipts')
                ->where('provider_event_code', 'evt-dep-duplicate-mismatch-1')
                ->value('delivery_status')
        );
        self::assertSame('Succeeded', (string) DB::table('reservation_deposit_payment_sessions')
            ->where('provider_code', 'simulated')
            ->where('provider_session_code', 'sim-dep-duplicate-mismatch-1')
            ->value('session_status'));
        self::assertSame('Applied', (string) DB::table('reservation_deposit_payment_sessions')
            ->where('provider_code', 'simulated')
            ->where('provider_session_code', 'sim-dep-duplicate-mismatch-1')
            ->value('settlement_status'));
        self::assertSame(1, DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Deposit')->count());
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'provider_event_code' => 'evt-invalid-signature',
            'provider_session_code' => 'sim-anything',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ];

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Payment-Signature' => 'wrong-signature',
        ])->postJson('/api/v1/payments/providers/simulated/webhooks', $payload)
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'invalid_signature');
    }

    public function test_generic_http_hmac_webhook_applies_deposit_payment_with_configured_signature_contract(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.secret', 'generic-webhook-secret');
        config()->set('booking.payment_providers.providers.generic_http_hmac.supported_event_types', ['payment.session.completed']);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '120000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        ReservationDepositPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'generic_http_hmac',
            'provider_session_code' => 'gen-dep-webhook-1',
            'payment_method' => 'Online',
            'amount' => '120000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'deposit'],
            'row_version' => 1,
        ]);

        $payload = [
            'provider_event_code' => 'evt-gen-dep-1',
            'provider_session_code' => 'gen-dep-webhook-1',
            'payment_scope' => 'deposit',
            'event_type' => 'payment.session.completed',
            'session_status' => 'paid',
            'occurred_at' => now('UTC')->toIso8601String(),
        ];

        $response = $this->postJson(
            '/api/v1/payments/providers/generic_http_hmac/webhooks',
            $payload,
            $this->genericWebhookHeaders($payload, 'generic-webhook-secret')
        );

        $response->assertStatus(202)
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.payment_scope', 'deposit')
            ->assertJsonPath('data.delivery_status', 'Applied');

        self::assertSame('Succeeded', (string) DB::table('reservation_deposit_payment_sessions')
            ->where('provider_code', 'generic_http_hmac')
            ->where('provider_session_code', 'gen-dep-webhook-1')
            ->value('session_status'));
        self::assertSame(1, DB::table('payments')->where('reservation_id', $reservationId)->count());
    }

    public function test_generic_http_hmac_webhook_ignores_stale_event_ordering(): void
    {
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.secret', 'generic-webhook-secret');
        config()->set('booking.payment_providers.providers.generic_http_hmac.supported_event_types', ['payment.session.updated']);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '60000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        ReservationDepositPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'generic_http_hmac',
            'provider_session_code' => 'gen-dep-stale-1',
            'payment_method' => 'Online',
            'amount' => '60000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'deposit'],
            'last_reconciled_at' => now('UTC'),
            'row_version' => 1,
        ]);

        $payload = [
            'provider_event_code' => 'evt-gen-stale-1',
            'provider_session_code' => 'gen-dep-stale-1',
            'payment_scope' => 'deposit',
            'event_type' => 'payment.session.updated',
            'session_status' => 'paid',
            'occurred_at' => now('UTC')->subMinutes(15)->toIso8601String(),
        ];

        $response = $this->postJson(
            '/api/v1/payments/providers/generic_http_hmac/webhooks',
            $payload,
            $this->genericWebhookHeaders($payload, 'generic-webhook-secret')
        );

        $response->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Ignored')
            ->assertJsonPath('data.ignored_reason', 'stale_event_order_ignored')
            ->assertJsonPath('data.payment_scope', 'deposit');

        self::assertSame('Pending', (string) DB::table('reservation_deposit_payment_sessions')
            ->where('provider_code', 'generic_http_hmac')
            ->where('provider_session_code', 'gen-dep-stale-1')
            ->value('session_status'));
        self::assertSame(0, DB::table('payments')->where('reservation_id', $reservationId)->count());
    }


    public function test_webhook_rejects_disabled_provider_configuration(): void
    {
        config()->set('booking.payment_providers.providers.simulated.enabled', false);

        $payload = [
            'provider_event_code' => 'evt-disabled-provider',
            'provider_session_code' => 'sim-disabled-provider',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ];

        $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.provider_code.0', 'Payment provider is disabled.');
    }

    public function test_unknown_webhook_event_type_is_ignored_but_receipt_is_persisted(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '40000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        ReservationDepositPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-dep-unknown-event-1',
            'payment_method' => 'Online',
            'amount' => '40000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'deposit'],
            'row_version' => 1,
        ]);

        $payload = [
            'provider_event_code' => 'evt-dep-unknown-event-1',
            'provider_session_code' => 'sim-dep-unknown-event-1',
            'payment_scope' => 'deposit',
            'event_type' => 'provider.healthcheck.ping',
            'simulation_outcome' => 'succeeded',
        ];

        $response = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $response->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Ignored')
            ->assertJsonPath('data.ignored_reason', 'unsupported_event_type')
            ->assertJsonPath('data.event_type', 'provider.healthcheck.ping')
            ->assertJsonPath('data.payment_scope', 'deposit');

        self::assertSame(0, DB::table('payments')->where('reservation_id', $reservationId)->count());
        self::assertSame(
            'Ignored',
            (string) DB::table('payment_provider_webhook_receipts')
                ->where('provider_event_code', 'evt-dep-unknown-event-1')
                ->value('delivery_status')
        );
    }

    public function test_webhook_marks_receipt_failed_when_referenced_session_cannot_be_found(): void
    {
        $payload = [
            'provider_event_code' => 'evt-missing-session-1',
            'provider_session_code' => 'sim-dep-missing-session-1',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ];

        $response = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $response->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Failed')
            ->assertJsonPath('data.payment_scope', 'deposit');

        self::assertSame(
            'Failed',
            (string) DB::table('payment_provider_webhook_receipts')
                ->where('provider_event_code', 'evt-missing-session-1')
                ->value('delivery_status')
        );
    }

    public function test_webhook_without_declared_scope_marks_receipt_failed_when_session_cannot_be_resolved(): void
    {
        $payload = [
            'provider_event_code' => 'evt-missing-session-no-scope-1',
            'provider_session_code' => 'sim-missing-session-no-scope-1',
            'simulation_outcome' => 'succeeded',
        ];

        $response = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $response->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Failed')
            ->assertJsonPath('data.payment_scope', null)
            ->assertJsonPath('data.failure_message', 'Webhook payload must identify a valid payment_scope for the referenced provider session.');

        self::assertSame(
            'Failed',
            (string) DB::table('payment_provider_webhook_receipts')
                ->where('provider_event_code', 'evt-missing-session-no-scope-1')
                ->value('delivery_status')
        );
    }


    public function test_webhook_with_invalid_declared_scope_marks_receipt_failed_cleanly(): void
    {
        $payload = [
            'provider_event_code' => 'evt-invalid-scope-1',
            'provider_session_code' => 'sim-invalid-scope-1',
            'payment_scope' => 'invoice',
            'simulation_outcome' => 'succeeded',
        ];

        $response = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $response->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Failed')
            ->assertJsonPath('data.payment_scope', 'invoice')
            ->assertJsonPath('data.failure_message', 'Webhook payload payment_scope is invalid for the referenced provider session.');

        self::assertSame(
            'Failed',
            (string) DB::table('payment_provider_webhook_receipts')
                ->where('provider_event_code', 'evt-invalid-scope-1')
                ->value('delivery_status')
        );
    }

    public function test_webhook_with_declared_scope_that_does_not_match_stored_session_fails_cleanly(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'bill_currency' => 'VND',
            'final_bill_amount' => '90000.00',
            'billed_at' => now('UTC'),
        ]);

        ReservationBillPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-scope-mismatch-1',
            'payment_method' => 'Online',
            'amount' => '90000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'bill'],
            'row_version' => 1,
        ]);

        $payload = [
            'provider_event_code' => 'evt-scope-mismatch-1',
            'provider_session_code' => 'sim-scope-mismatch-1',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'succeeded',
        ];

        $response = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $response->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Failed')
            ->assertJsonPath('data.payment_scope', 'deposit')
            ->assertJsonPath('data.failure_message', 'Webhook payload payment_scope does not match the stored payment session scope.');

        self::assertSame(
            'Failed',
            (string) DB::table('payment_provider_webhook_receipts')
                ->where('provider_event_code', 'evt-scope-mismatch-1')
                ->value('delivery_status')
        );
        self::assertSame(0, DB::table('payments')->where('reservation_id', $reservationId)->count());
    }

    public function test_webhook_fails_when_provider_session_code_is_ambiguous_across_payment_scopes(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $depositReservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $billReservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'bill_currency' => 'VND',
            'final_bill_amount' => '120000.00',
            'billed_at' => now('UTC'),
        ]);

        ReservationDepositPaymentSession::query()->create([
            'reservation_id' => $depositReservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-ambiguous-scope-1',
            'payment_method' => 'Online',
            'amount' => '50000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'deposit'],
            'row_version' => 1,
        ]);

        ReservationBillPaymentSession::query()->create([
            'reservation_id' => $billReservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-ambiguous-scope-1',
            'payment_method' => 'Online',
            'amount' => '120000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'bill'],
            'row_version' => 1,
        ]);

        $payload = [
            'provider_event_code' => 'evt-ambiguous-scope-1',
            'provider_session_code' => 'sim-ambiguous-scope-1',
            'simulation_outcome' => 'succeeded',
        ];

        $response = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $response->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Failed')
            ->assertJsonPath('data.payment_scope', null)
            ->assertJsonPath('data.failure_message', 'Webhook provider_session_code is ambiguous because it exists in both deposit and bill payment session scopes.');

        self::assertSame(0, DB::table('payments')->whereIn('reservation_id', [$depositReservationId, $billReservationId])->count());
    }

    public function test_terminal_session_regression_event_is_ignored_state_safely(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        $paymentId = $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'payment_provider' => 'simulated',
            'transaction_code' => 'dep-terminal-1',
        ]);

        $session = ReservationDepositPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'linked_payment_id' => $paymentId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-dep-terminal-1',
            'provider_payment_code' => 'sim-pay-terminal-1',
            'payment_method' => 'Online',
            'amount' => '50000.00',
            'currency' => 'VND',
            'session_status' => 'Succeeded',
            'settlement_status' => 'Applied',
            'provider_payload_json' => ['payment_scope' => 'deposit'],
            'row_version' => 1,
        ]);

        $payload = [
            'provider_event_code' => 'evt-dep-terminal-regression',
            'provider_session_code' => 'sim-dep-terminal-1',
            'payment_scope' => 'deposit',
            'simulation_outcome' => 'pending',
        ];

        $response = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $response->assertStatus(202)
            ->assertJsonPath('data.delivery_status', 'Ignored')
            ->assertJsonPath('data.payment_scope', 'deposit')
            ->assertJsonPath('data.ignored_reason', 'terminal_state_regression_ignored')
            ->assertJsonPath('data.message', 'Webhook event was ignored because it would regress a terminal payment session state.');

        $session->refresh();
        self::assertSame('Succeeded', (string) ($session->session_status?->value ?? $session->session_status));
        self::assertSame(1, DB::table('payments')->where('reservation_id', $reservationId)->count());
        self::assertSame(
            'Webhook event was ignored because it would regress a terminal payment session state.',
            (string) DB::table('payment_provider_webhook_receipts')
                ->where('provider_event_code', 'evt-dep-terminal-regression')
                ->value('failure_message')
        );
    }

    public function test_bill_webhook_applies_final_payment_without_breaking_settlement_domain(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'bill_currency' => 'VND',
            'final_bill_amount' => '220000.00',
            'billed_at' => now('UTC'),
        ]);

        $session = ReservationBillPaymentSession::query()->create([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-bill-webhook-1',
            'payment_method' => 'Online',
            'amount' => '220000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'provider_payload_json' => ['payment_scope' => 'bill'],
            'row_version' => 1,
        ]);

        $payload = [
            'provider_event_code' => 'evt-bill-1',
            'provider_session_code' => 'sim-bill-webhook-1',
            'payment_scope' => 'bill',
            'simulation_outcome' => 'succeeded',
        ];
        $reservationRowVersionBefore = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');

        $response = $this->postJson('/api/v1/payments/providers/simulated/webhooks', $payload, $this->webhookHeaders($payload));

        $response->assertStatus(202)
            ->assertJsonPath('data.payment_scope', 'bill')
            ->assertJsonPath('data.delivery_status', 'Applied');

        $session->refresh();
        self::assertSame('Succeeded', (string) ($session->session_status?->value ?? $session->session_status));
        self::assertSame('Applied', (string) ($session->settlement_status?->value ?? $session->settlement_status));
        self::assertNotNull($session->linked_payment_id);
        self::assertSame(1, DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->count());
        self::assertGreaterThan(
            $reservationRowVersionBefore,
            (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version')
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>
     */
    private function webhookHeaders(array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Payment-Signature' => hash_hmac('sha256', $body, self::SIMULATED_WEBHOOK_SECRET),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>
     */
    private function genericWebhookHeaders(array $payload, string $secret): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now('UTC')->toIso8601String();

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Payment-Timestamp' => $timestamp,
            'X-Payment-Signature' => hash_hmac('sha256', $body, $secret),
        ];
    }
}
