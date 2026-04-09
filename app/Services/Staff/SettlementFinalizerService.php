<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Services\LoyaltyPointsService;
use App\Services\RestaurantTableStateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SettlementFinalizerService
{
    public function __construct(
        private readonly LoyaltyPointsService $loyaltyPointsService,
        private readonly RestaurantTableStateService $tableStateService,
    ) {}

    /**
     * @param callable(Reservation,Collection<int,ReservationOrder>,?int):void $consumeAppliedVoucherLocked
     */
    public function completeReservationSettlement(
        Reservation $reservation,
        ?int $staffUserId,
        callable $consumeAppliedVoucherLocked,
    ): void {
        $reservationId = (int) $reservation->reservation_id;

        $tableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->lockForUpdate()
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($tableIds !== []) {
            DB::table('restaurant_tables')
                ->whereIn('table_id', $tableIds)
                ->lockForUpdate()
                ->get();
        }

        /** @var Collection<int,ReservationOrder> $activeOrders */
        $activeOrders = ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->where('status', ReservationOrderStatus::Active->value)
            ->lockForUpdate()
            ->get();

        foreach ($activeOrders as $activeOrder) {
            $activeOrder->status = ReservationOrderStatus::Completed;
            $activeOrder->updated_by = $staffUserId;
            $activeOrder->updated_at = Carbon::now('UTC');
            $activeOrder->save();
        }

        $reservation->status = ReservationStatus::Completed;
        $reservation->checked_out_at = Carbon::now('UTC');
        $reservation->updated_by = $staffUserId;
        $reservation->save();

        /** @var Collection<int,ReservationOrder> $orders */
        $orders = ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->whereIn('status', [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value])
            ->with('items')
            ->lockForUpdate()
            ->get();

        $consumeAppliedVoucherLocked($reservation, $orders, $staffUserId);
        $this->loyaltyPointsService->syncReservationCompletionLocked($reservation, $staffUserId);
        $this->tableStateService->releaseTablesSafely($tableIds, Carbon::now('UTC'));
    }
}
