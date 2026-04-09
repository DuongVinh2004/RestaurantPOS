<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminMenuCategoryRequest;
use App\Http\Requests\Admin\CreateAdminMenuItemPriceRequest;
use App\Http\Requests\Admin\CreateAdminMenuItemRequest;
use App\Http\Requests\Admin\ListAdminMenuCategoriesRequest;
use App\Http\Requests\Admin\ListAdminMenuItemsRequest;
use App\Http\Requests\Admin\UpdateAdminMenuCategoryRequest;
use App\Http\Requests\Admin\UpdateAdminMenuItemRequest;
use App\Http\Resources\AdminMenuCategoryResource;
use App\Http\Resources\AdminMenuItemPriceResource;
use App\Http\Resources\AdminMenuItemResource;
use App\Services\Admin\AdminMenuService;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @deprecated Compatibility facade for the legacy aggregated admin menu stack.
 * Runtime routes now point at split controllers; keep this controller for internal adapters only.
 */
class AdminMenuController extends Controller
{
    public function __construct(
        private readonly AdminMenuService $menuService,
    ) {}

    public function listCategories(ListAdminMenuCategoriesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $categories = $this->menuService->listCategories($validated);

        return response()->json([
            'data' => AdminMenuCategoryResource::collection($categories)->toArray($request),
            'meta' => [
                'filters' => [
                    'include_deleted' => (bool) ($validated['include_deleted'] ?? false),
                ],
            ],
        ]);
    }

    public function createCategory(CreateAdminMenuCategoryRequest $request): JsonResponse
    {
        $category = $this->menuService->createCategory($request->validated());

        return response()->json([
            'data' => (new AdminMenuCategoryResource($category))->toArray($request),
        ], 201);
    }

    public function updateCategory(int $id, UpdateAdminMenuCategoryRequest $request): JsonResponse
    {
        $category = $this->menuService->updateCategory($id, $request->validated());

        return response()->json([
            'data' => (new AdminMenuCategoryResource($category))->toArray($request),
        ]);
    }

    public function listItems(ListAdminMenuItemsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $paginator = $this->menuService->listItems($validated);

        return response()->json([
            'data' => AdminMenuItemResource::collection(collect($paginator->items()))->toArray($request),
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

    public function showItem(int $id, ListAdminMenuItemsRequest $request): JsonResponse
    {
        try {
            $item = $this->menuService->showItem($id);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Menu item not found.');
        }

        return response()->json([
            'data' => (new AdminMenuItemResource($item))->toArray($request),
        ]);
    }

    public function createItem(CreateAdminMenuItemRequest $request): JsonResponse
    {
        $item = $this->menuService->createItem($request->validated());

        return response()->json([
            'data' => (new AdminMenuItemResource($item))->toArray($request),
        ], 201);
    }

    public function updateItem(int $id, UpdateAdminMenuItemRequest $request): JsonResponse
    {
        $item = $this->menuService->updateItem($id, $request->validated());

        return response()->json([
            'data' => (new AdminMenuItemResource($item))->toArray($request),
        ]);
    }

    public function listItemPrices(int $id, ListAdminMenuItemsRequest $request): JsonResponse
    {
        try {
            $prices = $this->menuService->listItemPrices($id, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Menu item not found.');
        }

        return response()->json([
            'data' => AdminMenuItemPriceResource::collection($prices)->toArray($request),
            'meta' => [
                'filters' => [
                    'as_of' => $request->validated('as_of'),
                ],
            ],
        ]);
    }

    public function createItemPrice(int $id, CreateAdminMenuItemPriceRequest $request): JsonResponse
    {
        try {
            $result = $this->menuService->createItemPrice($id, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Menu item not found.');
        }

        return response()->json([
            'data' => (new AdminMenuItemPriceResource($result['price']))->toArray($request),
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
