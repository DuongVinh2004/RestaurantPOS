<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCheckoutIdempotencyReplayServiceTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Group('booking-smoke')]
    public function test_pay_order_replays_same_payment_and_returns_completed_order_status_when_fully_settled(): void
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

        $service = $this->makeCheckoutService();

        $first = $service->payOrder(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.00,
            currency: 'VND',
            transactionCode: 'PAY-IDEM-1',
            paymentProvider: 'Cash',
            notes: 'full payment',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-pay-order-1'
        );

        $second = $service->payOrder(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 100000.00,
            currency: 'VND',
            transactionCode: 'PAY-IDEM-1',
            paymentProvider: 'Cash',
            notes: 'full payment',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-pay-order-1'
        );

        $this->assertSame($orderId, (int) $first->order_id);
        $this->assertSame($orderId, (int) $second->order_id);
        $this->assertSame('Completed', (string) ($first->status->value ?? $first->status));
        $this->assertSame('Completed', (string) ($second->status->value ?? $second->status));
        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Final')
                ->where('idempotency_key', 'idem-pay-order-1')
                ->count()
        );
    }

    public function test_pay_order_rejects_same_idempotency_key_when_payment_payload_differs(): void
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
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        $service = $this->makeCheckoutService();

        $service->payOrder(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 40000.00,
            currency: 'VND',
            transactionCode: 'PAY-IDEM-CONFLICT-1',
            paymentProvider: 'Cash',
            notes: 'partial payment',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-pay-conflict-1'
        );

        try {
            $service->payOrder(
                orderId: $orderId,
                paymentMethod: 'Cash',
                paidAmount: 30000.00,
                currency: 'VND',
                transactionCode: 'PAY-IDEM-CONFLICT-2',
                paymentProvider: 'Cash',
                notes: 'different partial payment',
                expectedRowVersion: null,
                staffUserId: $staffId,
                idempotencyKey: 'idem-pay-conflict-1'
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('idempotency_key', $errors);
            $this->assertSame(
                'This idempotency key is already bound to a different payment request payload.',
                $errors['idempotency_key'][0]
            );
        }

        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Final')
                ->where('idempotency_key', 'idem-pay-conflict-1')
                ->count()
        );
    }

    public function test_checkout_rejects_same_idempotency_key_when_checkout_payload_differs(): void
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
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
        ]);

        $service = $this->makeCheckoutService();

        $service->checkout(
            orderId: $orderId,
            paymentMethod: 'Cash',
            paidAmount: 90000.00,
            currency: 'VND',
            transactionCode: 'CHECKOUT-IDEM-CONFLICT-1',
            paymentProvider: 'Cash',
            notes: 'checkout with discount',
            discountAmount: 10000.00,
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-checkout-conflict-1'
        );

        try {
            $service->checkout(
                orderId: $orderId,
                paymentMethod: 'Cash',
                paidAmount: 100000.00,
                currency: 'VND',
                transactionCode: 'CHECKOUT-IDEM-CONFLICT-2',
                paymentProvider: 'Cash',
                notes: 'checkout without discount',
                discountAmount: 0.00,
                expectedRowVersion: null,
                staffUserId: $staffId,
                idempotencyKey: 'idem-checkout-conflict-1'
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('idempotency_key', $errors);
            $this->assertSame(
                'This idempotency key is already bound to a different payment request payload.',
                $errors['idempotency_key'][0]
            );
        }

        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Final')
                ->where('idempotency_key', 'idem-checkout-conflict-1')
                ->count()
        );
    }

    #[Group('booking-smoke')]
    public function test_refund_reservation_replays_existing_refund_rows_for_same_idempotency_key(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '100000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ]);
        $sourcePaymentId = $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-IDEM-1',
        ]);

        $service = $this->makeCheckoutService();

        $first = $service->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'deposit',
            refundAmount: 100000.00,
            currency: 'VND',
            transactionCode: 'RF-IDEM-1',
            paymentProvider: 'Cash',
            notes: 'refund deposit',
            reason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-refund-1'
        );

        $second = $service->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'deposit',
            refundAmount: 100000.00,
            currency: 'VND',
            transactionCode: 'RF-IDEM-1',
            paymentProvider: 'Cash',
            notes: 'refund deposit',
            reason: 'customer_request',
            expectedRowVersion: null,
            staffUserId: $staffId,
            idempotencyKey: 'idem-refund-1'
        );

        $refundIds = $first['refund']['refund_payment_ids'] ?? [];
        $this->assertCount(1, $refundIds);
        $this->assertSame($refundIds, $second['refund']['refund_payment_ids'] ?? []);
        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Refund')
                ->where('refund_of_payment_id', $sourcePaymentId)
                ->count()
        );
    }

    public function test_refund_reservation_rejects_same_idempotency_key_when_refund_payload_differs(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '100000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '100000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-IDEM-CONFLICT-1',
        ]);

        $service = $this->makeCheckoutService();

        $first = $service->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'deposit',
            refundAmount: 40000.00,
            currency: 'VND',
            transactionCode: 'RF-IDEM-CONFLICT-1',
            paymentProvider: 'Cash',
            notes: 'refund deposit partial',
            reason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-refund-conflict-1'
        );

        $this->assertSame('deposit', $first['refund']['refund_scope'] ?? null);

        try {
            $service->refundReservation(
                reservationId: $reservationId,
                paymentMethod: 'Cash',
                refundScope: 'deposit',
                refundAmount: 30000.00,
                currency: 'VND',
                transactionCode: 'RF-IDEM-CONFLICT-2',
                paymentProvider: 'Cash',
                notes: 'refund deposit with different amount',
                reason: 'customer_request',
                expectedRowVersion: null,
                staffUserId: $staffId,
                idempotencyKey: 'idem-refund-conflict-1'
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('idempotency_key', $errors);
            $this->assertSame(
                'This idempotency key is already bound to a different refund request payload.',
                $errors['idempotency_key'][0]
            );
        }

        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Refund')
                ->count()
        );
    }
}
