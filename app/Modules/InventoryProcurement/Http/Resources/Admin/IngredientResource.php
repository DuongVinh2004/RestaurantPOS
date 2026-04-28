<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class IngredientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ingredient_id' => (int) $this->ingredient_id,
            'code' => $this->code !== null ? (string) $this->code : null,
            'name' => (string) $this->name,
            'unit_code' => (string) $this->unit_code,
            'description' => $this->description !== null ? (string) $this->description : null,
            'is_active' => (bool) $this->is_active,
            'row_version' => (int) ($this->row_version ?? 1),
            'stock' => [
                'on_hand' => $this->decimal($this->stock_on_hand_quantity ?? 0),
                'unit_code' => (string) $this->unit_code,
            ],
            'recipe_usage_count' => (int) ($this->recipe_usage_count ?? 0),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 3, '.', '');
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
