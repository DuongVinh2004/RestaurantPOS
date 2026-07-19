<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Application\Services;

use App\Modules\Catalog\Domain\Models\MenuItemRecipe;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;

class OrderItemRecipeSnapshotService
{
    /**
     * @return list<array{
     *     recipe_line_id:int,
     *     ingredient_id:int,
     *     quantity:string,
     *     unit_code:string,
     *     sort_order:int,
     *     recipe_row_version:int
     * }>
     */
    public function snapshotForItemId(int $itemId): array
    {
        return $this->snapshotsForItemIds([$itemId])[$itemId] ?? [];
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int,list<array{
     *     recipe_line_id:int,
     *     ingredient_id:int,
     *     quantity:string,
     *     unit_code:string,
     *     sort_order:int,
     *     recipe_row_version:int
     * }>>
     */
    public function snapshotsForItemIds(array $itemIds): array
    {
        $normalizedItemIds = array_values(array_unique(array_filter(
            array_map('intval', $itemIds),
            static fn (int $itemId): bool => $itemId > 0,
        )));
        sort($normalizedItemIds);

        if ($normalizedItemIds === []) {
            return [];
        }

        $snapshots = array_fill_keys($normalizedItemIds, []);
        $recipeLines = MenuItemRecipe::query()
            ->whereIn('item_id', $normalizedItemIds)
            ->orderBy('item_id')
            ->orderBy('sort_order')
            ->orderBy('recipe_line_id')
            ->lockForUpdate()
            ->get([
                'recipe_line_id',
                'item_id',
                'ingredient_id',
                'quantity',
                'unit_code',
                'sort_order',
                'row_version',
            ]);

        foreach ($recipeLines as $recipeLine) {
            $itemId = (int) $recipeLine->item_id;
            $snapshots[$itemId][] = [
                'recipe_line_id' => (int) $recipeLine->recipe_line_id,
                'ingredient_id' => (int) $recipeLine->ingredient_id,
                'quantity' => number_format((float) $recipeLine->quantity, 3, '.', ''),
                'unit_code' => (string) $recipeLine->unit_code,
                'sort_order' => (int) $recipeLine->sort_order,
                'recipe_row_version' => (int) $recipeLine->row_version,
            ];
        }

        return $snapshots;
    }

    /**
     * @param  list<array{
     *     recipe_line_id:int,
     *     ingredient_id:int,
     *     quantity:string,
     *     unit_code:string,
     *     sort_order:int,
     *     recipe_row_version:int
     * }>|null  $snapshot
     */
    public function assignSnapshot(ReservationOrderItem $item, ?array $snapshot = null): void
    {
        $item->setAttribute(
            'recipe_snapshot',
            $snapshot ?? $this->snapshotForItemId((int) $item->item_id),
        );
    }
}
