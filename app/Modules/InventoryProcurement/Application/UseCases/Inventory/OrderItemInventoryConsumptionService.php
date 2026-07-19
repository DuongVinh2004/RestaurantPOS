<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Application\UseCases\Inventory;

use App\Enums\ReservationOrderItemStatus;
use App\Modules\InventoryProcurement\Domain\Models\IngredientStockMovement;
use App\Modules\Ordering\Application\Services\OrderItemRecipeSnapshotService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class OrderItemInventoryConsumptionService
{
    public function __construct(
        private readonly InventoryStockMovementService $stockMovementService,
        private readonly OrderItemRecipeSnapshotService $recipeSnapshotService,
    ) {}

    public function syncInventoryForStatusChange(
        Reservation $reservation,
        ReservationOrder $order,
        ReservationOrderItem $item,
        ReservationOrderItemStatus $previousStatus,
        ReservationOrderItemStatus $targetStatus,
        ?int $actorUserId = null,
    ): void {
        if ($previousStatus !== ReservationOrderItemStatus::Served && $targetStatus === ReservationOrderItemStatus::Served) {
            $this->processSnapshotMovement(
                reservation: $reservation,
                order: $order,
                item: $item,
                movementType: IngredientStockMovement::TYPE_STOCK_OUT,
                referenceType: 'ReservationOrderItemConsumption',
                notesPrefix: 'Consumed',
                actorUserId: $actorUserId,
                createdAt: $item->updated_at ?? Carbon::now('UTC'),
            );

            return;
        }

        if ($previousStatus === ReservationOrderItemStatus::InProgress && $targetStatus === ReservationOrderItemStatus::Cancelled) {
            $this->processSnapshotMovement(
                reservation: $reservation,
                order: $order,
                item: $item,
                movementType: IngredientStockMovement::TYPE_WASTAGE,
                referenceType: 'ReservationOrderItemWastage',
                notesPrefix: 'Wasted after preparation started',
                actorUserId: $actorUserId,
                createdAt: Carbon::now('UTC'),
            );
        }
    }

    private function processSnapshotMovement(
        Reservation $reservation,
        ReservationOrder $order,
        ReservationOrderItem $item,
        string $movementType,
        string $referenceType,
        string $notesPrefix,
        ?int $actorUserId,
        CarbonInterface $createdAt,
    ): void {
        $itemId = (int) ($item->item_id ?? 0);
        $quantityMultiplier = max(0.0, (float) ($item->quantity ?? 0));
        if ($itemId <= 0 || $quantityMultiplier <= 0.0001) {
            return;
        }

        // Parent combo items do not move stock; each committed component owns its own snapshot.
        $item->loadMissing('item');
        if ($item->item && $item->item->is_combo) {
            return;
        }

        $movementPayloads = [];
        foreach ($this->recipeSnapshotFor($item) as $recipeLine) {
            $ingredientId = $recipeLine['ingredient_id'];
            $movementPayloads[] = [
                'ingredient_id' => $ingredientId,
                'branch_id' => (int) ($reservation->branch_id ?? 0),
                'movement_type' => $movementType,
                'quantity' => round($quantityMultiplier * (float) $recipeLine['quantity'], 3),
                'unit_code' => $recipeLine['unit_code'],
                'reference_type' => $referenceType,
                'reference_id' => sprintf(
                    '%d:%d:%d',
                    (int) $item->order_item_id,
                    $recipeLine['recipe_line_id'],
                    $ingredientId,
                ),
                'notes' => sprintf(
                    '%s for reservation order item %d on order %d',
                    $notesPrefix,
                    (int) $item->order_item_id,
                    (int) $order->order_id,
                ),
                'created_at' => $createdAt,
            ];
        }

        DB::transaction(function () use ($movementPayloads, $actorUserId): void {
            $this->stockMovementService->assertSufficientStockForMovements($movementPayloads);

            foreach ($movementPayloads as $payload) {
                $ingredientId = (int) $payload['ingredient_id'];
                unset($payload['ingredient_id']);

                $this->stockMovementService->recordMovement($ingredientId, $payload, $actorUserId);
            }
        }, 3);
    }

    /**
     * @return list<array{
     *     recipe_line_id:int,
     *     ingredient_id:int,
     *     quantity:string,
     *     unit_code:string,
     *     sort_order:int,
     *     recipe_row_version:int
     * }>
     */
    private function recipeSnapshotFor(ReservationOrderItem $item): array
    {
        $snapshot = $item->getAttribute('recipe_snapshot');
        if ($snapshot === null) {
            // Forward patch backfills production rows. This fallback keeps legacy/test rows operable.
            $snapshot = $this->recipeSnapshotService->snapshotForItemId((int) $item->item_id);
        } elseif (is_string($snapshot)) {
            try {
                $snapshot = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->throwInvalidSnapshot();
            }
        }

        if (! is_array($snapshot) || ! array_is_list($snapshot)) {
            $this->throwInvalidSnapshot();
        }

        $normalized = [];
        $ingredientIds = [];
        foreach ($snapshot as $line) {
            if (! is_array($line)) {
                $this->throwInvalidSnapshot();
            }

            $recipeLineId = (int) ($line['recipe_line_id'] ?? 0);
            $ingredientId = (int) ($line['ingredient_id'] ?? 0);
            $quantity = number_format((float) ($line['quantity'] ?? 0), 3, '.', '');
            $unitCode = trim((string) ($line['unit_code'] ?? ''));
            if ($recipeLineId <= 0 || $ingredientId <= 0 || (float) $quantity <= 0.0001 || $unitCode === '') {
                $this->throwInvalidSnapshot();
            }
            if (isset($ingredientIds[$ingredientId])) {
                $this->throwInvalidSnapshot();
            }
            $ingredientIds[$ingredientId] = true;

            $normalized[] = [
                'recipe_line_id' => $recipeLineId,
                'ingredient_id' => $ingredientId,
                'quantity' => $quantity,
                'unit_code' => $unitCode,
                'sort_order' => (int) ($line['sort_order'] ?? 0),
                'recipe_row_version' => (int) ($line['recipe_row_version'] ?? 0),
            ];
        }

        return $normalized;
    }

    private function throwInvalidSnapshot(): never
    {
        throw ValidationException::withMessages([
            'recipe_snapshot' => 'Committed order-item recipe snapshot is invalid. Reload or reconcile the order item before changing kitchen state.',
        ]);
    }
}
