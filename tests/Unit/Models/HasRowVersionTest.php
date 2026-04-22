<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class HasRowVersionTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_reservation_save_increments_row_version_on_update(): void
    {
        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'row_version' => 1,
        ]);

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->findOrFail($reservationId);
        $reservation->notes = 'updated-note';
        $reservation->save();

        self::assertSame(2, (int) $reservation->fresh()->row_version);
    }

    public function test_reservation_order_save_increments_row_version_on_update(): void
    {
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'row_version' => 1,
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);

        /** @var ReservationOrder $order */
        $order = ReservationOrder::query()->findOrFail($orderId);
        $order->notes = 'updated-order-note';
        $order->save();

        self::assertSame(2, (int) $order->fresh()->row_version);
    }
}
