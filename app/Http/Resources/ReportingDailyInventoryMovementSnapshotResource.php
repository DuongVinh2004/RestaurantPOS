<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportingDailyInventoryMovementSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $qty = function (mixed $value): float {
            return round((float) ($value ?? 0), 3);
        };

        return [
            'snapshot_id' => (int) $this->snapshot_id,
            'business_date' => optional($this->business_date)?->format('Y-m-d'),
            'branch_id' => (int) $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn (): array => [
                'branch_id' => (int) $this->branch->branch_id,
                'branch_code' => (string) $this->branch->branch_code,
                'branch_name' => (string) $this->branch->branch_name,
                'is_default' => (bool) $this->branch->is_default,
            ]),
            'ingredient_id' => (int) $this->ingredient_id,
            'ingredient' => $this->whenLoaded('ingredient', fn (): array => [
                'ingredient_id' => (int) $this->ingredient->ingredient_id,
                'code' => (string) ($this->ingredient->code ?? ''),
                'name' => (string) $this->ingredient->name,
                'unit_code' => (string) $this->ingredient->unit_code,
                'is_active' => (bool) $this->ingredient->is_active,
            ]),
            'unit_code' => (string) $this->unit_code,
            'movement_summary' => [
                'movement_count' => (int) $this->movement_count,
                'purchase_receipt_movement_count' => (int) $this->purchase_receipt_movement_count,
                'stock_in_quantity' => $qty($this->stock_in_quantity),
                'stock_out_quantity' => $qty($this->stock_out_quantity),
                'adjustment_increase_quantity' => $qty($this->adjustment_increase_quantity),
                'adjustment_decrease_quantity' => $qty($this->adjustment_decrease_quantity),
                'wastage_quantity' => $qty($this->wastage_quantity),
                'net_quantity_delta' => $qty($this->net_quantity_delta),
                'last_movement_at' => $this->last_movement_at?->utc()->toIso8601String(),
            ],
            'freshness' => [
                'refreshed_at' => $this->refreshed_at?->utc()->toIso8601String(),
            ],
        ];
    }
}
