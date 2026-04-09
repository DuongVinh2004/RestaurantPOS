<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Reservation;
use App\Services\ReservationFinancialSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class ReservationFinancialSyncServiceFeatureTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_compute_reservation_bill_snapshot_rejects_mixed_currency_order_items(): void
    {
        $reservationId = $this->createReservation();
        $orderId = $this->createOrder(['reservation_id' => $reservationId, 'status' => 'Completed']);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'USD',
            'quantity' => 1,
            'unit_price' => 20,
            'line_total' => 20,
        ]);

        $service = new ReservationFinancialSyncService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Mixed currency is not supported');

        $service->computeReservationBillSnapshot($reservationId, 0.0, false);
    }

    public function test_sync_reservation_discount_snapshot_updates_final_bill_amount_and_currency(): void
    {
        $reservationId = $this->createReservation([
            'billed_at' => $this->nowUtc(),
            'final_bill_amount' => '0.00',
            'bill_currency' => null,
        ]);
        $orderId = $this->createOrder(['reservation_id' => $reservationId, 'status' => 'Completed']);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 2,
            'unit_price' => 75000,
            'line_total' => 150000,
        ]);

        $reservation = Reservation::query()->findOrFail($reservationId);
        $service = new ReservationFinancialSyncService();
        $service->syncReservationDiscountSnapshot($reservation, 10000.0, false);

        $this->assertSame(10000.0, (float) $reservation->discount_amount);
        $this->assertSame(140000.0, (float) $reservation->final_bill_amount);
        $this->assertSame('VND', (string) $reservation->bill_currency);
    }

    public function test_sync_deposit_snapshot_marks_partial_refund_correctly(): void
    {
        $reservation = new Reservation([
            'deposit_required_amount' => 100000.0,
        ]);

        $service = new ReservationFinancialSyncService();
        $service->syncDepositSnapshot($reservation, [
            'deposit_captured_amount' => 100000.0,
            'deposit_refunded_amount' => 40000.0,
            'deposit_net_amount' => 60000.0,
            'over_refunded_amount' => 0.0,
            'has_over_refund' => 0.0,
        ], false);

        $this->assertSame('PartiallyRefunded', (string) ($reservation->deposit_status->value ?? $reservation->deposit_status));
        $this->assertSame(60000.0, (float) $reservation->deposit_paid_amount);
    }
}
