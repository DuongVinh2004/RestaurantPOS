<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuModifierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'modifier_id' => (int) $this->modifier_id,
            'group_id' => (int) $this->group_id,
            'name' => (string) $this->name,
            'description' => $this->description,
            'price_adjustment' => (float) $this->price_adjustment,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
