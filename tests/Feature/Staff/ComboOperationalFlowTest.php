<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\KitchenStationOutputMode;
use App\Enums\ReservationOrderItemStatus;
use App\Modules\Catalog\Domain\Models\MenuCategory;
use App\Modules\Catalog\Domain\Models\MenuItemComboComponent;
use App\Modules\Catalog\Domain\Models\MenuItemRecipe;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\InventoryProcurement\Domain\Models\Ingredient;
use App\Modules\InventoryProcurement\Domain\Models\IngredientStockMovement;
use App\Modules\KitchenDispatch\Application\Actions\DispatchKitchenOrderAction;
use App\Modules\KitchenDispatch\Domain\Models\KitchenOrderItemTicket;
use App\Modules\KitchenDispatch\Domain\Models\KitchenStation;
use App\Modules\KitchenDispatch\Domain\Models\KitchenStationCategoryRoute;
use App\Modules\Ordering\Application\UseCases\OrderItems\StaffOrderItemLifecycleService;
use App\Modules\Ordering\Application\UseCases\Orders\StaffTableOrderService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class ComboOperationalFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_combo_end_to_end_flow()
    {
        // 1. Setup Data: Branch, User, Reservation, Order
        $branchId = 1;

        $userId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'branch_id' => $branchId,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'created_by' => $userId,
        ]);

        $order = ReservationOrder::find($orderId);

        // 2. Setup Catalog: 1 Combo, 2 Components
        $category = MenuCategory::create(['name' => 'Test Category', 'is_active' => true]);
        $categoryId = $category->category_id;

        $itemDrink = $this->createMenuItem(['category_id' => $categoryId, 'name' => 'Coke', 'code' => 'COKE']);
        $this->createMenuItemPrice(['item_id' => $itemDrink, 'price' => '20000', 'currency' => 'VND']);

        $itemFood = $this->createMenuItem(['category_id' => $categoryId, 'name' => 'Burger', 'code' => 'BURGER']);
        $this->createMenuItemPrice(['item_id' => $itemFood, 'price' => '50000', 'currency' => 'VND']);

        $comboItem = $this->createMenuItem(['category_id' => $categoryId, 'name' => 'Combo 1', 'code' => 'COMBO1', 'is_combo' => true]);
        $this->createMenuItemPrice(['item_id' => $comboItem, 'price' => '60000', 'currency' => 'VND']);

        MenuItemComboComponent::create(['combo_item_id' => $comboItem, 'component_item_id' => $itemDrink, 'quantity' => 2]);
        MenuItemComboComponent::create(['combo_item_id' => $comboItem, 'component_item_id' => $itemFood, 'quantity' => 1]);

        // 3. Setup Kitchen Station and Route
        $station = KitchenStation::create([
            'branch_id' => $branchId,
            'name' => 'Test Station',
            'code' => 'TEST_STN',
            'is_active' => true,
            'output_mode' => KitchenStationOutputMode::KDS,
        ]);
        KitchenStationCategoryRoute::create([
            'branch_id' => $branchId,
            'station_id' => $station->station_id,
            'category_id' => $category->category_id,
            'is_active' => true,
        ]);

        // 4. Setup Inventory: Recipe for Burger
        $ingredient = Ingredient::create(['name' => 'Beef Patty', 'sku' => 'BEEF-01', 'base_unit_code' => 'unit']);
        MenuItemRecipe::create(['item_id' => $itemFood, 'ingredient_id' => $ingredient->ingredient_id, 'quantity' => 1, 'unit_code' => 'unit']);

        IngredientStockMovement::create([
            'ingredient_id' => $ingredient->ingredient_id,
            'branch_id' => $branchId,
            'movement_type' => 'StockIn',
            'quantity_delta' => 10,
            'unit_code' => 'unit',
            'reference_type' => 'Manual',
            'reference_id' => 'INIT',
            'created_at' => Carbon::now(),
        ]);

        // ============================================
        // FLOW 1: Add Order Item
        // ============================================
        $orderService = app(StaffTableOrderService::class);
        $orderService->addItems($order->order_id, [
            [
                'item_id' => $comboItem,
                'qty' => 1,
            ],
        ], staffUserId: $userId, idempotencyKey: 'idem-123', expectedRowVersion: $order->row_version ?? 1);

        $orderItems = ReservationOrderItem::where('order_id', $order->order_id)->get();
        $this->assertCount(3, $orderItems, 'Should create 1 parent and 2 children items');

        $parent = $orderItems->where('item_id', $comboItem)->first();
        $this->assertNotNull($parent);
        $this->assertNull($parent->parent_order_item_id);
        $this->assertEquals(60000, $parent->unit_price);

        $children = $orderItems->where('parent_order_item_id', $parent->order_item_id);
        $this->assertCount(2, $children);
        $this->assertEquals(0, $children->first()->unit_price);

        // ============================================
        // FLOW 2: Dispatch to Kitchen
        // ============================================
        $dispatchAction = app(DispatchKitchenOrderAction::class);
        $dispatchAction->execute($order->order_id, ReservationOrder::find($order->order_id)->row_version, $userId);

        $tickets = KitchenOrderItemTicket::where('order_id', $order->order_id)->get();
        $this->assertCount(2, $tickets, 'Should only create tickets for the 2 child items, not the parent');
        foreach ($tickets as $ticket) {
            $this->assertNotEquals($comboItem, $ticket->item_id);
        }

        // ============================================
        // FLOW 3: Change Status to Served
        // ============================================
        $lifecycleService = app(StaffOrderItemLifecycleService::class);
        $lifecycleService->transitionItemStatus(
            orderId: $order->order_id,
            orderItemId: $parent->order_item_id,
            targetStatus: ReservationOrderItemStatus::Served,
            staffUserId: $userId,
            expectedOrderRowVersion: ReservationOrder::find($order->order_id)->row_version,
            expectedItemRowVersion: $parent->row_version
        );

        $freshChildren = ReservationOrderItem::where('parent_order_item_id', $parent->order_item_id)->get();
        foreach ($freshChildren as $child) {
            $this->assertEquals(ReservationOrderItemStatus::Served->value, $child->status->value, 'Children status should be cascaded');
        }

        // ============================================
        // FLOW 4: Verify Inventory Deduction
        // ============================================
        $movements = IngredientStockMovement::where('ingredient_id', $ingredient->ingredient_id)
            ->where('movement_type', 'StockOut')
            ->get();

        $this->assertCount(1, $movements, 'Should deduct exactly 1 beef patty for the burger component');
        $this->assertEquals(-1, $movements->first()->quantity_delta);

        echo "Combo End-to-End Flow Executed Successfully!\n";
    }

    public function test_combo_component_swap_flow()
    {
        $branchId = 1;
        $userId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation(['status' => 'Reserved', 'branch_id' => $branchId]);
        $this->attachReservationTable($reservationId, $tableId);

        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'created_by' => $userId,
        ]);

        $order = ReservationOrder::find($orderId);

        $category = MenuCategory::create(['name' => 'Test Category']);
        $categoryId = $category->category_id;
        $itemDrink = $this->createMenuItem(['category_id' => $categoryId, 'name' => 'Coke', 'code' => 'COKE']);
        $this->createMenuItemPrice(['item_id' => $itemDrink, 'price' => '20000', 'currency' => 'VND']);

        $itemFood = $this->createMenuItem(['category_id' => $categoryId, 'name' => 'Burger', 'code' => 'BURGER']);
        $this->createMenuItemPrice(['item_id' => $itemFood, 'price' => '50000', 'currency' => 'VND']);

        // The new item to swap to
        $itemMilkTea = $this->createMenuItem(['category_id' => $categoryId, 'name' => 'Milk Tea', 'code' => 'MILKTEA']);
        $this->createMenuItemPrice(['item_id' => $itemMilkTea, 'price' => '35000', 'currency' => 'VND']);

        $comboItem = $this->createMenuItem(['category_id' => $categoryId, 'name' => 'Combo 1', 'code' => 'COMBO1', 'is_combo' => true]);
        $this->createMenuItemPrice(['item_id' => $comboItem, 'price' => '60000', 'currency' => 'VND']);

        MenuItemComboComponent::create(['combo_item_id' => $comboItem, 'component_item_id' => $itemDrink, 'quantity' => 1]);
        MenuItemComboComponent::create(['combo_item_id' => $comboItem, 'component_item_id' => $itemFood, 'quantity' => 1]);

        $orderService = app(StaffTableOrderService::class);
        $orderService->addItems($order->order_id, [
            ['item_id' => $comboItem, 'qty' => 1],
        ], staffUserId: $userId, idempotencyKey: 'idem-swap-1', expectedRowVersion: $order->row_version ?? 1);

        $orderItems = ReservationOrderItem::where('order_id', $order->order_id)->get();
        $drinkChild = $orderItems->where('item_id', $itemDrink)->first();
        $this->assertNotNull($drinkChild);

        $headers = array_merge(
            $this->staffAuthHeaders($userId, 'test-key'),
            ['Idempotency-Key' => 'idem-swap-2']
        );
        $response = $this->postJson("/api/v1/staff/orders/{$order->order_id}/items/{$drinkChild->order_item_id}/component-swap", [
            'new_item_id' => $itemMilkTea,
            'unit_price' => 15000,
        ], $headers);
        $response->assertSuccessful();

        $freshDrinkChild = ReservationOrderItem::find($drinkChild->order_item_id);
        $this->assertEquals($itemMilkTea, $freshDrinkChild->item_id);
        $this->assertEquals('Milk Tea', $freshDrinkChild->item_name_snapshot);
        $this->assertEquals(15000, $freshDrinkChild->unit_price);
        $this->assertEquals(15000, $freshDrinkChild->line_total);
    }
}
