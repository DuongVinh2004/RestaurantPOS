<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases\Browsing;

use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\UserFavoriteMenuItem;
use Illuminate\Validation\ValidationException;

class FavoriteMenuItemService
{
    /**
     * @return array<int>
     */
    public function getFavoriteItemIds(int $userId): array
    {
        return UserFavoriteMenuItem::query()
            ->where('user_id', $userId)
            ->pluck('menu_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function addFavorite(int $userId, int $menuItemId): void
    {
        if (! MenuItem::query()->where('item_id', $menuItemId)->exists()) {
            throw ValidationException::withMessages([
                'menu_item_id' => ['Menu item does not exist.'],
            ]);
        }

        UserFavoriteMenuItem::query()->firstOrCreate([
            'user_id' => $userId,
            'menu_item_id' => $menuItemId,
        ]);
    }

    public function removeFavorite(int $userId, int $menuItemId): void
    {
        UserFavoriteMenuItem::query()
            ->where('user_id', $userId)
            ->where('menu_item_id', $menuItemId)
            ->delete();
    }

    public function syncFavorites(int $userId, array $menuItemIds): void
    {
        $existingItemIds = MenuItem::query()
            ->whereIn('item_id', $menuItemIds)
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($existingItemIds as $menuItemId) {
            UserFavoriteMenuItem::query()->firstOrCreate([
                'user_id' => $userId,
                'menu_item_id' => $menuItemId,
            ]);
        }
    }
}
