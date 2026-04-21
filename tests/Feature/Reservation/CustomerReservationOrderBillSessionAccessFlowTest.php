<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationOrderBillSessionAccessFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_session_linked_guest_can_view_active_order_and_bill_preview(): void
    {
        [$customerId, , $reservationId, $orderId, $sessionId] = $this->seedLinkedInServiceScenario(lockBill: true);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Session-Id' => $sessionId,
        ])->getJson('/api/v1/reservations/' . $reservationId . '/active-order')
            ->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.active_order.order_id', $orderId)
            ->assertJsonPath('data.active_order.totals.total_due', '100000.00');

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Session-Id' => $sessionId,
        ])->getJson('/api/v1/reservations/' . $reservationId . '/bill-preview')
            ->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.bill_preview.snapshot_mode', 'locked')
            ->assertJsonPath('data.bill_preview.total_due_amount', '100000.00')
            ->assertJsonPath('data.bill_preview.outstanding_amount', '100000.00')
            ->assertJsonPath('data.bill_preview.self_payment.available', true);

        $this->assertSame($customerId, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('user_id'));
    }

    public function test_session_linked_guest_can_create_show_and_confirm_bill_payment_session_without_impersonating_owner(): void
    {
        [$customerId, , $reservationId, , $sessionId] = $this->seedLinkedInServiceScenario(lockBill: true);

        $create = $this->withHeaders([
            'Accept' => 'application/json',
            'Idempotency-Key' => 'cust-bill-session-create-1',
            'X-Session-Id' => $sessionId,
        ])->postJson('/api/v1/reservations/' . $reservationId . '/bill/payment-sessions', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.bill.total_due_amount', '100000.00')
            ->assertJsonPath('data.payment_session.amount', '100000.00')
            ->assertJsonPath('data.payment_session.session_status', 'Pending');

        $paymentSessionId = (int) $create->json('data.payment_session.bill_payment_session_id');
        $paymentSessionRowVersion = (int) $create->json('data.payment_session.row_version');

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Session-Id' => $sessionId,
        ])->getJson('/api/v1/reservations/' . $reservationId . '/bill/payment-sessions/' . $paymentSessionId)
            ->assertOk()
            ->assertJsonPath('data.payment_session.bill_payment_session_id', $paymentSessionId);

        $this->withHeaders([
            'Accept' => 'application/json',
            'Idempotency-Key' => 'cust-bill-session-confirm-1',
            'X-Session-Id' => $sessionId,
        ])->postJson('/api/v1/reservations/' . $reservationId . '/bill/payment-sessions/' . $paymentSessionId . '/confirm', [
            'row_version' => $paymentSessionRowVersion,
            'simulation_outcome' => 'succeeded',
        ])->assertOk()
            ->assertJsonPath('data.payment_session.session_status', 'Succeeded')
            ->assertJsonPath('data.payment_session.settlement_status', 'Applied')
            ->assertJsonPath('data.bill.outstanding_amount', '0.00');

        $this->assertSame(
            $customerId,
            (int) DB::table('reservation_bill_payment_sessions')->where('bill_payment_session_id', $paymentSessionId)->value('customer_user_id')
        );
        $this->assertNull(DB::table('reservation_bill_payment_sessions')->where('bill_payment_session_id', $paymentSessionId)->value('created_by'));
        $this->assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->count());
    }

    public function test_staff_actor_cannot_use_customer_bill_read_routes_even_with_valid_session_link(): void
    {
        [, $staffId, $reservationId, , $sessionId] = $this->seedLinkedInServiceScenario(lockBill: true);

        $headers = array_merge(
            $this->staffAuthHeaders($staffId, 'staff-customer-bill-read-forbidden'),
            [
                'Accept' => 'application/json',
                'X-Session-Id' => $sessionId,
            ]
        );

        $this->withHeaders($headers)
            ->getJson('/api/v1/reservations/' . $reservationId . '/active-order')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');

        $this->withHeaders($headers)
            ->getJson('/api/v1/reservations/' . $reservationId . '/bill-preview')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    /**
     * @return array{0:int,1:int,2:int,3:int,4:string}
     */
    private function seedLinkedInServiceScenario(bool $lockBill): array
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
            app(OrderSettlementWorkflow::class)->lockBill(
                orderId: $orderId,
                discountAmount: null,
                notes: 'lock bill for session-linked customer self-payment',
                expectedRowVersion: 1,
                staffUserId: $staffId,
            );
        }

        $sessionId = 'sess-bill-linked-' . $reservationId;
        $this->createTableHold([
            'session_id' => $sessionId,
            'user_id' => $customerId,
            'confirmed_reservation_id' => $reservationId,
        ], [$tableId]);

        return [$customerId, $staffId, $reservationId, $orderId, $sessionId];
    }
}
