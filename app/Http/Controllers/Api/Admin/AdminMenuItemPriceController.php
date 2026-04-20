<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdminMenuItemPricesRequest;
use App\Http\Requests\Admin\StoreMenuItemPriceRequest;
use App\Http\Requests\Admin\UpdateMenuItemPriceRequest;
use App\Http\Resources\Admin\AdminMenuItemPriceResource;
use App\Services\Admin\AdminMenuManagementService;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;

class AdminMenuItemPriceController extends Controller
{
    public function __construct(
        private readonly AdminMenuManagementService $menuService,
    ) {}

    public function index(int $item_id, ListAdminMenuItemPricesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($request->wantsListingPagination()) {
            $paginator = $this->menuService->paginatePriceRows($item_id, $validated);

            return response()->json([
                'data' => AdminMenuItemPriceResource::collection(collect($paginator->items())),
                'meta' => ListingMetaFactory::paginated(
                    $paginator,
                    [
                        'as_of' => $validated['as_of'] ?? null,
                        'currency' => $validated['currency'] ?? null,
                    ],
                    [
                        'supported' => true,
                        'value' => (string) ($validated['sort'] ?? '-effective_from'),
                        'by' => (string) ($validated['sort_by'] ?? 'effective_from'),
                        'dir' => (string) ($validated['sort_dir'] ?? 'desc'),
                    ],
                    ListingMetaFactory::contract(
                        ['as_of', 'currency'],
                        ['effective_from', 'effective_to', 'price', 'price_id'],
                        '-effective_from',
                        true,
                        100,
                        [
                            'as_of' => 'filter[as_of]',
                            'currency' => 'filter[currency]',
                            'sort_by' => 'sort',
                            'sort_dir' => 'sort',
                        ],
                    ),
                    [
                        'item_id' => $item_id,
                    ],
                ),
            ]);
        }

        $prices = $this->menuService->listPriceRows($item_id, $validated);

        return response()->json([
            'data' => AdminMenuItemPriceResource::collection($prices),
            'meta' => ListingMetaFactory::legacyCollection(
                $prices->count(),
                [
                    'as_of' => $validated['as_of'] ?? null,
                    'currency' => $validated['currency'] ?? null,
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? '-effective_from'),
                    'by' => (string) ($validated['sort_by'] ?? 'effective_from'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'desc'),
                ],
                ListingMetaFactory::contract(
                    ['as_of', 'currency'],
                    ['effective_from', 'effective_to', 'price', 'price_id'],
                    '-effective_from',
                    true,
                    100,
                    [
                        'as_of' => 'filter[as_of]',
                        'currency' => 'filter[currency]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
                [
                    'item_id' => $item_id,
                ],
            ),
        ]);
    }

    public function show(int $price_id): JsonResponse
    {
        return response()->json([
            'data' => new AdminMenuItemPriceResource($this->menuService->showPriceRow($price_id)),
        ]);
    }

    public function store(int $item_id, StoreMenuItemPriceRequest $request): JsonResponse
    {
        $price = $this->menuService->createPriceRow(
            $item_id,
            $request->validated(),
            (int) ($request->attributes->get('staff_actor_user_id') ?? 0),
        );

        return response()->json([
            'data' => new AdminMenuItemPriceResource($price),
        ], 201);
    }

    public function update(int $price_id, UpdateMenuItemPriceRequest $request): JsonResponse
    {
        $price = $this->menuService->updatePriceRow(
            $price_id,
            $request->validated(),
            (int) ($request->attributes->get('staff_actor_user_id') ?? 0),
        );

        return response()->json([
            'data' => new AdminMenuItemPriceResource($price),
        ]);
    }
}
