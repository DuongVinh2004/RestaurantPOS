<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffOrderItemLifecycleFlowTest extends TestCase
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

    public function test_staff_can_update_order_item_quantity_and_line_total_recalculates(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-update-1'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 1,
                'row_version' => 1,
                'qty' => 3,
                'note' => 'extra spicy',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'order_item_updated')
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.items.0.order_item_id', $orderItemId)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items.0.line_total', '150000')
            ->assertJsonPath('data.items.0.notes', 'extra spicy');

        self::assertSame(3, (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('quantity'));
        self::assertSame('150000', number_format((float) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('line_total'), 0, '.', ''));
        self::assertSame('extra spicy', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('notes'));
        self::assertSame($staffId, (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('updated_by'));
        self::assertSame(
            (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version'),
            (int) $response->json('data.items.0.row_version')
        );

        $audit = $this->table('audit_logs')
            ->where('action', 'order_item.updated')
            ->where('entity_type', 'reservation_order_item')
            ->where('entity_id', (string) $orderItemId)
            ->orderByDesc('audit_id')
            ->first();
        self::assertNotNull($audit);

        $summary = json_decode((string) $audit->summary_json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($orderId, (int) $summary['order_id']);
        self::assertSame($orderItemId, (int) $summary['order_item_id']);
        self::assertSame(1, (int) $summary['old_quantity']);
        self::assertSame(3, (int) $summary['new_quantity']);
        self::assertSame('50000', (string) $summary['unit_price']);
        self::assertSame('50000', (string) $summary['old_line_total']);
        self::assertSame('150000', (string) $summary['new_line_total']);
        self::assertSame('VND', (string) $summary['currency']);
        self::assertSame($staffId, (int) $summary['actor_user_id']);
        self::assertSame(1, (int) $summary['branch_id']);
    }

    public function test_updating_notes_only_does_not_change_line_total(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario([
            'quantity' => 2,
            'unit_price' => '50000',
            'line_total' => '100000',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-note-only'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 1,
                'row_version' => 1,
                'note' => 'hold onions',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.line_total', '100000')
            ->assertJsonPath('data.items.0.notes', 'hold onions');

        self::assertSame('100000', number_format((float) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('line_total'), 0, '.', ''));
    }

    public function test_bill_snapshot_uses_updated_order_item_line_total(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario(staffRoleName: 'Manager');

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-bill-qty-update'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 1,
                'row_version' => 1,
                'qty' => 3,
            ])
            ->assertOk();

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-bill-snapshot'))
            ->postJson("/api/v1/staff/orders/{$orderId}/bill-snapshot", [
                'row_version' => $orderRowVersion,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.totals.subtotal', '150000')
            ->assertJsonPath('data.totals.total_due', '150000');

        $reservationId = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('reservation_id');
        self::assertSame('150000', number_format((float) $this->table('reservations')->where('reservation_id', $reservationId)->value('final_bill_amount'), 0, '.', ''));
    }

    public function test_payment_amount_uses_updated_bill_total(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario(staffRoleName: 'Manager');
        $reservationId = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('reservation_id');
        $branchId = (int) $this->table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
        ]);

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-pay-qty-update'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 1,
                'row_version' => 1,
                'qty' => 3,
            ])
            ->assertOk();

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-pay-updated-total'))
            ->postJson("/api/v1/staff/orders/{$orderId}/pay", [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'paid_amount' => 50000,
                'currency' => 'VND',
                'transaction_code' => 'ORDER-ITEM-UPDATED-TOTAL-PARTIAL',
                'row_version' => $orderRowVersion,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.total_amount', 150000)
            ->assertJsonPath('data.paid_amount', 50000)
            ->assertJsonPath('data.outstanding_amount', 100000)
            ->assertJsonPath('data.payment_status', 'Partial');

        self::assertSame('50000', number_format((float) $this->table('payments')->where('reservation_id', $reservationId)->where('payment_type', 'Final')->value('amount'), 0, '.', ''));
        self::assertSame('Reserved', (string) $this->table('reservations')->where('reservation_id', $reservationId)->value('status'));
    }

    public function test_served_item_inventory_quantity_matches_billed_updated_quantity(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario(staffRoleName: 'Manager');
        $itemId = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('item_id');
        $reservationId = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('reservation_id');
        $branchId = (int) $this->table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-UPDATED-QTY-BILL',
            'name' => 'Updated Quantity Billing Ingredient',
            'unit_code' => 'g',
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'quantity' => '2.500',
            'unit_code' => 'g',
            'sort_order' => 1,
        ]);
        $itemRowVersion = $this->refreshOrderItemRecipeSnapshot($orderItemId);
        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '10.000',
            'unit_code' => 'g',
        ]);

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-inventory-qty-update'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 1,
                'row_version' => $itemRowVersion,
                'qty' => 3,
            ])
            ->assertOk();

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $itemRowVersion = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version');

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-inventory-served-updated-qty'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => $orderRowVersion,
                'row_version' => $itemRowVersion,
                'status' => 'Served',
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.status', 'Served')
            ->assertJsonPath('data.items.0.line_total', '150000');

        self::assertSame('-7.500', number_format((float) $this->table('ingredient_stock_movements')
            ->where('ingredient_id', $ingredientId)
            ->where('reference_type', 'ReservationOrderItemConsumption')
            ->where('reference_id', 'like', $orderItemId.':%')
            ->value('quantity_delta'), 3, '.', ''));

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $bill = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-inventory-bill-updated-qty'))
            ->postJson("/api/v1/staff/orders/{$orderId}/bill-snapshot", [
                'row_version' => $orderRowVersion,
            ]);

        $bill
            ->assertOk()
            ->assertJsonPath('data.totals.subtotal', '150000')
            ->assertJsonPath('data.totals.total_due', '150000');
    }

    public function test_cancelled_item_line_total_is_excluded_from_bill(): void
    {
        [$staffId, $orderId] = $this->seedOrderItemScenario(staffRoleName: 'Manager');
        $cancelledOrderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '20000',
            'currency' => 'VND',
            'line_total' => '40000',
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-cancel-excluded'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$cancelledOrderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => 1,
                'status' => 'Cancelled',
            ])
            ->assertOk();

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $bill = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-cancel-excluded-bill'))
            ->postJson("/api/v1/staff/orders/{$orderId}/bill-snapshot", [
                'row_version' => $orderRowVersion,
            ]);

        $bill
            ->assertOk()
            ->assertJsonPath('data.totals.subtotal', '50000')
            ->assertJsonPath('data.totals.total_due', '50000');
    }

    public function test_staff_can_move_item_from_ordered_to_in_progress_to_served(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $first = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-status-progress'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => 1,
                'status' => 'InProgress',
            ]);

        $first
            ->assertOk()
            ->assertJsonPath('meta.action', 'order_item_status_updated')
            ->assertJsonPath('meta.status', 'InProgress')
            ->assertJsonPath('data.items.0.status', 'InProgress');

        self::assertSame(
            (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version'),
            (int) $first->json('data.items.0.row_version')
        );

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $itemRowVersion = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version');

        $second = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-status-served'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => $orderRowVersion,
                'row_version' => $itemRowVersion,
                'status' => 'Served',
            ]);

        $second
            ->assertOk()
            ->assertJsonPath('meta.status', 'Served')
            ->assertJsonPath('data.items.0.status', 'Served');

        self::assertSame('Served', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('status'));
        self::assertSame(
            (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version'),
            (int) $second->json('data.items.0.row_version')
        );
    }

    public function test_served_item_recipe_consumption_rejects_when_any_ingredient_insufficient(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $itemId = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('item_id');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-CONSUME-INSUFFICIENT',
            'name' => 'Insufficient Chili',
            'unit_code' => 'g',
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'quantity' => '12.500',
            'unit_code' => 'g',
            'sort_order' => 1,
        ]);
        $itemRowVersion = $this->refreshOrderItemRecipeSnapshot($orderItemId);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-consume-insufficient'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => $itemRowVersion,
                'status' => 'Served',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        self::assertSame('Ordered', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('status'));
        self::assertSame(0, (int) $this->table('ingredient_stock_movements')
            ->where('ingredient_id', $ingredientId)
            ->where('reference_type', 'ReservationOrderItemConsumption')
            ->count());
    }

    public function test_served_item_recipe_consumption_is_atomic_no_partial_movements(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $itemId = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('item_id');
        $reservationId = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('reservation_id');
        $branchId = (int) $this->table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $riceIngredientId = $this->createIngredient([
            'code' => 'ING-CONSUME-ATOMIC-RICE',
            'name' => 'Atomic Rice',
            'unit_code' => 'g',
        ]);
        $brothIngredientId = $this->createIngredient([
            'code' => 'ING-CONSUME-ATOMIC-BROTH',
            'name' => 'Atomic Broth',
            'unit_code' => 'ml',
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $riceIngredientId,
            'quantity' => '5.000',
            'unit_code' => 'g',
            'sort_order' => 1,
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $brothIngredientId,
            'quantity' => '10.000',
            'unit_code' => 'ml',
            'sort_order' => 2,
        ]);
        $itemRowVersion = $this->refreshOrderItemRecipeSnapshot($orderItemId);
        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $riceIngredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '5.000',
            'unit_code' => 'g',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-consume-atomic'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => $itemRowVersion,
                'status' => 'Served',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        self::assertSame('Ordered', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('status'));
        self::assertSame(0, (int) $this->table('ingredient_stock_movements')
            ->whereIn('ingredient_id', [$riceIngredientId, $brothIngredientId])
            ->where('reference_type', 'ReservationOrderItemConsumption')
            ->count());
        self::assertSame('5.000', number_format((float) $this->table('ingredient_stock_movements')
            ->where('ingredient_id', $riceIngredientId)
            ->sum('quantity_delta'), 3, '.', ''));
    }

    public function test_served_item_consumes_stock_once_on_retry_or_status_noop(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $itemId = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('item_id');
        $reservationId = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('reservation_id');
        $branchId = (int) $this->table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-CONSUME-01',
            'name' => 'Consumed Chili',
            'unit_code' => 'g',
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'quantity' => '12.500',
            'unit_code' => 'g',
            'sort_order' => 1,
        ]);
        $itemRowVersion = $this->refreshOrderItemRecipeSnapshot($orderItemId);
        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '25.000',
            'unit_code' => 'g',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-consume-served'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => $itemRowVersion,
                'status' => 'Served',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.status', 'Served');

        $movements = $this->table('ingredient_stock_movements')
            ->where('ingredient_id', $ingredientId)
            ->where('reference_type', 'ReservationOrderItemConsumption')
            ->orderBy('movement_id')
            ->get();

        self::assertCount(1, $movements);
        self::assertSame($branchId, (int) $movements[0]->branch_id);
        self::assertSame('-12.500', number_format((float) $movements[0]->quantity_delta, 3, '.', ''));

        $orderRowVersion = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('row_version');
        $itemRowVersion = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version');

        $noop = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-consume-served-noop'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => $orderRowVersion,
                'row_version' => $itemRowVersion,
                'status' => 'Served',
            ]);

        $noop->assertOk()
            ->assertJsonPath('meta.status', 'Served');

        self::assertSame(1, (int) $this->table('ingredient_stock_movements')
            ->where('ingredient_id', $ingredientId)
            ->where('reference_type', 'ReservationOrderItemConsumption')
            ->count());
    }

    public function test_serving_item_rejects_drifted_inventory_consumption_reference(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $itemId = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('item_id');
        $reservationId = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('reservation_id');
        $branchId = (int) $this->table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-CONSUME-DRIFT',
            'name' => 'Consumed Drift Chili',
            'unit_code' => 'g',
        ]);
        $recipeLineId = $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'quantity' => '12.500',
            'unit_code' => 'g',
            'sort_order' => 1,
        ]);
        $itemRowVersion = $this->refreshOrderItemRecipeSnapshot($orderItemId);
        $referenceId = $orderItemId.':'.$recipeLineId.':'.$ingredientId;

        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockOut',
            'quantity_delta' => '-1.000',
            'unit_code' => 'g',
            'reference_type' => 'ReservationOrderItemConsumption',
            'reference_id' => $referenceId,
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-consume-drift'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => $itemRowVersion,
                'status' => 'Served',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reference_id'])
            ->assertJsonPath('details.errors.reference_id.0', 'System stock movement reference [ReservationOrderItemConsumption:'.$referenceId.'] is already recorded with different movement details.');

        self::assertSame('Ordered', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('status'));
        self::assertSame(1, (int) $this->table('ingredient_stock_movements')
            ->where('ingredient_id', $ingredientId)
            ->where('reference_type', 'ReservationOrderItemConsumption')
            ->where('reference_id', $referenceId)
            ->count());
    }

    public function test_staff_can_cancel_unserved_item(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-status-cancel'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => 1,
                'status' => 'Cancelled',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.status', 'Cancelled')
            ->assertJsonPath('data.items.0.status', 'Cancelled');

        self::assertSame('Cancelled', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('status'));
    }

    public function test_cancelled_order_item_does_not_consume_recipe_stock(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $itemId = (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('item_id');
        $reservationId = (int) $this->table('reservation_orders')->where('order_id', $orderId)->value('reservation_id');
        $branchId = (int) $this->table('reservations')->where('reservation_id', $reservationId)->value('branch_id');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-CANCEL-NO-CONSUME',
            'name' => 'Cancelled No Consume Rice',
            'unit_code' => 'g',
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'quantity' => '25.000',
            'unit_code' => 'g',
            'sort_order' => 1,
        ]);
        $itemRowVersion = $this->refreshOrderItemRecipeSnapshot($orderItemId);
        $this->createIngredientStockMovement([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '50.000',
            'unit_code' => 'g',
            'reference_type' => 'manual_count',
            'reference_id' => 'cancel-no-consume-seed',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-cancel-no-consume'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => $itemRowVersion,
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

    public function test_stale_row_version_still_fails_and_does_not_change_line_total(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-stale'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 1,
                'row_version' => 99,
                'qty' => 2,
            ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('category_code', 'stale_write')
            ->assertJsonPath('details.errors.row_version.0', 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.');
        self::assertSame(1, (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('quantity'));
        self::assertSame('50000', number_format((float) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('line_total'), 0, '.', ''));
    }

    public function test_stale_order_row_version_is_rejected_before_order_item_mutation(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-row-stale'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 99,
                'row_version' => 1,
                'qty' => 2,
            ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('category_code', 'stale_write')
            ->assertJsonPath('details.errors.order_row_version.0', 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.');
    }

    public function test_order_item_mutation_rejects_branch_outside_staff_operational_scope(): void
    {
        $annexBranchId = $this->createBranch([
            'branch_code' => 'ITEMANNEX',
            'branch_name' => 'Item Annex',
        ]);
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario(
            tableOverrides: [
                'branch_id' => $annexBranchId,
            ],
            reservationOverrides: [
                'branch_id' => $annexBranchId,
            ],
        );

        $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-out-of-branch'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 1,
                'row_version' => 1,
                'qty' => 2,
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        self::assertSame(1, (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('quantity'));
    }

    public function test_served_item_cannot_be_edited_or_cancelled(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario([
            'status' => 'Served',
        ]);

        $update = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-served-update'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 1,
                'row_version' => 1,
                'qty' => 5,
            ]);

        $update
            ->assertStatus(422)
            ->assertJsonPath('details.errors.order_item_id.0', 'Served or cancelled items can no longer be edited.');

        $status = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-served-cancel'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => 1,
                'status' => 'Cancelled',
            ]);

        $status
            ->assertStatus(422)
            ->assertJsonPath('details.errors.status.0', 'Cannot transition order item from Served to Cancelled.');
    }

    public function test_bill_locked_order_item_mutations_are_rejected_deterministically(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario(
            itemOverrides: [],
            reservationOverrides: [
                'final_bill_amount' => '50000',
                'billed_at' => $this->nowUtc(),
            ],
        );

        $update = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-bill-lock-update'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 1,
                'row_version' => 1,
                'qty' => 2,
            ]);

        $update
            ->assertStatus(422)
            ->assertJsonPath('details.errors.reservation_id.0', 'Reservation bill has already been closed for payment. Reopen the bill before modifying order items.');

        $status = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-bill-lock-status'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => 1,
                'status' => 'Cancelled',
            ]);

        $status
            ->assertStatus(422)
            ->assertJsonPath('details.errors.reservation_id.0', 'Reservation bill has already been closed for payment. Reopen the bill before modifying order items.');
    }

    /**
     * @param  array<string,mixed>  $itemOverrides
     * @param  array<string,mixed>  $reservationOverrides
     * @param  array<string,mixed>  $tableOverrides
     * @return array{0:int,1:int,2:int}
     */
    private function seedOrderItemScenario(array $itemOverrides = [], array $reservationOverrides = [], array $tableOverrides = [], string $staffRoleName = 'Staff'): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => $staffRoleName]);
        $tableId = $this->createRestaurantTable(array_merge(['status' => 'Occupied'], $tableOverrides));
        $reservationId = $this->createReservation(array_merge([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0',
            'deposit_paid_amount' => '0',
            'deposit_status' => 'NotRequired',
            'bill_currency' => 'VND',
        ], $reservationOverrides));
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $itemId = $this->createMenuItem();
        $orderItemId = $this->createOrderItem(array_merge([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'unit_price' => '50000',
            'currency' => 'VND',
            'line_total' => '50000',
            'status' => 'Ordered',
            'row_version' => 1,
        ], $itemOverrides));

        return [$staffId, $orderId, $orderItemId];
    }

    private function table(string $table)
    {
        return DB::table($table);
    }
}
