<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdminMenuCategoriesRequest;
use App\Http\Requests\Admin\StoreMenuCategoryRequest;
use App\Http\Requests\Admin\UpdateMenuCategoryRequest;
use App\Http\Resources\Admin\AdminMenuCategoryResource;
use App\Services\Admin\AdminMenuManagementService;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;

class AdminMenuCategoryController extends Controller
{
    public function __construct(
        private readonly AdminMenuManagementService $menuService,
    ) {}

    public function index(ListAdminMenuCategoriesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->wantsListingPagination()) {
            $paginator = $this->menuService->paginateCategories($validated);

            return response()->json([
                'data' => AdminMenuCategoryResource::collection($paginator->getCollection()),
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
            'data' => AdminMenuCategoryResource::collection($categories),
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

    public function show(int $category_id): JsonResponse
    {
        return response()->json([
            'data' => new AdminMenuCategoryResource($this->menuService->showCategory($category_id)),
        ]);
    }

    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        $category = $this->menuService->createCategory(
            $request->validated(),
            (int) ($request->attributes->get('staff_actor_user_id') ?? 0),
        );

        return response()->json([
            'data' => new AdminMenuCategoryResource($category),
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
            'data' => new AdminMenuCategoryResource($category),
        ]);
    }
}
