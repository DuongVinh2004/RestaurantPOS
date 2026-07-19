<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class OrderItemInventoryConsumptionFlowTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_served_order_item_consumes_inventory_exactly_once_on_replay(): void
    {
        [$staffId, $orderId, $orderItemId, $branchId, $ingredientId] = $this->seedConsumableOrderItemScenario(
            ingredientCode: 'ING-CONSUME-LADDER',
            stockQuantity: '25.000',
            recipeQuantity: '12.500',
        );

        $headers = $this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId),
            'idem-order-item-inventory-consumption-ladder'
        );
        $payload = [
            'order_row_version' => 1,
            'row_version' => 1,
            'status' => 'Served',
        ];

        $first = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", $payload);
        $replay = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", $payload);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('meta.status', 'Served');
        $replay->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('meta.status', 'Served');

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $itemRowVersion = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version');

        $noop = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId),
            'idem-order-item-inventory-consumption-noop'
        ))->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
            'order_row_version' => $orderRowVersion,
            'row_version' => $itemRowVersion,
            'status' => 'Served',
        ]);

        $noop->assertOk()
            ->assertJsonPath('meta.status', 'Served');

        $movements = $this->table('ingredient_stock_movements')
            ->where('ingredient_id', $ingredientId)
            ->where('reference_type', 'ReservationOrderItemConsumption')
            ->where('reference_id', 'like', $orderItemId.':%')
            ->get();

        self::assertCount(1, $movements);
        self::assertSame($branchId, (int) $movements[0]->branch_id);
        self::assertSame('-12.500', number_format((float) $movements[0]->quantity_delta, 3, '.', ''));
        self::assertSame('12.500', number_format((float) $this->table('ingredient_stock_movements')
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->sum('quantity_delta'), 3, '.', ''));
    }

    public function test_cancelled_order_item_does_not_consume_inventory(): void
    {
        [$staffId, $orderId, $orderItemId, $branchId, $ingredientId] = $this->seedConsumableOrderItemScenario(
            ingredientCode: 'ING-CANCEL-LADDER',
            stockQuantity: '50.000',
            recipeQuantity: '25.000',
        );

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId),
            'idem-order-item-inventory-cancel-ladder'
        ))->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
            'order_row_version' => 1,
            'row_version' => 1,
            'status' => 'Cancelled',
        ]);

        $response->assertOk()
            ->assertJsonPath('meta.status', 'Cancelled');

        self::assertSame(0, (int) $this->table('ingredient_stock_movements')
            ->where('ingredient_id', $ingredientId)
            ->where('reference_type', 'ReservationOrderItemConsumption')
            ->where('reference_id', 'like', $orderItemId.':%')
            ->count());
        self::assertSame('50.000', number_format((float) $this->table('ingredient_stock_movements')
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->sum('quantity_delta'), 3, '.', ''));
    }

    public function test_cancel_after_served_is_denied_without_restocking_consumed_inventory(): void
    {
        [$staffId, $orderId, $orderItemId, $branchId, $ingredientId] = $this->seedConsumableOrderItemScenario(
            ingredientCode: 'ING-SERVED-CANCEL-NO-RESTOCK',
            stockQuantity: '25.000',
            recipeQuantity: '12.500',
        );

        $serve = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId),
            'idem-order-item-served-before-cancel'
        ))->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
            'order_row_version' => 1,
            'row_version' => 1,
            'status' => 'Served',
        ]);

        $serve->assertOk()
            ->assertJsonPath('meta.status', 'Served');

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $itemRowVersion = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version');

        $cancel = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId),
            'idem-order-item-cancel-after-served-no-restock'
        ))->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
            'order_row_version' => $orderRowVersion,
            'row_version' => $itemRowVersion,
            'status' => 'Cancelled',
        ]);

        $cancel->assertStatus(422)
            ->assertJsonPath('details.errors.status.0', 'Cannot transition order item from Served to Cancelled.');

        self::assertSame('Served', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('status'));
        self::assertSame(1, (int) $this->table('ingredient_stock_movements')
            ->where('ingredient_id', $ingredientId)
            ->where('reference_type', 'ReservationOrderItemConsumption')
            ->where('reference_id', 'like', $orderItemId.':%')
            ->count());
        self::assertSame('12.500', number_format((float) $this->table('ingredient_stock_movements')
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->sum('quantity_delta'), 3, '.', ''));
    }

    /**
     * @return array{0:int,1:int,2:int,3:int,4:int}
     */
    private function seedConsumableOrderItemScenario(
        string $ingredientCode,
        string $stockQuantity,
        string $recipeQuantity,
    ): array {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
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
        $itemId = $this->createMenuItem();
        $ingredientId = $this->createIngredient([
            'code' => $ingredientCode,
            'name' => $ingredientCode.' Ingredient',
            'unit_code' => 'g',
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'quantity' => $recipeQuantity,
            'unit_code' => 'g',
            'sort_order' => 1,
        ]);
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'unit_price' => '50000',
            'currency' => 'VND',
            'line_total' => '50000',
            'status' => 'Ordered',
            'row_version' => 1,
        ]);
        $branchId = (int) $this->table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => $stockQuantity,
            'unit_code' => 'g',
            'reference_type' => 'manual_count',
            'reference_id' => $ingredientCode.'-seed',
        ]);

        return [$staffId, $orderId, $orderItemId, $branchId, $ingredientId];
    }

    private function table(string $table)
    {
        return DB::table($table);
    }
}
