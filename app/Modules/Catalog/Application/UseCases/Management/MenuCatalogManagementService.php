<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases\Management;

use App\Modules\Catalog\Domain\Models\MenuCategory;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use App\Support\AuditEvent;
use App\Support\Listing\SafeLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MenuCatalogManagementService
{
    /**
     * @return EloquentCollection<int, MenuCategory>
     */
    public function listCategories(array $filters = []): EloquentCollection
    {
        return $this->baseCategoriesQuery($filters)->get();
    }

    public function paginateCategories(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $this->baseCategoriesQuery($filters)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function showCategory(int $categoryId): MenuCategory
    {
        return MenuCategory::query()->findOrFail($categoryId);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function createCategory(array $attributes, ?int $actorUserId = null): MenuCategory
    {
        return DB::transaction(function () use ($attributes, $actorUserId): MenuCategory {
            /** @var MenuCategory $category */
            $category = MenuCategory::query()->create([
                'name' => (string) $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
                'is_deleted' => (bool) ($attributes['is_deleted'] ?? false),
            ]);

            $fresh = $category->fresh() ?? $category;

            AuditEvent::info('admin.menu_category.created', [
                'category_id' => (int) $fresh->category_id,
                'name' => (string) $fresh->name,
                '_audit' => [
                    'action' => 'master_data.menu_category.created',
                    'entity_type' => 'menu_category',
                    'entity_id' => (string) $fresh->category_id,
                    'after' => $this->categorySnapshot($fresh),
                    'summary' => [
                        'name' => (string) $fresh->name,
                        'is_deleted' => (bool) ($fresh->is_deleted ?? false),
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $fresh;
        });
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function updateCategory(int $categoryId, array $attributes, ?int $actorUserId = null): MenuCategory
    {
        return DB::transaction(function () use ($categoryId, $attributes, $actorUserId): MenuCategory {
            $category = MenuCategory::query()->findOrFail($categoryId);
            $before = $this->categorySnapshot($category);
            $category->fill([
                'name' => (string) $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
                'is_deleted' => (bool) ($attributes['is_deleted'] ?? false),
            ]);
            $category->save();

            $fresh = $category->fresh() ?? $category;

            AuditEvent::info('admin.menu_category.updated', [
                'category_id' => (int) $fresh->category_id,
                'name' => (string) $fresh->name,
                '_audit' => [
                    'action' => 'master_data.menu_category.updated',
                    'entity_type' => 'menu_category',
                    'entity_id' => (string) $fresh->category_id,
                    'before' => $before,
                    'after' => $this->categorySnapshot($fresh),
                    'summary' => [
                        'name' => (string) $fresh->name,
                        'is_deleted' => (bool) ($fresh->is_deleted ?? false),
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $fresh;
        });
    }

    /**
     * @return EloquentCollection<int, MenuItem>
     */
    public function listItems(array $filters = []): EloquentCollection
    {
        return $this->baseItemsQuery($filters)->get();
    }

    public function paginateItems(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $this->baseItemsQuery($filters)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function showItem(int $itemId): MenuItem
    {
        return MenuItem::query()
            ->with([
                'category',
                'prices' => fn ($priceQuery) => $priceQuery
                    ->orderByDesc('effective_from')
                    ->orderByDesc('price_id'),
            ])
            ->findOrFail($itemId);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function createItem(array $attributes, ?int $actorUserId = null): MenuItem
    {
        return DB::transaction(function () use ($attributes, $actorUserId): MenuItem {
            /** @var MenuItem $item */
            $item = MenuItem::query()->create($this->normalizeItemAttributes($attributes));

            $fresh = $this->showItem((int) $item->item_id);

            AuditEvent::info('admin.menu_item.created', [
                'item_id' => (int) $fresh->item_id,
                'code' => (string) ($fresh->code ?? ''),
                '_audit' => [
                    'action' => 'master_data.menu_item.created',
                    'entity_type' => 'menu_item',
                    'entity_id' => (string) $fresh->item_id,
                    'subjects' => $fresh->category_id !== null
                        ? [['type' => 'menu_category', 'id' => (string) $fresh->category_id, 'role' => 'category']]
                        : [],
                    'after' => $this->itemSnapshot($fresh),
                    'summary' => [
                        'code' => $fresh->code,
                        'name' => (string) $fresh->name,
                        'is_available' => (bool) ($fresh->is_available ?? false),
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $fresh;
        });
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function updateItem(int $itemId, array $attributes, ?int $actorUserId = null): MenuItem
    {
        return DB::transaction(function () use ($itemId, $attributes, $actorUserId): MenuItem {
            $item = MenuItem::query()->with('category')->findOrFail($itemId);
            $before = $this->itemSnapshot($item);
            $item->fill($this->normalizeItemAttributes($attributes));
            $item->save();

            $fresh = $this->showItem($itemId);

            AuditEvent::info('admin.menu_item.updated', [
                'item_id' => (int) $fresh->item_id,
                'code' => (string) ($fresh->code ?? ''),
                '_audit' => [
                    'action' => 'master_data.menu_item.updated',
                    'entity_type' => 'menu_item',
                    'entity_id' => (string) $fresh->item_id,
                    'subjects' => $fresh->category_id !== null
                        ? [['type' => 'menu_category', 'id' => (string) $fresh->category_id, 'role' => 'category']]
                        : [],
                    'before' => $before,
                    'after' => $this->itemSnapshot($fresh),
                    'summary' => [
                        'code' => $fresh->code,
                        'name' => (string) $fresh->name,
                        'is_available' => (bool) ($fresh->is_available ?? false),
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $fresh;
        });
    }

    /**
     * @return EloquentCollection<int, MenuItemPrice>
     */
    public function listPriceRows(int $itemId, array $filters = []): EloquentCollection
    {
        return $this->basePriceRowsQuery($itemId, $filters)->get();
    }

    public function paginatePriceRows(int $itemId, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return $this->basePriceRowsQuery($itemId, $filters)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function showPriceRow(int $priceId): MenuItemPrice
    {
        return MenuItemPrice::query()
            ->with('item.category')
            ->findOrFail($priceId);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function createPriceRow(int $itemId, array $attributes, ?int $actorUserId = null): MenuItemPrice
    {
        MenuItem::query()->findOrFail($itemId);

        return DB::transaction(function () use ($itemId, $attributes, $actorUserId): MenuItemPrice {
            $effectiveFrom = Carbon::parse((string) $attributes['effective_from'])->utc();
            $effectiveTo = ! empty($attributes['effective_to'])
                ? Carbon::parse((string) $attributes['effective_to'])->utc()
                : null;

            $this->reconcileFuturePriceWindow($itemId, $effectiveFrom, $effectiveTo);

            /** @var MenuItemPrice $price */
            $price = MenuItemPrice::query()->create([
                'item_id' => $itemId,
                'price' => $attributes['price'],
                'currency' => $attributes['currency'] ?? 'VND',
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
            ]);

            $fresh = $this->showPriceRow((int) $price->price_id);

            AuditEvent::info('admin.menu_price.created', [
                'price_id' => (int) $fresh->price_id,
                'item_id' => (int) $fresh->item_id,
                '_audit' => [
                    'action' => 'master_data.menu_price.created',
                    'entity_type' => 'menu_price',
                    'entity_id' => (string) $fresh->price_id,
                    'subjects' => array_values(array_filter([
                        ['type' => 'menu_item', 'id' => (string) $fresh->item_id, 'role' => 'item'],
                        $fresh->item?->category_id !== null
                            ? ['type' => 'menu_category', 'id' => (string) $fresh->item->category_id, 'role' => 'category']
                            : null,
                    ])),
                    'after' => $this->priceSnapshot($fresh),
                    'summary' => [
                        'item_code' => $fresh->item?->code,
                        'price' => number_format((float) $fresh->price, 2, '.', ''),
                        'currency' => (string) ($fresh->currency ?? 'VND'),
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $fresh;
        });
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function updatePriceRow(int $priceId, array $attributes, ?int $actorUserId = null): MenuItemPrice
    {
        return DB::transaction(function () use ($priceId, $attributes, $actorUserId): MenuItemPrice {
            $price = MenuItemPrice::query()->with('item.category')->findOrFail($priceId);
            $before = $this->priceSnapshot($price);
            $effectiveFrom = Carbon::parse((string) $attributes['effective_from'])->utc();
            $effectiveTo = ! empty($attributes['effective_to'])
                ? Carbon::parse((string) $attributes['effective_to'])->utc()
                : null;

            $this->reconcileFuturePriceWindow((int) $price->item_id, $effectiveFrom, $effectiveTo, $priceId);

            $price->fill([
                'price' => $attributes['price'],
                'currency' => $attributes['currency'] ?? 'VND',
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
            ]);
            $price->save();

            $fresh = $this->showPriceRow($priceId);

            AuditEvent::info('admin.menu_price.updated', [
                'price_id' => (int) $fresh->price_id,
                'item_id' => (int) $fresh->item_id,
                '_audit' => [
                    'action' => 'master_data.menu_price.updated',
                    'entity_type' => 'menu_price',
                    'entity_id' => (string) $fresh->price_id,
                    'subjects' => array_values(array_filter([
                        ['type' => 'menu_item', 'id' => (string) $fresh->item_id, 'role' => 'item'],
                        $fresh->item?->category_id !== null
                            ? ['type' => 'menu_category', 'id' => (string) $fresh->item->category_id, 'role' => 'category']
                            : null,
                    ])),
                    'before' => $before,
                    'after' => $this->priceSnapshot($fresh),
                    'summary' => [
                        'item_code' => $fresh->item?->code,
                        'price' => number_format((float) $fresh->price, 2, '.', ''),
                        'currency' => (string) ($fresh->currency ?? 'VND'),
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $fresh;
        });
    }

    private function reconcileFuturePriceWindow(int $itemId, Carbon $effectiveFrom, ?Carbon &$effectiveTo, ?int $excludePriceId = null): void
    {
        if (! $effectiveFrom->isFuture()) {
            return;
        }

        $previousPrice = MenuItemPrice::query()
            ->where('item_id', $itemId)
            ->when($excludePriceId !== null, static fn ($query) => $query->whereKeyNot($excludePriceId))
            ->where('effective_from', '<', $effectiveFrom)
            ->orderByDesc('effective_from')
            ->orderByDesc('price_id')
            ->first();

        if ($previousPrice instanceof MenuItemPrice) {
            $previousEffectiveTo = $previousPrice->effective_to instanceof Carbon
                ? $previousPrice->effective_to->copy()->utc()
                : ($previousPrice->effective_to !== null ? Carbon::parse((string) $previousPrice->effective_to)->utc() : null);

            if ($previousEffectiveTo === null || $previousEffectiveTo->gt($effectiveFrom)) {
                DB::table('menu_item_prices')
                    ->where('price_id', (int) $previousPrice->price_id)
                    ->update([
                        'effective_to' => $effectiveFrom->toDateTimeString(),
                    ]);
            }
        }

        if ($effectiveTo !== null) {
            return;
        }

        $nextPrice = MenuItemPrice::query()
            ->where('item_id', $itemId)
            ->when($excludePriceId !== null, static fn ($query) => $query->whereKeyNot($excludePriceId))
            ->where('effective_from', '>', $effectiveFrom)
            ->orderBy('effective_from')
            ->orderBy('price_id')
            ->first();

        if ($nextPrice instanceof MenuItemPrice && $nextPrice->effective_from !== null) {
            $effectiveTo = $nextPrice->effective_from instanceof Carbon
                ? $nextPrice->effective_from->copy()->utc()
                : Carbon::parse((string) $nextPrice->effective_from)->utc();
        }
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>
     */
    private function normalizeItemAttributes(array $attributes): array
    {
        $normalized = [
            'category_id' => ! empty($attributes['category_id']) ? (int) $attributes['category_id'] : null,
            'code' => $attributes['code'] !== null && $attributes['code'] !== '' ? (string) $attributes['code'] : null,
            'name' => (string) $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'img_url' => $attributes['img_url'] ?? null,
            'is_available' => (bool) ($attributes['is_available'] ?? true),
        ];

        if (MenuItem::supportsPreorderColumns()) {
            $normalized['is_preorder_enabled'] = (bool) ($attributes['is_preorder_enabled'] ?? false);
            $normalized['preorder_quota_per_day'] = array_key_exists('preorder_quota_per_day', $attributes)
                && $attributes['preorder_quota_per_day'] !== null
                && $attributes['preorder_quota_per_day'] !== ''
                ? (int) $attributes['preorder_quota_per_day']
                : null;
            $normalized['preorder_cutoff_minutes'] = (int) ($attributes['preorder_cutoff_minutes'] ?? 0);
        }

        return $normalized;
    }

    /**
     * @return array<string,mixed>
     */
    private function categorySnapshot(MenuCategory $category): array
    {
        return [
            'name' => (string) $category->name,
            'description' => $category->description,
            'sort_order' => (int) ($category->sort_order ?? 0),
            'is_deleted' => (bool) ($category->is_deleted ?? false),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function itemSnapshot(MenuItem $item): array
    {
        return [
            'category_id' => $item->category_id !== null ? (int) $item->category_id : null,
            'category_name' => $item->relationLoaded('category')
                ? ($item->category?->name !== null ? (string) $item->category->name : null)
                : ($item->category()->value('name') !== null ? (string) $item->category()->value('name') : null),
            'code' => $item->code,
            'name' => (string) $item->name,
            'description' => $item->description,
            'img_url' => $item->img_url,
            'is_available' => (bool) ($item->is_available ?? false),
            'is_preorder_enabled' => $item->is_preorder_enabled !== null ? (bool) $item->is_preorder_enabled : false,
            'preorder_quota_per_day' => $item->preorder_quota_per_day !== null ? (int) $item->preorder_quota_per_day : null,
            'preorder_cutoff_minutes' => $item->preorder_cutoff_minutes !== null ? (int) $item->preorder_cutoff_minutes : 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function priceSnapshot(MenuItemPrice $price): array
    {
        return [
            'item_id' => (int) $price->item_id,
            'item_code' => $price->item?->code,
            'price' => number_format((float) $price->price, 2, '.', ''),
            'currency' => (string) ($price->currency ?? 'VND'),
            'effective_from' => $price->effective_from?->utc()?->toIso8601String(),
            'effective_to' => $price->effective_to?->utc()?->toIso8601String(),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function auditActor(?int $actorUserId): ?array
    {
        if ($actorUserId === null || $actorUserId <= 0) {
            return null;
        }

        return [
            'type' => 'staff_user',
            'user_id' => $actorUserId,
            'key' => 'staff_user:'.$actorUserId,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseCategoriesQuery(array $filters): Builder
    {
        $query = MenuCategory::query();

        if (! ((bool) ($filters['include_deleted'] ?? false))) {
            $query->where('is_deleted', false);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = SafeLike::contains($q);
            $query->where(function (Builder $inner) use ($like): void {
                $inner
                    ->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        [$sortBy, $sortDir] = $this->resolveCategorySort(
            (string) ($filters['sort_by'] ?? 'sort_order'),
            (string) ($filters['sort_dir'] ?? 'asc'),
        );

        $query->orderBy($sortBy, $sortDir);

        if ($sortBy !== 'sort_order') {
            $query->orderBy('sort_order');
        }

        if ($sortBy !== 'category_id') {
            $query->orderBy('category_id');
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseItemsQuery(array $filters): Builder
    {
        $query = MenuItem::query()
            ->with([
                'category',
                'prices' => fn ($priceQuery) => $priceQuery
                    ->orderByDesc('effective_from')
                    ->orderByDesc('price_id'),
            ]);

        if (array_key_exists('category_id', $filters) && $filters['category_id'] !== null && $filters['category_id'] !== '') {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (array_key_exists('is_available', $filters) && $filters['is_available'] !== null && $filters['is_available'] !== '') {
            $query->where('is_available', (bool) $filters['is_available']);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = SafeLike::contains($q);
            $query->where(function (Builder $inner) use ($like): void {
                $inner
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        [$sortBy, $sortDir] = $this->resolveItemSort(
            (string) ($filters['sort_by'] ?? 'name'),
            (string) ($filters['sort_dir'] ?? 'asc'),
        );

        $query->orderBy($sortBy, $sortDir);

        if ($sortBy !== 'item_id') {
            $query->orderBy('item_id', 'asc');
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function basePriceRowsQuery(int $itemId, array $filters): Builder
    {
        MenuItem::query()->findOrFail($itemId);

        $query = MenuItemPrice::query()
            ->with('item.category')
            ->where('item_id', $itemId);

        if (($filters['as_of'] ?? null) !== null) {
            $query->effectiveAt(Carbon::parse((string) $filters['as_of'])->utc());
        }

        if (($filters['currency'] ?? null) !== null) {
            $query->where('currency', strtoupper(trim((string) $filters['currency'])));
        }

        [$sortBy, $sortDir] = $this->resolvePriceSort(
            (string) ($filters['sort_by'] ?? 'effective_from'),
            (string) ($filters['sort_dir'] ?? 'desc'),
        );

        $query->orderBy($sortBy, $sortDir);

        if ($sortBy !== 'price_id') {
            $query->orderBy('price_id', 'desc');
        }

        return $query;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveCategorySort(string $sortBy, string $sortDir): array
    {
        $direction = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';
        $map = [
            'sort_order' => 'sort_order',
            'name' => 'name',
            'category_id' => 'category_id',
            'updated_at' => 'updated_at',
        ];

        return [$map[$sortBy] ?? 'sort_order', $direction];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveItemSort(string $sortBy, string $sortDir): array
    {
        $direction = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';
        $map = [
            'name' => 'name',
            'code' => 'code',
            'item_id' => 'item_id',
            'category_id' => 'category_id',
            'updated_at' => 'updated_at',
        ];

        return [$map[$sortBy] ?? 'name', $direction];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolvePriceSort(string $sortBy, string $sortDir): array
    {
        $direction = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';
        $map = [
            'effective_from' => 'effective_from',
            'effective_to' => 'effective_to',
            'price' => 'price',
            'price_id' => 'price_id',
        ];

        return [$map[$sortBy] ?? 'effective_from', $direction];
    }
}
