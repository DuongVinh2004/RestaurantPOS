<?php

declare(strict_types=1);

namespace App\Modules\Cashiering\Application\UseCases\Reconciliation;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Policies\ReservationStatusTransitionPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chot settlement cuoi cung cho reservation:
 * dong order active, mark completed, tieu voucher/loyalty, va nha ban.
 */
class SettlementFinalizerService
{
    public function __construct(
        private readonly LoyaltyPointsService $loyaltyPointsService,
        private readonly RestaurantTableStateService $tableStateService,
    ) {}

    /**
     * @param  callable(Reservation,Collection<int,ReservationOrder>,?int):void  $consumeAppliedVoucherLocked
     */
    public function completeReservationSettlement(
        Reservation $reservation,
        ?int $staffUserId,
        callable $consumeAppliedVoucherLocked,
    ): void {
        // Ham nay duoc goi khi bill da du tien; phan con lai la dong trang thai va don side-effect.
        // Pha 1: xac minh reservation du dieu kien complete va lock tat ca tai nguyen lien quan.
        $reservationId = (int) $reservation->reservation_id;
        $now = Carbon::now('UTC');

        ReservationStatusTransitionPolicy::assertTransitionAllowed(
            (string) ($reservation->status?->value ?? $reservation->status),
            ReservationStatus::Completed,
            false,
            'reservation'
        );
        $this->assertPaidServiceReservationHasBillSnapshot($reservation);

        // Table ids duoc lock cung reservation de release table sau settlement khong bi race.
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
        // Pha 2: moi order active phai duoc chuyen Completed truoc khi reservation dong lai.
        $activeOrders = ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->where('status', ReservationOrderStatus::Active->value)
            ->lockForUpdate()
            ->get();

        // Moi order active phai duoc chot truoc khi reservation chuyen sang Completed.
        foreach ($activeOrders as $activeOrder) {
            $activeOrder->status = ReservationOrderStatus::Completed;
            $activeOrder->updated_by = $staffUserId;
            $activeOrder->updated_at = $now;
            $activeOrder->save();
        }

        // Reservation chot thanh Completed chi sau khi active orders da duoc dong.
        $reservation->status = ReservationStatus::Completed;
        $reservation->checked_out_at = $now;
        $reservation->updated_by = $staffUserId;
        $reservation->save();

        /** @var Collection<int,ReservationOrder> $orders */
        // Reload lai tap order active/completed de voucher/loyalty nhin dung snapshot cuoi cung.
        $orders = ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->whereIn('status', [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value])
            ->with('items')
            ->lockForUpdate()
            ->get();

        // Sau khi chot reservation, moi xu ly quyen loi va nha ban de van hanh nhin thay ngay.
        $consumeAppliedVoucherLocked($reservation, $orders, $staffUserId);
        $this->loyaltyPointsService->syncReservationCompletionLocked($reservation, $staffUserId);
        $this->tableStateService->releaseTablesSafely($tableIds, $now, $staffUserId, [
            'reservation_id' => $reservationId,
            'source' => 'staff_settlement_finalize',
            'reason' => 'settlement_finalize',
        ]);
    }

    private function assertPaidServiceReservationHasBillSnapshot(Reservation $reservation): void
    {
        // Reservation da co payment dich vu thi bat buoc phai co bill snapshot day du truoc khi complete.
        $reservationId = (int) $reservation->reservation_id;
        if ($reservationId <= 0) {
            throw ValidationException::withMessages([
                'reservation' => ['Settlement finalization requires a persisted reservation.'],
            ]);
        }

        $hasPaidSettlement = DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->whereIn('payment_type', ['Deposit', 'Final'])
            ->whereIn('status', ['Success', 'Partial'])
            ->exists();

        if (! $hasPaidSettlement) {
            return;
        }

        $hasOnSpotServiceOrder = ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->where('order_type', ReservationOrderType::OnSpot->value)
            ->exists();

        if (! $hasOnSpotServiceOrder) {
            return;
        }

        if (
            $reservation->final_bill_amount !== null
            && trim((string) ($reservation->bill_currency ?? '')) !== ''
            && $reservation->billed_at !== null
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'bill_snapshot' => ['Paid service reservations require final_bill_amount, bill_currency, and billed_at before settlement can complete.'],
        ]);
    }
}
