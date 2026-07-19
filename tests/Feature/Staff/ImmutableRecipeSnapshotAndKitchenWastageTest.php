<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\ReservationOrderItemStatus;
use App\Modules\InventoryProcurement\Application\UseCases\Inventory\OrderItemInventoryConsumptionService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class ImmutableRecipeSnapshotAndKitchenWastageTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
    }

    public function test_order_commit_snapshot_remains_authoritative_across_recipe_edits_and_serve_replay(): void
    {
        $scenario = $this->seedOrderWithRecipe('IMMUTABLE-RECIPE', '80.000', '500.000');

        DB::table('menu_item_recipes')
            ->where('recipe_line_id', $scenario['recipe_line_id'])
            ->update(['quantity' => '100.000', 'row_version' => 2]);

        $orderItemId = $this->addOrderItem(
            $scenario['staff_id'],
            $scenario['order_id'],
            $scenario['item_id'],
            'immutable-recipe-add'
        );

        $snapshotJson = DB::table('reservation_order_items')
            ->where('order_item_id', $orderItemId)
            ->value('recipe_snapshot');
        self::assertNotNull($snapshotJson, 'Order commit must persist a recipe snapshot.');

        $snapshot = json_decode((string) $snapshotJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('100.000', $snapshot[0]['quantity'] ?? null);
        self::assertSame($scenario['ingredient_id'], $snapshot[0]['ingredient_id'] ?? null);

        DB::table('menu_item_recipes')
            ->where('recipe_line_id', $scenario['recipe_line_id'])
            ->update(['quantity' => '150.000', 'row_version' => 3]);

        $this->transitionStatus($scenario['staff_id'], $scenario['order_id'], $orderItemId, 'InProgress', 'immutable-recipe-start')
            ->assertOk();

        DB::table('menu_item_recipes')
            ->where('recipe_line_id', $scenario['recipe_line_id'])
            ->update(['quantity' => '175.000', 'row_version' => 4]);

        $this->transitionStatus($scenario['staff_id'], $scenario['order_id'], $orderItemId, 'Served', 'immutable-recipe-serve')
            ->assertOk();

        DB::table('menu_item_recipes')
            ->where('recipe_line_id', $scenario['recipe_line_id'])
            ->update(['quantity' => '200.000', 'row_version' => 5]);

        $this->transitionStatus($scenario['staff_id'], $scenario['order_id'], $orderItemId, 'Served', 'immutable-recipe-serve-noop')
            ->assertOk();

        $movements = DB::table('ingredient_stock_movements')
            ->where('ingredient_id', $scenario['ingredient_id'])
            ->where('reference_type', 'ReservationOrderItemConsumption')
            ->where('reference_id', 'like', $orderItemId.':%')
            ->get();

        self::assertCount(1, $movements);
        self::assertSame('-100.000', number_format((float) $movements[0]->quantity_delta, 3, '.', ''));
        self::assertSame('400.000', $this->stockBalance($scenario['branch_id'], $scenario['ingredient_id']));
    }

    public function test_in_progress_cancel_records_retry_safe_wastage_from_committed_snapshot(): void
    {
        $scenario = $this->seedOrderWithRecipe('KITCHEN-WASTAGE', '40.000', '200.000');
        $orderItemId = $this->addOrderItem(
            $scenario['staff_id'],
            $scenario['order_id'],
            $scenario['item_id'],
            'kitchen-wastage-add'
        );

        $this->transitionStatus($scenario['staff_id'], $scenario['order_id'], $orderItemId, 'InProgress', 'kitchen-wastage-start')
            ->assertOk();

        DB::table('menu_item_recipes')
            ->where('recipe_line_id', $scenario['recipe_line_id'])
            ->update(['quantity' => '90.000', 'row_version' => 2]);

        $this->transitionStatus($scenario['staff_id'], $scenario['order_id'], $orderItemId, 'Cancelled', 'kitchen-wastage-cancel')
            ->assertOk();
        $this->transitionStatus($scenario['staff_id'], $scenario['order_id'], $orderItemId, 'Cancelled', 'kitchen-wastage-cancel-noop')
            ->assertOk();

        $order = ReservationOrder::query()->findOrFail($scenario['order_id']);
        $reservation = Reservation::query()->findOrFail((int) $order->reservation_id);
        $orderItem = ReservationOrderItem::query()->findOrFail($orderItemId);
        app(OrderItemInventoryConsumptionService::class)->syncInventoryForStatusChange(
            reservation: $reservation,
            order: $order,
            item: $orderItem,
            previousStatus: ReservationOrderItemStatus::InProgress,
            targetStatus: ReservationOrderItemStatus::Cancelled,
            actorUserId: $scenario['staff_id'],
        );

        $movements = DB::table('ingredient_stock_movements')
            ->where('ingredient_id', $scenario['ingredient_id'])
            ->where('reference_type', 'ReservationOrderItemWastage')
            ->where('reference_id', 'like', $orderItemId.':%')
            ->get();

        self::assertCount(1, $movements);
        self::assertSame('Wastage', (string) $movements[0]->movement_type);
        self::assertSame('-40.000', number_format((float) $movements[0]->quantity_delta, 3, '.', ''));
        self::assertSame($scenario['staff_id'], (int) $movements[0]->created_by);
        self::assertSame('160.000', $this->stockBalance($scenario['branch_id'], $scenario['ingredient_id']));
    }

    /**
     * @return array{staff_id:int,order_id:int,item_id:int,ingredient_id:int,recipe_line_id:int,branch_id:int}
     */
    private function seedOrderWithRecipe(string $code, string $recipeQuantity, string $openingStock): array
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $itemId = $this->createMenuItem(['code' => $code.'-ITEM', 'name' => $code.' Item']);
        $this->createMenuItemPrice(['item_id' => $itemId, 'price' => '50000', 'currency' => 'VND']);
        $ingredientId = $this->createIngredient([
            'code' => $code.'-INGREDIENT',
            'name' => $code.' Ingredient',
            'unit_code' => 'g',
        ]);
        $recipeLineId = $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'quantity' => $recipeQuantity,
            'unit_code' => 'g',
            'sort_order' => 10,
            'row_version' => 1,
        ]);
        $branchId = (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => $openingStock,
            'unit_code' => 'g',
            'reference_type' => 'manual_count',
            'reference_id' => strtolower($code).'-opening',
        ]);

        return [
            'staff_id' => $staffId,
            'order_id' => $orderId,
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'recipe_line_id' => $recipeLineId,
            'branch_id' => $branchId,
        ];
    }

    private function addOrderItem(int $staffId, int $orderId, int $itemId, string $idempotencyKey): int
    {
        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId),
            $idempotencyKey
        ))->postJson("/api/v1/staff/orders/{$orderId}/items", [
            'row_version' => (int) DB::table('reservation_orders')->where('order_id', $orderId)->value('row_version'),
            'items' => [[
                'menu_item_id' => $itemId,
                'qty' => 1,
                'note' => null,
            ]],
        ]);

        $response->assertOk();

        return (int) DB::table('reservation_order_items')
            ->where('order_id', $orderId)
            ->where('item_id', $itemId)
            ->value('order_item_id');
    }

    private function transitionStatus(int $staffId, int $orderId, int $orderItemId, string $status, string $idempotencyKey)
    {
        return $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId),
            $idempotencyKey
        ))->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
            'order_row_version' => (int) DB::table('reservation_orders')->where('order_id', $orderId)->value('row_version'),
            'row_version' => (int) DB::table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version'),
            'status' => $status,
        ]);
    }

    private function stockBalance(int $branchId, int $ingredientId): string
    {
        return number_format((float) DB::table('ingredient_stock_movements')
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->sum('quantity_delta'), 3, '.', '');
    }
}
