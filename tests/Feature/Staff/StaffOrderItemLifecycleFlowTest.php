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

    public function test_staff_can_update_order_item_quantity_and_note(): void
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
            ->assertJsonPath('data.items.0.notes', 'extra spicy');

        self::assertSame(3, (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('quantity'));
        self::assertSame('extra spicy', (string) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('notes'));
        self::assertSame($staffId, (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('updated_by'));
        self::assertSame(
            (int) $this->table('reservation_order_items')->where('order_item_id', $orderItemId)->value('row_version'),
            (int) $response->json('data.items.0.row_version')
        );
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

    public function test_serving_item_consumes_inventory_once_from_recipe_lines(): void
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

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-consume-served'))
            ->postJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}/status", [
                'order_row_version' => 1,
                'row_version' => 1,
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

    public function test_stale_item_row_version_is_rejected(): void
    {
        [$staffId, $orderId, $orderItemId] = $this->seedOrderItemScenario();

        $response = $this->withHeaders($this->withIdempotencyKey($this->staffAuthHeaders($staffId), 'idem-order-item-stale'))
            ->patchJson("/api/v1/staff/orders/{$orderId}/items/{$orderItemId}", [
                'order_row_version' => 1,
                'row_version' => 99,
                'qty' => 2,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('details.errors.row_version.0', 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.');
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

    /**
     * @param  array<string,mixed>  $itemOverrides
     * @return array{0:int,1:int,2:int}
     */
    private function seedOrderItemScenario(array $itemOverrides = []): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
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
        $orderItemId = $this->createOrderItem(array_merge([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '50000.00',
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
