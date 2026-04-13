<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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
        $staffId = $this->createUser(['role_name' => 'Staff']);
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
}
