<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Catalog\Application\UseCases\PolicyPreview\MenuPreorderPolicyService;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationPreorderService
{
    public function __construct(
        private readonly MenuPreorderPolicyService $menuPreorderPolicyService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotForReservation(Reservation $reservation): array
    {
        $order = ReservationOrder::query()
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->where('order_type', ReservationOrderType::PreOrder->value)
            ->orderByRaw(
                'CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END',
                [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value]
            )
            ->orderByDesc('order_id')
            ->with([
                'items' => fn ($query) => $query
                    ->where('status', '!=', ReservationOrderItemStatus::Cancelled->value)
                    ->orderBy('order_item_id'),
                'items.item',
            ])
            ->first();

        if (! $order instanceof ReservationOrder) {
            return [
                'reservation_id' => (int) $reservation->reservation_id,
                'pre_order' => [
                    'present' => false,
                    'order_id' => null,
                    'status' => null,
                    'lines' => [],
                    'totals' => [
                        'subtotal' => '0.00',
                        'currency' => null,
                    ],
                ],
            ];
        }

        $lines = $order->items->map(function (ReservationOrderItem $item): array {
            return [
                'order_item_id' => (int) $item->order_item_id,
                'item_id' => (int) $item->item_id,
                'quantity' => (int) $item->quantity,
                'unit_price' => Money::format($item->unit_price ?? 0, true),
                'currency' => (string) ($item->currency ?? 'VND'),
                'line_total' => Money::format($item->line_total ?? 0, true),
                'status' => $item->status?->value ?? (string) $item->status,
                'item_name_snapshot' => $item->item_name_snapshot,
                'item' => $item->relationLoaded('item') && $item->item
                    ? [
                        'item_id' => (int) $item->item->item_id,
                        'code' => $item->item->code,
                        'name' => $item->item->name,
                    ]
                    : null,
            ];
        })->values();

        $subtotalMinor = Money::sumMinor($order->items, fn (ReservationOrderItem $item): mixed => $item->line_total ?? 0, true);
        $currency = $order->items->first()?->currency;

        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'pre_order' => [
                'present' => $lines->isNotEmpty(),
                'order_id' => (int) $order->order_id,
                'status' => $order->status?->value ?? (string) $order->status,
                'lines' => $lines->all(),
                'totals' => [
                    'subtotal' => Money::formatMinor($subtotalMinor),
                    'currency' => $currency !== null ? (string) $currency : null,
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $requestedItems
     * @return array<string, mixed>
     */
    public function replaceForReservation(Reservation $reservation, array $requestedItems, ?int $actorUserId = null): array
    {
        $reservationId = (int) $reservation->reservation_id;

        return DB::transaction(function () use ($reservationId, $requestedItems, $actorUserId): array {
            /** @var Reservation|null $lockedReservation */
            $lockedReservation = Reservation::query()
                ->whereKey($reservationId)
                ->lockForUpdate()
                ->first();

            if (! $lockedReservation instanceof Reservation) {
                throw ValidationException::withMessages([
                    'reservation_id' => ['Reservation not found.'],
                ]);
            }

            $this->assertReservationAllowsPreorderMutation($lockedReservation);
            $serviceStart = Carbon::parse((string) $lockedReservation->start_time)->utc();

            if (! MenuItem::supportsPreorderColumns()) {
                throw ValidationException::withMessages([
                    'pre_order_items' => ['Hệ thống chưa được đồng bộ contract pre-order. Vui lòng áp dụng patch database mới nhất rồi thử lại.'],
                ]);
            }

            $prepared = $this->menuPreorderPolicyService->prepareRequestedItems(
                $requestedItems,
                $serviceStart,
                $reservationId,
            );

            /** @var ReservationOrder|null $order */
            $order = ReservationOrder::query()
                ->where('reservation_id', $reservationId)
                ->where('order_type', ReservationOrderType::PreOrder->value)
                ->where('status', ReservationOrderStatus::Active->value)
                ->orderByDesc('order_id')
                ->lockForUpdate()
                ->first();

            $now = Carbon::now('UTC');

            if (! $order instanceof ReservationOrder) {
                $order = new ReservationOrder();
                $order->reservation_id = $reservationId;
                $order->setAttribute('order_type', ReservationOrderType::PreOrder);
                $order->status = ReservationOrderStatus::Active;
                $order->notes = null;
                $order->created_by = $actorUserId;
                $order->updated_by = $actorUserId;
                $order->created_at = $now;
                $order->updated_at = $now;
                $order->save();
            } else {
                $order->updated_by = $actorUserId;
                $order->updated_at = $now;
                $order->save();
            }

            ReservationOrderItem::query()
                ->where('order_id', (int) $order->order_id)
                ->lockForUpdate()
                ->get()
                ->each(static function (ReservationOrderItem $item): void {
                    $item->delete();
                });

            foreach ($prepared['rows'] as $row) {
                $itemId = (int) $row['item_id'];
                $quantity = (int) $row['quantity'];
                $menuItem = $prepared['menu_items']->get($itemId);
                $priceRow = $prepared['price_rows']->get($itemId);

                $item = new ReservationOrderItem();
                $item->order_id = (int) $order->order_id;
                $item->item_id = $itemId;
                $item->quantity = $quantity;
                $unitPriceMinor = Money::minorUnits($priceRow->price, true);
                $item->unit_price = Money::formatMinor($unitPriceMinor);
                $item->currency = (string) $priceRow->currency;
                $item->line_total = Money::formatMinor($unitPriceMinor * $quantity);
                $item->item_name_snapshot = $menuItem ? (string) $menuItem->name : null;
                $item->status = ReservationOrderItemStatus::Ordered;
                $item->notes = null;
                $item->updated_by = $actorUserId;
                $item->created_at = $now;
                $item->updated_at = $now;
                $item->save();
            }

            $lockedReservation->loadMissing('orders.items.item');

            return $this->snapshotForReservation($lockedReservation);
        });
    }

    private function assertReservationAllowsPreorderMutation(Reservation $reservation): void
    {
        $status = $reservation->status instanceof ReservationStatus
            ? $reservation->status
            : ReservationStatus::from((string) $reservation->getRawOriginal('status'));

        if ($status !== ReservationStatus::Confirmed) {
            throw ValidationException::withMessages([
                'reservation_id' => ['Only Confirmed reservations can update pre-order items.'],
            ]);
        }

        if ($reservation->checked_in_at !== null) {
            throw ValidationException::withMessages([
                'reservation_id' => ['Checked-in reservations cannot update pre-order items.'],
            ]);
        }

        if ($reservation->checked_out_at !== null) {
            throw ValidationException::withMessages([
                'reservation_id' => ['Completed reservations cannot update pre-order items.'],
            ]);
        }

        if ($reservation->cancelled_at !== null) {
            throw ValidationException::withMessages([
                'reservation_id' => ['Cancelled reservations cannot update pre-order items.'],
            ]);
        }
    }
}
