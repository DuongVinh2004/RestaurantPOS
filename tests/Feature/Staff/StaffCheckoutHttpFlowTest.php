<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCheckoutHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_settlement_preview_returns_order_totals_for_staff_route(): void
    {
        [$staffId, $orderId] = $this->seedActiveOrderScenario();

        $response = $this->withHeaders($this->staffAuthHeaders($staffId))
            ->getJson("/api/v1/staff/orders/{$orderId}/settlement-preview");

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'settlement_preview')
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.total_amount', 100000)
            ->assertJsonPath('data.outstanding_amount', 100000);
    }

    public function test_staff_can_list_reservation_orders_for_operational_lookup(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();
        $secondOrderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Completed',
        ]);
        $this->createOrderItem([
            'order_id' => $secondOrderId,
            'quantity' => 1,
            'unit_price' => '25000.00',
            'currency' => 'VND',
            'line_total' => '25000.00',
        ]);

        $response = $this->withHeaders($this->staffAuthHeaders($staffId))
            ->getJson("/api/v1/staff/reservations/{$reservationId}/orders");

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'reservation_orders_lookup')
            ->assertJsonPath('meta.reservation_id', $reservationId)
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('meta.sort.supported', false)
            ->assertJsonPath('meta.pagination.supported', false)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.order_id', $orderId)
            ->assertJsonPath('data.1.order_id', $secondOrderId);
    }

    public function test_reservation_order_lookup_returns_not_found_for_unknown_reservation(): void
    {
        [$staffId] = $this->seedActiveOrderScenario();

        $this->withHeaders($this->staffAuthHeaders($staffId))
            ->getJson('/api/v1/staff/reservations/999999/orders')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_settlement_preview_rejects_branch_drifted_reservation_assignment(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();
        $annexBranchId = $this->createBranch([
            'branch_code' => 'ANNEXSETPRE',
            'branch_name' => 'Annex Settlement Preview',
        ]);

        $this->table('reservations')
            ->where('reservation_id', $reservationId)
            ->update(['branch_id' => $annexBranchId]);

        $response = $this->withHeaders($this->staffAuthHeaders($staffId))
            ->getJson("/api/v1/staff/orders/{$orderId}/settlement-preview");

        $response
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.reservation_id.0', 'Reservation branch does not match its assigned tables.');
    }

    public function test_bill_snapshot_endpoint_locks_bill_without_marking_legacy_alias(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-bill-snapshot'))
            ->postJson("/api/v1/staff/orders/{$orderId}/bill-snapshot", [
                'row_version' => 1,
                'notes' => 'snapshot bill',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'lock_bill')
            ->assertJsonPath('meta.legacy_route_alias', null)
            ->assertJsonPath('meta.legacy_route_deprecated', false)
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.totals.total_due', '100000.00')
            ->assertJsonPath('data.payment_status', 'Failed');

        $this->assertNotNull($this->table('reservations')->where('reservation_id', $reservationId)->value('billed_at'));
        $this->assertSame(
            '100000.00',
            number_format((float) $this->table('reservations')->where('reservation_id', $reservationId)->value('final_bill_amount'), 2, '.', '')
        );
    }

    public function test_close_alias_returns_deprecation_metadata(): void
    {
        [$staffId, $orderId] = $this->seedActiveOrderScenario();

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-close-alias'))
            ->postJson("/api/v1/staff/orders/{$orderId}/close", [
                'row_version' => 1,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'lock_bill')
            ->assertJsonPath('meta.legacy_route_alias', 'close')
            ->assertJsonPath('meta.legacy_route_deprecated', true);
    }

    public function test_pay_and_finalize_alias_complete_the_reservation_settlement(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();
        $this->openCashierShiftForReservationBranch($staffId, $reservationId);

        $payResponse = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-pay'))
            ->postJson("/api/v1/staff/orders/{$orderId}/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'paid_amount' => 100000,
                'currency' => 'VND',
                'transaction_code' => 'PAY-HTTP-1',
                'row_version' => 1,
            ]);

        $payResponse
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.status', 'Completed')
            ->assertJsonPath('data.payment_status', 'Success');

        $this->assertSame('Completed', (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('status'));

        [$staffId2, $orderId2, $reservationId2] = $this->seedActiveOrderScenario();
        $this->openCashierShiftForReservationBranch($staffId2, $reservationId2);

        $checkoutResponse = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId2), 'idem-finalize-checkout'))
            ->postJson("/api/v1/staff/orders/{$orderId2}/settlement/finalize", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'paid_amount' => 100000,
                'currency' => 'VND',
                'transaction_code' => 'PAY-HTTP-2',
                'row_version' => 1,
            ]);

        $checkoutResponse
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderId2)
            ->assertJsonPath('data.payment_status', 'Success')
            ->assertJsonPath('data.outstanding_amount', 0);

        $this->assertSame('Completed', (string) $this->table('reservations')->where('reservation_id', $reservationId2)->value('status'));
        $this->assertSame(1, (int) $this->table('payments')->where('reservation_id', $reservationId2)->where('payment_type', 'Final')->count());
    }

    public function test_settlement_and_refund_mutations_require_open_cashier_shift_in_the_reservation_branch(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();
        $otherBranchId = $this->createBranch([
            'branch_code' => 'CHKSHIFT',
            'branch_name' => 'Checkout Shift Branch',
        ]);
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $otherBranchId,
            'status' => 'Open',
        ]);

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-finalize-without-branch-shift'))
            ->postJson("/api/v1/staff/orders/{$orderId}/settlement/finalize", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'paid_amount' => 100000,
                'currency' => 'VND',
                'transaction_code' => 'PAY-NO-BRANCH-SHIFT-1',
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cashier_shift']);

        $this->assertSame(0, (int) $this->table('payments')->where('reservation_id', $reservationId)->count());

        $completedReservationId = $this->createReservation([
            'user_id' => $this->createUser(['role_name' => 'Customer']),
            'status' => 'Completed',
            'final_bill_amount' => '100000.00',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $this->createPayment([
            'reservation_id' => $completedReservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'REFUND-NO-BRANCH-SHIFT-PAID-1',
        ]);

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-refund-without-branch-shift'))
            ->postJson("/api/v1/staff/reservations/{$completedReservationId}/refund", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'refund_scope' => 'all',
                'currency' => 'VND',
                'transaction_code' => 'REFUND-NO-BRANCH-SHIFT-1',
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cashier_shift']);
    }

    public function test_settlement_and_refund_mutations_reject_open_shift_with_mismatched_currency(): void
    {
        [$staffId, $orderId, $reservationId] = $this->seedActiveOrderScenario();
        $branchId = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
            'currency' => 'USD',
        ]);

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-finalize-shift-currency-mismatch'))
            ->postJson("/api/v1/staff/orders/{$orderId}/settlement/finalize", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'paid_amount' => 100000,
                'currency' => 'VND',
                'transaction_code' => 'PAY-SHIFT-CURRENCY-MISMATCH-1',
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.cashier_shift.0', 'Open cashier shift currency must match the mutation currency for this branch.');

        $this->assertSame(0, (int) DB::table('payments')->where('reservation_id', $reservationId)->count());

        $completedReservationId = $this->createReservation([
            'user_id' => $this->createUser(['role_name' => 'Customer']),
            'status' => 'Completed',
            'branch_id' => $branchId,
            'final_bill_amount' => '100000.00',
            'bill_currency' => 'VND',
            'billed_at' => $this->nowUtc(),
        ]);
        $this->createPayment([
            'reservation_id' => $completedReservationId,
            'branch_id' => $branchId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'created_by' => $staffId,
            'transaction_code' => 'REFUND-SHIFT-CURRENCY-SOURCE-1',
        ]);

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-refund-shift-currency-mismatch'))
            ->postJson("/api/v1/staff/reservations/{$completedReservationId}/refund", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'refund_scope' => 'all',
                'currency' => 'VND',
                'transaction_code' => 'REFUND-SHIFT-CURRENCY-MISMATCH-1',
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.cashier_shift.0', 'Open cashier shift currency must match the mutation currency for this branch.');

        $this->assertSame(0, (int) DB::table('payments')->where('reservation_id', $completedReservationId)->where('payment_type', 'Refund')->count());
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function seedActiveOrderScenario(): array
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

        return [$staffId, $orderId, $reservationId];
    }

    private function table(string $table)
    {
        return DB::table($table);
    }

    private function openCashierShiftForReservationBranch(int $staffId, int $reservationId): void
    {
        $branchId = (int) $this->table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId > 0 ? $branchId : 1,
            'status' => 'Open',
        ]);
    }
}
