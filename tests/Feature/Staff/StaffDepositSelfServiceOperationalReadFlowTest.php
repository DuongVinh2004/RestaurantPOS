<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffDepositSelfServiceOperationalReadFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('app.timezone', 'Asia/Bangkok');
        config()->set('booking.check_in_grace_minutes', 15);
        config()->set('booking.no_show_grace_minutes', 15);
    }

    public function test_inbox_surfaces_deposit_self_service_metadata_and_filtering(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'SS-INBOX-01', 'zone' => 'Main']);

        $submittedReservationId = $this->createReservation([
            'reservation_code' => 'SS-INBOX-SUBMITTED',
            'deposit_required_amount' => '150000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'deposit_intent_status' => 'Submitted',
            'deposit_intent_submitted_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($submittedReservationId, $tableId);

        $this->createReservation([
            'reservation_code' => 'SS-INBOX-NONE',
            'deposit_required_amount' => '150000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'deposit_requirement_acknowledged_at' => null,
            'deposit_intent_status' => 'None',
            'deposit_intent_submitted_at' => null,
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations?bucket=all&deposit_acknowledged=1&deposit_intent_status=Submitted');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.filters.deposit_acknowledged', true)
            ->assertJsonPath('meta.filters.deposit_intent_status', 'Submitted')
            ->assertJsonPath('data.0.reservation_id', $submittedReservationId)
            ->assertJsonPath('data.0.deposit_self_service.requirement_acknowledged', true)
            ->assertJsonPath('data.0.deposit_self_service.intent_status', 'Submitted')
            ->assertJsonPath('data.0.deposit_self_service.follow_up.needs_staff_follow_up', true)
            ->assertJsonPath('data.0.summary.deposit_intent_submitted', true);
    }

    public function test_board_surfaces_deposit_self_service_metadata_on_assigned_and_unassigned_reservations(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'SS-BOARD-01', 'zone' => 'Main', 'status' => 'Available']);

        $assignedReservationId = $this->createReservation([
            'reservation_code' => 'SS-BOARD-ASSIGNED',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 10:10:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:10:00', 'UTC'),
            'deposit_required_amount' => '120000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'deposit_intent_status' => 'Submitted',
            'deposit_intent_submitted_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($assignedReservationId, $tableId);

        $unassignedReservationId = $this->createReservation([
            'reservation_code' => 'SS-BOARD-UNASSIGNED',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 10:20:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:20:00', 'UTC'),
            'guest_count' => 2,
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'deposit_intent_status' => 'Revoked',
            'deposit_intent_submitted_at' => $this->nowUtc(),
            'deposit_intent_revoked_at' => $this->nowUtc(),
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/tables/board?from=2026-03-21T09:30:00Z&to=2026-03-21T12:00:00Z&include_holds=0');

        $response->assertOk()
            ->assertJsonPath('summary.deposit_acknowledged_reservation_count', 2)
            ->assertJsonPath('summary.deposit_intent_submitted_reservation_count', 1)
            ->assertJsonPath('summary.deposit_self_service_follow_up_count', 2)
            ->assertJsonPath('data.0.reservation.deposit.self_service.intent_status', 'Submitted')
            ->assertJsonPath('data.0.reservation.flags.deposit_self_service_follow_up', true)
            ->assertJsonPath('data.0.availability.requires_deposit_follow_up', true);

        $unassigned = collect($response->json('unassigned_reservations'))->keyBy('reservation_id');
        self::assertSame('Revoked', data_get($unassigned[$unassignedReservationId], 'deposit.self_service.intent_status'));
        self::assertTrue((bool) data_get($unassigned[$unassignedReservationId], 'flags.deposit_self_service_follow_up'));
    }

    public function test_timeline_surfaces_deposit_self_service_flags_and_optional_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-21 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'SS-TL-01', 'zone' => 'Main']);

        $submittedReservationId = $this->createReservation([
            'reservation_code' => 'SS-TL-SUBMITTED',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 10:10:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:10:00', 'UTC'),
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'deposit_requirement_acknowledged_at' => $this->nowUtc(),
            'deposit_intent_status' => 'Submitted',
            'deposit_intent_submitted_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($submittedReservationId, $tableId);

        $this->createReservation([
            'reservation_code' => 'SS-TL-NONE',
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 10:20:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:20:00', 'UTC'),
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'deposit_requirement_acknowledged_at' => null,
            'deposit_intent_status' => 'None',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations/timeline?date=2026-03-21&deposit_acknowledged=1&deposit_intent_status=Submitted');

        $response->assertOk()
            ->assertJsonPath('meta.filters.deposit_acknowledged', true)
            ->assertJsonPath('meta.filters.deposit_intent_status', 'Submitted')
            ->assertJsonPath('data.summary.total_reservations', 1)
            ->assertJsonPath('data.summary.flag_counts.deposit_acknowledged', 1)
            ->assertJsonPath('data.summary.flag_counts.deposit_intent_submitted', 1)
            ->assertJsonPath('data.summary.flag_counts.deposit_self_service_follow_up', 1)
            ->assertJsonPath('data.items.0.reservation.reservation_id', $submittedReservationId)
            ->assertJsonPath('data.items.0.deposit.self_service.intent_status', 'Submitted')
            ->assertJsonPath('data.items.0.flags.deposit_acknowledged', true)
            ->assertJsonPath('data.items.0.flags.deposit_intent_submitted', true)
            ->assertJsonPath('data.items.0.flags.deposit_self_service_follow_up', true);
    }
}
