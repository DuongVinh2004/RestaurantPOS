<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\UseCases\Management\MenuCatalogManagementService;
use App\Modules\Catalog\Http\Requests\Admin\ListMenuCategoriesRequest;
use App\Modules\Catalog\Http\Requests\Admin\ListMenuItemPricesRequest;
use App\Modules\Catalog\Http\Requests\Admin\ListMenuItemsRequest;
use App\Modules\Catalog\Http\Requests\Admin\StoreMenuCategoryRequest;
use App\Modules\Catalog\Http\Requests\Admin\StoreMenuItemPriceRequest;
use App\Modules\Catalog\Http\Requests\Admin\StoreMenuItemRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateMenuCategoryRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateMenuItemRequest;
use App\Modules\Catalog\Http\Resources\Admin\MenuCategoryResource;
use App\Modules\Catalog\Http\Resources\Admin\MenuItemPriceResource;
use App\Modules\Catalog\Http\Resources\Admin\MenuItemResource;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @deprecated Compatibility facade for the legacy aggregated admin menu stack.
 * Runtime routes now point at split controllers; keep this controller for internal adapters only.
 */
class LegacyMenuController extends Controller
{
    public function __construct(
        private readonly MenuCatalogManagementService $menuService,
    ) {}

    public function listCategories(ListMenuCategoriesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $categories = $this->menuService->listCategories($validated);

        return response()->json([
            'data' => MenuCategoryResource::collection($categories)->toArray($request),
            'meta' => [
                'filters' => [
                    'include_deleted' => (bool) ($validated['include_deleted'] ?? false),
                ],
            ],
        ]);
    }

    public function createCategory(StoreMenuCategoryRequest $request): JsonResponse
    {
        $category = $this->menuService->createCategory($request->validated());

        return response()->json([
            'data' => (new MenuCategoryResource($category))->toArray($request),
        ], 201);
    }

    public function updateCategory(int $id, UpdateMenuCategoryRequest $request): JsonResponse
    {
        $category = $this->menuService->updateCategory($id, $request->validated());

        return response()->json([
            'data' => (new MenuCategoryResource($category))->toArray($request),
        ]);
    }

    public function listItems(ListMenuItemsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $paginator = $this->menuService->paginateItems($validated);

        return response()->json([
            'data' => MenuItemResource::collection(collect($paginator->items()))->toArray($request),
            'meta' => [
                'filters' => [
                    'category_id' => array_key_exists('category_id', $validated) && $validated['category_id'] !== null
                        ? (int) $validated['category_id']
                        : null,
                    'is_available' => array_key_exists('is_available', $validated) && $validated['is_available'] !== null
                        ? (bool) $validated['is_available']
                        : null,
                    'q' => ($validated['q'] ?? null) !== null && trim((string) $validated['q']) !== ''
                        ? trim((string) $validated['q'])
                        : null,
                ],
                'pagination' => $this->paginationMeta($paginator),
            ],
        ]);
    }

    public function showItem(int $id, ListMenuItemsRequest $request): JsonResponse
    {
        try {
            $item = $this->menuService->showItem($id);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Menu item not found.');
        }

        return response()->json([
            'data' => (new MenuItemResource($item))->toArray($request),
        ]);
    }

    public function createItem(StoreMenuItemRequest $request): JsonResponse
    {
        $item = $this->menuService->createItem($request->validated());

        return response()->json([
            'data' => (new MenuItemResource($item))->toArray($request),
        ], 201);
    }

    public function updateItem(int $id, UpdateMenuItemRequest $request): JsonResponse
    {
        $item = $this->menuService->updateItem($id, $request->validated());

        return response()->json([
            'data' => (new MenuItemResource($item))->toArray($request),
        ]);
    }

    public function listItemPrices(int $id, ListMenuItemPricesRequest $request): JsonResponse
    {
        try {
            $prices = $this->menuService->listPriceRows($id, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Menu item not found.');
        }

        return response()->json([
            'data' => MenuItemPriceResource::collection($prices)->toArray($request),
            'meta' => [
                'filters' => [
                    'as_of' => $request->validated('as_of'),
                ],
            ],
        ]);
    }

    public function createItemPrice(int $id, StoreMenuItemPriceRequest $request): JsonResponse
    {
        try {
            $price = $this->menuService->createPriceRow($id, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Menu item not found.');
        }

        return response()->json([
            'data' => (new MenuItemPriceResource($price))->toArray($request),
        ], 201);
    }

    /**
     * @return array<string, int>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function notFoundResponse(Request $request, string $message): JsonResponse
    {
        return ApiErrorResponse::json(
            $request,
            404,
            'not_found',
            $message,
        );
    }
}
