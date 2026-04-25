<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Application\UseCases\Inventory;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemRecipe;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\InventoryProcurement\Domain\Models\Ingredient;
use App\Modules\InventoryProcurement\Domain\Models\IngredientStockMovement;
use App\Support\Listing\SafeLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryManagementService
{
    public function __construct(
        private readonly InventoryStockMovementService $stockMovementService,
        private readonly StaffBranchContextService $staffBranchContextService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateIngredients(array $filters = [], ?int $actorUserId = null): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? config('booking.admin_inventory_page_default', 25)), (int) config('booking.admin_inventory_page_max', 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $keyword = trim((string) ($filters['q'] ?? ''));
        $requestedBranchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $accessibleBranchIds = $this->branchScopeForActor($actorUserId, $requestedBranchId);
        [$sortColumn, $sortDirection, $rawSort] = $this->resolveIngredientSort(
            (string) ($filters['sort_by'] ?? 'name'),
            (string) ($filters['sort_dir'] ?? 'asc'),
        );

        $query = $this->baseIngredientsQuery($requestedBranchId, $accessibleBranchIds)
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, static fn ($query) => $query->where('ingredients.is_active', (bool) $filters['is_active']))
            ->when($keyword !== '', static function ($query) use ($keyword): void {
                $like = SafeLike::contains($keyword);
                $query->where(function ($inner) use ($like): void {
                    $inner
                        ->where('ingredients.name', 'like', $like)
                        ->orWhere('ingredients.code', 'like', $like)
                        ->orWhere('ingredients.description', 'like', $like);
                });
            });

        if ($rawSort) {
            $query->orderByRaw($sortColumn.' '.$sortDirection);
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        if ($sortColumn !== 'ingredients.ingredient_id') {
            $query->orderBy('ingredients.ingredient_id');
        }

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends([
                'is_active' => array_key_exists('is_active', $filters) && $filters['is_active'] !== null ? (bool) $filters['is_active'] : null,
                'q' => $keyword !== '' ? $keyword : null,
                'sort' => $filters['sort'] ?? null,
            ]);
    }

    public function findIngredient(int $ingredientId, ?int $branchId = null, ?int $actorUserId = null): Ingredient
    {
        $accessibleBranchIds = $this->branchScopeForActor($actorUserId, $branchId);

        /** @var Ingredient|null $ingredient */
        $ingredient = $this->baseIngredientsQuery($branchId, $accessibleBranchIds)
            ->where('ingredients.ingredient_id', $ingredientId)
            ->first();

        if (! $ingredient instanceof Ingredient) {
            throw (new ModelNotFoundException)->setModel(Ingredient::class, [$ingredientId]);
        }

        return $ingredient;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createIngredient(array $payload): Ingredient
    {
        $ingredient = new Ingredient;
        $ingredient->fill([
            'code' => $this->normalizeNullableString($payload['code'] ?? null),
            'name' => trim((string) $payload['name']),
            'unit_code' => trim((string) $payload['unit_code']),
            'description' => $this->normalizeNullableString($payload['description'] ?? null),
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);
        $ingredient->save();

        return $this->findIngredient((int) $ingredient->ingredient_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateIngredient(int $ingredientId, array $payload): Ingredient
    {
        /** @var Ingredient $ingredient */
        $ingredient = Ingredient::query()->findOrFail($ingredientId);

        if (array_key_exists('code', $payload)) {
            $ingredient->code = $this->normalizeNullableString($payload['code']);
        }

        if (array_key_exists('name', $payload)) {
            $ingredient->name = trim((string) $payload['name']);
        }

        if (array_key_exists('unit_code', $payload)) {
            $ingredient->unit_code = trim((string) $payload['unit_code']);
        }

        if (array_key_exists('description', $payload)) {
            $ingredient->description = $this->normalizeNullableString($payload['description']);
        }

        if (array_key_exists('is_active', $payload)) {
            $ingredient->is_active = (bool) $payload['is_active'];
        }

        $ingredient->save();

        return $this->findIngredient($ingredientId);
    }

    /**
     * @return array{item: MenuItem, lines: Collection<int, MenuItemRecipe>}
     */
    public function getMenuItemRecipe(int $itemId): array
    {
        /** @var MenuItem $item */
        $item = MenuItem::query()->findOrFail($itemId);

        /** @var Collection<int, MenuItemRecipe> $lines */
        $lines = MenuItemRecipe::query()
            ->with(['ingredient' => static fn ($query) => $query->select('ingredient_id', 'code', 'name', 'unit_code', 'is_active')])
            ->where('item_id', $itemId)
            ->orderBy('sort_order')
            ->orderBy('recipe_line_id')
            ->get();

        return [
            'item' => $item,
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{item: MenuItem, lines: Collection<int, MenuItemRecipe>}
     */
    public function syncMenuItemRecipe(int $itemId, array $lines): array
    {
        return DB::transaction(function () use ($itemId, $lines): array {
            /** @var MenuItem $item */
            $item = MenuItem::query()->lockForUpdate()->findOrFail($itemId);

            $ingredientIds = collect($lines)
                ->pluck('ingredient_id')
                ->filter(static fn ($value): bool => $value !== null)
                ->map(static fn ($value): int => (int) $value)
                ->unique()
                ->values()
                ->all();

            $ingredients = Ingredient::query()
                ->whereIn('ingredient_id', $ingredientIds)
                ->get()
                ->keyBy('ingredient_id');

            if (count($ingredientIds) !== $ingredients->count()) {
                throw (new ModelNotFoundException)->setModel(Ingredient::class, $ingredientIds);
            }

            MenuItemRecipe::query()
                ->where('item_id', $itemId)
                ->when($ingredientIds !== [], static fn ($query) => $query->whereNotIn('ingredient_id', $ingredientIds))
                ->when($ingredientIds === [], static fn ($query) => $query)
                ->delete();

            if ($ingredientIds === []) {
                MenuItemRecipe::query()->where('item_id', $itemId)->delete();

                return $this->getMenuItemRecipe($itemId);
            }

            foreach ($lines as $index => $line) {
                /** @var Ingredient $ingredient */
                $ingredient = $ingredients->get((int) $line['ingredient_id']);
                $unitCode = $this->resolveIngredientUnitCode(
                    $ingredient,
                    $line['unit_code'] ?? null,
                    'lines.'.$index.'.unit_code'
                );

                MenuItemRecipe::query()->updateOrCreate(
                    [
                        'item_id' => $itemId,
                        'ingredient_id' => (int) $ingredient->ingredient_id,
                    ],
                    [
                        'quantity' => $line['quantity'],
                        'unit_code' => $unitCode,
                        'sort_order' => (int) ($line['sort_order'] ?? (($index + 1) * 10)),
                        'notes' => $this->normalizeNullableString($line['notes'] ?? null),
                    ]
                );
            }

            return $this->getMenuItemRecipe((int) $item->item_id);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateIngredientMovements(int $ingredientId, array $filters = [], ?int $actorUserId = null): LengthAwarePaginator
    {
        Ingredient::query()->findOrFail($ingredientId);

        $perPage = max(1, min((int) ($filters['per_page'] ?? config('booking.admin_inventory_page_default', 25)), (int) config('booking.admin_inventory_page_max', 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $requestedBranchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $accessibleBranchIds = $this->branchScopeForActor($actorUserId, $requestedBranchId);
        [$sortColumn, $sortDirection] = $this->resolveMovementSort(
            (string) ($filters['sort_by'] ?? 'created_at'),
            (string) ($filters['sort_dir'] ?? 'desc'),
        );

        $query = IngredientStockMovement::query()
            ->where('ingredient_id', $ingredientId)
            ->when(isset($filters['movement_type']), static fn ($query) => $query->where('movement_type', (string) $filters['movement_type']))
            ->when(isset($filters['branch_id']), static fn ($query) => $query->where('branch_id', (int) $filters['branch_id']));

        if ($requestedBranchId === null && is_array($accessibleBranchIds)) {
            if ($accessibleBranchIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('branch_id', $accessibleBranchIds);
            }
        }

        return $query->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'movement_id', static fn ($query) => $query->orderByDesc('movement_id'))
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends([
                'movement_type' => $filters['movement_type'] ?? null,
                'branch_id' => $filters['branch_id'] ?? null,
                'sort' => $filters['sort'] ?? null,
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{movement: IngredientStockMovement, stock_on_hand: string}
     */
    public function createIngredientMovement(int $ingredientId, array $payload, ?int $actorUserId = null): array
    {
        $branchId = $this->resolveWritableBranchId($actorUserId, $payload['branch_id'] ?? null);

        $movement = $this->stockMovementService->recordMovement($ingredientId, [
            'movement_type' => (string) $payload['movement_type'],
            'quantity' => $payload['quantity'],
            'unit_code' => $payload['unit_code'] ?? null,
            'reference_type' => $payload['reference_type'] ?? null,
            'reference_id' => $payload['reference_id'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'branch_id' => $branchId,
            'created_at' => now('UTC'),
        ], $actorUserId);

        $stockBranchId = isset($movement->branch_id) ? (int) $movement->branch_id : null;

        return [
            'movement' => $movement,
            'stock_on_hand' => $this->stockMovementService->currentStockOnHand($ingredientId, $stockBranchId),
        ];
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     */
    private function baseIngredientsQuery(?int $branchId = null, ?array $accessibleBranchIds = null): Builder
    {
        $stockSubquery = IngredientStockMovement::query()
            ->selectRaw('ingredient_id, SUM(quantity_delta) as stock_on_hand_quantity')
            ->groupBy('ingredient_id');

        if ($branchId !== null) {
            $stockSubquery->where('branch_id', $branchId);
        } elseif (is_array($accessibleBranchIds)) {
            if ($accessibleBranchIds === []) {
                $stockSubquery->whereRaw('1 = 0');
            } else {
                $stockSubquery->whereIn('branch_id', $accessibleBranchIds);
            }
        }

        $recipeUsageSubquery = MenuItemRecipe::query()
            ->selectRaw('ingredient_id, COUNT(*) as recipe_usage_count')
            ->groupBy('ingredient_id');

        return Ingredient::query()
            ->select([
                'ingredients.*',
                DB::raw('COALESCE(stock_levels.stock_on_hand_quantity, 0) as stock_on_hand_quantity'),
                DB::raw('COALESCE(recipe_usage.recipe_usage_count, 0) as recipe_usage_count'),
            ])
            ->leftJoinSub($stockSubquery, 'stock_levels', static function ($join): void {
                $join->on('stock_levels.ingredient_id', '=', 'ingredients.ingredient_id');
            })
            ->leftJoinSub($recipeUsageSubquery, 'recipe_usage', static function ($join): void {
                $join->on('recipe_usage.ingredient_id', '=', 'ingredients.ingredient_id');
            });
    }

    /**
     * @return list<int>|null
     */
    private function branchScopeForActor(?int $actorUserId, ?int $requestedBranchId = null): ?array
    {
        if ($actorUserId === null || $actorUserId <= 0) {
            return null;
        }

        return $this->staffBranchContextService->branchScopeOrAccessible($actorUserId, $requestedBranchId);
    }

    private function resolveWritableBranchId(?int $actorUserId, mixed $requestedBranchId = null): ?int
    {
        if ($actorUserId === null || $actorUserId <= 0) {
            return $requestedBranchId !== null && $requestedBranchId !== '' ? (int) $requestedBranchId : null;
        }

        if ($requestedBranchId !== null && $requestedBranchId !== '') {
            return $this->staffBranchContextService->assertAccessibleBranch($actorUserId, (int) $requestedBranchId);
        }

        $context = $this->staffBranchContextService->branchAccessContext($actorUserId);
        $currentBranchId = isset($context['current_branch_id']) ? (int) $context['current_branch_id'] : 0;
        if ($currentBranchId <= 0) {
            throw (new ModelNotFoundException)->setModel(Branch::class);
        }

        return $this->staffBranchContextService->assertAccessibleBranch($actorUserId, $currentBranchId);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveIngredientUnitCode(Ingredient $ingredient, mixed $requestedUnitCode, string $field): string
    {
        $ingredientUnitCode = trim((string) $ingredient->unit_code);
        if ($ingredientUnitCode === '') {
            throw ValidationException::withMessages([
                $field => 'Ingredient unit code must be set before linking recipes.',
            ]);
        }

        $normalizedRequestedUnitCode = trim((string) ($requestedUnitCode ?? ''));
        if ($normalizedRequestedUnitCode !== '' && ! $this->unitCodesMatch($ingredientUnitCode, $normalizedRequestedUnitCode)) {
            throw ValidationException::withMessages([
                $field => sprintf('Unit code must match ingredient unit [%s].', $ingredientUnitCode),
            ]);
        }

        return $ingredientUnitCode;
    }

    private function unitCodesMatch(string $expectedUnitCode, string $actualUnitCode): bool
    {
        return strtolower(trim($expectedUnitCode)) === strtolower(trim($actualUnitCode));
    }

    /**
     * @return array{0:string,1:string,2:bool}
     */
    private function resolveIngredientSort(string $sortBy, string $sortDir): array
    {
        $direction = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        return match ($sortBy) {
            'code' => ['ingredients.code', $direction, false],
            'ingredient_id' => ['ingredients.ingredient_id', $direction, false],
            'stock_on_hand_quantity' => ['COALESCE(stock_levels.stock_on_hand_quantity, 0)', $direction, true],
            'recipe_usage_count' => ['COALESCE(recipe_usage.recipe_usage_count, 0)', $direction, true],
            'updated_at' => ['ingredients.updated_at', $direction, false],
            default => ['ingredients.name', $direction, false],
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveMovementSort(string $sortBy, string $sortDir): array
    {
        $direction = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        return match ($sortBy) {
            'movement_id' => ['movement_id', $direction],
            'movement_type' => ['movement_type', $direction],
            'quantity_delta' => ['quantity_delta', $direction],
            default => ['created_at', $direction],
        };
    }
}
