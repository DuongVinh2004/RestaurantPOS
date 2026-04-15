<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenStationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $outputMode = $this->output_mode?->value ?? (string) $this->output_mode;

        return [
            'station_id' => (int) $this->station_id,
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'description' => $this->description !== null ? (string) $this->description : null,
            'output_mode' => $outputMode,
            'printer_target' => $this->printer_target !== null ? (string) $this->printer_target : null,
            'is_active' => (bool) $this->is_active,
            'route_count' => isset($this->route_count) ? (int) $this->route_count : (int) ($this->categoryRoutes_count ?? 0),
            'ticket_counts' => [
                'queued' => isset($this->queued_ticket_count) ? (int) $this->queued_ticket_count : 0,
                'fired' => isset($this->fired_ticket_count) ? (int) $this->fired_ticket_count : 0,
                'ready' => isset($this->ready_ticket_count) ? (int) $this->ready_ticket_count : 0,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
