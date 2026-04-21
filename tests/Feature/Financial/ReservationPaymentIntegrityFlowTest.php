<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class ReservationPaymentIntegrityFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_refund_cancel_after_customer_session_payments_reverses_deposit_and_final_without_double_counting(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(10),
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 1,
            'unit_price' => '200000.00',
            'currency' => 'VND',
            'line_total' => '200000.00',
        ]);

        app(OrderSettlementWorkflow::class)->lockBill(
            orderId: $orderId,
            discountAmount: null,
            notes: 'lock bill for refund-cancel integration',
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('branch_id'),
            'currency' => 'VND',
        ]);

        $customer = User::query()->findOrFail($customerId);

        $depositCreate = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'dep-int-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/' . $reservationId . '/deposit/payment-sessions', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'amount' => 50000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ]);

        $depositCreate->assertCreated();
        $depositSessionId = (int) $depositCreate->json('data.payment_session.deposit_payment_session_id');

        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'dep-int-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/' . $reservationId . '/deposit/payment-sessions/' . $depositSessionId . '/confirm', [
            'row_version' => (int) $depositCreate->json('data.payment_session.row_version'),
            'simulation_outcome' => 'succeeded',
        ])->assertOk()
            ->assertJsonPath('data.deposit.status', 'Paid')
            ->assertJsonPath('data.deposit.paid_amount', '50000.00')
            ->assertJsonPath('data.deposit.outstanding_amount', '0.00');

        $billCreate = $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'bill-int-create-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/' . $reservationId . '/bill/payment-sessions', [
            'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
            'amount' => 150000,
            'provider_code' => 'simulated',
            'currency' => 'VND',
        ]);

        $billCreate->assertCreated();
        $billSessionId = (int) $billCreate->json('data.payment_session.bill_payment_session_id');

        $this->actingAs($customer)->withHeaders([
            'Idempotency-Key' => 'bill-int-confirm-1',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/reservations/' . $reservationId . '/bill/payment-sessions/' . $billSessionId . '/confirm', [
            'row_version' => (int) $billCreate->json('data.payment_session.row_version'),
            'simulation_outcome' => 'succeeded',
        ])->assertOk()
            ->assertJsonPath('data.bill.total_due_amount', '200000.00')
            ->assertJsonPath('data.bill.deposit_applied_amount', '50000.00')
            ->assertJsonPath('data.bill.final_paid_amount', '150000.00')
            ->assertJsonPath('data.bill.outstanding_amount', '0.00');

        $refundCancel = $this->withHeaders($this->withIdempotencyKey('staff-refund-cancel-session-payments-1', $this->staffAuthHeaders($staffId, 'staff-refund-cancel-session-payments')))
            ->postJson('/api/v1/staff/reservations/' . $reservationId . '/refund-cancel', [
                'row_version' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'),
                'payment_method' => 'Cash',
                'refund_scope' => 'all',
                'currency' => 'VND',
                'transaction_code' => 'RF-CUST-SESS-1',
                'payment_provider' => 'Cash',
                'reason' => 'customer_request',
                'cancel_reason' => 'customer_request',
            ]);

        $refundCancel->assertOk()
            ->assertJsonPath('data.refund.payment_summary.deposit_refunded', '50000.00')
            ->assertJsonPath('data.refund.payment_summary.deposit_net', '0.00')
            ->assertJsonPath('data.refund.payment_summary.final_refunded', '150000.00')
            ->assertJsonPath('data.refund.payment_summary.final_net', '0.00');

        self::assertSame('Cancelled', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertSame('Refunded', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('deposit_status'));
        self::assertSame(0.0, (float) DB::table('reservations')->where('reservation_id', $reservationId)->value('deposit_paid_amount'));
        self::assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Deposit')->count());
        self::assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->count());
        self::assertSame(2, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Refund')->count());
    }

    public function test_partial_staff_final_payment_touches_reservation_row_version_without_completing_settlement(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(5),
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 1,
            'unit_price' => '200000.00',
            'currency' => 'VND',
            'line_total' => '200000.00',
        ]);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-PARTIAL-ROW-VERSION-1',
        ]);

        app(OrderSettlementWorkflow::class)->lockBill(
            orderId: $orderId,
            discountAmount: null,
            notes: 'lock bill for partial payment row-version',
            expectedRowVersion: 1,
            staffUserId: $staffId,
        );
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('branch_id'),
            'currency' => 'VND',
        ]);

        $reservationRowVersionBefore = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version');
        $orderRowVersion = (int) DB::table('reservation_orders')->where('order_id', $orderId)->value('row_version');

        $order = app(OrderSettlementWorkflow::class)->payOrder(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.00,
            currency: 'VND',
            transactionCode: 'FINAL-PARTIAL-ROW-VERSION-1',
            paymentProvider: 'Cash',
            notes: 'partial final payment',
            expectedRowVersion: $orderRowVersion,
            staffUserId: $staffId,
            idempotencyKey: 'staff-partial-final-payment-row-version-1',
        );

        self::assertSame('Reserved', (string) DB::table('reservations')->where('reservation_id', $reservationId)->value('status'));
        self::assertGreaterThan(
            $reservationRowVersionBefore,
            (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version')
        );
        self::assertSame(150000.0, round((float) ($order->getAttribute('paid_amount') ?? 0.0), 2));
        self::assertSame(50000.0, round((float) ($order->getAttribute('deposit_applied_amount') ?? 0.0), 2));
        self::assertSame(100000.0, round((float) ($order->getAttribute('final_paid_amount') ?? 0.0), 2));
        self::assertSame(50000.0, round((float) ($order->getAttribute('outstanding_amount') ?? 0.0), 2));
    }
}
