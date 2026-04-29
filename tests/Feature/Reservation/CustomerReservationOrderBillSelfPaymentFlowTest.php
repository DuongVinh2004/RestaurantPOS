<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Payments\Application\UseCases\PaymentSessions\CustomerReservationBillPaymentService;
use App\Modules\Payments\Domain\Models\ReservationBillPaymentSession;
use App\Modules\Payments\Infrastructure\Integrations\CustomerBillPayment\CustomerBillPaymentProvider;
use App\Modules\Payments\Infrastructure\Integrations\CustomerBillPayment\CustomerBillPaymentProviderRegistry;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationOrderBillSelfPaymentFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        config()->set('booking.payment_providers.customer_self_pay.enabled', true);
        config()->set('booking.payment_providers.providers.simulated.enabled', true);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_owner_can_view_active_order_and_bill_preview(): void
    {
        [$customerId, , $reservationId, $orderId] = $this->seedInServiceOrderScenario(lockBill: false);
        $customer = User::query()->findOrFail($customerId);

        $activeOrder = $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/active-order');

        $activeOrder->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.active_order.order_id', $orderId)
            ->assertJsonPath('data.active_order.totals.total_due', '100000.00')
            ->assertJsonPath('data.active_order.totals.outstanding', '100000.00')
            ->assertJsonPath('meta.has_active_order', true);

        $billPreview = $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/bill-preview');

        $billPreview->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.bill_preview.snapshot_mode', 'provisional')
            ->assertJsonPath('data.bill_preview.active_order_present', true)
            ->assertJsonPath('data.bill_preview.computed_subtotal_amount', '100000.00')
            ->assertJsonPath('data.bill_preview.total_due_amount', '100000.00')
            ->assertJsonPath('data.bill_preview.outstanding_amount', '100000.00')
            ->assertJsonPath('data.bill_preview.payment_status', 'Failed')
            ->assertJsonPath('data.bill_preview.self_payment.available', false)
            ->assertJsonPath('data.bill_preview.self_payment.next_step', 'awaiting_staff_bill_lock');
    }

    public function test_wrong_owner_cannot_view_customer_order_or_bill_preview(): void
    {
        [$ownerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: false);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $other = User::query()->findOrFail($otherId);

        $this->actingAs($other)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/active-order')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        $this->actingAs($other)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/bill-preview')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_wrong_owner_cannot_access_customer_bill_payment_session(): void
    {
        [$ownerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $owner = User::query()->findOrFail($ownerId);
        $other = User::query()->findOrFail($otherId);

        $create = $this->actingAs($owner)->withHeaders([
            'Idempotency-Key' => 'cust-bill-owner-session-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ])->assertCreated()
            ->assertJsonPath('data.payment_session.provider_payload.mode', 'simulated');

        $sessionId = (int) $create->json('data.payment_session.bill_payment_session_id');
        self::assertStringStartsWith(
            'simulated://bill-payment/',
            (string) $create->json('data.payment_session.provider_payload.payment_url')
        );
        self::assertNull(data_get($create->json(), 'data.payment_session.provider_payload._booking_request'));

        $storedPayload = json_decode((string) DB::table('reservation_bill_payment_sessions')
            ->where('bill_payment_session_id', $sessionId)
            ->value('provider_payload_json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('simulated', (string) ($storedPayload['mode'] ?? ''));
        self::assertNotSame('', (string) data_get($storedPayload, '_booking_request.fingerprint'));
        self::assertNull(data_get($storedPayload, '_booking_request.idempotency_key'));
        self::assertArrayNotHasKey('customer_user_id', $storedPayload);

        $this->actingAs($other)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions/'.$sessionId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_no_active_order_returns_null_and_bill_preview_remains_meaningful(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'bill_currency' => 'VND',
        ]);
        $customer = User::query()->findOrFail($customerId);

        $activeOrder = $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/active-order');

        $activeOrder->assertOk()
            ->assertJsonPath('data.active_order', null)
            ->assertJsonPath('meta.has_active_order', false);

        $billPreview = $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/bill-preview');

        $billPreview->assertOk()
            ->assertJsonPath('data.active_order', null)
            ->assertJsonPath('data.bill_preview.active_order_present', false)
            ->assertJsonPath('data.bill_preview.computed_subtotal_amount', '0.00')
            ->assertJsonPath('data.bill_preview.total_due_amount', '0.00')
            ->assertJsonPath('data.bill_preview.outstanding_amount', '0.00')
            ->assertJsonPath('data.bill_preview.self_payment.available', false)
            ->assertJsonPath('data.bill_preview.self_payment.next_step', 'awaiting_staff_bill_lock');
    }

    public function test_bill_payment_session_create_rejects_when_bill_is_not_locked_or_already_settled(): void
    {
        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: false);
        $customer = User::query()->findOrFail($customerId);

        $this->actingAs($customer)
            ->withHeaders([
                'Idempotency-Key' => 'cust-bill-create-unlocked-1',
                'Accept' => 'application/json',
            ])
            ->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', [
                'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
                'provider_code' => 'simulated',
                'currency' => 'VND',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        [$settledCustomerId, , $settledReservationId] = $this->seedInServiceOrderScenario(lockBill: true);
        $settledCustomer = User::query()->findOrFail($settledCustomerId);
        $this->createPayment([
            'reservation_id' => $settledReservationId,
            'amount' => '100000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'payment_type' => 'Final',
            'status' => 'Success',
            'transaction_code' => 'BILL-ALREADY-PAID-1',
        ]);

        $this->actingAs($settledCustomer)
            ->withHeaders([
                'Idempotency-Key' => 'cust-bill-create-settled-1',
                'Accept' => 'application/json',
            ])
            ->postJson('/api/v1/reservations/'.$settledReservationId.'/bill/payment-sessions', [
                'row_version' => (int) DB::table('reservations')->where('reservation_id', $settledReservationId)->value('row_version'),
                'provider_code' => 'simulated',
                'currency' => 'VND',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');
    }

    public function test_bill_preview_and_create_surface_staff_settlement_only_when_customer_self_pay_is_disabled(): void
    {
        config()->set('booking.payment_providers.customer_self_pay.enabled', false);

        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true);
        $customer = User::query()->findOrFail($customerId);

        $preview = $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/bill-preview');

        $preview->assertOk()
            ->assertJsonPath('data.bill_preview.self_payment.supported', false)
            ->assertJsonPath('data.bill_preview.self_payment.available', false)
            ->assertJsonPath('data.bill_preview.self_payment.next_step', 'staff_settlement_only')
            ->assertJsonPath('data.bill_preview.self_payment.disabled_reason', 'Customer self-pay is intentionally disabled for this rollout. Use staff settlement.');

        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-disabled-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'amount' => 100000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.provider_code.0', 'Customer self-pay is intentionally disabled for this rollout. Use staff settlement.');
    }

    public function test_branch_override_can_disable_customer_self_payment_without_disabling_other_testing_defaults(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'PAYOFF',
            'branch_name' => 'Payment Rollout Off',
        ]);
        $this->upsertFeatureFlagOverride(
            'customer.bill_self_payment',
            false,
            'testing',
            $branchId,
            ['reason' => 'pilot paused for branch'],
        );

        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true, branchId: $branchId);
        $customer = User::query()->findOrFail($customerId);

        $preview = $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/bill-preview');

        $preview->assertOk()
            ->assertJsonPath('data.bill_preview.self_payment.supported', false)
            ->assertJsonPath('data.bill_preview.self_payment.available', false)
            ->assertJsonPath('data.bill_preview.self_payment.next_step', 'staff_settlement_only')
            ->assertJsonPath('data.bill_preview.self_payment.disabled_reason', 'Customer bill self-payment is disabled for day 1. Keep bill preview and active-order reads only, and use staff settlement.');

        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-branch-disabled-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'amount' => 100000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.provider_code.0', 'Customer bill self-payment is disabled for day 1. Keep bill preview and active-order reads only, and use staff settlement.');
    }

    public function test_duplicate_create_and_confirm_do_not_double_apply_final_payment(): void
    {
        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true);
        $customer = User::query()->findOrFail($customerId);
        $headers = [
            'Idempotency-Key' => 'cust-bill-idem-create-1',
            'Accept' => 'application/json',
        ];
        $payload = [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'amount' => 100000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ];

        $firstCreate = $this->actingAs($customer)->withHeaders($headers)
            ->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', $payload);
        $secondCreate = $this->actingAs($customer)->withHeaders($headers)
            ->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', $payload);

        $firstCreate->assertCreated()->assertHeader('Idempotency-Replayed', 'false');
        $secondCreate->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame(
            $firstCreate->json('data.payment_session.bill_payment_session_id'),
            $secondCreate->json('data.payment_session.bill_payment_session_id')
        );
        $this->assertSame(1, (int) DB::table('reservation_bill_payment_sessions')->where('reservation_id', $reservationId)->count());
        $this->assertAuditLogCount(1, 'payment_session.created', 'reservation', $reservationId);

        $sessionId = (int) $firstCreate->json('data.payment_session.bill_payment_session_id');
        $rowVersion = (int) $firstCreate->json('data.payment_session.row_version');
        $reservationRowVersionBeforeConfirm = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');

        $firstConfirm = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => $rowVersion,
            'simulation_outcome' => 'succeeded',
        ]);

        $firstConfirm->assertOk()
            ->assertJsonPath('data.bill.total_due_amount', '100000.00')
            ->assertJsonPath('data.bill.outstanding_amount', '0.00')
            ->assertJsonPath('data.payment_session.session_status', 'Succeeded')
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied');

        $createdLog = $this->assertAuditLogRecorded('payment_session.created', 'reservation', $reservationId);
        self::assertSame($customerId, $createdLog->actor_user_id);
        self::assertSame('customer_account', $createdLog->actor_type);
        $this->assertAuditSubjectRecorded($createdLog, 'bill_payment_session', $sessionId, 'payment_session');

        self::assertGreaterThan(
            $reservationRowVersionBeforeConfirm,
            (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version')
        );

        $linkedPaymentId = (int) $firstConfirm->json('data.payment_session.linked_payment_id');
        $nextRowVersion = (int) $firstConfirm->json('data.payment_session.row_version');

        $confirmedLog = $this->assertAuditLogRecorded('payment_session.confirmed', 'reservation', $reservationId);
        self::assertSame($customerId, $confirmedLog->actor_user_id);
        self::assertSame('customer_account', $confirmedLog->actor_type);
        self::assertFalse((bool) data_get($confirmedLog->summary_json, 'replayed_final_state'));
        $this->assertAuditSubjectRecorded($confirmedLog, 'bill_payment_session', $sessionId, 'payment_session');
        $this->assertAuditSubjectRecorded($confirmedLog, 'payment', $linkedPaymentId, 'payment');

        $secondConfirm = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-confirm-2',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => $nextRowVersion,
            'simulation_outcome' => 'succeeded',
        ]);

        $secondConfirm->assertOk()
            ->assertJsonPath('data.payment_session.linked_payment_id', $linkedPaymentId)
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied');

        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Final')
                ->count()
        );
        $this->assertSame('Reserved', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));

        $preview = $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/bill-preview');

        $preview->assertOk()
            ->assertJsonPath('data.bill_preview.outstanding_amount', '0.00')
            ->assertJsonPath('data.bill_preview.self_payment.available', false)
            ->assertJsonPath('data.bill_preview.self_payment.awaiting_staff_finalization', true)
            ->assertJsonPath('data.bill_preview.self_payment.next_step', 'payment_recorded_awaiting_staff_finalization');
    }

    public function test_bill_create_session_service_rejects_same_idempotency_key_when_payload_differs(): void
    {
        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true);

        /** @var CustomerReservationBillPaymentService $service */
        $service = app(CustomerReservationBillPaymentService::class);
        $rowVersion = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');

        $service->createSession($reservationId, [
            'row_version' => $rowVersion,
            'amount' => 100000,
            'payment_method' => 'Online',
            'provider_code' => 'simulated',
            'currency' => 'VND',
            'notes' => 'first bill session',
        ], $customerId, null, 'cust-bill-service-idem-conflict-1');

        try {
            $service->createSession($reservationId, [
                'row_version' => $rowVersion,
                'amount' => 90000,
                'payment_method' => 'Online',
                'provider_code' => 'simulated',
                'currency' => 'VND',
                'notes' => 'different bill session amount',
            ], $customerId, null, 'cust-bill-service-idem-conflict-1');

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('idempotency_key', $errors);
            $this->assertSame(
                'This idempotency key is already bound to a different bill payment session request payload.',
                $errors['idempotency_key'][0]
            );
        }

        $this->assertSame(
            1,
            (int) DB::table('reservation_bill_payment_sessions')
                ->where('reservation_id', $reservationId)
                ->where('customer_user_id', $customerId)
                ->count()
        );
    }

    public function test_create_rejects_provider_session_code_collision_with_existing_deposit_payment_session(): void
    {
        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true);
        $customer = User::query()->findOrFail($customerId);

        DB::table('reservation_deposit_payment_sessions')->insert([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'linked_payment_id' => null,
            'provider_code' => 'simulated',
            'provider_session_code' => 'shared-provider-session-code-2',
            'provider_payment_code' => null,
            'payment_method' => 'Online',
            'amount' => '50000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'failure_code' => null,
            'failure_message' => null,
            'provider_payload_json' => json_encode(['payment_scope' => 'deposit'], JSON_THROW_ON_ERROR),
            'idempotency_key' => null,
            'provider_expires_at' => null,
            'last_reconciled_at' => null,
            'confirmed_at' => null,
            'failed_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'created_by' => null,
            'updated_by' => null,
            'row_version' => 1,
        ]);

        $provider = new class implements CustomerBillPaymentProvider
        {
            public function code(): string
            {
                return 'simulated';
            }

            public function createSession(Reservation $reservation, int $customerUserId, array $payload): array
            {
                return [
                    'provider_code' => 'simulated',
                    'provider_session_code' => 'shared-provider-session-code-2',
                    'payment_method' => 'Online',
                    'session_status' => 'Pending',
                    'provider_payload' => ['payment_scope' => 'bill'],
                ];
            }

            public function refreshSession(Reservation $reservation, ReservationBillPaymentSession $session, array $payload): array
            {
                return [];
            }

            public function confirmSession(Reservation $reservation, ReservationBillPaymentSession $session, array $payload): array
            {
                return [];
            }
        };

        $registry = Mockery::mock(CustomerBillPaymentProviderRegistry::class);
        $registry->shouldReceive('resolve')->once()->andReturn($provider);
        $this->app->instance(CustomerBillPaymentProviderRegistry::class, $registry);

        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-collision-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'amount' => 100000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.provider_session_code.0', 'Provider session code is already bound to an existing [deposit] payment session for this provider.');

        $this->assertSame(0, (int) DB::table('reservation_bill_payment_sessions')->where('reservation_id', $reservationId)->count());
    }

    public function test_refresh_after_applied_bill_session_is_a_safe_noop(): void
    {
        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true);
        $customer = User::query()->findOrFail($customerId);

        $create = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-refresh-noop-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'amount' => 100000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ])->assertCreated();

        $sessionId = (int) $create->json('data.payment_session.bill_payment_session_id');

        $confirm = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-refresh-noop-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => (int) $create->json('data.payment_session.row_version'),
            'simulation_outcome' => 'succeeded',
        ])->assertOk();

        $linkedPaymentId = (int) $confirm->json('data.payment_session.linked_payment_id');
        $terminalRowVersion = (int) $confirm->json('data.payment_session.row_version');

        $refresh = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-refresh-noop-refresh-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions/'.$sessionId.'/refresh', [
            'row_version' => $terminalRowVersion,
            'simulation_outcome' => 'pending',
        ]);

        $refresh->assertOk()
            ->assertJsonPath('data.payment_session.session_status', 'Succeeded')
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied')
            ->assertJsonPath('data.payment_session.linked_payment_id', $linkedPaymentId)
            ->assertJsonPath('data.payment_session.row_version', $terminalRowVersion)
            ->assertJsonPath('data.bill.outstanding_amount', '0.00');

        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Final')
                ->count()
        );
    }

    public function test_terminal_succeeded_bill_session_relinks_existing_payment_marker_without_duplicate(): void
    {
        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true);
        $customer = User::query()->findOrFail($customerId);

        $sessionId = (int) DB::table('reservation_bill_payment_sessions')->insertGetId([
            'reservation_id' => $reservationId,
            'order_id' => null,
            'customer_user_id' => $customerId,
            'linked_payment_id' => null,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-bill-existing-payment-1',
            'provider_payment_code' => 'sim-bill-pay-existing-payment-1',
            'payment_method' => 'Online',
            'amount' => '100000.00',
            'currency' => 'VND',
            'session_status' => 'Succeeded',
            'settlement_status' => 'NotApplied',
            'failure_code' => null,
            'failure_message' => null,
            'provider_payload_json' => json_encode(['payment_scope' => 'bill'], JSON_THROW_ON_ERROR),
            'idempotency_key' => null,
            'provider_expires_at' => null,
            'last_reconciled_at' => now('UTC'),
            'confirmed_at' => now('UTC'),
            'failed_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'created_by' => $customerId,
            'updated_by' => $customerId,
            'row_version' => 4,
        ], 'bill_payment_session_id');

        $paymentId = $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'payment_method' => 'Online',
            'payment_provider' => 'simulated',
            'transaction_code' => 'sim-bill-pay-existing-payment-1',
            'idempotency_key' => 'customer-bill-session:'.$sessionId,
            'provider_response_json' => [
                'source' => 'customer_bill_payment_session',
                'bill_payment_session_id' => $sessionId,
                'provider_code' => 'simulated',
                'provider_session_code' => 'sim-bill-existing-payment-1',
                'provider_payment_code' => 'sim-bill-pay-existing-payment-1',
            ],
        ]);

        $registry = Mockery::mock(CustomerBillPaymentProviderRegistry::class);
        $registry->shouldReceive('resolve')->never();
        $this->app->instance(CustomerBillPaymentProviderRegistry::class, $registry);

        $confirm = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-existing-payment-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => 4,
            'simulation_outcome' => 'failed',
        ]);

        $confirm->assertOk()
            ->assertJsonPath('data.payment_session.linked_payment_id', $paymentId)
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied')
            ->assertJsonPath('data.bill.outstanding_amount', '0.00');

        $this->assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->count());
        $this->assertSame($paymentId, (int) DB::table('reservation_bill_payment_sessions')->where('bill_payment_session_id', $sessionId)->value('linked_payment_id'));
    }

    public function test_pending_or_failed_confirmation_does_not_apply_real_final_payment(): void
    {
        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true);
        $customer = User::query()->findOrFail($customerId);

        $create = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-pending-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ])->assertCreated();

        $sessionId = (int) $create->json('data.payment_session.bill_payment_session_id');
        $rowVersion = (int) $create->json('data.payment_session.row_version');

        $pending = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-pending-refresh-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions/'.$sessionId.'/refresh', [
            'row_version' => $rowVersion,
            'simulation_outcome' => 'pending',
        ]);

        $pending->assertOk()
            ->assertJsonPath('data.payment_session.session_status', 'Pending')
            ->assertJsonPath('data.payment_session.linked_payment_id', null)
            ->assertJsonPath('data.bill.outstanding_amount', '100000.00');

        $failed = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-failed-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => (int) $pending->json('data.payment_session.row_version'),
            'simulation_outcome' => 'failed',
        ]);

        $failed->assertOk()
            ->assertJsonPath('data.payment_session.session_status', 'Failed')
            ->assertJsonPath('data.payment_session.linked_payment_id', null)
            ->assertJsonPath('data.bill.outstanding_amount', '100000.00');

        $this->assertSame(0, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->count());
    }

    public function test_customer_bill_self_payment_keeps_existing_staff_finalize_flow_working(): void
    {
        [$customerId, $staffId, $reservationId, $orderId] = $this->seedInServiceOrderScenario(lockBill: true);
        $customer = User::query()->findOrFail($customerId);

        $create = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-finalize-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'amount' => 100000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ])->assertCreated();

        $sessionId = (int) $create->json('data.payment_session.bill_payment_session_id');
        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-bill-finalize-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/bill/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => (int) $create->json('data.payment_session.row_version'),
            'simulation_outcome' => 'succeeded',
        ])->assertOk();

        $orderRowVersion = (int) DB::table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $order = app(OrderSettlementWorkflow::class)->payOrder(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 0.0,
            currency: 'VND',
            transactionCode: '',
            paymentProvider: 'Cash',
            notes: 'staff finalize after customer self-payment',
            expectedRowVersion: $orderRowVersion,
            staffUserId: $staffId,
            idempotencyKey: 'staff-finalize-after-customer-payment-1',
        );

        $this->assertSame('Completed', (string) ($order->status?->value ?? $order->status));
        $this->assertSame('Completed', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        $this->assertNotNull(DB::table('reservations')->where('reservation_id', $reservationId)->value('checked_out_at'));
        $this->assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->count());
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function seedInServiceOrderScenario(bool $lockBill, ?int $branchId = null): array
    {
        $branchId ??= 1;
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift([
            'branch_id' => $branchId,
            'cashier_user_id' => $staffId,
        ]);
        $tableId = $this->createRestaurantTable([
            'status' => 'Occupied',
            'branch_id' => $branchId,
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        if ($lockBill) {
            app(OrderSettlementWorkflow::class)->lockBill(
                orderId: $orderId,
                discountAmount: null,
                notes: 'lock bill for customer self-payment',
                expectedRowVersion: 1,
                staffUserId: $staffId,
            );
        }

        return [$customerId, $staffId, $reservationId, $orderId];
    }
}
