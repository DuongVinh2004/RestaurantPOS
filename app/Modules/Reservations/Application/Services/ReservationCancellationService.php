<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationCancellationService
{
    public function __construct(
        private readonly RestaurantTableStateService $tableStateService,
    ) {
    }

    /**
     * @param Collection<int,ReservationOrder> $orders
     * @param array<int,int> $tableIds
     */
    public function cancelAfterPaymentLocked(
        Reservation $reservation,
        Collection $orders,
        array $tableIds,
        ?int $staffUserId,
        ?string $cancelReason,
        ?Carbon $now = null,
    ): void {
        $currentStatus = (string) ($reservation->status?->value ?? $reservation->status);
        $now ??= Carbon::now('UTC');

        if (! in_array($currentStatus, ReservationStatus::activeDbValues(), true)) {
            throw ValidationException::withMessages([
                'reservation' => 'cancel-after-payment only supports Confirmed or checked-in (Reserved) reservations.',
            ]);
        }

        foreach ($orders as $order) {
            if ((string) ($order->status?->value ?? $order->status) !== ReservationOrderStatus::Active->value) {
                continue;
            }

            ReservationOrderItem::query()
                ->where('order_id', $order->order_id)
                ->whereNotIn('status', [ReservationOrderItemStatus::Cancelled->value, ReservationOrderItemStatus::Served->value])
                ->update([
                    'status' => ReservationOrderItemStatus::Cancelled->value,
                    'updated_by' => $staffUserId,
                    'updated_at' => $now,
                ]);

            $order->status = ReservationOrderStatus::Cancelled;
            $order->updated_by = $staffUserId;
            $order->updated_at = $now;
            $order->save();
        }

        if ($currentStatus === ReservationStatus::checkedInDbValue() && $tableIds !== []) {
            $this->tableStateService->releaseTablesSafely(
                $tableIds,
                $now,
                $staffUserId,
                [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'source' => 'reservation_cancel_after_payment',
                    'reason' => 'cancel_after_payment',
                ]
            );
        }

        $reservation->status = ReservationStatus::Cancelled;
        $reservation->cancelled_at = $now;
        $reservation->cancelled_by = $staffUserId;
        $reservation->cancel_reason = $cancelReason !== null && trim($cancelReason) !== ''
            ? trim($cancelReason)
            : ($reservation->cancel_reason ?: 'Cancelled after payment/refund flow');
    }
}
