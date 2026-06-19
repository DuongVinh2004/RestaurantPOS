<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffReservationInboxFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_staff_can_list_upcoming_reservations_with_pagination_meta(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $tableA = $this->createRestaurantTableWithSeats(4, ['table_code' => 'A-01', 'zone' => 'Main']);
        $tableB = $this->createRestaurantTableWithSeats(6, ['table_code' => 'B-02', 'zone' => 'Patio']);

        $upcomingReservationId = $this->createReservation([
            'reservation_code' => 'INBOX-UPCOMING-01',
        ]);
        $this->attachReservationTable($upcomingReservationId, $tableA);

        $checkedInReservationId = $this->createReservation([
            'reservation_code' => 'INBOX-CHECKEDIN-01',
            'status' => 'Reserved',
            'start_time' => $this->nowUtc()->copy()->subMinutes(30),
            'end_time' => $this->nowUtc()->copy()->addHour(),
        ]);
        $this->attachReservationTable($checkedInReservationId, $tableB);

        $historyReservationId = $this->createReservation([
            'reservation_code' => 'INBOX-HISTORY-01',
            'status' => 'Completed',
            'start_time' => $this->nowUtc()->copy()->subDays(2)->setHour(18),
            'end_time' => $this->nowUtc()->copy()->subDays(2)->setHour(20),
        ]);
        $this->attachReservationTable($historyReservationId, $tableA);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations?bucket=upcoming&per_page=50&reservation_code=INBOX-');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('meta.filters.bucket', 'upcoming');
        $response->assertJsonCount(2, 'data');

        $reservationIds = collect($response->json('data'))->pluck('reservation_id')->all();
        self::assertContains($upcomingReservationId, $reservationIds);
        self::assertContains($checkedInReservationId, $reservationIds);
        self::assertNotContains($historyReservationId, $reservationIds);
    }

    public function test_staff_can_filter_reservations_by_table_status_and_code(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $targetTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'VIP-09']);
        $otherTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'STD-01']);

        $matchReservationId = $this->createReservation([
            'reservation_code' => 'VIP-MATCH-001',
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($matchReservationId, $targetTableId);

        $otherReservationId = $this->createReservation([
            'reservation_code' => 'VIP-OTHER-002',
            'status' => 'Cancelled',
        ]);
        $this->attachReservationTable($otherReservationId, $otherTableId);

        $response = $this->withHeaders($headers)->getJson(sprintf(
            '/api/v1/staff/reservations?bucket=all&status=Confirmed&table_id=%d&reservation_code=VIP-MATCH',
            $targetTableId,
        ));

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.reservation_id', $matchReservationId);
        $response->assertJsonPath('data.0.reservation_code', 'VIP-MATCH-001');
        $response->assertJsonMissingPath('data.1');
        self::assertNotSame($matchReservationId, $otherReservationId);
    }

    public function test_staff_can_search_reservations_by_customer_identity_fields(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $targetUserId = $this->createUser([
            'full_name' => 'Nguyen Thi Searchable',
            'phone' => '0901123456',
            'email' => 'searchable@example.com',
        ]);
        $otherUserId = $this->createUser([
            'full_name' => 'Tran Van Other',
            'phone' => '0909988776',
            'email' => 'other@example.com',
        ]);

        $tableId = $this->createRestaurantTableWithSeats(4);

        $targetReservationId = $this->createReservation([
            'user_id' => $targetUserId,
            'reservation_code' => 'RSV-SEARCH-001',
        ]);
        $this->attachReservationTable($targetReservationId, $tableId);

        $otherReservationId = $this->createReservation([
            'user_id' => $otherUserId,
            'reservation_code' => 'RSV-SEARCH-002',
        ]);
        $this->attachReservationTable($otherReservationId, $tableId);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations?bucket=all&q=0901123456');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.reservation_id', $targetReservationId);
        $response->assertJsonPath('data.0.user.full_name', 'Nguyen Thi Searchable');
        self::assertNotSame($targetReservationId, $otherReservationId);
    }

    public function test_staff_inbox_search_matches_guest_snapshot_reservations(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);
        $tableId = $this->createRestaurantTableWithSeats(4);

        $targetReservationId = $this->createReservation([
            'user_id' => null,
            'guest_name' => 'Guest Caller',
            'guest_phone' => '0903344556',
            'guest_email' => 'guest.caller@example.test',
            'reservation_code' => 'RSV-GUEST-SEARCH-001',
            'source' => 'Offline',
        ]);
        $this->attachReservationTable($targetReservationId, $tableId);

        $otherReservationId = $this->createReservation([
            'reservation_code' => 'RSV-GUEST-SEARCH-002',
        ]);
        $this->attachReservationTable($otherReservationId, $tableId);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations?bucket=all&q=0903344556');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reservation_id', $targetReservationId)
            ->assertJsonPath('data.0.user.user_id', null)
            ->assertJsonPath('data.0.user.full_name', 'Guest Caller')
            ->assertJsonPath('data.0.user.phone', '0903344556')
            ->assertJsonPath('data.0.user.email', 'guest.caller@example.test')
            ->assertJsonPath('data.0.guest.full_name', 'Guest Caller');

        self::assertNotSame($targetReservationId, $otherReservationId);
    }

    public function test_staff_can_include_financial_fields_in_inbox_response(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);

        $tableId = $this->createRestaurantTableWithSeats(4);
        $reservationId = $this->createReservation([
            'reservation_code' => 'RSV-MONEY-001',
            'deposit_required_amount' => '200000',
            'deposit_paid_amount' => '50000',
            'discount_amount' => '10000',
            'final_bill_amount' => '350000',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'amount' => '50000',
            'payment_type' => 'Deposit',
            'status' => 'Success',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations?bucket=all&reservation_code=RSV-MONEY-001&include_financials=1');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.reservation_id', $reservationId);
        $response->assertJsonPath('data.0.financials.deposit_required_amount', '200000');
        $response->assertJsonPath('data.0.financials.payment_summary.captured_total', '50000');
        $response->assertJsonPath('data.0.financials.payment_summary.deposit_net', '50000');
    }

    public function test_staff_history_bucket_includes_terminal_future_reservations(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'HX-01']);

        $cancelledFutureReservationId = $this->createReservation([
            'reservation_code' => 'RSV-FUTURE-CANCELLED',
            'status' => 'Cancelled',
            'start_time' => $this->nowUtc()->copy()->addDay()->setHour(18),
            'end_time' => $this->nowUtc()->copy()->addDay()->setHour(20),
            'cancelled_at' => $this->nowUtc(),
        ]);
        $this->attachReservationTable($cancelledFutureReservationId, $tableId);

        $upcomingResponse = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations?bucket=upcoming&reservation_code=RSV-FUTURE-CANCELLED');
        $upcomingResponse->assertOk()->assertJsonPath('meta.total', 0);

        $historyResponse = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations?bucket=history&reservation_code=RSV-FUTURE-CANCELLED');
        $historyResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reservation_id', $cancelledFutureReservationId);
    }

    public function test_staff_today_bucket_uses_branch_operational_timezone_day_window(): void
    {
        config()->set('app.timezone', 'UTC');
        config()->set('booking.multi_branch.default_branch_timezone', 'Asia/Ho_Chi_Minh');

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'TZ-01']);

        $localStart = now('Asia/Ho_Chi_Minh')->startOfDay()->addMinutes(30);
        $localEnd = $localStart->copy()->addHour();

        $reservationId = $this->createReservation([
            'reservation_code' => 'RSV-TODAY-TZ-001',
            'status' => 'Confirmed',
            'start_time' => $localStart->copy()->utc(),
            'end_time' => $localEnd->copy()->utc(),
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($headers)->getJson('/api/v1/staff/reservations?bucket=today&reservation_code=RSV-TODAY-TZ-001');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reservation_id', $reservationId);
    }
}
