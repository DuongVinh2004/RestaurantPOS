<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\ReservationOrderItemStatus;
use App\Modules\KitchenDispatch\Application\Actions\DispatchKitchenOrderAction;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenRoutingService;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenTicketConsistencyInspector;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenTicketReconciliationService;
use App\Modules\KitchenDispatch\Domain\Models\KitchenOrderItemTicket;
use App\Modules\Ordering\Application\UseCases\OrderItems\StaffOrderItemLifecycleService;
use App\Modules\Ordering\Application\UseCases\Orders\StaffTableOrderService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class KdsComponentSubstitutionSafetyTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_available_same_category_component_can_be_swapped_before_dispatch(): void
    {
        $scenario = $this->createComboScenario();

        $this->withHeaders($this->withIdempotencyKey($scenario['headers'], 'idem-b11-swap-before-dispatch'))
            ->postJson(sprintf(
                '/api/v1/staff/orders/%d/items/%d/component-swap',
                $scenario['order_id'],
                $scenario['component_order_item_id'],
            ), [
                'new_item_id' => $scenario['replacement_item_id'],
            ])
            ->assertOk()
            ->assertJsonPath('meta.action', 'order_item_component_swapped');

        $component = DB::table('reservation_order_items')
            ->where('order_item_id', $scenario['component_order_item_id'])
            ->first();

        self::assertSame($scenario['replacement_item_id'], (int) $component->item_id);
        self::assertSame('B11 Replacement Component', (string) $component->item_name_snapshot);
        self::assertSame(0, DB::table('kitchen_order_item_tickets')
            ->where('order_item_id', $scenario['component_order_item_id'])
            ->count());
    }

    public function test_pre_dispatch_swap_rejects_unavailable_and_cross_category_targets_without_mutation(): void
    {
        $scenario = $this->createComboScenario();
        $otherCategoryId = $this->ensureMenuCategory('B11 Other Component Category');
        $unavailableItemId = $this->createMenuItem([
            'category_id' => $scenario['category_id'],
            'name' => 'B11 Unavailable Component',
            'is_available' => 0,
        ]);
        $crossCategoryItemId = $this->createMenuItem([
            'category_id' => $otherCategoryId,
            'name' => 'B11 Cross Category Component',
            'is_available' => 1,
        ]);
        $before = (array) DB::table('reservation_order_items')
            ->where('order_item_id', $scenario['component_order_item_id'])
            ->first();

        foreach ([
            'unavailable' => $unavailableItemId,
            'cross-category' => $crossCategoryItemId,
        ] as $case => $targetItemId) {
            $this->withHeaders($this->withIdempotencyKey($scenario['headers'], 'idem-b11-invalid-'.$case))
                ->postJson(sprintf(
                    '/api/v1/staff/orders/%d/items/%d/component-swap',
                    $scenario['order_id'],
                    $scenario['component_order_item_id'],
                ), [
                    'new_item_id' => $targetItemId,
                ])
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_error')
                ->assertJsonValidationErrors(['new_item_id']);

            self::assertSame($before, (array) DB::table('reservation_order_items')
                ->where('order_item_id', $scenario['component_order_item_id'])
                ->first());
        }
    }

    #[DataProvider('dispatchedTicketStateProvider')]
    public function test_component_swap_after_dispatch_or_fire_is_denied_and_original_recipe_is_consumed(string $ticketState): void
    {
        $scenario = $this->createComboScenario(withRoute: true, withRecipes: true);
        $dispatch = app(DispatchKitchenOrderAction::class)->execute(
            $scenario['order_id'],
            (int) ReservationOrder::query()->findOrFail($scenario['order_id'])->row_version,
            $scenario['staff_user_id'],
        );
        /** @var KitchenOrderItemTicket $ticket */
        $ticket = $dispatch['tickets']->first();
        self::assertInstanceOf(KitchenOrderItemTicket::class, $ticket);

        if ($ticketState === 'fired') {
            $ticket = app(KitchenRoutingService::class)->fireTicket(
                (int) $ticket->ticket_id,
                (int) $ticket->row_version,
                $scenario['staff_user_id'],
            );
        }

        $before = [
            'item' => (array) DB::table('reservation_order_items')
                ->where('order_item_id', $scenario['component_order_item_id'])
                ->first(),
            'ticket' => (array) DB::table('kitchen_order_item_tickets')
                ->where('ticket_id', $ticket->ticket_id)
                ->first(),
            'stock_movements' => DB::table('ingredient_stock_movements')->orderBy('movement_id')->get()
                ->map(static fn ($row): array => (array) $row)->all(),
            'audit_count' => DB::table('audit_logs')->count(),
        ];

        $this->withHeaders($this->withIdempotencyKey($scenario['headers'], 'idem-b11-swap-after-'.$ticketState))
            ->postJson(sprintf(
                '/api/v1/staff/orders/%d/items/%d/component-swap',
                $scenario['order_id'],
                $scenario['component_order_item_id'],
            ), [
                'new_item_id' => $scenario['replacement_item_id'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonValidationErrors(['order_item_id']);

        $after = [
            'item' => (array) DB::table('reservation_order_items')
                ->where('order_item_id', $scenario['component_order_item_id'])
                ->first(),
            'ticket' => (array) DB::table('kitchen_order_item_tickets')
                ->where('ticket_id', $ticket->ticket_id)
                ->first(),
            'stock_movements' => DB::table('ingredient_stock_movements')->orderBy('movement_id')->get()
                ->map(static fn ($row): array => (array) $row)->all(),
            'audit_count' => DB::table('audit_logs')->count(),
        ];
        self::assertSame($before, $after);

        app(StaffOrderItemLifecycleService::class)->transitionItemStatus(
            orderId: $scenario['order_id'],
            orderItemId: $scenario['component_order_item_id'],
            targetStatus: ReservationOrderItemStatus::Served,
            staffUserId: $scenario['staff_user_id'],
            expectedOrderRowVersion: (int) DB::table('reservation_orders')
                ->where('order_id', $scenario['order_id'])
                ->value('row_version'),
            expectedItemRowVersion: (int) DB::table('reservation_order_items')
                ->where('order_item_id', $scenario['component_order_item_id'])
                ->value('row_version'),
        );

        self::assertSame($scenario['original_item_id'], (int) DB::table('reservation_order_items')
            ->where('order_item_id', $scenario['component_order_item_id'])
            ->value('item_id'));
        self::assertSame($scenario['original_item_id'], (int) DB::table('kitchen_order_item_tickets')
            ->where('ticket_id', $ticket->ticket_id)
            ->value('item_id'));
        self::assertSame('-1.000', number_format((float) DB::table('ingredient_stock_movements')
            ->where('ingredient_id', $scenario['original_ingredient_id'])
            ->where('movement_type', 'StockOut')
            ->sum('quantity_delta'), 3, '.', ''));
        self::assertSame(0, DB::table('ingredient_stock_movements')
            ->where('ingredient_id', $scenario['replacement_ingredient_id'])
            ->where('movement_type', 'StockOut')
            ->count());
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function dispatchedTicketStateProvider(): array
    {
        return [
            'queued after dispatch' => ['queued'],
            'in progress after fire' => ['fired'],
        ];
    }

    public function test_consistency_inspector_reports_item_category_route_and_station_snapshot_drift(): void
    {
        $scenario = $this->createComboScenario(withRoute: true);
        $dispatch = app(DispatchKitchenOrderAction::class)->execute(
            $scenario['order_id'],
            (int) ReservationOrder::query()->findOrFail($scenario['order_id'])->row_version,
            $scenario['staff_user_id'],
        );
        /** @var KitchenOrderItemTicket $ticket */
        $ticket = $dispatch['tickets']->first();
        $otherCategoryId = $this->ensureMenuCategory('B11 Drift Category');
        $otherItemId = $this->createMenuItem([
            'category_id' => $otherCategoryId,
            'name' => 'B11 Drift Item',
        ]);

        DB::table('reservation_order_items')
            ->where('order_item_id', $scenario['component_order_item_id'])
            ->update([
                'item_id' => $otherItemId,
                'item_name_snapshot' => 'B11 Drift Item',
                'row_version' => DB::raw('row_version + 1'),
                'updated_at' => $this->nowUtc(),
            ]);

        $ticket = KitchenOrderItemTicket::query()
            ->with(['station', 'route', 'orderItem.item'])
            ->findOrFail($ticket->ticket_id);
        $inspection = app(KitchenTicketConsistencyInspector::class)->describe($ticket);

        self::assertSame('drift_detected', data_get($inspection, 'reconciliation.sync_status'));
        self::assertFalse(data_get($inspection, 'reconciliation.item_matches_order_item'));
        self::assertFalse(data_get($inspection, 'reconciliation.category_matches_order_item'));
        self::assertTrue(data_get($inspection, 'reconciliation.route_matches_category'));
        self::assertTrue(data_get($inspection, 'reconciliation.station_matches_route'));
        self::assertContains('ticket_item_snapshot_mismatch', data_get($inspection, 'reconciliation.drift_reasons', []));
        self::assertContains('ticket_category_snapshot_mismatch', data_get($inspection, 'reconciliation.drift_reasons', []));

        $otherStationId = $this->createKitchenStation(['branch_id' => 1, 'name' => 'B11 Wrong Snapshot Station']);
        DB::table('kitchen_order_item_tickets')
            ->where('ticket_id', $ticket->ticket_id)
            ->update([
                'category_id' => $otherCategoryId,
                'station_id' => $otherStationId,
                'updated_at' => $this->nowUtc(),
            ]);

        $ticket = KitchenOrderItemTicket::query()
            ->with(['station', 'route', 'orderItem.item'])
            ->findOrFail($ticket->ticket_id);
        $inspection = app(KitchenTicketConsistencyInspector::class)->describe($ticket);

        self::assertSame('route_category_mismatch', data_get($inspection, 'reconciliation.routing_status'));
        self::assertFalse(data_get($inspection, 'reconciliation.item_matches_order_item'));
        self::assertTrue(data_get($inspection, 'reconciliation.category_matches_order_item'));
        self::assertFalse(data_get($inspection, 'reconciliation.route_matches_category'));
        self::assertFalse(data_get($inspection, 'reconciliation.station_matches_route'));
        self::assertContains('route_category_snapshot_mismatch', data_get($inspection, 'reconciliation.drift_reasons', []));
        self::assertContains('station_route_snapshot_mismatch', data_get($inspection, 'reconciliation.drift_reasons', []));

        $report = app(KitchenTicketReconciliationService::class)->scan(['branch_id' => 1]);
        self::assertSame(1, $report['drift_count']);
        self::assertSame('route_category_mismatch', data_get($report, 'tickets.0.routing_status'));
    }

    /**
     * @return array{
     *     branch_id:int,
     *     staff_user_id:int,
     *     headers:array<string,string>,
     *     order_id:int,
     *     category_id:int,
     *     original_item_id:int,
     *     replacement_item_id:int,
     *     component_order_item_id:int,
     *     original_ingredient_id:int|null,
     *     replacement_ingredient_id:int|null
     * }
     */
    private function createComboScenario(bool $withRoute = false, bool $withRecipes = false): array
    {
        $branchId = 1;
        $staffUserId = $this->createUser(['role_name' => 'Manager']);
        $tableId = $this->createRestaurantTable(['branch_id' => $branchId, 'status' => 'Occupied']);
        $reservationId = $this->createReservation(['branch_id' => $branchId, 'status' => 'Reserved']);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'created_by' => $staffUserId,
        ]);
        $categoryId = $this->ensureMenuCategory('B11 Component Category');
        $originalItemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'B11 Original Component',
            'is_available' => 1,
            'is_combo' => 0,
        ]);
        $replacementItemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'B11 Replacement Component',
            'is_available' => 1,
            'is_combo' => 0,
        ]);
        $comboItemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'B11 Combo',
            'is_available' => 1,
            'is_combo' => 1,
        ]);
        $this->createMenuItemPrice(['item_id' => $originalItemId, 'price' => '10000', 'currency' => 'VND']);
        $this->createMenuItemPrice(['item_id' => $replacementItemId, 'price' => '12000', 'currency' => 'VND']);
        $this->createMenuItemPrice(['item_id' => $comboItemId, 'price' => '20000', 'currency' => 'VND']);
        DB::table('menu_item_combo_components')->insert([
            'combo_item_id' => $comboItemId,
            'component_item_id' => $originalItemId,
            'quantity' => 1,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        if ($withRoute) {
            $stationId = $this->createKitchenStation(['branch_id' => $branchId]);
            $this->createKitchenStationRoute([
                'station_id' => $stationId,
                'branch_id' => $branchId,
                'category_id' => $categoryId,
            ]);
        }

        $originalIngredientId = null;
        $replacementIngredientId = null;
        if ($withRecipes) {
            $originalIngredientId = $this->createIngredient(['name' => 'B11 Original Ingredient', 'unit_code' => 'unit']);
            $replacementIngredientId = $this->createIngredient(['name' => 'B11 Replacement Ingredient', 'unit_code' => 'unit']);
            $this->createMenuItemRecipeLine([
                'item_id' => $originalItemId,
                'ingredient_id' => $originalIngredientId,
                'quantity' => '1.000',
                'unit_code' => 'unit',
            ]);
            $this->createMenuItemRecipeLine([
                'item_id' => $replacementItemId,
                'ingredient_id' => $replacementIngredientId,
                'quantity' => '1.000',
                'unit_code' => 'unit',
            ]);
            $this->createIngredientStockMovement([
                'ingredient_id' => $originalIngredientId,
                'branch_id' => $branchId,
                'quantity_delta' => '10.000',
                'unit_code' => 'unit',
                'reference_type' => 'B11Seed',
                'reference_id' => 'original',
            ]);
            $this->createIngredientStockMovement([
                'ingredient_id' => $replacementIngredientId,
                'branch_id' => $branchId,
                'quantity_delta' => '10.000',
                'unit_code' => 'unit',
                'reference_type' => 'B11Seed',
                'reference_id' => 'replacement',
            ]);
        }

        app(StaffTableOrderService::class)->addItems(
            $orderId,
            [['item_id' => $comboItemId, 'qty' => 1]],
            staffUserId: $staffUserId,
            idempotencyKey: 'idem-b11-add-combo-'.bin2hex(random_bytes(4)),
            expectedRowVersion: 1,
        );
        /** @var ReservationOrderItem $component */
        $component = ReservationOrderItem::query()
            ->where('order_id', $orderId)
            ->where('item_id', $originalItemId)
            ->whereNotNull('parent_order_item_id')
            ->firstOrFail();

        return [
            'branch_id' => $branchId,
            'staff_user_id' => $staffUserId,
            'headers' => $this->staffAuthHeaders($staffUserId, 'b11-kds-substitution-key'),
            'order_id' => $orderId,
            'category_id' => $categoryId,
            'original_item_id' => $originalItemId,
            'replacement_item_id' => $replacementItemId,
            'component_order_item_id' => (int) $component->order_item_id,
            'original_ingredient_id' => $originalIngredientId,
            'replacement_ingredient_id' => $replacementIngredientId,
        ];
    }
}
