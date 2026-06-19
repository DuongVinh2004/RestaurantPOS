<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationDepositSelfServiceFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();

        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_owner_can_acknowledge_deposit_requirement(): void
    {
        [$user, $reservationId] = $this->seedOwnedPendingDepositReservation();

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'idem-deposit-ack-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/acknowledge", [
                'row_version' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.action', 'customer_reservation_deposit_acknowledge')
            ->assertJsonPath('data.reservation.row_version', 2)
            ->assertJsonPath('data.deposit.self_service.requirement_acknowledged', true)
            ->assertJsonPath('data.deposit.self_service.intent_status', 'None')
            ->assertJsonPath('data.deposit.self_service.can_submit_intent', true);

        $record = $this->table('reservations')->where('reservation_id', $reservationId)->first();
        $this->assertNotNull($record->deposit_requirement_acknowledged_at);
        $this->assertSame('None', (string) $record->deposit_intent_status);
        $this->assertSame(2, (int) $record->row_version);
    }

    public function test_owner_can_submit_deposit_intent_after_acknowledgement(): void
    {
        [$user, $reservationId] = $this->seedOwnedPendingDepositReservation([
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'row_version' => 2,
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'idem-deposit-intent-submit-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/intent", [
                'row_version' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.action', 'customer_reservation_deposit_submit_intent')
            ->assertJsonPath('data.reservation.row_version', 3)
            ->assertJsonPath('data.deposit.self_service.intent_status', 'Submitted')
            ->assertJsonPath('data.deposit.self_service.can_revoke_intent', true)
            ->assertJsonPath('data.deposit.self_service.next_step', 'awaiting_staff_payment_collection');

        $record = $this->table('reservations')->where('reservation_id', $reservationId)->first();
        $this->assertSame('Submitted', (string) $record->deposit_intent_status);
        $this->assertNotNull($record->deposit_intent_submitted_at);
        $this->assertNull($record->deposit_intent_revoked_at);
        $this->assertSame(3, (int) $record->row_version);
    }

    public function test_owner_can_revoke_submitted_deposit_intent_when_no_payment_recorded(): void
    {
        [$user, $reservationId] = $this->seedOwnedPendingDepositReservation([
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'deposit_intent_status' => 'Submitted',
            'deposit_intent_submitted_at' => $this->nowUtc(),
            'row_version' => 3,
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'idem-deposit-intent-revoke-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/intent/revoke", [
                'row_version' => 3,
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.action', 'customer_reservation_deposit_revoke_intent')
            ->assertJsonPath('data.deposit.self_service.intent_status', 'Revoked')
            ->assertJsonPath('data.deposit.self_service.can_submit_intent', true)
            ->assertJsonPath('data.deposit.self_service.next_step', 'customer_intent_revoked');

        $record = $this->table('reservations')->where('reservation_id', $reservationId)->first();
        $this->assertSame('Revoked', (string) $record->deposit_intent_status);
        $this->assertNotNull($record->deposit_intent_revoked_at);
        $this->assertSame(4, (int) $record->row_version);
    }

    public function test_other_customer_cannot_mutate_owned_reservation_deposit_self_service_state(): void
    {
        [$owner, $reservationId] = $this->seedOwnedPendingDepositReservation();
        $other = User::query()->findOrFail($this->createUser(['role_name' => 'Customer']));

        $response = $this->actingAs($other)
            ->withHeaders(['Idempotency-Key' => 'idem-deposit-ack-other-user'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/acknowledge", [
                'row_version' => 1,
            ]);

        $response->assertNotFound();
        $this->assertNull($this->table('reservations')->where('reservation_id', $reservationId)->value('deposit_requirement_acknowledged_at'));
    }

    public function test_submit_intent_requires_prior_acknowledgement(): void
    {
        [$user, $reservationId] = $this->seedOwnedPendingDepositReservation();

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'idem-deposit-intent-needs-ack'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/intent", [
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_revoke_intent_is_rejected_after_actual_deposit_payment_exists(): void
    {
        [$user, $reservationId] = $this->seedOwnedPendingDepositReservation([
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'deposit_intent_status' => 'Submitted',
            'deposit_intent_submitted_at' => $this->nowUtc(),
            'deposit_paid_amount' => '50000',
            'deposit_status' => 'Pending',
            'row_version' => 3,
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000',
            'payment_provider' => 'POS',
            'payment_method' => 'Cash',
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'idem-deposit-intent-revoke-paid'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/intent/revoke", [
                'row_version' => 3,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_acknowledgement_is_rejected_for_cancelled_reservation(): void
    {
        [$user, $reservationId] = $this->seedOwnedPendingDepositReservation([
            'status' => 'Cancelled',
            'cancelled_at' => $this->nowUtc(),
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'idem-deposit-ack-cancelled'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/acknowledge", [
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_session_linked_customer_can_acknowledge_submit_and_revoke_deposit_intent_without_impersonating_owner(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $sessionId = 'sess-deposit-self-service-flow';
        $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $ownerId,
            'confirmed_reservation_id' => $reservationId,
        ], [$tableId]);

        $ack = $this->withHeaders(['Idempotency-Key' => 'idem-deposit-session-ack-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/acknowledge?session_id={$sessionId}", [
                'row_version' => 1,
            ]);

        $ack->assertOk()
            ->assertJsonPath('data.deposit.self_service.requirement_acknowledged', true);

        $submit = $this->withHeaders(['Idempotency-Key' => 'idem-deposit-session-submit-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/intent?session_id={$sessionId}", [
                'row_version' => 2,
            ]);

        $submit->assertOk()
            ->assertJsonPath('data.deposit.self_service.intent_status', 'Submitted');

        $revoke = $this->withHeaders(['Idempotency-Key' => 'idem-deposit-session-revoke-1'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/intent/revoke?session_id={$sessionId}", [
                'row_version' => 3,
            ]);

        $revoke->assertOk()
            ->assertJsonPath('data.deposit.self_service.intent_status', 'Revoked');

        $record = $this->table('reservations')->where('reservation_id', $reservationId)->first();
        $this->assertSame('Revoked', (string) $record->deposit_intent_status);
        $this->assertNull($record->updated_by);
    }

    public function test_unlinked_session_cannot_mutate_reservation_deposit_self_service_state(): void
    {
        [$owner, $reservationId] = $this->seedOwnedPendingDepositReservation();
        $response = $this->withHeaders(['Idempotency-Key' => 'idem-deposit-session-unlinked-ack'])
            ->postJson("/api/v1/reservations/{$reservationId}/deposit/acknowledge?session_id=sess-unlinked-deposit", [
                'row_version' => 1,
            ]);

        $response->assertNotFound();
        $this->assertNull($this->table('reservations')->where('reservation_id', $reservationId)->value('deposit_requirement_acknowledged_at'));
    }

    public function test_customer_deposit_self_service_mutations_require_idempotency_key(): void
    {
        [$user, $reservationId] = $this->seedOwnedPendingDepositReservation([
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'deposit_intent_status' => 'Submitted',
            'deposit_intent_submitted_at' => $this->nowUtc(),
            'row_version' => 3,
        ]);

        $cases = [
            [
                'uri' => "/api/v1/reservations/{$reservationId}/deposit/acknowledge",
                'payload' => ['row_version' => 3],
            ],
            [
                'uri' => "/api/v1/reservations/{$reservationId}/deposit/intent",
                'payload' => ['row_version' => 3],
            ],
            [
                'uri' => "/api/v1/reservations/{$reservationId}/deposit/intent/revoke",
                'payload' => ['row_version' => 3],
            ],
        ];

        foreach ($cases as $case) {
            $response = $this->actingAs($user)->postJson($case['uri'], $case['payload']);

            $response->assertStatus(422)
                ->assertJsonPath('error', 'idempotency_key_required');
        }
    }

    public function test_staff_cannot_use_customer_deposit_self_service_mutation_endpoints(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'deposit_required_amount' => '50000',
            'deposit_status' => 'Pending',
        ]);
        config()->set('staff_auth.api_keys', ['customer-deposit-self-service-staff-key' => $staffId]);

        $response = $this->withHeaders([
            'X-Staff-Key' => 'customer-deposit-self-service-staff-key',
            'Idempotency-Key' => 'idem-staff-should-not-ack',
        ])->postJson("/api/v1/reservations/{$reservationId}/deposit/acknowledge", [
            'row_version' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_customer_deposit_preview_surfaces_self_service_state_after_ack_and_intent(): void
    {
        [$user, $reservationId] = $this->seedOwnedPendingDepositReservation([
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'deposit_intent_status' => 'Submitted',
            'deposit_intent_submitted_at' => $this->nowUtc(),
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/reservations/{$reservationId}/deposit-preview");

        $response->assertOk()
            ->assertJsonPath('meta.intent_supported', true)
            ->assertJsonPath('data.deposit.self_service.requirement_acknowledged', true)
            ->assertJsonPath('data.deposit.self_service.intent_status', 'Submitted')
            ->assertJsonPath('data.deposit.self_service.can_revoke_intent', true)
            ->assertJsonPath('data.deposit.self_service.requires_staff_payment_collection', true);
    }

    public function test_staff_deposit_preview_sees_customer_acknowledgement_and_intent_without_regressing_existing_flow(): void
    {
        [, $reservationId] = $this->seedDepositReservation([
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'deposit_intent_status' => 'Submitted',
            'deposit_intent_submitted_at' => $this->nowUtc(),
        ]);
        $cashierId = $this->createUser(['role_name' => 'Cashier']);

        $response = $this->withHeaders($this->staffHeaders($cashierId, 'staff-deposit-preview-self-service-key'))
            ->getJson("/api/v1/staff/reservations/{$reservationId}/deposit-preview");

        $response->assertOk()
            ->assertJsonPath('data.deposit.self_service.requirement_acknowledged', true)
            ->assertJsonPath('data.deposit.self_service.intent_status', 'Submitted')
            ->assertJsonPath('data.deposit.self_service.can_revoke_intent', true)
            ->assertJsonPath('data.deposit.can_accept_payment', true);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array{0:User,1:int}
     */
    private function seedOwnedPendingDepositReservation(array $overrides = []): array
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation(array_merge([
            'user_id' => $userId,
            'deposit_required_amount' => '100000',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
            'row_version' => 1,
        ], $overrides));
        $this->attachReservationTable($reservationId);

        return [User::query()->findOrFail($userId), $reservationId];
    }
}
