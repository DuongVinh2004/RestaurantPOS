<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\CheckoutPayments\Application\Services\CheckoutResponseFactory;
use App\Modules\CheckoutPayments\Application\Services\SettlementAmountCalculator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CheckoutResponseFactoryTest extends TestCase
{
    public function test_attach_totals_sets_paid_and_outstanding_amounts_without_recomputing_snapshot(): void
    {
        $reservation = new Reservation([
            'reservation_id' => 11,
            'reservation_code' => 'RES-FACTORY-001',
            'user_id' => 25,
            'guest_count' => 2,
            'status' => ReservationStatus::Reserved->value,
            'start_time' => Carbon::parse('2026-03-18 19:00:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-18 21:00:00', 'UTC'),
            'discount_amount' => 0,
            'checked_in_at' => Carbon::parse('2026-03-18 19:05:00', 'UTC'),
            'bill_currency' => 'VND',
        ]);
        $reservation->exists = true;

        $reservation->setRelation('payments', collect([
            new Payment([
                'payment_id' => 501,
                'reservation_id' => 11,
                'payment_type' => 'Deposit',
                'payment_method' => 'Cash',
                'amount' => 30.0,
                'currency' => 'VND',
                'status' => 'Success',
                'paid_at' => Carbon::now('UTC'),
                'refund_of_payment_id' => null,
                'provider_response_json' => null,
            ]),
            new Payment([
                'payment_id' => 502,
                'reservation_id' => 11,
                'payment_type' => 'Final',
                'payment_method' => 'Cash',
                'amount' => 25.0,
                'currency' => 'VND',
                'status' => 'Success',
                'paid_at' => Carbon::now('UTC'),
                'refund_of_payment_id' => null,
                'provider_response_json' => null,
            ]),
        ]));

        $order = new ReservationOrder([
            'order_id' => 41,
            'reservation_id' => 11,
            'order_type' => ReservationOrderType::OnSpot->value,
            'status' => ReservationOrderStatus::Active->value,
            'notes' => 'factory-test',
            'row_version' => 1,
        ]);
        $order->exists = true;
        $order->setRelation('reservation', $reservation);

        $factory = new CheckoutResponseFactory(new SettlementAmountCalculator());
        $hydrated = $factory->attachTotals($order, 100.0, 10.0, 90.0, 'VND');
        $payload = $factory->buildCheckoutResponse($hydrated, 'VND');

        $this->assertSame(90.0, $hydrated->getAttribute('total_due_amount'));
        $this->assertSame(55.0, $hydrated->getAttribute('paid_amount'));
        $this->assertSame(30.0, $hydrated->getAttribute('deposit_applied_amount'));
        $this->assertSame(25.0, $hydrated->getAttribute('final_paid_amount'));
        $this->assertSame(35.0, $hydrated->getAttribute('outstanding_amount'));
        $this->assertSame('Partial', $payload['payment_status']);
        $this->assertSame('VND', $payload['currency']);
        $this->assertSame('Reserved', $payload['reservation_status']);
    }

    public function test_build_refund_response_formats_summary_and_currency(): void
    {
        $reservation = new Reservation([
            'reservation_id' => 12,
            'reservation_code' => 'RES-FACTORY-REFUND-001',
            'user_id' => 26,
            'guest_count' => 2,
            'status' => ReservationStatus::Completed->value,
            'start_time' => Carbon::parse('2026-03-18 19:00:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-18 21:00:00', 'UTC'),
            'bill_currency' => 'vnd',
            'discount_amount' => 0,
            'checked_in_at' => Carbon::parse('2026-03-18 19:05:00', 'UTC'),
            'checked_out_at' => Carbon::parse('2026-03-18 21:05:00', 'UTC'),
        ]);

        $factory = new CheckoutResponseFactory(new SettlementAmountCalculator());
        $payload = $factory->buildRefundResponse(
            reservation: $reservation,
            summary: [
                'deposit_captured_amount' => 100.0,
                'deposit_refunded_amount' => 20.0,
                'deposit_net_amount' => 80.0,
                'final_captured_amount' => 50.0,
                'final_refunded_amount' => 10.0,
                'final_net_amount' => 40.0,
                'captured_amount' => 150.0,
                'refunded_amount' => 30.0,
                'net_paid_amount' => 120.0,
            ],
            refundPaymentIds: [5, '6'],
            refundAmountThisCall: 30.0,
            refundScope: 'all',
            cancelled: true,
            currency: '',
        );

        $this->assertSame('30.00', $payload['refund']['refund_amount']);
        $this->assertSame('VND', $payload['refund']['currency']);
        $this->assertSame([5, 6], $payload['refund']['refund_payment_ids']);
        $this->assertTrue($payload['refund']['cancelled']);
        $this->assertSame('Completed', $payload['refund']['reservation_status']);
        $this->assertSame('120.00', $payload['refund']['payment_summary']['net_paid_total']);
    }
}
