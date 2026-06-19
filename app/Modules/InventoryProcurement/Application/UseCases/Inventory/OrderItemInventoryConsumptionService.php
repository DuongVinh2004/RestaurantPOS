<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Application\UseCases\Inventory;

use App\Enums\ReservationOrderItemStatus;
use App\Modules\Catalog\Domain\Models\MenuItemRecipe;
use App\Modules\InventoryProcurement\Domain\Models\Ingredient;
use App\Modules\InventoryProcurement\Domain\Models\IngredientStockMovement;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderItemInventoryConsumptionService
{
    public function __construct(
        private readonly InventoryStockMovementService $stockMovementService,
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
            $this->processConsumption($reservation, $order, $item, $actorUserId);
        } elseif ($previousStatus === ReservationOrderItemStatus::Served && $targetStatus === ReservationOrderItemStatus::Cancelled) {
            $this->processReturn($reservation, $order, $item, $actorUserId);
        }
    }

    private function processConsumption(
        Reservation $reservation,
        ReservationOrder $order,
        ReservationOrderItem $item,
        ?int $actorUserId = null,
    ): void {
        $itemId = (int) ($item->item_id ?? 0);
        if ($itemId <= 0) {
            return;
        }

        $quantityMultiplier = max(0.0, (float) ($item->quantity ?? 0));
        if ($quantityMultiplier <= 0.0001) {
            return;
        }

        // Parent combo items do not consume inventory; their components will do it.
        $item->loadMissing('item');
        if ($item->item && $item->item->is_combo) {
            return;
        }

        $recipeLines = MenuItemRecipe::query()
            ->where('item_id', $itemId)
            ->orderBy('sort_order')
            ->orderBy('recipe_line_id')
            ->get();

        // Moi recipe line tao ra mot stock-out movement co reference_id xac dinh de replay an toan.
        $movementPayloads = [];
        foreach ($recipeLines as $recipeLine) {
            $ingredientId = (int) ($recipeLine->ingredient_id ?? 0);
            $recipeQuantity = max(0.0, (float) ($recipeLine->quantity ?? 0));

            if ($ingredientId <= 0 || $recipeQuantity <= 0.0001) {
                continue;
            }

            $referenceId = sprintf('%d:%d:%d', (int) $item->order_item_id, (int) $recipeLine->recipe_line_id, $ingredientId);

            $movementPayloads[] = [
                'ingredient_id' => $ingredientId,
                'branch_id' => (int) ($reservation->branch_id ?? 0),
                'movement_type' => IngredientStockMovement::TYPE_STOCK_OUT,
                'quantity' => round($quantityMultiplier * $recipeQuantity, 3),
                'unit_code' => (string) ($recipeLine->unit_code ?? ''),
                'reference_type' => 'ReservationOrderItemConsumption',
                'reference_id' => $referenceId,
                'notes' => sprintf(
                    'Consumed for reservation order item %d on order %d',
                    (int) $item->order_item_id,
                    (int) $order->order_id
                ),
                'created_at' => $item->updated_at ?? Carbon::now('UTC'),
            ];
        }

        DB::transaction(function () use ($movementPayloads, $actorUserId): void {
            // Preflight ton kho truoc, sau do moi ghi tung movement de tranh ghi nua chung nua ngat.
            $this->stockMovementService->assertSufficientStockForMovements($movementPayloads);

            foreach ($movementPayloads as $payload) {
                $ingredientId = (int) $payload['ingredient_id'];
                unset($payload['ingredient_id']);

                $this->stockMovementService->recordMovement($ingredientId, $payload, $actorUserId);
            }
        }, 3);
    }

    private function processReturn(
        Reservation $reservation,
        ReservationOrder $order,
        ReservationOrderItem $item,
        ?int $actorUserId = null,
    ): void {
        $itemId = (int) ($item->item_id ?? 0);
        if ($itemId <= 0) {
            return;
        }

        $quantityMultiplier = max(0.0, (float) ($item->quantity ?? 0));
        if ($quantityMultiplier <= 0.0001) {
            return;
        }

        $item->loadMissing('item');
        if ($item->item && $item->item->is_combo) {
            return;
        }

        $recipeLines = MenuItemRecipe::query()
            ->where('item_id', $itemId)
            ->orderBy('sort_order')
            ->orderBy('recipe_line_id')
            ->get();

        $movementPayloads = [];
        foreach ($recipeLines as $recipeLine) {
            $ingredientId = (int) ($recipeLine->ingredient_id ?? 0);
            $recipeQuantity = max(0.0, (float) ($recipeLine->quantity ?? 0));

            if ($ingredientId <= 0 || $recipeQuantity <= 0.0001) {
                continue;
            }

            $referenceId = sprintf('%d:%d:%d', (int) $item->order_item_id, (int) $recipeLine->recipe_line_id, $ingredientId);

            $movementPayloads[] = [
                'ingredient_id' => $ingredientId,
                'branch_id' => (int) ($reservation->branch_id ?? 0),
                'movement_type' => IngredientStockMovement::TYPE_STOCK_IN, // Return stock
                'quantity' => round($quantityMultiplier * $recipeQuantity, 3),
                'unit_code' => (string) ($recipeLine->unit_code ?? ''),
                'reference_type' => 'ReservationOrderItemReturn',
                'reference_id' => $referenceId,
                'notes' => sprintf(
                    'Returned for cancelled order item %d on order %d',
                    (int) $item->order_item_id,
                    (int) $order->order_id
                ),
                'created_at' => Carbon::now('UTC'),
            ];
        }

        // Return movements don't need assertSufficientStockForMovements preflight since they increase stock
        DB::transaction(function () use ($movementPayloads, $actorUserId): void {
            // Sort to prevent deadlocks when locking ingredients in loop
            usort($movementPayloads, fn ($a, $b) => $a['ingredient_id'] <=> $b['ingredient_id']);

            $ingredientIdsToLock = array_unique(array_column($movementPayloads, 'ingredient_id'));
            if (! empty($ingredientIdsToLock)) {
                Ingredient::query()
                    ->whereIn('ingredient_id', $ingredientIdsToLock)
                    ->orderBy('ingredient_id')
                    ->lockForUpdate()
                    ->pluck('ingredient_id');
            }

            foreach ($movementPayloads as $payload) {
                $ingredientId = (int) $payload['ingredient_id'];
                unset($payload['ingredient_id']);

                $this->stockMovementService->recordMovement($ingredientId, $payload, $actorUserId);
            }
        }, 3);
    }
}
