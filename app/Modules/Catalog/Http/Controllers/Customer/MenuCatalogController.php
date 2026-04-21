<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\ListMenuCategoriesRequest;
use App\Modules\Catalog\Http\Requests\ListMenuItemsRequest;
use App\Modules\Catalog\Http\Requests\PreviewMenuPreorderRequest;
use App\Modules\Catalog\Http\Requests\ShowMenuItemRequest;
use App\Modules\Catalog\Http\Resources\Customer\MenuCategoryResource;
use App\Modules\Catalog\Http\Resources\Customer\MenuItemResource;
use App\Modules\Catalog\Application\UseCases\Browsing\MenuCatalogBrowser;
use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class MenuCatalogController extends Controller
{
    public function __construct(
        private readonly MenuCatalogBrowser $catalogService,
    ) {}

    public function categories(ListMenuCategoriesRequest $request): JsonResponse
    {
        $result = $this->catalogService->listCategories($request->validated());

        return response()->json([
            'data' => MenuCategoryResource::collection($result['categories'])->toArray($request),
            'meta' => $result['meta'],
        ]);
    }

    public function items(ListMenuItemsRequest $request): JsonResponse
    {
        $paginator = $this->catalogService->paginateItems($request->validated());

        return response()->json([
            'data' => MenuItemResource::collection(collect($paginator->items()))->toArray($request),
            'meta' => $this->paginationMeta($paginator, [
                'service_time' => $this->resolveServiceTimeIso($request->validated()['service_time'] ?? null),
                'filters' => [
                    'category_id' => isset($request->validated()['category_id']) ? (int) $request->validated()['category_id'] : null,
                    'preorder_only' => (bool) ($request->validated()['preorder_only'] ?? false),
                    'q' => $request->validated()['q'] ?? null,
                ],
            ]),
        ]);
    }

    public function show(int $id, ShowMenuItemRequest $request): JsonResponse
    {
        try {
            $item = $this->catalogService->findVisibleItem($id, $request->validated());
        } catch (ValidationException) {
            return ApiErrorResponse::json(
                $request,
                404,
                'not_found',
                'Menu item is not available for the selected service time.',
            );
        }

        return response()->json([
            'data' => (new MenuItemResource($item))->toArray($request),
            'meta' => [
                'service_time' => $this->resolveServiceTimeIso($request->validated()['service_time'] ?? null),
            ],
        ]);
    }

    public function previewPreorder(PreviewMenuPreorderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $serviceTime = Carbon::parse((string) $validated['start_time'])->utc();

        $preview = $this->catalogService->previewPreorder(
            requestedItems: (array) $validated['pre_order_items'],
            serviceTime: $serviceTime,
        );

        return response()->json([
            'data' => $preview,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function paginationMeta(LengthAwarePaginator $paginator, array $extra = []): array
    {
        return array_merge([
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ], $extra);
    }

    private function resolveServiceTimeIso(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc()->toIso8601String();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value)->utc()->toIso8601String();
        }

        return Carbon::now('UTC')->toIso8601String();
    }
}
