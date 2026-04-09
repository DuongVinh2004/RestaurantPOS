<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\IngredientStockMovement;
use App\Services\Branch\BranchContextService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockMovementService
{
    /**
     * @var list<string>
     */
    private const REPLAY_SAFE_REFERENCE_TYPES = [
        'PurchaseReceipt',
        'ReservationOrderItemConsumption',
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
        );
        if ($existingMovement instanceof IngredientStockMovement) {
            $this->assertReplayCompatible(
                existingMovement: $existingMovement,
                branchId: $branchId,
                movementType: $movementType,
                quantityDelta: $quantityDelta,
                unitCode: $unitCode,
            );

            return $existingMovement;
        }

        $movement = new IngredientStockMovement;
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

    public function currentStockOnHand(int $ingredientId, ?int $branchId = null): string
    {
        $quantity = IngredientStockMovement::query()
            ->where('ingredient_id', $ingredientId)
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->sum('quantity_delta');

        return number_format((float) $quantity, 3, '.', '');
    }

    private function normalizeQuantityDelta(string $movementType, mixed $quantity): string
    {
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

    private function findReplaySafeMovement(int $ingredientId, ?string $referenceType, ?string $referenceId): ?IngredientStockMovement
    {
        if ($referenceType === null || $referenceId === null || ! in_array($referenceType, self::REPLAY_SAFE_REFERENCE_TYPES, true)) {
            return null;
        }

        /** @var IngredientStockMovement|null $movement */
        $movement = IngredientStockMovement::query()
            ->where('ingredient_id', $ingredientId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderByDesc('movement_id')
            ->first();

        return $movement;
    }

    private function assertReplayCompatible(
        IngredientStockMovement $existingMovement,
        int $branchId,
        string $movementType,
        string $quantityDelta,
        string $unitCode,
    ): void {
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
