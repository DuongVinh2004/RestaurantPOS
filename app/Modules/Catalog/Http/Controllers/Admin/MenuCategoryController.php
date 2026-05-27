<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\UseCases\Management\MenuCatalogManagementService;
use App\Modules\Catalog\Http\Requests\Admin\ListMenuCategoriesRequest;
use App\Modules\Catalog\Http\Requests\Admin\StoreMenuCategoryRequest;
use App\Modules\Catalog\Http\Requests\Admin\UpdateMenuCategoryRequest;
use App\Modules\Catalog\Http\Resources\Admin\MenuCategoryResource;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

class MenuCategoryController extends Controller
{
    public function __construct(
        private readonly MenuCatalogManagementService $menuService,
    ) {}

    #[ResponseFromApiResource(MenuCategoryResource::class, collection: true)]
    public function index(ListMenuCategoriesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->wantsListingPagination()) {
            $paginator = $this->menuService->paginateCategories($validated);

            return response()->json([
                'data' => MenuCategoryResource::collection(collect($paginator->items())),
                'meta' => ListingMetaFactory::paginated(
                    $paginator,
                    [
                        'include_deleted' => (bool) ($validated['include_deleted'] ?? false),
                        'q' => $validated['q'] ?? null,
                    ],
                    [
                        'supported' => true,
                        'value' => (string) ($validated['sort'] ?? 'sort_order'),
                        'by' => (string) ($validated['sort_by'] ?? 'sort_order'),
                        'dir' => (string) ($validated['sort_dir'] ?? 'asc'),
                    ],
                    ListingMetaFactory::contract(
                        ['include_deleted', 'q'],
                        ['sort_order', 'name', 'category_id', 'updated_at'],
                        'sort_order',
                        true,
                        100,
                        [
                            'include_deleted' => 'filter[include_deleted]',
                            'q' => 'filter[q]',
                            'sort_by' => 'sort',
                            'sort_dir' => 'sort',
                        ],
                    ),
                ),
            ]);
        }

        $categories = $this->menuService->listCategories($validated);

        return response()->json([
            'data' => MenuCategoryResource::collection($categories),
            'meta' => ListingMetaFactory::legacyCollection(
                $categories->count(),
                [
                    'include_deleted' => (bool) ($validated['include_deleted'] ?? false),
                    'q' => $validated['q'] ?? null,
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? 'sort_order'),
                    'by' => (string) ($validated['sort_by'] ?? 'sort_order'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'asc'),
                ],
                ListingMetaFactory::contract(
                    ['include_deleted', 'q'],
                    ['sort_order', 'name', 'category_id', 'updated_at'],
                    'sort_order',
                    true,
                    100,
                    [
                        'include_deleted' => 'filter[include_deleted]',
                        'q' => 'filter[q]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
            ),
        ]);
    }

    #[ResponseFromApiResource(MenuCategoryResource::class)]
    public function show(int $category_id): JsonResponse
    {
        return response()->json([
            'data' => new MenuCategoryResource($this->menuService->showCategory($category_id)),
        ]);
    }

    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        $category = $this->menuService->createCategory(
            $request->validated(),
            (int) ($request->attributes->get('staff_actor_user_id') ?? 0),
        );

        return response()->json([
            'data' => new MenuCategoryResource($category),
        ], 201);
    }

    public function update(int $category_id, UpdateMenuCategoryRequest $request): JsonResponse
    {
        $category = $this->menuService->updateCategory(
            $category_id,
            $request->validated(),
            (int) ($request->attributes->get('staff_actor_user_id') ?? 0),
        );

        return response()->json([
            'data' => new MenuCategoryResource($category),
        ]);
    }
}
