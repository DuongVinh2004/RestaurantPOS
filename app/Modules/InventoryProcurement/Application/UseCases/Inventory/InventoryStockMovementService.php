<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Application\UseCases\Inventory;

use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\InventoryProcurement\Domain\Models\Ingredient;
use App\Modules\InventoryProcurement\Domain\Models\IngredientStockMovement;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockMovementService
{
    private const QUANTITY_SCALE = 1000;

    /**
     * @var list<string>
     */
    private const REPLAY_SAFE_REFERENCE_TYPES = [
        'PurchaseReceipt',
        'ReservationOrderItemConsumption',
        'ReservationOrderItemReturn',
    ];

    public function __construct(
        private readonly BranchContextService $branchContextService,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function recordMovement(int $ingredientId, array $payload, ?int $actorUserId = null): IngredientStockMovement
    {
        return DB::transaction(function () use ($ingredientId, $payload, $actorUserId): IngredientStockMovement {
            // Ingredient bi lock tai goc de moi stock mutation cho cung ingredient chay noi tiep.
            /** @var Ingredient|null $ingredient */
            $ingredient = Ingredient::query()->lockForUpdate()->find($ingredientId);

            if (! $ingredient instanceof Ingredient) {
                throw (new ModelNotFoundException)->setModel(Ingredient::class, [$ingredientId]);
            }

            return $this->recordMovementForIngredient($ingredient, $payload, $actorUserId);
        }, 3);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function recordMovementForIngredient(Ingredient $ingredient, array $payload, ?int $actorUserId = null): IngredientStockMovement
    {
        // Toan bo movement deu duoc chuan hoa ve branch, unit, quantity_delta va reference lineage truoc khi save.
        $branchId = $this->branchContextService->resolveBranchId($payload['branch_id'] ?? null);
        $movementType = (string) $payload['movement_type'];
        $unitCode = $this->resolveIngredientUnitCode($ingredient, $payload['unit_code'] ?? null, 'unit_code');
        $quantityDelta = $this->normalizeQuantityDelta($movementType, $payload['quantity'] ?? 0);
        $referenceType = $this->normalizeNullableString($payload['reference_type'] ?? null);
        $referenceId = $this->normalizeNullableString($payload['reference_id'] ?? null);

        $existingMovement = $this->findReplaySafeMovement(
            ingredientId: (int) $ingredient->ingredient_id,
            referenceType: $referenceType,
            referenceId: $referenceId,
            lockForUpdate: true,
        );
        if ($existingMovement instanceof IngredientStockMovement) {
            // Purchase receipt va order-item consumption co the replay an toan neu payload khop 100%.
            $this->assertReplayCompatible(
                existingMovement: $existingMovement,
                branchId: $branchId,
                movementType: $movementType,
                quantityDelta: $quantityDelta,
                unitCode: $unitCode,
            );

            return $existingMovement;
        }

        if ($this->isNegativeMovementType($movementType)) {
            // Moi stock out/decrease/wastage phai qua preflight de ledger khong am.
            $this->assertStockWillNotGoNegative(
                (int) $ingredient->ingredient_id,
                $branchId,
                $this->toScaledQuantity($quantityDelta),
            );
        }

        $movement = new IngredientStockMovement;
        // Ledger movement la su that ke toan cua ton kho, khong sua line cu ma chi them dong moi.
        $movement->fill([
            'branch_id' => $branchId,
            'ingredient_id' => (int) $ingredient->ingredient_id,
            'movement_type' => $movementType,
            'quantity_delta' => $quantityDelta,
            'unit_code' => $unitCode,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
            'created_by' => $actorUserId,
            'created_at' => $payload['created_at'] ?? now('UTC'),
        ]);
        $movement->save();

        return $movement->fresh() ?? $movement;
    }

    /**
     * @param  list<array{
     *     ingredient_id:int,
     *     branch_id:int|null,
     *     movement_type:string,
     *     quantity:mixed,
     *     unit_code?:mixed,
     *     reference_type?:mixed,
     *     reference_id?:mixed
     * }>  $movements
     */
    public function assertSufficientStockForMovements(array $movements): void
    {
        DB::transaction(function () use ($movements): void {
            // Helper nay dung cho batch outflow: gop nhu cau theo ingredient/branch roi check mot lan truoc khi ghi.
            $requirements = [];

            foreach ($movements as $movement) {
                $movementType = (string) $movement['movement_type'];
                if (! $this->isNegativeMovementType($movementType)) {
                    continue;
                }

                $ingredientId = (int) $movement['ingredient_id'];
                $branchId = $this->branchContextService->resolveBranchId($movement['branch_id'] ?? null);

                /** @var Ingredient $ingredient */
                $ingredient = Ingredient::query()->findOrFail($ingredientId);
                $unitCode = $this->resolveIngredientUnitCode($ingredient, $movement['unit_code'] ?? null, 'unit_code');
                $quantityDelta = $this->normalizeQuantityDelta($movementType, $movement['quantity'] ?? 0);
                $referenceType = $this->normalizeNullableString($movement['reference_type'] ?? null);
                $referenceId = $this->normalizeNullableString($movement['reference_id'] ?? null);

                $existingMovement = $this->findReplaySafeMovement(
                    ingredientId: $ingredientId,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                );
                if ($existingMovement instanceof IngredientStockMovement) {
                    // Movement replay hop le khong duoc tinh lai vao nhu cau ton kho.
                    $this->assertReplayCompatible(
                        existingMovement: $existingMovement,
                        branchId: $branchId,
                        movementType: $movementType,
                        quantityDelta: $quantityDelta,
                        unitCode: $unitCode,
                    );

                    continue;
                }

                // Key format ensures ksort() orders by ingredient_id first, preventing row-lock deadlocks.
                $key = sprintf('%010d:%010d', $ingredientId, $branchId);
                $requirements[$key] ??= [
                    'branch_id' => $branchId,
                    'ingredient_id' => $ingredientId,
                    'quantity_delta' => 0,
                ];
                $requirements[$key]['quantity_delta'] += $this->toScaledQuantity($quantityDelta);
            }

            // Sort on dinh giup lock/check luon theo cung thu tu, giam nguy co deadlock khi batch lon.
            ksort($requirements);

            foreach ($requirements as $requirement) {
                $this->assertStockWillNotGoNegative(
                    (int) $requirement['ingredient_id'],
                    (int) $requirement['branch_id'],
                    (int) $requirement['quantity_delta'],
                );
            }
        }, 3);
    }

    public function currentStockOnHand(int $ingredientId, ?int $branchId = null): string
    {
        // Ton kho hien tai duoc suy ra tu tong ledger, khong luu denormalized snapshot o service nay.
        $quantity = IngredientStockMovement::query()
            ->where('ingredient_id', $ingredientId)
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->sum('quantity_delta');

        return number_format((float) $quantity, 3, '.', '');
    }

    private function normalizeQuantityDelta(string $movementType, mixed $quantity): string
    {
        // Quantity duoc doi dau theo movement type de moi noi chi can nhap tri tuyet doi.
        $absolute = abs((float) $quantity);

        return match ($movementType) {
            IngredientStockMovement::TYPE_STOCK_IN,
            IngredientStockMovement::TYPE_ADJUSTMENT_INCREASE => number_format($absolute, 3, '.', ''),
            IngredientStockMovement::TYPE_STOCK_OUT,
            IngredientStockMovement::TYPE_ADJUSTMENT_DECREASE,
            IngredientStockMovement::TYPE_WASTAGE => number_format($absolute * -1, 3, '.', ''),
            default => throw new \InvalidArgumentException(sprintf('Unsupported stock movement type [%s].', $movementType)),
        };
    }

    private function isNegativeMovementType(string $movementType): bool
    {
        return in_array($movementType, [
            IngredientStockMovement::TYPE_STOCK_OUT,
            IngredientStockMovement::TYPE_ADJUSTMENT_DECREASE,
            IngredientStockMovement::TYPE_WASTAGE,
        ], true);
    }

    private function assertStockWillNotGoNegative(int $ingredientId, int $branchId, int $quantityDelta): void
    {
        // Khoa ca ingredient va ledger movement cua branch de current stock + projected stock duoc tinh nhat quan.
        Ingredient::query()->where('ingredient_id', $ingredientId)->lockForUpdate()->firstOrFail();

        $currentStock = IngredientStockMovement::query()
            ->where('ingredient_id', $ingredientId)
            ->where('branch_id', $branchId)
            ->orderBy('movement_id')
            ->lockForUpdate()
            ->pluck('quantity_delta')
            ->reduce(
                fn (int $carry, mixed $quantity): int => $carry + $this->toScaledQuantity($quantity),
                0,
            );

        $projectedStock = $currentStock + $quantityDelta;
        if ($projectedStock >= 0) {
            return;
        }

        throw ValidationException::withMessages([
            'quantity' => [sprintf(
                'Stock movement cannot reduce ingredient %d in branch %d below zero. Current stock is %s; requested decrease is %s.',
                $ingredientId,
                $branchId,
                $this->formatScaledQuantity($currentStock),
                $this->formatScaledQuantity(abs($quantityDelta)),
            )],
        ]);
    }

    private function toScaledQuantity(mixed $quantity): int
    {
        return (int) round(((float) $quantity) * self::QUANTITY_SCALE);
    }

    private function formatScaledQuantity(int $quantity): string
    {
        return number_format($quantity / self::QUANTITY_SCALE, 3, '.', '');
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
                $field => 'Ingredient unit code must be set before recording stock movements.',
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

    private function findReplaySafeMovement(
        int $ingredientId,
        ?string $referenceType,
        ?string $referenceId,
        bool $lockForUpdate = false,
    ): ?IngredientStockMovement {
        // Chi mot so lineage he thong moi duoc replay; stock adjustment tay thi khong.
        if ($referenceType === null || $referenceId === null || ! in_array($referenceType, self::REPLAY_SAFE_REFERENCE_TYPES, true)) {
            return null;
        }

        /** @var IngredientStockMovement|null $movement */
        $query = IngredientStockMovement::query()
            ->where('ingredient_id', $ingredientId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderByDesc('movement_id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $movement = $query->first();

        return $movement;
    }

    private function assertReplayCompatible(
        IngredientStockMovement $existingMovement,
        int $branchId,
        string $movementType,
        string $quantityDelta,
        string $unitCode,
    ): void {
        // Replay khac branch/type/quantity/unit phai fail ngay de tranh reference trung ma noi dung lech.
        $existingQuantityDelta = number_format((float) $existingMovement->quantity_delta, 3, '.', '');
        $isCompatible = (int) $existingMovement->branch_id === $branchId
            && (string) $existingMovement->movement_type === $movementType
            && $existingQuantityDelta === $quantityDelta
            && $this->unitCodesMatch((string) $existingMovement->unit_code, $unitCode);

        if ($isCompatible) {
            return;
        }

        throw ValidationException::withMessages([
            'reference_id' => sprintf(
                'System stock movement reference [%s:%s] is already recorded with different movement details.',
                (string) $existingMovement->reference_type,
                (string) $existingMovement->reference_id
            ),
        ]);
    }
}
