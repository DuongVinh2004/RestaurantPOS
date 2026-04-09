<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemPrice;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * @deprecated Compatibility facade for the legacy aggregated admin menu stack.
 * Runtime routes now use AdminMenuManagementService through split controllers.
 */
class AdminMenuService
{
    public function __construct(
        private readonly AdminMenuManagementService $menuManagementService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return EloquentCollection<int, MenuCategory>
     */
    public function listCategories(array $filters = []): EloquentCollection
    {
        return MenuCategory::query()
            ->when(! ((bool) ($filters['include_deleted'] ?? false)), static fn ($query) => $query->where('is_deleted', false))
            ->ordered()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCategory(array $attributes): MenuCategory
    {
        return $this->menuManagementService->createCategory($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCategory(int $categoryId, array $attributes): MenuCategory
    {
        return $this->menuManagementService->updateCategory($categoryId, $attributes);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listItems(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));

        return MenuItem::query()
            ->with([
                'category',
                'prices' => fn ($priceQuery) => $priceQuery
                    ->orderByDesc('effective_from')
                    ->orderByDesc('price_id'),
            ])
            ->when(
                array_key_exists('category_id', $filters) && $filters['category_id'] !== null && $filters['category_id'] !== '',
                static fn ($query) => $query->where('category_id', (int) $filters['category_id'])
            )
            ->when(
                array_key_exists('is_available', $filters) && $filters['is_available'] !== null && $filters['is_available'] !== '',
                static fn ($query) => $query->where('is_available', (bool) $filters['is_available'])
            )
            ->when(
                trim((string) ($filters['q'] ?? '')) !== '',
                static function ($query) use ($filters): void {
                    $needle = trim((string) $filters['q']);

                    $query->where(function ($inner) use ($needle): void {
                        $inner->where('name', 'like', '%'.$needle.'%')
                            ->orWhere('code', 'like', '%'.$needle.'%');
                    });
                }
            )
            ->orderBy('name')
            ->orderBy('item_id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function showItem(int $itemId): MenuItem
    {
        return $this->menuManagementService->showItem($itemId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(array $attributes): MenuItem
    {
        return $this->menuManagementService->createItem($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateItem(int $itemId, array $attributes): MenuItem
    {
        return $this->menuManagementService->updateItem($itemId, $attributes);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return EloquentCollection<int, MenuItemPrice>
     */
    public function listItemPrices(int $itemId, array $filters = []): EloquentCollection
    {
        MenuItem::query()->findOrFail($itemId);

        return MenuItemPrice::query()
            ->with('item.category')
            ->where('item_id', $itemId)
            ->when(
                ! empty($filters['as_of']),
                static function ($query) use ($filters): void {
                    $query->effectiveAt(Carbon::parse((string) $filters['as_of'])->utc());
                }
            )
            ->orderByDesc('effective_from')
            ->orderByDesc('price_id')
            ->get();
    }

    public function showPriceRow(int $priceId): MenuItemPrice
    {
        return $this->menuManagementService->showPriceRow($priceId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{price: MenuItemPrice}
     */
    public function createItemPrice(int $itemId, array $attributes): array
    {
        return [
            'price' => $this->menuManagementService->createPriceRow($itemId, $attributes),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateItemPrice(int $priceId, array $attributes): MenuItemPrice
    {
        return $this->menuManagementService->updatePriceRow($priceId, $attributes);
    }
}
