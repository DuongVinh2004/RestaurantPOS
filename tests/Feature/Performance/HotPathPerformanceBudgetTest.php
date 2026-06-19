<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\Support\ProfilesDatabaseQueries;
use Tests\TestCase;

class HotPathPerformanceBudgetTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;
    use ProfilesDatabaseQueries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        /** @var Repository $config */
        $config = config();
        $config->set('booking.require_redis_for_booking_api', false);
        $config->set('staff_auth.database_store_enabled', false);
        $config->set('staff_auth.allow_env_fallback', true);
        $config->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        $config->set('staff_auth.allow_role_name_fallback', true);
        $config->set('staff_auth.allowed_role_names', ['Admin', 'Staff']);
        $config->set('staff_auth.api_keys', []);
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
            '/api/v1/staff/tables/board?from='.urlencode($windowStart->toIso8601String()).'&to='.urlencode($windowEnd->toIso8601String())
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
            20,
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
            ->getJson('/api/v1/reservations/'.$reservationId.'/active-order'));

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
            ->getJson('/api/v1/reservations/'.$reservationId.'/bill-preview'));

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
            ->getJson('/api/v1/staff/tables/'.$tableId.'/active-order'));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            30,
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
        [, , , $orderId] = $this->seedInServiceOrderScenario(lockBill: false);
        $cashierId = $this->createUser(['role_name' => 'Cashier']);

        $profile = $this->profileQueries(fn () => $this
            ->withHeaders($this->staffAuthHeaders($cashierId, 'perf-settlement-preview'))
            ->getJson('/api/v1/staff/orders/'.$orderId.'/settlement-preview'));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            26,
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

    public function test_staff_waiting_list_index_stays_within_query_budget(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->staffAuthHeaders($staffId, 'perf-waiting-list');

        $this->seedWaitingListQueueScenario();

        $profile = $this->profileQueries(fn () => $this->withHeaders($headers)->getJson(
            '/api/v1/staff/waiting-list?page=1&per_page=10&active_only=1'
        ));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            18,
            $profile['query_count'],
            sprintf(
                'staff_waiting_list_index metrics=%s',
                json_encode([
                    'query_count' => $profile['query_count'],
                    'sql_time_ms' => $profile['sql_time_ms'],
                    'wall_time_ms' => $profile['wall_time_ms'],
                    'query_patterns' => $profile['query_patterns'],
                ], JSON_THROW_ON_ERROR)
            )
        );
    }

    public function test_customer_reservation_list_stays_within_query_budget(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 10:00:00', 'UTC'));

        [$customerId] = $this->seedCustomerReservationReadScenario();
        $customer = User::query()->findOrFail($customerId);

        $profile = $this->profileQueries(fn () => $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations?page=1&per_page=10&bucket=all'));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            16,
            $profile['query_count'],
            sprintf(
                'customer_reservation_list metrics=%s',
                json_encode([
                    'query_count' => $profile['query_count'],
                    'sql_time_ms' => $profile['sql_time_ms'],
                    'wall_time_ms' => $profile['wall_time_ms'],
                    'query_patterns' => $profile['query_patterns'],
                ], JSON_THROW_ON_ERROR)
            )
        );
    }

    public function test_customer_reservation_detail_stays_within_query_budget(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 10:00:00', 'UTC'));

        [$customerId, $reservationId] = $this->seedCustomerReservationReadScenario();
        $customer = User::query()->findOrFail($customerId);

        $profile = $this->profileQueries(fn () => $this->actingAs($customer)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            15,
            $profile['query_count'],
            sprintf(
                'customer_reservation_detail metrics=%s',
                json_encode([
                    'query_count' => $profile['query_count'],
                    'sql_time_ms' => $profile['sql_time_ms'],
                    'wall_time_ms' => $profile['wall_time_ms'],
                    'query_patterns' => $profile['query_patterns'],
                ], JSON_THROW_ON_ERROR)
            )
        );
    }

    public function test_staff_reporting_daily_sales_index_stays_within_query_budget(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 10:00:00', 'UTC'));

        $staffId = $this->createUser(['role_name' => 'Admin']);
        $headers = $this->staffAuthHeaders($staffId, 'perf-reporting-sales');

        $this->seedReportingDailySalesScenario();

        $profile = $this->profileQueries(fn () => $this->withHeaders($headers)->getJson(
            '/api/v1/staff/reporting/daily-sales?page=1&per_page=10&start_date=2026-03-25&end_date=2026-04-05'
        ));

        $profile['result']->assertOk();

        self::assertLessThanOrEqual(
            14,
            $profile['query_count'],
            sprintf(
                'staff_reporting_daily_sales metrics=%s',
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
        $tableIds = [];

        for ($index = 1; $index <= 12; $index++) {
            $tableIds[$index] = $this->createRestaurantTableWithSeats(4 + ($index % 4), [
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
            $tableId = (int) $tableIds[$index];
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
        ], [(int) $tableIds[4], (int) $tableIds[5]]);
    }

    private function seedWaitingListQueueScenario(): void
    {
        $now = Carbon::now('UTC')->startOfMinute();

        for ($index = 1; $index <= 8; $index++) {
            $customerId = $this->createUser(['role_name' => 'Customer']);
            $this->createWaitingListEntry([
                'user_id' => $customerId,
                'guest_name' => sprintf('Queue Guest %02d', $index),
                'phone' => sprintf('0909000%03d', $index),
                'guest_count' => 2 + ($index % 3),
                'requested_at' => $now->copy()->subMinutes(30 - $index),
                'status' => 'Waiting',
                'priority' => 20 - $index,
            ]);
        }

        for ($index = 1; $index <= 2; $index++) {
            $customerId = $this->createUser(['role_name' => 'Customer']);
            $waitingId = $this->createWaitingListEntry([
                'user_id' => $customerId,
                'guest_name' => sprintf('Notified Guest %02d', $index),
                'phone' => sprintf('0911000%03d', $index),
                'guest_count' => 2 + $index,
                'requested_at' => $now->copy()->subMinutes(45 + $index),
                'status' => 'Notified',
                'priority' => 30 - $index,
                'notified_at' => $now->copy()->subMinutes(5 + $index),
                'notify_expires_at' => $now->copy()->addMinutes(10 - $index),
                'customer_response_status' => $index === 1 ? 'Accepted' : null,
                'customer_responded_at' => $index === 1 ? $now->copy()->subMinutes(2) : null,
                'customer_confirmed_arrival_at' => $index === 1 ? $now->copy()->subMinute() : null,
            ]);

            $tableId = $this->createRestaurantTableWithSeats(4 + $index, [
                'table_code' => sprintf('WL-%02d', $index),
                'zone' => 'Queue',
                'status' => 'Available',
            ]);

            $this->createTableHold([
                'session_id' => 'waiting-list:'.$waitingId,
                'start_time' => $now->copy()->subMinutes(5 + $index),
                'end_time' => $now->copy()->addMinutes(10 - $index),
                'expire_at' => $now->copy()->addMinutes(10 - $index),
                'hold_status' => 'Holding',
            ], [$tableId]);
        }
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedCustomerReservationReadScenario(): array
    {
        $now = Carbon::now('UTC')->startOfMinute();
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $itemId = $this->createMenuItem();
        $firstReservationId = 0;

        for ($index = 1; $index <= 8; $index++) {
            $reservationId = $this->createReservation([
                'user_id' => $customerId,
                'status' => 'Confirmed',
                'start_time' => $now->copy()->addDays($index)->setTime(18, 0),
                'end_time' => $now->copy()->addDays($index)->setTime(20, 0),
                'guest_count' => 2 + ($index % 3),
                'deposit_required_amount' => '100000',
                'deposit_paid_amount' => '50000',
                'deposit_status' => 'Pending',
                'discount_amount' => '10000',
                'final_bill_amount' => '180000',
                'bill_currency' => 'VND',
            ]);
            $firstReservationId = $firstReservationId === 0 ? $reservationId : $firstReservationId;

            $tableId = $this->createRestaurantTableWithSeats(4 + ($index % 2), [
                'table_code' => sprintf('CR-%02d', $index),
                'zone' => 'Dining',
                'status' => 'Available',
            ]);
            $this->attachReservationTable($reservationId, $tableId);

            $orderId = $this->createOrder([
                'reservation_id' => $reservationId,
                'order_type' => 'PreOrder',
                'status' => 'Active',
            ]);
            $this->createOrderItem([
                'order_id' => $orderId,
                'item_id' => $itemId,
                'quantity' => 1 + ($index % 2),
                'unit_price' => '60000',
                'currency' => 'VND',
                'line_total' => $index % 2 === 0 ? '60000' : '120000',
                'status' => 'Ordered',
            ]);
            $this->createPayment([
                'reservation_id' => $reservationId,
                'payment_type' => 'Deposit',
                'amount' => '50000',
                'currency' => 'VND',
                'status' => 'Success',
                'payment_method' => 'Card',
                'payment_provider' => 'Other',
            ]);
        }

        return [$customerId, $firstReservationId];
    }

    private function seedReportingDailySalesScenario(): void
    {
        $now = Carbon::now('UTC')->startOfMinute();

        for ($index = 0; $index < 10; $index++) {
            DB::table('reporting_daily_sales_snapshots')->insert([
                'branch_id' => 1,
                'business_date' => $now->copy()->subDays($index)->toDateString(),
                'currency' => 'VND',
                'billed_reservation_count' => 10 + $index,
                'billed_guest_count' => 20 + $index,
                'gross_bill_amount' => (string) (250000 + ($index * 10000)).'.00',
                'discount_amount' => '10000',
                'billed_total_amount' => (string) (240000 + ($index * 10000)).'.00',
                'invoice_issued_count' => 2,
                'invoiced_total_amount' => '240000',
                'invoiced_tax_amount' => '24000',
                'payment_row_count' => 12 + $index,
                'refund_row_count' => 1,
                'captured_amount' => (string) (240000 + ($index * 10000)).'.00',
                'refunded_amount' => '0',
                'net_paid_amount' => (string) (240000 + ($index * 10000)).'.00',
                'deposit_net_amount' => '50000',
                'final_net_amount' => (string) (190000 + ($index * 10000)).'.00',
                'cashier_shift_closed_count' => 1,
                'cash_discrepancy_amount' => '0',
                'refreshed_at' => $now->copy()->subMinutes($index + 1),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
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
            'deposit_required_amount' => '0',
            'deposit_paid_amount' => '0',
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
            'unit_price' => '50000',
            'currency' => 'VND',
            'line_total' => '100000',
        ]);

        if ($lockBill) {
            app(OrderSettlementWorkflow::class)->lockBill(
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
