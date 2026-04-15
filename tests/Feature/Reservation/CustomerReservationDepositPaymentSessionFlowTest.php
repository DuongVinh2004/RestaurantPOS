<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Models\User;
use App\Modules\CheckoutPayments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\CheckoutPayments\Infrastructure\CustomerDepositPayment\CustomerDepositPaymentProvider;
use App\Modules\CheckoutPayments\Infrastructure\CustomerDepositPayment\CustomerDepositPaymentProviderRegistry;
use App\Modules\CheckoutPayments\Application\Services\CustomerReservationDepositPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationDepositPaymentSessionFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

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
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
        Cache::store('array')->flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_owner_can_create_and_show_customer_deposit_payment_session(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '150000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        $customer = User::query()->findOrFail($customerId);

        $create = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', [
            'row_version' => 1,
            'amount' => 100000,
            'payment_method' => 'Online',
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.deposit.required_amount', '150000.00')
            ->assertJsonPath('data.deposit.outstanding_amount', '150000.00')
            ->assertJsonPath('data.payment_session.amount', '100000.00')
            ->assertJsonPath('data.payment_session.currency', 'VND')
            ->assertJsonPath('data.payment_session.session_status', 'Pending');

        $sessionId = (int) $create->json('data.payment_session.deposit_payment_session_id');

        $show = $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$sessionId);

        $show->assertOk()
            ->assertJsonPath('data.payment_session.deposit_payment_session_id', $sessionId)
            ->assertJsonPath('data.payment_session.session_status', 'Pending');
    }

    public function test_session_linked_customer_can_create_show_and_confirm_deposit_payment_session(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '150000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $sessionId = 'sess-deposit-payment-link';
        $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $customerId,
            'confirmed_reservation_id' => $reservationId,
        ], [$tableId]);

        $create = $this->withHeaders([
            'Idempotency-Key' => 'cust-dep-session-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions?session_id='.$sessionId, [
            'row_version' => 1,
            'amount' => 100000,
            'payment_method' => 'Online',
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.payment_session.amount', '100000.00')
            ->assertJsonPath('data.payment_session.session_status', 'Pending');

        $depositSessionId = (int) $create->json('data.payment_session.deposit_payment_session_id');
        $this->assertSame($customerId, (int) DB::table('reservation_deposit_payment_sessions')->where('deposit_payment_session_id', $depositSessionId)->value('customer_user_id'));
        $this->assertNull(DB::table('reservation_deposit_payment_sessions')->where('deposit_payment_session_id', $depositSessionId)->value('created_by'));

        $show = $this->getJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$depositSessionId);
        $show->assertStatus(401);

        $showWithSession = $this->getJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$depositSessionId.'?session_id='.$sessionId);
        $showWithSession->assertOk()
            ->assertJsonPath('data.payment_session.deposit_payment_session_id', $depositSessionId);

        $confirm = $this->withHeaders([
            'Idempotency-Key' => 'cust-dep-session-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$depositSessionId.'/confirm?session_id='.$sessionId, [
            'row_version' => (int) $create->json('data.payment_session.row_version'),
            'simulation_outcome' => 'succeeded',
        ]);

        $confirm->assertOk()
            ->assertJsonPath('data.payment_session.session_status', 'Succeeded')
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied')
            ->assertJsonPath('data.deposit.paid_amount', '100000.00');

        $this->assertNull(DB::table('reservation_deposit_payment_sessions')->where('deposit_payment_session_id', $depositSessionId)->value('updated_by'));
    }

    public function test_wrong_owner_cannot_access_customer_deposit_payment_session(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
        ]);

        $owner = User::query()->findOrFail($ownerId);
        $other = User::query()->findOrFail($otherId);

        $create = $this->actingAs($owner)->withHeaders([
            'Idempotency-Key' => 'cust-dep-create-owner-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', [
            'row_version' => 1,
            'amount' => 100000,
            'provider_code' => 'simulated',
        ]);

        $sessionId = (int) $create->json('data.payment_session.deposit_payment_session_id');

        $this->actingAs($other)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$sessionId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_unlinked_session_cannot_access_customer_deposit_payment_session(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $sessionId = 'sess-deposit-session-owner';
        $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $ownerId,
            'confirmed_reservation_id' => $reservationId,
        ], [$tableId]);

        $create = $this->withHeaders([
            'Idempotency-Key' => 'cust-dep-session-owner-create-2',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions?session_id='.$sessionId, [
            'row_version' => 1,
            'amount' => 50000,
            'provider_code' => 'simulated',
        ]);

        $depositSessionId = (int) $create->json('data.payment_session.deposit_payment_session_id');

        $this->getJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$depositSessionId.'?session_id=sess-deposit-session-other')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_create_rejects_when_reservation_does_not_require_deposit_or_is_already_fully_paid(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $customer = User::query()->findOrFail($customerId);

        $noDepositReservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
        ]);

        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-no-required-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$noDepositReservationId.'/deposit/payment-sessions', [
            'row_version' => 1,
            'provider_code' => 'simulated',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        $paidReservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '100000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ]);
        $this->createPayment([
            'reservation_id' => $paidReservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-ALREADY-PAID-1',
        ]);

        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-already-paid-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$paidReservationId.'/deposit/payment-sessions', [
            'row_version' => 1,
            'provider_code' => 'simulated',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');
    }

    public function test_create_rejects_when_customer_self_pay_is_intentionally_disabled(): void
    {
        config()->set('booking.payment_providers.customer_self_pay.enabled', false);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $customer = User::query()->findOrFail($customerId);

        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-disabled-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', [
            'row_version' => 1,
            'amount' => 50000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.provider_code.0', 'Customer self-pay is intentionally disabled for this rollout. Use staff settlement.');
    }

    public function test_duplicate_create_with_same_idempotency_key_replays_single_session(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
        ]);
        $customer = User::query()->findOrFail($customerId);

        $payload = [
            'row_version' => 1,
            'amount' => 60000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ];
        $headers = [
            'Idempotency-Key' => 'cust-dep-idem-create-1',
            'Accept' => 'application/json',
        ];

        $first = $this->actingAs($customer)->withHeaders($headers)
            ->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', $payload);
        $second = $this->actingAs($customer)->withHeaders($headers)
            ->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', $payload);

        $first->assertCreated()->assertHeader('Idempotency-Replayed', 'false');
        $second->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame(
            $first->json('data.payment_session.deposit_payment_session_id'),
            $second->json('data.payment_session.deposit_payment_session_id')
        );
        $this->assertSame(
            1,
            DB::table('reservation_deposit_payment_sessions')
                ->where('reservation_id', $reservationId)
                ->count()
        );
    }

    public function test_create_session_service_rejects_same_idempotency_key_when_payload_differs(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);

        /** @var CustomerReservationDepositPaymentService $service */
        $service = app(CustomerReservationDepositPaymentService::class);

        $service->createSession($reservationId, [
            'row_version' => 1,
            'amount' => 50000,
            'payment_method' => 'Online',
            'provider_code' => 'simulated',
            'currency' => 'VND',
            'notes' => 'first deposit session',
        ], $customerId, null, 'cust-dep-service-idem-conflict-1');

        try {
            $service->createSession($reservationId, [
                'row_version' => 1,
                'amount' => 60000,
                'payment_method' => 'Online',
                'provider_code' => 'simulated',
                'currency' => 'VND',
                'notes' => 'different deposit session amount',
            ], $customerId, null, 'cust-dep-service-idem-conflict-1');

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('idempotency_key', $errors);
            $this->assertSame(
                'This idempotency key is already bound to a different deposit payment session request payload.',
                $errors['idempotency_key'][0]
            );
        }

        $this->assertSame(
            1,
            (int) DB::table('reservation_deposit_payment_sessions')
                ->where('reservation_id', $reservationId)
                ->where('customer_user_id', $customerId)
                ->count()
        );
    }

    public function test_create_rejects_provider_session_code_collision_with_existing_bill_payment_session(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $customer = User::query()->findOrFail($customerId);

        DB::table('reservation_bill_payment_sessions')->insert([
            'reservation_id' => $reservationId,
            'order_id' => null,
            'customer_user_id' => $customerId,
            'linked_payment_id' => null,
            'provider_code' => 'simulated',
            'provider_session_code' => 'shared-provider-session-code-1',
            'provider_payment_code' => null,
            'payment_method' => 'Online',
            'amount' => '100000.00',
            'currency' => 'VND',
            'session_status' => 'Pending',
            'settlement_status' => 'NotApplied',
            'failure_code' => null,
            'failure_message' => null,
            'provider_payload_json' => json_encode(['payment_scope' => 'bill'], JSON_THROW_ON_ERROR),
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

        $provider = new class implements CustomerDepositPaymentProvider
        {
            public function code(): string
            {
                return 'simulated';
            }

            public function createSession(Reservation $reservation, int $customerUserId, array $payload): array
            {
                return [
                    'provider_code' => 'simulated',
                    'provider_session_code' => 'shared-provider-session-code-1',
                    'payment_method' => 'Online',
                    'session_status' => 'Pending',
                    'provider_payload' => ['payment_scope' => 'deposit'],
                ];
            }

            public function refreshSession(Reservation $reservation, ReservationDepositPaymentSession $session, array $payload): array
            {
                return [];
            }

            public function confirmSession(Reservation $reservation, ReservationDepositPaymentSession $session, array $payload): array
            {
                return [];
            }
        };

        $registry = Mockery::mock(CustomerDepositPaymentProviderRegistry::class);
        $registry->shouldReceive('resolve')->once()->andReturn($provider);
        $this->app->instance(CustomerDepositPaymentProviderRegistry::class, $registry);

        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-collision-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', [
            'row_version' => 1,
            'amount' => 50000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.provider_session_code.0', 'Provider session code is already bound to an existing [bill] payment session for this provider.');

        $this->assertSame(0, (int) DB::table('reservation_deposit_payment_sessions')->where('reservation_id', $reservationId)->count());
    }

    public function test_successful_confirm_applies_real_deposit_payment_and_confirm_replay_does_not_double_apply(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $tableId = $this->createRestaurantTable(['status' => 'Reserved']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $customer = User::query()->findOrFail($customerId);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $create = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-confirm-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', [
            'row_version' => 1,
            'amount' => 50000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ])->assertCreated();

        $sessionId = (int) $create->json('data.payment_session.deposit_payment_session_id');
        $sessionRowVersion = (int) $create->json('data.payment_session.row_version');
        $beforeVersion = (int) $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes')
            ->assertOk()
            ->json('data.current_version');

        $firstConfirm = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-confirm-1',
            'Accept' => 'application/json',
            'X-Staff-Key' => '',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => $sessionRowVersion,
            'simulation_outcome' => 'succeeded',
        ]);

        $firstConfirm->assertOk()
            ->assertJsonPath('data.deposit.paid_amount', '50000.00')
            ->assertJsonPath('data.deposit.outstanding_amount', '0.00')
            ->assertJsonPath('data.payment_session.session_status', 'Succeeded')
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied');

        $createdLog = $this->assertAuditLogRecorded('payment_session.created', 'reservation', $reservationId);
        self::assertSame($customerId, $createdLog->actor_user_id);
        self::assertSame('customer_account', $createdLog->actor_type);
        $this->assertAuditSubjectRecorded($createdLog, 'deposit_payment_session', $sessionId, 'payment_session');

        $firstChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version='.$beforeVersion);

        $firstChanges->assertOk()->assertJsonPath('data.has_changes', true);
        $event = collect($firstChanges->json('data.events'))->firstWhere('type', 'reservation.deposit_paid');
        self::assertIsArray($event);
        self::assertSame($reservationId, (int) data_get($event, 'payload.reservation_id'));
        self::assertSame([$tableId], array_values((array) data_get($event, 'payload.table_ids', [])));
        self::assertSame('Paid', (string) data_get($event, 'payload.deposit_status'));
        $afterFirstVersion = (int) $firstChanges->json('data.current_version');

        $linkedPaymentId = (int) $firstConfirm->json('data.payment_session.linked_payment_id');
        $nextRowVersion = (int) $firstConfirm->json('data.payment_session.row_version');

        $confirmedLog = $this->assertAuditLogRecorded('payment_session.confirmed', 'reservation', $reservationId);
        self::assertSame($customerId, $confirmedLog->actor_user_id);
        self::assertSame('customer_account', $confirmedLog->actor_type);
        self::assertFalse((bool) data_get($confirmedLog->summary_json, 'replayed_final_state'));
        $this->assertAuditSubjectRecorded($confirmedLog, 'deposit_payment_session', $sessionId, 'payment_session');
        $this->assertAuditSubjectRecorded($confirmedLog, 'payment', $linkedPaymentId, 'payment');

        $secondConfirm = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-confirm-2',
            'Accept' => 'application/json',
            'X-Staff-Key' => '',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => $nextRowVersion,
            'simulation_outcome' => 'succeeded',
        ]);

        $secondConfirm->assertOk()
            ->assertJsonPath('data.payment_session.linked_payment_id', $linkedPaymentId)
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied');

        $replayChanges = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/tables/board/changes?after_version='.$afterFirstVersion);

        $replayChanges->assertOk()
            ->assertJsonPath('data.has_changes', false)
            ->assertJsonCount(0, 'data.events');

        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Deposit')
                ->where('status', 'Success')
                ->count()
        );

        $reservationRow = DB::table('reservations')->where('reservation_id', $reservationId)->first();
        $this->assertSame('Paid', (string) ($reservationRow->deposit_status ?? ''));
        $this->assertSame(50000.0, (float) ($reservationRow->deposit_paid_amount ?? 0.0));
    }

    public function test_terminal_applied_deposit_session_refresh_is_noop_without_recalling_provider(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ]);
        $customer = User::query()->findOrFail($customerId);

        $paymentId = $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'payment_provider' => 'simulated',
            'transaction_code' => 'dep-terminal-refresh-1',
        ]);

        $sessionId = (int) DB::table('reservation_deposit_payment_sessions')->insertGetId([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'linked_payment_id' => $paymentId,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-dep-terminal-refresh-1',
            'provider_payment_code' => 'sim-pay-terminal-refresh-1',
            'payment_method' => 'Online',
            'amount' => '50000.00',
            'currency' => 'VND',
            'session_status' => 'Succeeded',
            'settlement_status' => 'Applied',
            'failure_code' => null,
            'failure_message' => null,
            'provider_payload_json' => json_encode(['payment_scope' => 'deposit'], JSON_THROW_ON_ERROR),
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
            'row_version' => 7,
        ], 'deposit_payment_session_id');

        $registry = Mockery::mock(CustomerDepositPaymentProviderRegistry::class);
        $registry->shouldReceive('resolve')->never();
        $this->app->instance(CustomerDepositPaymentProviderRegistry::class, $registry);

        $refresh = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-terminal-refresh-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$sessionId.'/refresh', [
            'row_version' => 7,
            'simulation_outcome' => 'pending',
        ]);

        $refresh->assertOk()
            ->assertJsonPath('data.payment_session.session_status', 'Succeeded')
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied')
            ->assertJsonPath('data.payment_session.linked_payment_id', $paymentId)
            ->assertJsonPath('data.payment_session.row_version', 7)
            ->assertJsonPath('data.deposit.paid_amount', '50000.00')
            ->assertJsonPath('data.deposit.outstanding_amount', '0.00');

        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Deposit')
                ->count()
        );
    }

    public function test_terminal_succeeded_deposit_session_backfills_settlement_without_recalling_provider(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '70000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $customer = User::query()->findOrFail($customerId);

        $sessionId = (int) DB::table('reservation_deposit_payment_sessions')->insertGetId([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'linked_payment_id' => null,
            'provider_code' => 'simulated',
            'provider_session_code' => 'sim-dep-terminal-backfill-1',
            'provider_payment_code' => 'sim-pay-terminal-backfill-1',
            'payment_method' => 'Online',
            'amount' => '70000.00',
            'currency' => 'VND',
            'session_status' => 'Succeeded',
            'settlement_status' => 'NotApplied',
            'failure_code' => null,
            'failure_message' => null,
            'provider_payload_json' => json_encode(['payment_scope' => 'deposit'], JSON_THROW_ON_ERROR),
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
        ], 'deposit_payment_session_id');

        $registry = Mockery::mock(CustomerDepositPaymentProviderRegistry::class);
        $registry->shouldReceive('resolve')->never();
        $this->app->instance(CustomerDepositPaymentProviderRegistry::class, $registry);

        $confirm = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-terminal-backfill-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => 4,
            'simulation_outcome' => 'failed',
        ]);

        $confirm->assertOk()
            ->assertJsonPath('data.payment_session.session_status', 'Succeeded')
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied')
            ->assertJsonPath('data.deposit.paid_amount', '70000.00')
            ->assertJsonPath('data.deposit.outstanding_amount', '0.00');

        $linkedPaymentId = (int) $confirm->json('data.payment_session.linked_payment_id');
        $this->assertGreaterThan(0, $linkedPaymentId);
        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Deposit')
                ->where('status', 'Success')
                ->count()
        );
        $this->assertSame('Paid', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('deposit_status'));
        $this->assertSame(70000.0, (float) DB::table('reservations')->where('reservation_id', $reservationId)->value('deposit_paid_amount'));
    }

    public function test_pending_or_failed_confirmation_does_not_update_real_paid_state(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '120000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
        ]);
        $customer = User::query()->findOrFail($customerId);

        $create = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-pending-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', [
            'row_version' => 1,
            'amount' => 120000,
            'provider_code' => 'simulated',
        ])->assertCreated();

        $sessionId = (int) $create->json('data.payment_session.deposit_payment_session_id');
        $rowVersion = (int) $create->json('data.payment_session.row_version');

        $pending = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-pending-refresh-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$sessionId.'/refresh', [
            'row_version' => $rowVersion,
            'simulation_outcome' => 'pending',
        ]);

        $pending->assertOk()
            ->assertJsonPath('data.payment_session.session_status', 'Pending')
            ->assertJsonPath('data.payment_session.linked_payment_id', null)
            ->assertJsonPath('data.deposit.paid_amount', '0.00');

        $failed = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-failed-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => (int) $pending->json('data.payment_session.row_version'),
            'simulation_outcome' => 'failed',
        ]);

        $failed->assertOk()
            ->assertJsonPath('data.payment_session.session_status', 'Failed')
            ->assertJsonPath('data.payment_session.linked_payment_id', null)
            ->assertJsonPath('data.deposit.paid_amount', '0.00');

        $this->assertSame(0, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Deposit')->count());
    }

    public function test_customer_generated_deposit_payment_remains_refundable_via_existing_staff_refund_cancel_flow(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '80000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $customer = User::query()->findOrFail($customerId);

        $create = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-refund-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions', [
            'row_version' => 1,
            'amount' => 80000,
            'provider_code' => 'simulated',
        ])->assertCreated();

        $sessionId = (int) $create->json('data.payment_session.deposit_payment_session_id');
        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'cust-dep-refund-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/'.$reservationId.'/deposit/payment-sessions/'.$sessionId.'/confirm', [
            'row_version' => (int) $create->json('data.payment_session.row_version'),
            'simulation_outcome' => 'succeeded',
        ])->assertOk();

        $currentReservationRowVersion = (int) DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->value('row_version');

        $result = $this->makeCheckoutService()->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'deposit',
            refundAmount: 80000.00,
            currency: 'VND',
            transactionCode: 'RF-CUSTOMER-DEP-1',
            paymentProvider: 'Cash',
            notes: 'refund customer deposit',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: $currentReservationRowVersion,
            staffUserId: $staffId,
            idempotencyKey: 'idem-refund-customer-deposit-1'
        );

        $reservation = $result['reservation']->fresh();
        $this->assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertSame('Refunded', (string) ($reservation->deposit_status->value ?? $reservation->deposit_status));
        $this->assertSame(0.0, (float) ($reservation->deposit_paid_amount ?? 0.0));
    }
}
