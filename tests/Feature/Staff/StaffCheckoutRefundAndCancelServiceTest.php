<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffCheckoutRefundAndCancelServiceTest extends TestCase
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

    public function test_refund_cancel_requires_at_least_one_existing_payment(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '200000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
        ]);

        $service = $this->makeCheckoutService();

        try {
            $service->refundAndCancelReservation(
                reservationId: $reservationId,
                paymentMethod: 'Cash',
                refundScope: 'all',
                refundAmount: null,
                currency: 'VND',
                transactionCode: 'RF-NO-PAY',
                paymentProvider: 'Cash',
                notes: 'test',
                reason: 'customer_request',
                cancelReason: 'customer_request',
                expectedRowVersion: 1,
                staffUserId: $staffId,
                idempotencyKey: 'idem-refund-no-payment'
            );

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('refund_amount', $errors);
            $this->assertStringContainsString('cancel-after-payment', $errors['refund_amount'][0]);
        }
    }

    public function test_cancel_after_payment_clears_stale_lifecycle_timestamps(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc()->copy()->subMinutes(30),
            'checked_out_at' => $this->nowUtc()->copy()->subMinutes(5),
            'no_show_at' => $this->nowUtc()->copy()->subHour(),
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '100000.00',
            'deposit_status' => 'Paid',
        ]);
        $this->attachReservationTable($reservationId);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '100000.00',
            'transaction_code' => 'DEP-PAID-1',
        ]);

        $service = $this->makeCheckoutService();
        $result = $service->refundAndCancelReservation(
            reservationId: $reservationId,
            paymentMethod: 'Cash',
            refundScope: 'all',
            refundAmount: null,
            currency: 'VND',
            transactionCode: 'RF-CANCEL-1',
            paymentProvider: 'Cash',
            notes: 'cancel with refund',
            reason: 'customer_request',
            cancelReason: 'customer_request',
            expectedRowVersion: 1,
            staffUserId: $staffId,
            idempotencyKey: 'idem-refund-cancel-1'
        );

        $reservation = $result['reservation']->fresh();

        $this->assertSame('Cancelled', (string) ($reservation->status->value ?? $reservation->status));
        $this->assertNull($reservation->checked_in_at);
        $this->assertNull($reservation->checked_out_at);
        $this->assertNull($reservation->no_show_at);
        $this->assertNotNull($reservation->cancelled_at);
    }
}
