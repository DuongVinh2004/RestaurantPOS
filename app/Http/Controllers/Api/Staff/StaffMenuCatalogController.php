<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\ListMenuItemsRequest;
use App\Http\Resources\CustomerMenuItemResource;
use App\Services\Menu\CustomerMenuCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class StaffMenuCatalogController extends Controller
{
    public function __construct(
        private readonly CustomerMenuCatalogService $catalogService,
    ) {}

    public function index(ListMenuItemsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $paginator = $this->catalogService->paginateItems($validated);

        return response()->json([
            'data' => CustomerMenuItemResource::collection(collect($paginator->items()))->toArray($request),
            'meta' => $this->paginationMeta($paginator, [
                'service_time' => $this->resolveServiceTimeIso($validated['service_time'] ?? null),
                'filters' => [
                    'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
                    'preorder_only' => (bool) ($validated['preorder_only'] ?? false),
                    'q' => $validated['q'] ?? null,
                ],
            ]),
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
