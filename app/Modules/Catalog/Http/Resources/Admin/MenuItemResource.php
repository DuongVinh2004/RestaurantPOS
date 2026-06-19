<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources\Admin;

use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $currentPrice = $this->resolveCurrentPrice();

        return [
            'item_id' => (int) $this->item_id,
            'category_id' => $this->category_id !== null ? (int) $this->category_id : null,
            'code' => $this->code,
            'name' => (string) $this->name,
            'description' => $this->description,
            'img_url' => $this->img_url,
            'is_available' => (bool) ($this->is_available ?? false),
            'is_combo' => (bool) ($this->is_combo ?? false),
            'is_best_seller' => (bool) ($this->is_best_seller ?? false),
            'compare_at_price_amount' => $this->compare_at_price_amount !== null ? (float) $this->compare_at_price_amount : null,
            'serving_size' => $this->serving_size !== null ? (int) $this->serving_size : null,
            'combo_items_json' => $this->combo_items_json ?? null,
            'is_preorder_enabled' => $this->is_preorder_enabled !== null ? (bool) $this->is_preorder_enabled : null,
            'preorder_quota_per_day' => $this->preorder_quota_per_day !== null ? (int) $this->preorder_quota_per_day : null,
            'preorder_cutoff_minutes' => $this->preorder_cutoff_minutes !== null ? (int) $this->preorder_cutoff_minutes : null,
            'category' => $this->whenLoaded('category', function () {
                if (! $this->category) {
                    return null;
                }

                return new MenuCategoryResource($this->category);
            }),
            'modifier_groups' => $this->whenLoaded('modifierGroups', fn () => MenuModifierGroupResource::collection($this->modifierGroups)),
            'current_price' => $currentPrice ? new MenuItemPriceResource($currentPrice) : null,
            'prices' => $this->whenLoaded('prices', fn () => MenuItemPriceResource::collection($this->prices)),
        ];
    }

    private function resolveCurrentPrice(): ?MenuItemPrice
    {
        if ($this->relationLoaded('prices')) {
            $now = now();

            /** @var MenuItemPrice|null $price */
            $price = $this->prices
                ->filter(function (MenuItemPrice $row) use ($now): bool {
                    if ($row->effective_from === null || $row->effective_from > $now) {
                        return false;
                    }

                    return $row->effective_to === null || $row->effective_to > $now;
                })
                ->first();

            return $price;
        }

        return $this->priceAt();
    }
}
