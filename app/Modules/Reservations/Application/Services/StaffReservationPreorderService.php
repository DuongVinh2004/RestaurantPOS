<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\PreorderStatus;
use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Modules\Ordering\Domain\Models\Preorder;
use App\Modules\Ordering\Domain\Models\PreorderItem;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Support\AuditEvent;
use App\Support\ValidationExceptionFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StaffReservationPreorderService
{
    public function __construct(
        private readonly ReservationLockService $locks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getPreorderForStaff(int $reservationId): array
    {
        $reservation = $this->getReservation($reservationId);
        $preorder = $reservation->preorder()->with('items.menuItem')->first();

        if (!$preorder) {
            return [
                'reservation_id' => $reservationId,
                'pre_order' => [
                    'present' => false,
                ],
            ];
        }

        return [
            'reservation_id' => $reservationId,
            'pre_order' => [
                'present' => true,
                'preorder_id' => $preorder->preorder_id,
                'order_row_version' => $preorder->row_version,
                'order_status' => $preorder->status->value,
                'service_time' => $reservation->start_time?->toIso8601String(),
                'currency' => $preorder->currency,
                'totals' => [
                    'quantity' => $preorder->total_quantity,
                    'subtotal' => $preorder->subtotal_amount,
                ],
                'lines' => $preorder->items->map(fn (PreorderItem $item) => [
                    'order_item_id' => $item->preorder_item_id,
                    'item_id' => $item->menu_item_id,
                    'name' => $item->item_name_snapshot,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price_snapshot,
                    'line_total' => $item->line_total_snapshot,
                    'currency' => $item->currency,
                    'notes' => $item->notes,
                ])->values()->all(),
            ],
        ];
    }

    public function confirmPreorder(int $reservationId, int $staffId): Preorder
    {
        return $this->locks->withReservationLock($reservationId, function () use ($reservationId, $staffId) {
            return DB::transaction(function () use ($reservationId, $staffId) {
                $reservation = $this->getReservation($reservationId);
                $preorder = $this->getPreorderForUpdate($reservation);

                if ($preorder->status !== PreorderStatus::Submitted) {
                    throw ValidationExceptionFactory::make([
                        'status' => ['Only submitted pre-orders can be confirmed.'],
                    ]);
                }

                $preorder->status = PreorderStatus::Confirmed;
                $preorder->confirmed_at = Carbon::now('UTC');
                $preorder->save();

                AuditEvent::info('staff.reservation.preorder.confirmed', [
                    'reservation_id' => $reservationId,
                    'preorder_id' => $preorder->preorder_id,
                    'staff_user_id' => $staffId,
                ]);

                return $preorder;
            });
        });
    }

    public function rejectPreorder(int $reservationId, int $staffId): Preorder
    {
        return $this->locks->withReservationLock($reservationId, function () use ($reservationId, $staffId) {
            return DB::transaction(function () use ($reservationId, $staffId) {
                $reservation = $this->getReservation($reservationId);
                $preorder = $this->getPreorderForUpdate($reservation);

                if (!in_array($preorder->status, [PreorderStatus::Submitted, PreorderStatus::Confirmed], true)) {
                    throw ValidationExceptionFactory::make([
                        'status' => ['Only submitted or confirmed pre-orders can be rejected.'],
                    ]);
                }

                $preorder->status = PreorderStatus::Rejected;
                $preorder->rejected_at = Carbon::now('UTC');
                $preorder->save();

                AuditEvent::info('staff.reservation.preorder.rejected', [
                    'reservation_id' => $reservationId,
                    'preorder_id' => $preorder->preorder_id,
                    'staff_user_id' => $staffId,
                ]);

                return $preorder;
            });
        });
    }

    public function convertPreorder(int $reservationId, int $staffId): ReservationOrder
    {
        return $this->locks->withReservationLock($reservationId, function () use ($reservationId, $staffId) {
            return DB::transaction(function () use ($reservationId, $staffId) {
                $reservation = $this->getReservation($reservationId);
                $preorder = $this->getPreorderForUpdate($reservation);

                if ($preorder->status !== PreorderStatus::Confirmed) {
                    throw ValidationExceptionFactory::make([
                        'status' => ['Only confirmed pre-orders can be converted to active orders.'],
                    ]);
                }

                if ($reservation->checked_in_at === null) {
                    throw ValidationExceptionFactory::make([
                        'reservation' => ['Reservation must be checked in before converting pre-order.'],
                    ]);
                }

                $now = Carbon::now('UTC');

                // Create a new OnSpot ReservationOrder based on the Preorder
                $order = new ReservationOrder;
                $order->reservation_id = $reservationId;
                $order->order_type = ReservationOrderType::OnSpot;
                $order->status = ReservationOrderStatus::Active;
                $order->notes = 'Converted from Preorder #' . $preorder->preorder_id;
                $order->created_by = $staffId;
                $order->updated_by = $staffId;
                $order->created_at = $now;
                $order->updated_at = $now;
                $order->save();

                // Convert items
                foreach ($preorder->items as $preorderItem) {
                    $item = new ReservationOrderItem;
                    $item->order_id = $order->order_id;
                    $item->item_id = $preorderItem->menu_item_id;
                    $item->quantity = $preorderItem->quantity;
                    $item->unit_price = $preorderItem->unit_price_snapshot;
                    $item->line_total = $preorderItem->line_total_snapshot;
                    $item->currency = $preorderItem->currency;
                    $item->item_name_snapshot = $preorderItem->item_name_snapshot;
                    $item->status = ReservationOrderItemStatus::Ordered;
                    $item->notes = $preorderItem->notes;
                    $item->updated_by = $staffId;
                    $item->created_at = $now;
                    $item->updated_at = $now;
                    $item->save();
                }

                // Update Preorder Status
                $preorder->status = PreorderStatus::Converted;
                $preorder->converted_at = $now;
                $preorder->save();

                AuditEvent::info('staff.reservation.preorder.converted', [
                    'reservation_id' => $reservationId,
                    'preorder_id' => $preorder->preorder_id,
                    'order_id' => $order->order_id,
                    'staff_user_id' => $staffId,
                ]);

                return $order->load('items.item');
            });
        });
    }

    private function getReservation(int $reservationId): Reservation
    {
        $reservation = Reservation::query()->whereKey($reservationId)->first();
        if (!$reservation) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }
        return $reservation;
    }

    private function getPreorderForUpdate(Reservation $reservation): Preorder
    {
        $preorder = $reservation->preorder()->lockForUpdate()->first();
        if (!$preorder) {
            throw ValidationExceptionFactory::make([
                'pre_order' => ['No pre-order found for this reservation.'],
            ]);
        }
        return $preorder;
    }
}
