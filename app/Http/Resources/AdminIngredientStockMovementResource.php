<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AdminIngredientStockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'movement_id' => (int) $this->movement_id,
            'branch_id' => isset($this->branch_id) ? (int) $this->branch_id : null,
            'ingredient_id' => (int) $this->ingredient_id,
            'movement_type' => (string) $this->movement_type,
            'quantity_delta' => number_format((float) $this->quantity_delta, 3, '.', ''),
            'unit_code' => (string) $this->unit_code,
            'reference' => [
                'type' => $this->reference_type !== null ? (string) $this->reference_type : null,
                'id' => $this->reference_id !== null ? (string) $this->reference_id : null,
            ],
            'notes' => $this->notes !== null ? (string) $this->notes : null,
            'created_by' => $this->created_by !== null ? (int) $this->created_by : null,
            'created_at' => $this->iso($this->created_at),
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }
}
