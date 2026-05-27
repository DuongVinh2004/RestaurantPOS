<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\UseCases\Management\MenuCatalogManagementService;
use App\Modules\Catalog\Http\Requests\Admin\ListMenuItemsRequest;
use App\Modules\Catalog\Http\Requests\Admin\StoreMenuItemRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateMenuItemRequest;
use App\Modules\Catalog\Http\Resources\Admin\MenuItemResource;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

class MenuItemController extends Controller
{
    public function __construct(
        private readonly MenuCatalogManagementService $menuService,
    ) {}

    #[ResponseFromApiResource(MenuItemResource::class, collection: true)]
    public function index(ListMenuItemsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->wantsListingPagination()) {
            $paginator = $this->menuService->paginateItems($validated);

            return response()->json([
                'data' => MenuItemResource::collection(collect($paginator->items())),
                'meta' => ListingMetaFactory::paginated(
                    $paginator,
                    [
                        'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
                        'is_available' => array_key_exists('is_available', $validated) ? $validated['is_available'] : null,
                        'q' => $validated['q'] ?? null,
                    ],
                    [
                        'supported' => true,
                        'value' => (string) ($validated['sort'] ?? 'name'),
                        'by' => (string) ($validated['sort_by'] ?? 'name'),
                        'dir' => (string) ($validated['sort_dir'] ?? 'asc'),
                    ],
                    ListingMetaFactory::contract(
                        ['category_id', 'is_available', 'q'],
                        ['name', 'code', 'item_id', 'category_id', 'updated_at'],
                        'name',
                        true,
                        100,
                        [
                            'category_id' => 'filter[category_id]',
                            'is_available' => 'filter[is_available]',
                            'q' => 'filter[q]',
                            'sort_by' => 'sort',
                            'sort_dir' => 'sort',
                        ],
                    ),
                ),
            ]);
        }

        $items = $this->menuService->listItems($validated);

        return response()->json([
            'data' => MenuItemResource::collection($items),
            'meta' => ListingMetaFactory::legacyCollection(
                $items->count(),
                [
                    'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
                    'is_available' => array_key_exists('is_available', $validated) ? $validated['is_available'] : null,
                    'q' => $validated['q'] ?? null,
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? 'name'),
                    'by' => (string) ($validated['sort_by'] ?? 'name'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'asc'),
                ],
                ListingMetaFactory::contract(
                    ['category_id', 'is_available', 'q'],
                    ['name', 'code', 'item_id', 'category_id', 'updated_at'],
                    'name',
                    true,
                    100,
                    [
                        'category_id' => 'filter[category_id]',
                        'is_available' => 'filter[is_available]',
                        'q' => 'filter[q]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
            ),
        ]);
    }

    #[ResponseFromApiResource(MenuItemResource::class)]
    public function show(int $item_id): JsonResponse
    {
        return response()->json([
            'data' => new MenuItemResource($this->menuService->showItem($item_id)),
        ]);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $item = $this->menuService->createItem(
            $request->validated(),
            (int) ($request->attributes->get('staff_actor_user_id') ?? 0),
        );

        return response()->json([
            'data' => new MenuItemResource($item),
        ], 201);
    }

    public function update(int $item_id, UpdateMenuItemRequest $request): JsonResponse
    {
        $item = $this->menuService->updateItem(
            $item_id,
            $request->validated(),
            (int) ($request->attributes->get('staff_actor_user_id') ?? 0),
        );

        return response()->json([
            'data' => new MenuItemResource($item),
        ]);
    }
}
