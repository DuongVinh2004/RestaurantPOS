<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMenuItemRecipeLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ingredient = $this->relationLoaded('ingredient') ? $this->ingredient : null;

        return [
            'recipe_line_id' => (int) $this->recipe_line_id,
            'item_id' => (int) $this->item_id,
            'ingredient' => [
                'ingredient_id' => $ingredient instanceof Ingredient ? (int) $ingredient->ingredient_id : (int) $this->ingredient_id,
                'code' => $ingredient instanceof Ingredient && $ingredient->code !== null ? (string) $ingredient->code : null,
                'name' => $ingredient instanceof Ingredient ? (string) $ingredient->name : null,
                'unit_code' => $ingredient instanceof Ingredient ? (string) $ingredient->unit_code : (string) $this->unit_code,
                'is_active' => $ingredient instanceof Ingredient ? (bool) $ingredient->is_active : null,
            ],
            'quantity' => number_format((float) $this->quantity, 3, '.', ''),
            'unit_code' => (string) $this->unit_code,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'notes' => $this->notes !== null ? (string) $this->notes : null,
        ];
    }
}
