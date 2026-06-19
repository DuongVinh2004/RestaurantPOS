<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\UseCases\Browsing\FavoriteMenuItemService;
use App\Support\Auth\RequestActorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteMenuItemController extends Controller
{
    public function __construct(
        private readonly FavoriteMenuItemService $favoriteService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);
        $userId = $actor->resolveCustomerUser()?->user_id;

        if ($userId === null) {
            return response()->json(['data' => []]);
        }

        $favorites = $this->favoriteService->getFavoriteItemIds($userId);

        return response()->json([
            'data' => $favorites,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);
        $userId = $actor->resolveCustomerUser()?->user_id;

        if ($userId === null) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'menu_item_id' => ['required', 'integer', 'min:1'],
        ]);

        $this->favoriteService->addFavorite($userId, (int) $data['menu_item_id']);

        return response()->json([
            'message' => 'Added to favorites.',
        ]);
    }

    public function destroy(int $menuItemId, Request $request): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);
        $userId = $actor->resolveCustomerUser()?->user_id;

        if ($userId === null) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $this->favoriteService->removeFavorite($userId, $menuItemId);

        return response()->json([
            'message' => 'Removed from favorites.',
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);
        $userId = $actor->resolveCustomerUser()?->user_id;

        if ($userId === null) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'menu_item_ids' => ['required', 'array'],
            'menu_item_ids.*' => ['integer', 'min:1'],
        ]);

        $this->favoriteService->syncFavorites($userId, $data['menu_item_ids']);

        return response()->json([
            'message' => 'Favorites synced successfully.',
        ]);
    }
}
