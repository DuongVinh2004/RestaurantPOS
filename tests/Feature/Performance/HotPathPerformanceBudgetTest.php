<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\User;
use App\Modules\CheckoutPayments\Application\Services\StaffCheckoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\Support\ProfilesDatabaseQueries;
use Tests\TestCase;

class HotPathPerformanceBudgetTest extends TestCase
{
    use BuildsBookingScenario;
    use ProfilesDatabaseQueries;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.allow_role_name_fallback', true);
        config()->set('staff_auth.allowed_role_names', ['Admin', 'Staff']);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_staff_table_board_candidate_preview_stays_within_query_budget(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'perf-board');
        $windowStart = Carbon::now('UTC')->copy()->subHour();
        $windowEnd = Carbon::now('UTC')->copy()->addHours(2);

        $this->seedBoardCandidateScenario();

        $profile = $this->profileQueries(fn () => $this->withHeaders($headers)->getJson(
            '/api/v1/staff/tables/board?from=' . urlencode($windowStart->toIso8601String()) . '&to=' . urlencode($windowEnd->toIso8601String())
        ));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            22,
            $profile['query_count'],
            sprintf(
                'board metrics=%s',
                json_encode([
                    'query_count' => $profile['query_count'],
                    'sql_time_ms' => $profile['sql_time_ms'],
                    'wall_time_ms' => $profile['wall_time_ms'],
                    'query_patterns' => $profile['query_patterns'],
                ], JSON_THROW_ON_ERROR)
            )
        );
    }

    public function test_staff_timeline_candidate_preview_stays_within_query_budget(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'perf-timeline');

        $this->seedBoardCandidateScenario();

        $profile = $this->profileQueries(fn () => $this->withHeaders($headers)->getJson(
            '/api/v1/staff/reservations/timeline?date=2026-04-05&lane_by=table&include_candidate_tables=1'
        ));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            19,
            $profile['query_count'],
            sprintf(
                'timeline metrics=%s',
                json_encode([
                    'query_count' => $profile['query_count'],
                    'sql_time_ms' => $profile['sql_time_ms'],
                    'wall_time_ms' => $profile['wall_time_ms'],
                    'query_patterns' => $profile['query_patterns'],
                ], JSON_THROW_ON_ERROR)
            )
        );
    }

    public function test_customer_active_order_read_stays_within_query_budget(): void
    {
        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true);
        $customer = User::query()->findOrFail($customerId);

        $profile = $this->profileQueries(fn () => $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/' . $reservationId . '/active-order'));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            10,
            $profile['query_count'],
            sprintf(
                'active_order metrics=%s',
                json_encode([
                    'query_count' => $profile['query_count'],
                    'sql_time_ms' => $profile['sql_time_ms'],
                    'wall_time_ms' => $profile['wall_time_ms'],
                    'query_patterns' => $profile['query_patterns'],
                ], JSON_THROW_ON_ERROR)
            )
        );
    }

    public function test_customer_bill_preview_stays_within_query_budget(): void
    {
        [$customerId, , $reservationId] = $this->seedInServiceOrderScenario(lockBill: true);
        $customer = User::query()->findOrFail($customerId);

        $profile = $this->profileQueries(fn () => $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/' . $reservationId . '/bill-preview'));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            16,
            $profile['query_count'],
            sprintf(
                'bill_preview metrics=%s',
                json_encode([
                    'query_count' => $profile['query_count'],
                    'sql_time_ms' => $profile['sql_time_ms'],
                    'wall_time_ms' => $profile['wall_time_ms'],
                    'query_patterns' => $profile['query_patterns'],
                ], JSON_THROW_ON_ERROR)
            )
        );
    }

    public function test_staff_active_order_by_table_stays_within_query_budget(): void
    {
        [, $staffId, $reservationId] = $this->seedInServiceOrderScenario(lockBill: false);
        $tableId = (int) DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->value('table_id');

        $profile = $this->profileQueries(fn () => $this
            ->withHeaders($this->staffAuthHeaders($staffId, 'perf-active-order-staff'))
            ->getJson('/api/v1/staff/tables/' . $tableId . '/active-order'));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            19,
            $profile['query_count'],
            sprintf(
                'staff_active_order_by_table metrics=%s',
                json_encode([
                    'query_count' => $profile['query_count'],
                    'sql_time_ms' => $profile['sql_time_ms'],
                    'wall_time_ms' => $profile['wall_time_ms'],
                    'query_patterns' => $profile['query_patterns'],
                ], JSON_THROW_ON_ERROR)
            )
        );
    }

    public function test_staff_settlement_preview_stays_within_query_budget(): void
    {
        [, $staffId, , $orderId] = $this->seedInServiceOrderScenario(lockBill: false);

        $profile = $this->profileQueries(fn () => $this
            ->withHeaders($this->staffAuthHeaders($staffId, 'perf-settlement-preview'))
            ->getJson('/api/v1/staff/orders/' . $orderId . '/settlement-preview'));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            16,
            $profile['query_count'],
            sprintf(
                'staff_settlement_preview metrics=%s',
                json_encode([
                    'query_count' => $profile['query_count'],
                    'sql_time_ms' => $profile['sql_time_ms'],
                    'wall_time_ms' => $profile['wall_time_ms'],
                    'query_patterns' => $profile['query_patterns'],
                ], JSON_THROW_ON_ERROR)
            )
        );
    }

    private function seedBoardCandidateScenario(): void
    {
        $now = Carbon::now('UTC')->startOfMinute();

        for ($index = 1; $index <= 12; $index++) {
            $this->createRestaurantTableWithSeats(4 + ($index % 4), [
                'table_code' => sprintf('PF-%02d', $index),
                'zone' => $index <= 10 ? 'Main' : 'Patio',
                'status' => 'Available',
            ]);
        }

        for ($index = 1; $index <= 6; $index++) {
            $this->createReservation([
                'reservation_code' => sprintf('PF-UN-%02d', $index),
                'status' => 'Confirmed',
                'guest_count' => 2 + ($index % 4),
                'start_time' => $now->copy()->addMinutes(5 + ($index * 5)),
                'end_time' => $now->copy()->addMinutes(65 + ($index * 5)),
            ]);
        }

        for ($index = 1; $index <= 3; $index++) {
            $tableId = (int) $index;
            $reservationId = $this->createReservation([
                'reservation_code' => sprintf('PF-AS-%02d', $index),
                'status' => 'Confirmed',
                'guest_count' => 4,
                'start_time' => $now->copy()->addMinutes(15),
                'end_time' => $now->copy()->addMinutes(95),
            ]);
            $this->attachReservationTable($reservationId, $tableId);
        }

        $this->createTableHold([
            'start_time' => $now->copy()->addMinutes(20),
            'end_time' => $now->copy()->addMinutes(80),
            'expire_at' => $now->copy()->addMinutes(90),
            'hold_status' => 'Holding',
        ], [4, 5]);
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function seedInServiceOrderScenario(bool $lockBill): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
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
            app(StaffCheckoutService::class)->lockBill(
                orderId: $orderId,
                discountAmount: null,
                notes: 'lock bill for performance profiling',
                expectedRowVersion: 1,
                staffUserId: $staffId,
            );
        }

        return [$customerId, $staffId, $reservationId, $orderId];
    }
}
