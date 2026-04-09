<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ReservationOrderItemStatus;
use App\Models\ReservationOrder;
use App\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin ReservationOrder
 */
class StaffOrderReadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ReservationOrder $order */
        $order = $this->resource;
        $reservation = $order->reservation;
        $tables = $reservation?->tables ?? collect();
        $preferredTableId = $request->attributes->get('staff_order_read_context_table_id');
        $primaryTable = $this->resolvePrimaryTable($tables, $preferredTableId !== null ? (int) $preferredTableId : null);

        $orderPayload = (new ReservationOrderResource($order))->toArray($request);
        $reservationPayload = $reservation ? (new ReservationResource($reservation))->toArray($request) : null;

        return [
            'order' => $orderPayload,
            'table' => $primaryTable ? (new RestaurantTableResource($primaryTable))->toArray($request) : null,
            'tables' => RestaurantTableResource::collection($tables)->toArray($request),
            'reservation' => $reservationPayload,
            'customer' => $reservationPayload['user'] ?? null,
            'items' => $orderPayload['items'] ?? [],
            'item_summary' => $this->buildItemSummary($order),
            'financial_summary' => [
                'settlement_scope' => 'reservation',
                'subtotal' => data_get($orderPayload, 'totals.subtotal'),
                'discount' => data_get($orderPayload, 'totals.discount'),
                'total_due' => data_get($orderPayload, 'totals.total_due'),
                'paid' => data_get($orderPayload, 'totals.paid'),
                'deposit_applied' => data_get($orderPayload, 'totals.deposit_applied'),
                'deposit_net' => data_get($orderPayload, 'totals.deposit_net'),
                'final_paid' => data_get($orderPayload, 'totals.final_paid'),
                'outstanding' => data_get($orderPayload, 'totals.outstanding'),
                'currency' => data_get($orderPayload, 'totals.currency'),
                'payment_status' => $orderPayload['payment_status'] ?? null,
                'reservation_payment_summary' => $reservationPayload['payment_summary'] ?? null,
            ],
        ];
    }

    /**
     * @param Collection<int,RestaurantTable> $tables
     */
    private function resolvePrimaryTable(Collection $tables, ?int $preferredTableId): ?RestaurantTable
    {
        if ($tables->isEmpty()) {
            return null;
        }

        if ($preferredTableId !== null) {
            /** @var RestaurantTable|null $preferred */
            $preferred = $tables->firstWhere('table_id', $preferredTableId);
            if ($preferred instanceof RestaurantTable) {
                return $preferred;
            }
        }

        /** @var RestaurantTable|null $first */
        $first = $tables->sortBy('table_id')->first();

        return $first;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildItemSummary(ReservationOrder $order): array
    {
        $statusCounts = [
            ReservationOrderItemStatus::Ordered->value => 0,
            ReservationOrderItemStatus::InProgress->value => 0,
            ReservationOrderItemStatus::Served->value => 0,
            ReservationOrderItemStatus::Cancelled->value => 0,
        ];
        $statusQuantities = [
            ReservationOrderItemStatus::Ordered->value => 0,
            ReservationOrderItemStatus::InProgress->value => 0,
            ReservationOrderItemStatus::Served->value => 0,
            ReservationOrderItemStatus::Cancelled->value => 0,
        ];

        $lineCount = 0;
        $quantityTotal = 0;
        $activeQuantity = 0;
        $cancelledQuantity = 0;

        foreach ($order->items as $item) {
            $status = (string) ($item->status?->value ?? $item->status);
            $quantity = (int) ($item->quantity ?? 0);

            $lineCount++;
            $quantityTotal += $quantity;
            $statusCounts[$status] = (int) ($statusCounts[$status] ?? 0) + 1;
            $statusQuantities[$status] = (int) ($statusQuantities[$status] ?? 0) + $quantity;

            if ($status === ReservationOrderItemStatus::Cancelled->value) {
                $cancelledQuantity += $quantity;
                continue;
            }

            $activeQuantity += $quantity;
        }

        return [
            'line_count' => $lineCount,
            'quantity_total' => $quantityTotal,
            'active_quantity' => $activeQuantity,
            'cancelled_quantity' => $cancelledQuantity,
            'status_counts' => $statusCounts,
            'status_quantities' => $statusQuantities,
        ];
    }
}
