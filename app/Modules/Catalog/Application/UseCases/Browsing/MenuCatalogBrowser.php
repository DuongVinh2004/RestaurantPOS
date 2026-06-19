<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases\Browsing;

use App\Modules\Catalog\Application\UseCases\PolicyPreview\MenuPreorderPolicyService;
use App\Modules\Catalog\Domain\Models\MenuCategory;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MenuCatalogBrowser
{
    public function __construct(
        private readonly MenuPreorderPolicyService $menuPreorderPolicyService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{categories: Collection<int, MenuCategory>, meta: array<string, mixed>}
     */
    public function listCategories(array $filters = []): array
    {
        $serviceTime = $this->resolveServiceTime($filters['service_time'] ?? null);
        $items = $this->baseItemsQuery($serviceTime, [
            'preorder_only' => (bool) ($filters['preorder_only'] ?? false),
        ])->get();

        $categoryIds = $items
            ->pluck('category_id')
            ->filter(static fn ($value): bool => $value !== null)
            ->map(static fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        $categories = $categoryIds === []
            ? collect()
            : MenuCategory::query()
                ->whereIn('category_id', $categoryIds)
                ->where('is_deleted', 0)
                ->ordered()
                ->get();

        /** @var Collection<int, Collection<int, MenuItem>> $itemsByCategory */
        $itemsByCategory = $items->groupBy(static fn (MenuItem $item): string => (string) ($item->category_id ?? 'uncategorized'));

        $categories->each(function (MenuCategory $category) use ($itemsByCategory): void {
            /** @var Collection<int, MenuItem> $group */
            $group = $itemsByCategory->get((string) $category->category_id, collect());
            $category->setRelation('items', $group->values());
            $category->setAttribute('items_count', $group->count());
        });

        $uncategorizedItems = $itemsByCategory->get('uncategorized', collect())->values();
        if ($uncategorizedItems->isNotEmpty()) {
            $pseudo = new MenuCategory;
            $pseudo->exists = false;
            $pseudo->forceFill([
                'category_id' => null,
                'name' => 'Uncategorized',
                'description' => null,
                'sort_order' => PHP_INT_MAX,
                'is_deleted' => 0,
            ]);
            $pseudo->setRelation('items', $uncategorizedItems);
            $pseudo->setAttribute('items_count', $uncategorizedItems->count());
            $categories->push($pseudo);
        }

        return [
            'categories' => $categories->values(),
            'meta' => [
                'service_time' => $serviceTime->copy()->utc()->toIso8601String(),
                'filters' => [
                    'preorder_only' => (bool) ($filters['preorder_only'] ?? false),
                ],
                'item_count' => $items->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateItems(array $filters = []): LengthAwarePaginator
    {
        $serviceTime = $this->resolveServiceTime($filters['service_time'] ?? null);
        $perPage = max(1, min((int) ($filters['per_page'] ?? config('booking.customer_menu_page_default', 20)), (int) config('booking.customer_menu_page_max', 100)));

        $query = $this->baseItemsQuery($serviceTime, $filters);

        return $query->paginate($perPage)->appends([
            'service_time' => $serviceTime->copy()->utc()->toIso8601String(),
            'category_id' => $filters['category_id'] ?? null,
            'preorder_only' => (bool) ($filters['preorder_only'] ?? false),
            'q' => $filters['q'] ?? null,
            'per_page' => $perPage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function findVisibleItem(int $itemId, array $filters = []): MenuItem
    {
        $serviceTime = $this->resolveServiceTime($filters['service_time'] ?? null);

        /** @var MenuItem|null $item */
        $item = $this->baseItemsQuery($serviceTime, $filters)
            ->where('menu_items.item_id', $itemId)
            ->first();

        if (! $item instanceof MenuItem) {
            throw ValidationException::withMessages([
                'item_id' => ['Menu item is not available for the selected service time.'],
            ]);
        }

        return $item;
    }

    /**
     * @param  array<int, array<string, mixed>>  $requestedItems
     * @return array<string, mixed>
     */
    public function previewPreorder(array $requestedItems, Carbon $serviceTime): array
    {
        $prepared = $this->menuPreorderPolicyService->prepareRequestedItems($requestedItems, $serviceTime);

        /** @var Collection<int, MenuItem> $menuItems */
        $menuItems = $prepared['menu_items'];
        /** @var Collection<int, MenuItemPrice> $priceRows */
        $priceRows = $prepared['price_rows'];
        $rows = $prepared['rows'];

        $currency = null;
        $subtotal = 0.0;
        $lines = [];

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $quantity = (int) $row['quantity'];
            /** @var MenuItem $menuItem */
            $menuItem = $menuItems->get($itemId);
            /** @var MenuItemPrice $priceRow */
            $priceRow = $priceRows->get($itemId);

            $unitPrice = (float) $priceRow->price;
            $lineTotal = $unitPrice * $quantity;
            $subtotal += $lineTotal;
            $currency ??= (string) ($priceRow->currency ?: 'VND');

            $lines[] = [
                'item_id' => $itemId,
                'code' => (string) ($menuItem->code ?? ''),
                'name' => (string) $menuItem->name,
                'category_id' => $menuItem->category_id !== null ? (int) $menuItem->category_id : null,
                'quantity' => $quantity,
                'unit_price' => number_format($unitPrice, 0, '.', ''),
                'line_total' => number_format($lineTotal, 0, '.', ''),
                'currency' => (string) ($priceRow->currency ?: 'VND'),
                'preorder_cutoff_minutes' => (int) ($menuItem->preorder_cutoff_minutes ?? 0),
                'preorder_quota_per_day' => $menuItem->preorder_quota_per_day !== null ? (int) $menuItem->preorder_quota_per_day : null,
            ];
        }

        return [
            'service_time' => $serviceTime->copy()->utc()->toIso8601String(),
            'currency' => $currency ?? 'VND',
            'lines' => $lines,
            'totals' => [
                'item_count' => count($lines),
                'quantity' => array_sum(array_map(static fn (array $line): int => (int) $line['quantity'], $lines)),
                'subtotal' => number_format($subtotal, 0, '.', ''),
            ],
            'normalized_pre_order_items' => array_map(static fn (array $row): array => [
                'item_id' => (int) $row['item_id'],
                'quantity' => (int) $row['quantity'],
            ], $rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseItemsQuery(Carbon $serviceTime, array $filters)
    {
        $priceSubquery = MenuItemPrice::query()
            ->select('price_id', 'item_id', 'price', 'currency', 'effective_from', 'effective_to')
            ->effectiveAt($serviceTime);

        return MenuItem::query()
            ->select([
                'menu_items.item_id',
                'menu_items.category_id',
                'menu_items.code',
                'menu_items.name',
                'menu_items.description',
                'menu_items.img_url',
                'menu_items.is_available',
                'menu_items.is_preorder_enabled',
                'menu_items.preorder_quota_per_day',
                'menu_items.preorder_cutoff_minutes',
                'menu_items.is_combo',
                'menu_items.is_best_seller',
                'menu_items.compare_at_price_amount',
                'menu_items.serving_size',
                'menu_items.created_at',
                'menu_items.updated_at',
                'menu_categories.name as category_name',
                'menu_categories.sort_order as category_sort_order',
                'effective_prices.price_id as current_price_id',
                'effective_prices.price as current_price_amount',
                'effective_prices.currency as current_price_currency',
                'effective_prices.effective_from as current_price_effective_from',
                'effective_prices.effective_to as current_price_effective_to',
            ])
            ->leftJoin('menu_categories', function ($join): void {
                $join->on('menu_categories.category_id', '=', 'menu_items.category_id')
                    ->where('menu_categories.is_deleted', '=', 0);
            })
            ->joinSub($priceSubquery, 'effective_prices', function ($join): void {
                $join->on('effective_prices.item_id', '=', 'menu_items.item_id');
            })
            ->where('menu_items.is_available', 1)
            ->when((int) ($filters['category_id'] ?? 0) > 0, static function ($query) use ($filters): void {
                $query->where('menu_items.category_id', (int) $filters['category_id']);
            })
            ->when((bool) ($filters['preorder_only'] ?? false), static function ($query): void {
                $query->where('menu_items.is_preorder_enabled', 1);
            })
            ->when(($filters['q'] ?? null) !== null && trim((string) $filters['q']) !== '', static function ($query) use ($filters): void {
                $keyword = trim((string) $filters['q']);
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $keyword).'%';
                $query->where(function ($inner) use ($like): void {
                    $inner
                        ->where('menu_items.name', 'like', $like)
                        ->orWhere('menu_items.code', 'like', $like)
                        ->orWhere('menu_items.description', 'like', $like)
                        ->orWhere('menu_categories.name', 'like', $like);
                });
            })
            ->with(['comboComponents.componentItem'])
            ->orderByRaw('CASE WHEN menu_items.category_id IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('menu_categories.sort_order')
            ->orderBy('menu_categories.category_id')
            ->orderBy('menu_items.name')
            ->orderBy('menu_items.item_id');
    }

    private function resolveServiceTime(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value)->utc();
        }

        return Carbon::now('UTC');
    }
}
