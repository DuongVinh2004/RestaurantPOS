<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationSelfServiceVisibilityAndGuardTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_owner_list_exposes_customer_facing_operational_fields_without_staff_only_data(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $user = User::query()->findOrFail($userId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['zone' => 'Main Hall']);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'start_time' => $this->nowUtc()->copy()->addHours(5),
            'end_time' => $this->nowUtc()->copy()->addHours(7),
            'status' => 'Confirmed',
            'deposit_status' => 'Required',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '20000.00',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '20000.00',
            'payment_provider' => 'Cash',
            'payment_method' => 'Cash',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/reservations?bucket=all');

        $response->assertOk()
            ->assertJsonPath('meta.access_scope', 'owner')
            ->assertJsonPath('data.0.reservation_id', $reservationId)
            ->assertJsonPath('data.0.user.email', (string) $user->email)
            ->assertJsonPath('data.0.status_flags.is_active', true)
            ->assertJsonPath('data.0.table_summary.count', 1)
            ->assertJsonPath('data.0.table_summary.zones.0', 'Main Hall')
            ->assertJsonPath('data.0.deposit_summary.status', 'Required')
            ->assertJsonPath('data.0.deposit_summary.outstanding_amount', '30000.00')
            ->assertJsonPath('data.0.customer_self_service.scope', 'owner')
            ->assertJsonPath('data.0.customer_self_service.can_attempt_cancel', true)
            ->assertJsonPath('data.0.customer_self_service.can_attempt_reschedule', true);

        self::assertNotNull($response->json('data.0.booking_time'));
        self::assertNull($response->json('data.0.payments.0.provider_response_json'));
    }

    public function test_session_list_redacts_financial_details_but_keeps_safe_operational_fields(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['zone' => 'Patio']);
        $start = $this->nowUtc()->copy()->addHours(6);
        $end = $start->copy()->addHours(2);
        $sessionId = 'sess-customer-list-visibility-001';

        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'Confirmed',
            'deposit_status' => 'Required',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '20000.00',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'confirmed_reservation_id' => $reservationId,
            'hold_status' => 'Confirmed',
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
        ], [$tableId]);

        $response = $this->getJson('/api/v1/reservations?bucket=all&session_id=' . $sessionId);

        $response->assertOk()
            ->assertJsonPath('meta.access_scope', 'session')
            ->assertJsonPath('data.0.reservation_id', $reservationId)
            ->assertJsonPath('data.0.user.email', null)
            ->assertJsonPath('data.0.deposit_summary', null)
            ->assertJsonPath('data.0.payment_summary', null)
            ->assertJsonPath('data.0.table_summary.count', 1)
            ->assertJsonPath('data.0.table_summary.zones.0', 'Patio')
            ->assertJsonPath('data.0.customer_self_service.scope', 'session');
    }

    public function test_owner_list_uses_branch_local_self_service_cutoff_overrides(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'DAL01',
            'timezone' => 'America/Chicago',
            'booking_policy' => [
                'reservation' => [
                    'cancellation_cutoff_minutes' => 600,
                    'reschedule_cutoff_minutes' => 600,
                ],
            ],
        ]);
        $userId = $this->createUser(['role_name' => 'Customer']);
        $user = User::query()->findOrFail($userId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['branch_id' => $branchId, 'zone' => 'Branch Zone']);
        $start = $this->nowUtc()->copy()->addHours(8);
        $end = $start->copy()->addHours(2);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'branch_id' => $branchId,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->actingAs($user)->getJson('/api/v1/reservations?bucket=all');

        $response->assertOk()
            ->assertJsonPath('data.0.branch_id', $branchId)
            ->assertJsonPath('data.0.customer_self_service.can_attempt_cancel', false)
            ->assertJsonPath('data.0.customer_self_service.can_attempt_reschedule', false);
    }

    public function test_show_requires_authenticated_owner_or_valid_session(): void
    {
        $reservationId = $this->createReservation([
            'user_id' => $this->createUser(['role_name' => 'Customer']),
            'start_time' => $this->nowUtc()->copy()->addHours(4),
            'end_time' => $this->nowUtc()->copy()->addHours(6),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));

        $response = $this->getJson('/api/v1/reservations/' . $reservationId);

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_staff_override_can_read_reservation_show_route_when_staff_auth_is_present(): void
    {
        $ownerUserId = $this->createUser(['role_name' => 'Customer']);
        $owner = User::query()->findOrFail($ownerUserId);
        $staffUserId = $this->createUser(['role_name' => 'Admin']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerUserId,
            'start_time' => $this->nowUtc()->copy()->addHours(5),
            'end_time' => $this->nowUtc()->copy()->addHours(7),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4, ['zone' => 'VIP']));

        $response = $this->withHeaders($this->staffAuthHeaders($staffUserId))
            ->getJson('/api/v1/reservations/' . $reservationId);

        $response->assertOk()
            ->assertJsonPath('data.access_scope', 'staff')
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.user.user_id', $ownerUserId)
            ->assertJsonPath('data.user.email', (string) $owner->email)
            ->assertJsonPath('data.table_summary.zones.0', 'VIP');
    }

    public function test_staff_actor_is_rejected_from_customer_self_service_mutation_endpoint(): void
    {
        $ownerUserId = $this->createUser(['role_name' => 'Customer']);
        $staffUserId = $this->createUser(['role_name' => 'Admin']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerUserId,
            'start_time' => $this->nowUtc()->copy()->addHours(4),
            'end_time' => $this->nowUtc()->copy()->addHours(6),
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4));

        $response = $this->withHeaders($this->withIdempotencyKey('customer-self-service-staff-block', $this->staffAuthHeaders($staffUserId)))
            ->postJson('/api/v1/reservations/' . $reservationId . '/cancel', [
                'row_version' => 1,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_pre_resolved_staff_user_is_not_treated_as_customer_owner_on_shared_customer_routes(): void
    {
        $staffUserId = $this->createUser(['role_name' => 'Staff']);
        $staff = User::query()->findOrFail($staffUserId);

        $response = $this->actingAs($staff)->getJson('/api/v1/reservations');

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_owner_reschedule_rejects_stale_row_version_from_self_service(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $user = User::query()->findOrFail($userId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'start_time' => $this->nowUtc()->copy()->addHours(6),
            'end_time' => $this->nowUtc()->copy()->addHours(8),
            'guest_count' => 2,
            'status' => 'Confirmed',
            'row_version' => 2,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $newStart = $this->nowUtc()->copy()->addHours(9);
        $newEnd = $newStart->copy()->addHours(2);

        $response = $this->actingAs($user)->postJson(
            '/api/v1/reservations/' . $reservationId . '/reschedule',
            [
                'row_version' => 1,
                'start_time' => $newStart->toIso8601String(),
                'end_time' => $newEnd->toIso8601String(),
            ],
            $this->withIdempotencyKey('customer-self-service-reschedule-stale-row-version')
        );

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('details.errors.row_version.0', 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.');
    }

    public function test_owner_cannot_cancel_checked_in_reservation_from_self_service(): void
    {
        $userId = $this->createUser(['role_name' => 'Customer']);
        $user = User::query()->findOrFail($userId);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'start_time' => $this->nowUtc()->copy()->addHours(2),
            'end_time' => $this->nowUtc()->copy()->addHours(4),
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(5),
            'row_version' => 2,
        ]);
        $this->attachReservationTable($reservationId, $this->createRestaurantTableWithSeats(4, ['status' => 'Occupied']));

        $response = $this->actingAs($user)->postJson(
            '/api/v1/reservations/' . $reservationId . '/cancel',
            ['row_version' => 2],
            $this->withIdempotencyKey('customer-self-service-cancel-checked-in')
        );

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.status.0', 'Only Confirmed reservations can be cancelled by the customer self-service flow.');
    }
}
