<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCheckoutRefundAllocationGuardTest extends TestCase
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

    public function test_refund_reservation_rejects_amount_that_exceeds_source_payment_balance(): void
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
            'transaction_code' => 'DEP-SOURCE-1',
        ]);

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'refund_of_payment_id' => $sourcePaymentId,
            'amount' => '80000.00',
            'currency' => 'VND',
            'transaction_code' => 'DEP-REFUND-EXISTING',
        ]);

        $service = $this->makeCheckoutService();

        try {
            $service->refundReservation(
                reservationId: $reservationId,
                paymentMethod: 'Cash',
                refundScope: 'deposit',
                refundAmount: 30000.00,
                currency: 'VND',
                transactionCode: 'RF-OVER-ALLOC-1',
                paymentProvider: 'Cash',
                notes: 'should fail',
                reason: 'customer_request',
                expectedRowVersion: 1,
                staffUserId: $staffId,
                idempotencyKey: 'idem-rf-over-alloc-1'
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('refund_amount', $errors);
            $this->assertStringContainsString('exceeds refundable balance', $errors['refund_amount'][0]);
        }

        $this->assertSame(
            1,
            (int) DB::table('payments')
                ->where('reservation_id', $reservationId)
                ->where('payment_type', 'Refund')
                ->where('refund_of_payment_id', $sourcePaymentId)
                ->count()
        );
    }
}
