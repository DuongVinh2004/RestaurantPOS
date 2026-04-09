<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffOrderReadFlowTest extends TestCase
{
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
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
    }

    public function test_staff_can_view_order_detail_with_item_and_financial_summary(): void
    {
        [$staffId, $tableId, $reservationId, $orderId, $customerId] = $this->seedOrderReadScenario();

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-order-read-detail'))
            ->getJson("/api/v1/staff/orders/{$orderId}");

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'order_detail')
            ->assertJsonPath('data.order.order_id', $orderId)
            ->assertJsonPath('data.order.reservation_id', $reservationId)
            ->assertJsonPath('data.table.table_id', $tableId)
            ->assertJsonPath('data.customer.user_id', $customerId)
            ->assertJsonPath('data.item_summary.line_count', 2)
            ->assertJsonPath('data.item_summary.status_counts.Ordered', 1)
            ->assertJsonPath('data.item_summary.status_counts.Cancelled', 1)
            ->assertJsonPath('data.item_summary.active_quantity', 2)
            ->assertJsonPath('data.financial_summary.subtotal', '100000.00')
            ->assertJsonPath('data.financial_summary.discount', '10000.00')
            ->assertJsonPath('data.financial_summary.total_due', '90000.00')
            ->assertJsonPath('data.financial_summary.deposit_applied', '20000.00')
            ->assertJsonPath('data.financial_summary.outstanding', '70000.00')
            ->assertJsonPath('data.financial_summary.payment_status', 'Partial')
            ->assertJsonPath('data.reservation.payment_summary.deposit_captured', '20000.00');
    }

    public function test_staff_can_view_completed_order_detail(): void
    {
        [$staffId, , , $orderId] = $this->seedOrderReadScenario([
            'order' => [
                'status' => 'Completed',
            ],
            'reservation' => [
                'status' => 'Completed',
            ],
        ]);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-order-read-completed'))
            ->getJson("/api/v1/staff/orders/{$orderId}");

        $response
            ->assertOk()
            ->assertJsonPath('data.order.status', 'Completed');
    }

    public function test_staff_can_view_active_order_by_table(): void
    {
        [$staffId, $tableId, , $orderId] = $this->seedOrderReadScenario();

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-order-read-by-table'))
            ->getJson("/api/v1/staff/tables/{$tableId}/active-order");

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'active_order_by_table')
            ->assertJsonPath('data.order.order_id', $orderId)
            ->assertJsonPath('data.table.table_id', $tableId);
    }

    public function test_staff_active_order_by_reservation_prioritizes_on_spot_order_over_preorder(): void
    {
        [$staffId, , $reservationId, $onSpotOrderId] = $this->seedOrderReadScenario();

        $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'PreOrder',
            'status' => 'Active',
            'row_version' => 1,
        ]);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-order-read-by-reservation'))
            ->getJson("/api/v1/staff/reservations/{$reservationId}/active-order");

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'active_order_by_reservation')
            ->assertJsonPath('data.order.order_id', $onSpotOrderId)
            ->assertJsonPath('data.order.order_type', 'OnSpot');
    }

    public function test_active_order_by_table_rejects_branch_mismatched_reservation_assignment(): void
    {
        [$staffId, $tableId] = $this->seedOrderReadScenario();
        $annexBranchId = $this->createBranch([
            'branch_code' => 'ANNEXREAD',
            'branch_name' => 'Annex Read',
        ]);

        DB::table('restaurant_tables')
            ->where('table_id', $tableId)
            ->update(['branch_id' => $annexBranchId]);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'staff-order-read-branch-mismatch'))
            ->getJson("/api/v1/staff/tables/{$tableId}/active-order");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation_id']);
    }

    public function test_table_without_active_order_returns_not_found(): void
    {
        [$staffId, $tableId] = $this->seedTableWithoutActiveOrder();

        $response = $this->withHeaders(array_merge(
            $this->staffHeaders($staffId, 'staff-order-read-missing-table'),
            ['X-Request-Id' => 'req-staff-order-read-table-404']
        ))
            ->getJson("/api/v1/staff/tables/{$tableId}/active-order");

        $response->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-staff-order-read-table-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-staff-order-read-table-404');
    }

    public function test_reservation_without_active_order_returns_not_found(): void
    {
        [$staffId, $reservationId] = $this->seedReservationWithoutActiveOrder();

        $response = $this->withHeaders(array_merge(
            $this->staffHeaders($staffId, 'staff-order-read-missing-reservation'),
            ['X-Request-Id' => 'req-staff-order-read-reservation-404']
        ))
            ->getJson("/api/v1/staff/reservations/{$reservationId}/active-order");

        $response->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-staff-order-read-reservation-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-staff-order-read-reservation-404');
    }

    public function test_non_staff_requests_are_rejected(): void
    {
        [, , , $orderId] = $this->seedOrderReadScenario();

        $response = $this->getJson("/api/v1/staff/orders/{$orderId}");

        $response->assertUnauthorized();
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function seedOrderReadScenario(array $overrides = []): array
    {
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Order Reader',
            'email' => 'order-reader@example.test',
            'phone' => '0909000111',
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation(array_merge([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'discount_amount' => '10000.00',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '20000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ], $overrides['reservation'] ?? []));
        $this->attachReservationTable($reservationId, $tableId);

        $orderId = $this->createOrder(array_merge([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ], $overrides['order'] ?? []));

        $orderedItemId = $this->createMenuItem();
        $cancelledItemId = $this->createMenuItem();

        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $orderedItemId,
            'quantity' => 2,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $cancelledItemId,
            'quantity' => 1,
            'unit_price' => '30000.00',
            'currency' => 'VND',
            'line_total' => '30000.00',
            'status' => 'Cancelled',
            'row_version' => 1,
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '20000.00',
            'currency' => 'VND',
        ]);

        return [$staffId, $tableId, $reservationId, $orderId, $customerId];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedTableWithoutActiveOrder(): array
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Available']);

        return [$staffId, $tableId];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedReservationWithoutActiveOrder(): array
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Completed',
            'row_version' => 1,
        ]);

        return [$staffId, $reservationId];
    }

    /**
     * @return array<string,string>
     */
    private function staffHeaders(int $staffId, string $apiKey): array
    {
        return $this->staffAuthHeaders($staffId, $apiKey);
    }
}
