<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffOrderSettlementWorkflowCharacterizationTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    #[Group('booking-smoke')]
    public function test_characterize_preview_settlement_reports_current_totals_without_locking_bill(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Manager']);

        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
        ]);

        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 3,
            'unit_price' => '40000.00',
            'currency' => 'VND',
            'line_total' => '120000.00',
        ]);

        /** @var OrderSettlementWorkflow $workflow */
        $workflow = app(OrderSettlementWorkflow::class);

        $preview = $workflow->previewSettlement($orderId, 'VND', $staffId);

        $this->assertSame($orderId, (int) $preview['order_id']);
        $this->assertSame('120000.00', number_format((float) $preview['total_amount'], 2, '.', ''));
        $this->assertSame('120000.00', number_format((float) $preview['outstanding_amount'], 2, '.', ''));
        $this->assertSame('Failed', (string) $preview['payment_status']);

        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();
        $this->assertNull($reservation->billed_at);
        $this->assertNull($reservation->final_bill_amount);
    }

    #[Group('booking-smoke')]
    public function test_characterize_lock_bill_locks_the_bill_amount_and_updates_reservation_state(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Manager']);

        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
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
            'unit_price' => '50000.00', // 100k total
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        /** @var OrderSettlementWorkflow $workflow */
        $workflow = app(OrderSettlementWorkflow::class);

        // Apply a discount of 15k and lock the bill
        $lockedOrder = $workflow->lockBill(
            orderId: $orderId,
            discountAmount: 15000.0,
            notes: 'Characterization test lock',
            staffUserId: $staffId
        );

        $this->assertNotNull($lockedOrder);

        // Assert reservation state via DB directly for characterization
        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();

        // Expected side effects:
        // 1. discount_amount should be set
        // 2. final_bill_amount should be subtotal (100k) - discount (15k) = 85k
        // 3. billed_at should not be null
        // 4. updated_by should be staffId

        $this->assertSame('15000.00', number_format((float) $reservation->discount_amount, 2, '.', ''));
        $this->assertSame('85000.00', number_format((float) $reservation->final_bill_amount, 2, '.', ''));
        $this->assertNotNull($reservation->billed_at);
        $this->assertSame($staffId, (int) $reservation->updated_by);

        // The order itself should remain Active until paid
        $this->assertSame('Active', $lockedOrder->status?->value ?? $lockedOrder->status);
    }

    public function test_characterize_pay_order_completes_the_order_and_creates_payment_record(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Manager']);

        $this->createCashierShift(['cashier_user_id' => $staffId]);

        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
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
            'unit_price' => '50000.00', // 100k total
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        /** @var OrderSettlementWorkflow $workflow */
        $workflow = app(OrderSettlementWorkflow::class);

        $paidOrder = $workflow->payOrder(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.0,
            currency: 'VND',
            transactionCode: 'CHAR-PAY-01',
            paymentProvider: 'Cash',
            notes: 'Characterization full payment',
            staffUserId: $staffId,
            idempotencyKey: 'idem-char-pay-1'
        );

        $this->assertSame('Completed', $paidOrder->status?->value ?? $paidOrder->status);

        // Characterize payment creation
        $paymentCount = DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Final')
            ->where('status', 'Success')
            ->where('amount', '100000.00')
            ->count();

        $this->assertSame(1, $paymentCount);

        // Characterize reservation state after payment
        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();
        $this->assertSame('Completed', $reservation->status);
        $this->assertNotNull($reservation->checked_out_at);

        $this->assertSame(
            'Completed',
            (string) DB::table('reservation_orders')->where('order_id', $orderId)->value('status')
        );
        $this->assertSame(
            'Available',
            (string) DB::table('restaurant_tables')->where('table_id', $tableId)->value('status')
        );
    }

    public function test_characterize_full_refund_cancel_after_payment_marks_reservation_cancelled_and_keeps_table_released(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Manager']);

        $this->createCashierShift(['cashier_user_id' => $staffId]);

        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
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
            'unit_price' => '100000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        /** @var OrderSettlementWorkflow $workflow */
        $workflow = app(OrderSettlementWorkflow::class);

        $workflow->payOrder(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.0,
            currency: 'VND',
            transactionCode: 'CHAR-REFUND-CAPTURE-01',
            paymentProvider: 'Cash',
            notes: 'Characterization payment before refund',
            staffUserId: $staffId,
            idempotencyKey: 'idem-char-refund-capture-1'
        );

        $preview = $workflow->previewRefund(
            reservationId: $reservationId,
            refundScope: 'final',
            refundAmount: 100000.0,
            currency: 'VND',
            cancelAfterPayment: true,
            staffUserId: $staffId
        );

        $this->assertSame('100000.00', (string) data_get($preview, 'refund.refund_amount'));
        $this->assertTrue((bool) data_get($preview, 'refund.cancelled'));

        $reservationRowVersion = (int) DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->value('row_version');

        $refund = $workflow->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'final',
            refundAmount: 100000.0,
            currency: 'VND',
            transactionCode: 'CHAR-REFUND-01',
            paymentProvider: 'Cash',
            notes: 'Characterization full refund and cancel',
            reason: 'guest_request',
            cancelReason: 'Refunded after payment',
            expectedRowVersion: $reservationRowVersion,
            staffUserId: $staffId,
            idempotencyKey: 'idem-char-refund-1'
        );

        $this->assertSame('100000.00', (string) data_get($refund, 'refund.refund_amount'));
        $this->assertTrue((bool) data_get($refund, 'refund.cancelled'));
        $this->assertCount(1, (array) data_get($refund, 'refund.refund_payment_ids', []));

        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();
        $this->assertSame('Cancelled', (string) $reservation->status);
        $this->assertNull($reservation->checked_out_at);
        $this->assertNotNull($reservation->cancelled_at);
        $this->assertSame($staffId, (int) $reservation->cancelled_by);

        $this->assertSame(
            1,
            DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Refund')
                ->where('status', 'Refunded')
                ->count()
        );
        $this->assertSame(
            'Available',
            (string) DB::table('restaurant_tables')->where('table_id', $tableId)->value('status')
        );
    }
}
