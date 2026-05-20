<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Application\UseCases\Inventory;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemRecipe;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\InventoryProcurement\Domain\Models\Ingredient;
use App\Modules\InventoryProcurement\Domain\Models\IngredientStockMovement;
use App\Support\AuditEvent;
use App\Support\Listing\SafeLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryManagementService
{
    private const STALE_ROW_VERSION_MESSAGE = 'The row_version is stale (row_version mismatch). Reload the resource and try again.';

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

        // Man hinh ingredient khong doc bang stock snapshot rieng; no build tu ledger + recipe usage ngay trong query.
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
    public function createIngredient(array $payload, ?int $actorUserId = null): Ingredient
    {
        $ingredient = new Ingredient;
        // Ingredient la master data nen create flow giu rat thang: normalize field truoc khi save.
        $ingredient->fill([
            'code' => $this->normalizeNullableString($payload['code'] ?? null),
            'name' => trim((string) $payload['name']),
            'unit_code' => trim((string) $payload['unit_code']),
            'description' => $this->normalizeNullableString($payload['description'] ?? null),
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);
        $ingredient->save();

        $fresh = $this->findIngredient((int) $ingredient->ingredient_id);

        AuditEvent::info('admin.ingredient.created', [
            'ingredient_id' => (int) $fresh->ingredient_id,
            'code' => $fresh->code,
            '_audit' => [
                'action' => 'inventory.ingredient.created',
                'entity_type' => 'ingredient',
                'entity_id' => (string) $fresh->ingredient_id,
                'after' => $this->ingredientAuditSnapshot($fresh),
                'summary' => [
                    'code' => $fresh->code,
                    'name' => (string) $fresh->name,
                    'unit_code' => (string) $fresh->unit_code,
                    'is_active' => (bool) $fresh->is_active,
                ],
                'actor' => $this->auditActor($actorUserId),
            ],
        ]);

        return $fresh;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateIngredient(int $ingredientId, array $payload, ?int $actorUserId = null): Ingredient
    {
        return DB::transaction(function () use ($ingredientId, $payload, $actorUserId): Ingredient {
            /** @var Ingredient $ingredient */
            $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($ingredientId);
            $before = $this->ingredientAuditSnapshot($ingredient);

            // Ingredient edit dung optimistic lock de tranh de thay doi unit/code mo phong nhau.
            $this->assertExpectedRowVersion((int) ($ingredient->row_version ?? 1), (int) $payload['row_version']);

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

            $this->bumpRowVersion($ingredient);
            $ingredient->save();

            $fresh = $this->findIngredient($ingredientId);

            AuditEvent::info('admin.ingredient.updated', [
                'ingredient_id' => $ingredientId,
                'code' => $fresh->code,
                '_audit' => [
                    'action' => 'inventory.ingredient.updated',
                    'entity_type' => 'ingredient',
                    'entity_id' => (string) $fresh->ingredient_id,
                    'before' => $before,
                    'after' => $this->ingredientAuditSnapshot($fresh),
                    'summary' => [
                        'code' => $fresh->code,
                        'name' => (string) $fresh->name,
                        'unit_code' => (string) $fresh->unit_code,
                        'row_version' => (int) ($fresh->row_version ?? 1),
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $fresh;
        }, 3);
    }

    /**
     * @return array{item: MenuItem, lines: Collection<int, MenuItemRecipe>, row_version:int}
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
            'row_version' => $this->recipeAggregateRowVersion($lines),
        ];
    }

    /**
     * @param  array{row_version:int,lines:list<array<string, mixed>>}  $payload
     * @return array{item: MenuItem, lines: Collection<int, MenuItemRecipe>, row_version:int}
     */
    public function syncMenuItemRecipe(int $itemId, array $payload): array
    {
        return DB::transaction(function () use ($itemId, $payload): array {
            /** @var MenuItem $item */
            $item = MenuItem::query()->lockForUpdate()->findOrFail($itemId);
            /** @var list<array<string,mixed>> $lines */
            $lines = array_values((array) ($payload['lines'] ?? []));

            // Recipe duoc coi nhu mot aggregate; row_version lay theo max cua cac line hien tai.
            /** @var Collection<int, MenuItemRecipe> $existingLines */
            $existingLines = MenuItemRecipe::query()
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->get();
            $currentRecipeRowVersion = $this->recipeAggregateRowVersion($existingLines);
            $this->assertExpectedRowVersion($currentRecipeRowVersion, (int) $payload['row_version']);
            $nextRecipeRowVersion = $currentRecipeRowVersion + 1;
            $existingByIngredientId = $existingLines->keyBy('ingredient_id');

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

            // Xoa truoc cac ingredient khong con xuat hien trong payload de sync co tinh thay the toan bo recipe.
            MenuItemRecipe::query()
                ->where('item_id', $itemId)
                ->when($ingredientIds !== [], static fn ($query) => $query->whereNotIn('ingredient_id', $ingredientIds))
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

                // Moi ingredient co toi da mot recipe line; sync reuse line cu theo ingredient_id thay vi theo thu tu payload.
                /** @var MenuItemRecipe|null $recipeLine */
                $recipeLine = $existingByIngredientId->get((int) $ingredient->ingredient_id);
                if (! $recipeLine instanceof MenuItemRecipe) {
                    $recipeLine = new MenuItemRecipe;
                    $recipeLine->item_id = $itemId;
                    $recipeLine->ingredient_id = (int) $ingredient->ingredient_id;
                }

                $recipeLine->quantity = $line['quantity'];
                $recipeLine->unit_code = $unitCode;
                $recipeLine->sort_order = (int) ($line['sort_order'] ?? (($index + 1) * 10));
                $recipeLine->notes = $this->normalizeNullableString($line['notes'] ?? null);
                $recipeLine->row_version = $nextRecipeRowVersion;
                $recipeLine->save();
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

        // Khi khong chot branch cu the, ledger bi ep ve cac branch actor duoc phep xem.
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
        // Writable branch duoc resolve tai day de stock service nhan payload da sach va dung scope.
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
            // Tra ve stock_on_hand moi ngay sau movement de UI khong phai goi them mot request tong hop.
            'movement' => $movement,
            'stock_on_hand' => $this->stockMovementService->currentStockOnHand($ingredientId, $stockBranchId),
        ];
    }

    /**
     * @param  list<int>|null  $accessibleBranchIds
     */
    private function baseIngredientsQuery(?int $branchId = null, ?array $accessibleBranchIds = null): Builder
    {
        // Query nen cho inventory list ghep 2 view logic: ton kho hien tai va so recipe dang su dung ingredient.
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
        // Actor null duoc hieu la caller noi bo, khong ep branch scope.
        if ($actorUserId === null || $actorUserId <= 0) {
            return null;
        }

        return $this->staffBranchContextService->branchScopeOrAccessible($actorUserId, $requestedBranchId);
    }

    private function resolveWritableBranchId(?int $actorUserId, mixed $requestedBranchId = null): ?int
    {
        // Create movement cho staff phai luon chot vao branch hien hanh hoac branch ma staff co quyen ghi.
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
        // Recipe line va movement phai di cung don vi chuan cua ingredient de tranh doi don vi ngam.
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

    private function assertExpectedRowVersion(int $currentRowVersion, int $expectedRowVersion): void
    {
        // Aggregate inventory admin flow dung row_version de ngan stale write.
        if ($currentRowVersion === $expectedRowVersion) {
            return;
        }

        throw ValidationException::withMessages([
            'row_version' => [self::STALE_ROW_VERSION_MESSAGE],
        ]);
    }

    private function bumpRowVersion(Ingredient $ingredient): void
    {
        // Moi update ingredient hop le deu tang row_version.
        $ingredient->row_version = max(1, (int) ($ingredient->row_version ?? 1)) + 1;
    }

    /**
     * @return array<string,mixed>
     */
    private function ingredientAuditSnapshot(Ingredient $ingredient): array
    {
        return [
            'code' => $ingredient->code,
            'name' => (string) $ingredient->name,
            'unit_code' => (string) $ingredient->unit_code,
            'description' => $ingredient->description,
            'is_active' => (bool) $ingredient->is_active,
            'row_version' => (int) ($ingredient->row_version ?? 1),
        ];
    }

    /**
     * @return array{type:string,user_id:int,key:string}|null
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
     * @param  Collection<int, MenuItemRecipe>  $lines
     */
    private function recipeAggregateRowVersion(Collection $lines): int
    {
        if ($lines->isEmpty()) {
            return 1;
        }

        return max(1, (int) $lines->max('row_version'));
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
