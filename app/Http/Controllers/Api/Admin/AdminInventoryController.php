<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminIngredientRequest;
use App\Http\Requests\Admin\CreateAdminIngredientStockMovementRequest;
use App\Http\Requests\Admin\ListAdminIngredientsRequest;
use App\Http\Requests\Admin\ListAdminIngredientStockMovementsRequest;
use App\Http\Requests\Admin\UpdateAdminIngredientRequest;
use App\Http\Requests\Admin\UpsertAdminMenuItemRecipeRequest;
use App\Http\Resources\AdminIngredientResource;
use App\Http\Resources\AdminIngredientStockMovementResource;
use App\Http\Resources\AdminMenuItemRecipeLineResource;
use App\Services\Admin\AdminInventoryService;
use App\Services\FeatureFlagService;
use App\Support\ApiErrorResponse;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function __construct(
        private readonly AdminInventoryService $inventoryService,
        private readonly FeatureFlagService $featureFlags,
    ) {}

    public function listIngredients(ListAdminIngredientsRequest $request): JsonResponse
    {
        $this->assertInventoryUpliftEnabled($request);
        $validated = $request->validated();
        $paginator = $this->inventoryService->paginateIngredients($validated);

        return response()->json([
            'data' => AdminIngredientResource::collection(collect($paginator->items()))->toArray($request),
            'meta' => ListingMetaFactory::paginated(
                $paginator,
                [
                    'is_active' => array_key_exists('is_active', $validated) && $validated['is_active'] !== null ? (bool) $validated['is_active'] : null,
                    'q' => $validated['q'] ?? null,
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? 'name'),
                    'by' => (string) ($validated['sort_by'] ?? 'name'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'asc'),
                ],
                ListingMetaFactory::contract(
                    ['is_active', 'q'],
                    ['name', 'code', 'ingredient_id', 'stock_on_hand_quantity', 'recipe_usage_count', 'updated_at'],
                    'name',
                    true,
                    (int) config('booking.admin_inventory_page_max', 100),
                    [
                        'is_active' => 'filter[is_active]',
                        'q' => 'filter[q]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
            ),
        ]);
    }

    public function showIngredient(int $id, ListAdminIngredientsRequest $request): JsonResponse
    {
        $this->assertInventoryUpliftEnabled($request);
        try {
            $ingredient = $this->inventoryService->findIngredient($id);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Ingredient not found.');
        }

        return response()->json([
            'data' => (new AdminIngredientResource($ingredient))->toArray($request),
        ]);
    }

    public function createIngredient(CreateAdminIngredientRequest $request): JsonResponse
    {
        $this->assertInventoryUpliftEnabled($request);
        $ingredient = $this->inventoryService->createIngredient($request->validated());

        return response()->json([
            'data' => (new AdminIngredientResource($ingredient))->toArray($request),
        ], 201);
    }

    public function updateIngredient(int $id, UpdateAdminIngredientRequest $request): JsonResponse
    {
        $this->assertInventoryUpliftEnabled($request);
        try {
            $ingredient = $this->inventoryService->updateIngredient($id, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Ingredient not found.');
        }

        return response()->json([
            'data' => (new AdminIngredientResource($ingredient))->toArray($request),
        ]);
    }

    public function showMenuItemRecipe(int $id, ListAdminIngredientsRequest $request): JsonResponse
    {
        $this->assertInventoryUpliftEnabled($request);
        try {
            $result = $this->inventoryService->getMenuItemRecipe($id);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Menu item not found.');
        }

        return response()->json([
            'data' => AdminMenuItemRecipeLineResource::collection($result['lines'])->toArray($request),
            'meta' => [
                'item' => [
                    'item_id' => (int) $result['item']->item_id,
                    'code' => $result['item']->code !== null ? (string) $result['item']->code : null,
                    'name' => (string) $result['item']->name,
                ],
                'count' => $result['lines']->count(),
            ],
        ]);
    }

    public function upsertMenuItemRecipe(int $id, UpsertAdminMenuItemRecipeRequest $request): JsonResponse
    {
        $this->assertInventoryUpliftEnabled($request);
        try {
            $result = $this->inventoryService->syncMenuItemRecipe($id, (array) $request->validated('lines', []));
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Menu item or ingredient not found.');
        }

        return response()->json([
            'data' => AdminMenuItemRecipeLineResource::collection($result['lines'])->toArray($request),
            'meta' => [
                'item' => [
                    'item_id' => (int) $result['item']->item_id,
                    'code' => $result['item']->code !== null ? (string) $result['item']->code : null,
                    'name' => (string) $result['item']->name,
                ],
                'count' => $result['lines']->count(),
            ],
        ]);
    }

    public function listIngredientMovements(int $id, ListAdminIngredientStockMovementsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->assertInventoryUpliftEnabled(
            $request,
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
        );

        try {
            $ingredient = $this->inventoryService->findIngredient($id);
            $paginator = $this->inventoryService->paginateIngredientMovements($id, $validated);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Ingredient not found.');
        }

        return response()->json([
            'data' => AdminIngredientStockMovementResource::collection(collect($paginator->items()))->toArray($request),
            'meta' => ListingMetaFactory::paginated(
                $paginator,
                [
                    'movement_type' => $validated['movement_type'] ?? null,
                    'branch_id' => $validated['branch_id'] ?? null,
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? '-created_at'),
                    'by' => (string) ($validated['sort_by'] ?? 'created_at'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'desc'),
                ],
                ListingMetaFactory::contract(
                    ['movement_type', 'branch_id'],
                    ['created_at', 'movement_id', 'movement_type', 'quantity_delta'],
                    '-created_at',
                    true,
                    (int) config('booking.admin_inventory_page_max', 100),
                    [
                        'movement_type' => 'filter[movement_type]',
                        'branch_id' => 'filter[branch_id]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
                [
                    'ingredient' => [
                        'ingredient_id' => (int) $ingredient->ingredient_id,
                        'name' => (string) $ingredient->name,
                        'unit_code' => (string) $ingredient->unit_code,
                        'stock_on_hand' => number_format((float) ($ingredient->stock_on_hand_quantity ?? 0), 3, '.', ''),
                    ],
                ],
            ),
        ]);
    }

    public function createIngredientMovement(int $id, CreateAdminIngredientStockMovementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->assertInventoryUpliftEnabled(
            $request,
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
        );

        try {
            $result = $this->inventoryService->createIngredientMovement(
                $id,
                $validated,
                $this->resolveStaffActorUserId($request)
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Ingredient not found.');
        }

        return response()->json([
            'data' => (new AdminIngredientStockMovementResource($result['movement']))->toArray($request),
            'meta' => [
                'stock_on_hand' => $result['stock_on_hand'],
            ],
        ], 201);
    }

    private function resolveStaffActorUserId(mixed $request): ?int
    {
        $actor = $request->attributes->get('staff_actor_user_id');

        return is_numeric($actor) ? (int) $actor : null;
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

    private function assertInventoryUpliftEnabled(Request $request, ?int $branchId = null): void
    {
        $feature = $this->featureFlags->resolve('inventory.uplift', $branchId);
        if ($feature['enabled'] ?? false) {
            return;
        }

        throw new HttpResponseException(ApiErrorResponse::json(
            $request,
            403,
            'feature_disabled',
            (string) ($feature['message'] ?? 'Inventory uplift features are disabled for this rollout.'),
            extra: [
                'feature_key' => (string) ($feature['feature_key'] ?? 'inventory.uplift'),
                'branch_id' => $feature['branch_id'] ?? $branchId,
            ],
        ));
    }
}
