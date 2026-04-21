<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\Cashiering\Application\UseCases\Reconciliation\SettlementFinalizerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class SettlementFinalizerServiceTest extends TestCase
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

    public function test_complete_reservation_settlement_completes_orders_consumes_voucher_syncs_loyalty_and_releases_tables(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => Carbon::parse('2026-03-18 18:30:00', 'UTC'),
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $activeOrderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => ReservationOrderType::OnSpot->value,
            'status' => ReservationOrderStatus::Active->value,
        ]);
        $completedOrderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => ReservationOrderType::OnSpot->value,
            'status' => ReservationOrderStatus::Completed->value,
        ]);

        $loyalty = Mockery::mock(LoyaltyPointsService::class);
        $loyalty->shouldReceive('syncReservationCompletionLocked')
            ->once()
            ->with(Mockery::on(fn ($reservation) => (int) $reservation->reservation_id === $reservationId), $staffId);

        $tableState = Mockery::mock(RestaurantTableStateService::class);
        $tableState->shouldReceive('releaseTablesSafely')
            ->once()
            ->with(
                [$tableId],
                Mockery::type(Carbon::class),
                $staffId,
                Mockery::on(fn (array $context): bool => $context === [
                    'reservation_id' => $reservationId,
                    'source' => 'staff_settlement_finalize',
                    'reason' => 'settlement_finalize',
                ])
            );

        $service = new SettlementFinalizerService($loyalty, $tableState);

        $voucherConsumed = false;
        $reservation = \App\Modules\Reservations\Domain\Models\Reservation::query()->findOrFail($reservationId);

        $service->completeReservationSettlement(
            reservation: $reservation,
            staffUserId: $staffId,
            consumeAppliedVoucherLocked: function ($lockedReservation, $lockedOrders, $actorUserId) use (&$voucherConsumed, $reservationId, $activeOrderId, $completedOrderId, $staffId): void {
                $voucherConsumed = true;
                $this->assertSame($reservationId, (int) $lockedReservation->reservation_id);
                $this->assertSame([$activeOrderId, $completedOrderId], $lockedOrders->pluck('order_id')->map(fn ($id) => (int) $id)->sort()->values()->all());
                $this->assertSame($staffId, $actorUserId);
            },
        );

        $reservation = \App\Modules\Reservations\Domain\Models\Reservation::query()->findOrFail($reservationId);
        $activeOrder = \App\Modules\Ordering\Domain\Models\ReservationOrder::query()->findOrFail($activeOrderId);
        $completedOrder = \App\Modules\Ordering\Domain\Models\ReservationOrder::query()->findOrFail($completedOrderId);

        $this->assertTrue($voucherConsumed);
        $this->assertSame(ReservationStatus::Completed->value, (string) ($reservation->status?->value ?? $reservation->status));
        $this->assertNotNull($reservation->checked_out_at);
        $this->assertSame($staffId, (int) ($reservation->updated_by ?? 0));
        $this->assertSame(ReservationOrderStatus::Completed->value, (string) ($activeOrder->status?->value ?? $activeOrder->status));
        $this->assertSame($staffId, (int) ($activeOrder->updated_by ?? 0));
        $this->assertSame(ReservationOrderStatus::Completed->value, (string) ($completedOrder->status?->value ?? $completedOrder->status));
    }
}
