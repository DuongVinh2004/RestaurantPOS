<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Application\UseCases\Inventory;

use App\Enums\ReservationOrderItemStatus;
use App\Modules\Catalog\Domain\Models\MenuItemRecipe;
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

    public function consumeIfServed(
        Reservation $reservation,
        ReservationOrder $order,
        ReservationOrderItem $item,
        ReservationOrderItemStatus $previousStatus,
        ReservationOrderItemStatus $targetStatus,
        ?int $actorUserId = null,
    ): void {
        if ($previousStatus === ReservationOrderItemStatus::Served || $targetStatus !== ReservationOrderItemStatus::Served) {
            return;
        }

        $itemId = (int) ($item->item_id ?? 0);
        if ($itemId <= 0) {
            return;
        }

        $quantityMultiplier = max(0.0, (float) ($item->quantity ?? 0));
        if ($quantityMultiplier <= 0.0001) {
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
            $this->stockMovementService->assertSufficientStockForMovements($movementPayloads);

            foreach ($movementPayloads as $payload) {
                $ingredientId = (int) $payload['ingredient_id'];
                unset($payload['ingredient_id']);

                $this->stockMovementService->recordMovement($ingredientId, $payload, $actorUserId);
            }
        }, 3);
    }
}
