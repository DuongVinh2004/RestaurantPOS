<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenStationCategoryRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'route_id' => (int) $this->route_id,
            'station_id' => (int) $this->station_id,
            'category_id' => (int) $this->category_id,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'category' => $this->whenLoaded('category', fn () => [
                'category_id' => (int) $this->category->category_id,
                'name' => (string) $this->category->name,
                'sort_order' => (int) ($this->category->sort_order ?? 0),
            ]),
            'station' => $this->whenLoaded('station', fn () => [
                'station_id' => (int) $this->station->station_id,
                'code' => (string) $this->station->code,
                'name' => (string) $this->station->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
