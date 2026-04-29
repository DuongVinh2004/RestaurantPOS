<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCheckoutRefundLifecycleTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_partial_deposit_refund_updates_reservation_snapshot_and_links_refund_to_source_payment(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Manager']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
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
            'transaction_code' => 'DEP-LIFECYCLE-1',
        ]);

        $service = $this->makeCheckoutService();

        $result = $service->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'deposit',
            refundAmount: 40000.00,
            currency: 'VND',
            transactionCode: 'RF-LIFECYCLE-1',
            paymentProvider: 'Cash',
            notes: 'partial deposit refund',
            reason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-lifecycle-1'
        );

        $reservation = $result['reservation']->fresh();
        $refundPaymentId = (int) (($result['refund']['refund_payment_ids'] ?? [])[0] ?? 0);
        $refundRow = DB::table('payments')->where('payment_id', $refundPaymentId)->first();

        $this->assertSame('PartiallyRefunded', (string) ($reservation->deposit_status->value ?? $reservation->deposit_status));
        $this->assertSame(60000.0, (float) ($reservation->deposit_paid_amount ?? 0.0));
        $this->assertSame($sourcePaymentId, (int) ($refundRow->refund_of_payment_id ?? 0));
        $this->assertSame('Refund', (string) ($refundRow->payment_type ?? ''));
        $this->assertSame(40000.0, (float) ($refundRow->amount ?? 0.0));
        $this->assertSame('VND', (string) ($refundRow->currency ?? ''));
    }

    public function test_refund_records_cashier_shift_id(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Manager']);
        $shiftId = $this->createCashierShift(['cashier_user_id' => $staffId]);
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
            'transaction_code' => 'DEP-SHIFT-FK-1',
        ]);

        $result = $this->makeCheckoutService()->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'deposit',
            refundAmount: 25000.00,
            currency: 'VND',
            transactionCode: 'RF-SHIFT-FK-1',
            paymentProvider: 'Cash',
            notes: 'refund shift linkage',
            reason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-shift-fk-1'
        );

        $refundPaymentId = (int) (($result['refund']['refund_payment_ids'] ?? [])[0] ?? 0);
        $refundShiftId = DB::table('payments')->where('payment_id', $refundPaymentId)->value('cashier_shift_id');

        $this->assertSame($shiftId, (int) $refundShiftId);
    }

    public function test_refund_rejects_currency_mismatch_against_captured_payment_currency(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Manager']);
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'currency' => 'USD',
        ]);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'deposit_required_amount' => '50000.00',
            'deposit_paid_amount' => '50000.00',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-CURRENCY-MISMATCH-1',
        ]);

        try {
            $this->makeCheckoutService()->refundReservation(
                reservationId: $reservationId,
                paymentMethod: 'Cash',
                refundScope: 'deposit',
                refundAmount: 10000.00,
                currency: 'USD',
                transactionCode: 'RF-CURRENCY-MISMATCH-1',
                paymentProvider: 'Cash',
                notes: 'currency mismatch should be rejected',
                reason: 'customer_request',
                expectedRowVersion: 1,
                staffUserId: $staffId,
                idempotencyKey: 'idem-rf-currency-mismatch-1'
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('currency', $errors);
            $this->assertSame('Refund currency must match the reservation payment currency.', $errors['currency'][0]);
        }

        $this->assertSame(0, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Refund')->count());
    }

    public function test_second_refund_attempt_after_full_refund_is_rejected_without_creating_new_refund_payment(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Manager']);
        $this->createCashierShift(['cashier_user_id' => $staffId]);
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
            'transaction_code' => 'DEP-LIFECYCLE-FULL-1',
        ]);

        $service = $this->makeCheckoutService();

        $service->refundReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'deposit',
            refundAmount: 100000.00,
            currency: 'VND',
            transactionCode: 'RF-LIFECYCLE-FULL-1',
            paymentProvider: 'Cash',
            notes: 'full deposit refund',
            reason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-rf-lifecycle-full-1'
        );

        try {
            $service->refundReservation(
                reservationId: $reservationId,
                paymentMethod: 'Cash',
                refundScope: 'deposit',
                refundAmount: 1000.00,
                currency: 'VND',
                transactionCode: 'RF-LIFECYCLE-FULL-2',
                paymentProvider: 'Cash',
                notes: 'should be rejected',
                reason: 'customer_request',
                expectedRowVersion: null,
                staffUserId: $staffId,
                idempotencyKey: 'idem-rf-lifecycle-full-2'
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('refund_amount', $errors);
            $this->assertStringContainsString('exceeds refundable balance', $errors['refund_amount'][0]);
        }

        $this->assertSame(1, (int) DB::table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Refund')->count());
    }
}
