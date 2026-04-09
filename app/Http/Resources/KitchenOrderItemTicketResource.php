<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenOrderItemTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ticketStatus = $this->ticket_status?->value ?? (string) $this->ticket_status;
        $outputMode = $this->output_mode?->value ?? (string) $this->output_mode;
        $orderItem = $this->relationLoaded('orderItem') ? $this->orderItem : null;
        $item = $this->relationLoaded('item') ? $this->item : null;
        $station = $this->relationLoaded('station') ? $this->station : null;
        $route = $this->relationLoaded('route') ? $this->route : null;

        return [
            'ticket_id' => (int) $this->ticket_id,
            'ticket_status' => $ticketStatus,
            'route_source' => $this->route_source !== null ? (string) $this->route_source : null,
            'dispatch_count' => (int) ($this->dispatch_count ?? 0),
            'recall_count' => (int) ($this->recall_count ?? 0),
            'output_mode' => $outputMode,
            'printer_target' => $this->printer_target !== null ? (string) $this->printer_target : null,
            'ticket_notes' => $this->ticket_notes !== null ? (string) $this->ticket_notes : null,
            'order' => [
                'order_id' => (int) $this->order_id,
                'reservation_id' => (int) $this->reservation_id,
            ],
            'station' => $station ? [
                'station_id' => (int) $station->station_id,
                'code' => (string) $station->code,
                'name' => (string) $station->name,
            ] : null,
            'route' => $route ? [
                'route_id' => (int) $route->route_id,
                'category_id' => (int) $route->category_id,
                'sort_order' => (int) ($route->sort_order ?? 0),
                'is_active' => (bool) ($route->is_active ?? false),
            ] : null,
            'routing' => [
                'route_present' => $route !== null,
                'route_active' => $route !== null ? (bool) ($route->is_active ?? false) : null,
                'station_matches_route' => $route !== null && $station !== null
                    ? ((int) $route->station_id === (int) $station->station_id)
                    : null,
            ],
            'order_item' => $orderItem ? [
                'order_item_id' => (int) $orderItem->order_item_id,
                'item_id' => (int) $orderItem->item_id,
                'quantity' => (int) $orderItem->quantity,
                'status' => $orderItem->status?->value ?? (string) $orderItem->status,
                'notes' => $orderItem->notes !== null ? (string) $orderItem->notes : null,
                'item_name_snapshot' => $orderItem->item_name_snapshot !== null ? (string) $orderItem->item_name_snapshot : null,
            ] : null,
            'item' => $item ? [
                'item_id' => (int) $item->item_id,
                'name' => (string) $item->name,
                'category_id' => $item->category_id !== null ? (int) $item->category_id : null,
                'category_name' => $item->relationLoaded('category') && $item->category !== null ? (string) $item->category->name : null,
            ] : null,
            'first_dispatched_at' => $this->first_dispatched_at?->toIso8601String(),
            'fired_at' => $this->fired_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'last_recalled_at' => $this->last_recalled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
