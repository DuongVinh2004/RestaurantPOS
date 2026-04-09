<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerMenuCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'category_id' => $this->category_id !== null ? (int) $this->category_id : null,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order !== null ? (int) $this->sort_order : null,
            'items_count' => (int) ($this->items_count ?? ($this->relationLoaded('items') ? $this->items->count() : 0)),
            'items' => $this->relationLoaded('items')
                ? CustomerMenuItemResource::collection($this->items)
                : [],
        ];
    }
}
