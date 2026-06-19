<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuModifierGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group_id' => (int) $this->group_id,
            'name' => (string) $this->name,
            'description' => $this->description,
            'min_selections' => (int) $this->min_selections,
            'max_selections' => (int) $this->max_selections,
            'is_active' => (bool) $this->is_active,
            'modifiers' => $this->whenLoaded('modifiers', fn () => MenuModifierResource::collection($this->modifiers)),
        ];
    }
}
