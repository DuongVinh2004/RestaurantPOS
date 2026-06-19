<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;
use Throwable;

#[Group('runtime-smoke')]
final class RuntimeMysqlRedisSmokeTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertRuntimeServicesAvailable();
        $this->flushRuntimeRedis();
    }

    protected function tearDown(): void
    {
        if (app()->environment('testing')) {
            try {
                $this->useRuntimeRedisCache();
                Cache::store('redis')->flush();
            } catch (Throwable) {
                // Avoid hiding the original test failure during cleanup.
            }
        }

        parent::tearDown();
    }

    public function test_walk_in_service_session_contends_on_same_table_with_mysql_and_redis_locks(): void
    {
        $branchId = $this->createRuntimeOpenBranch();
        $firstStaffId = $this->createUser(['role_name' => 'Staff']);
        $secondStaffId = $this->createUser(['role_name' => 'Staff']);
        $this->assignRuntimeStaffBranch($firstStaffId, $branchId);
        $this->assignRuntimeStaffBranch($secondStaffId, $branchId);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'email' => 'runtime.walkin.customer@example.test',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'table_code' => 'RT-WALKIN-01',
            'status' => 'Available',
        ]);
        $startedAt = $this->nowUtc()->copy()->addMinutes(45)->startOfMinute();
        $payload = [
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'table_ids' => [$tableId],
            'guest_count' => 2,
            'started_at' => $startedAt->toIso8601String(),
            'service_minutes' => 75,
        ];

        $this->useRuntimeRedisCache();

        $first = $this->postJson(
            '/api/v1/staff/service-sessions/walk-in',
            $payload,
            $this->withIdempotencyKey($this->staffAuthHeaders($firstStaffId, 'runtime-walkin-first'), 'runtime-walkin-first'),
        );
        $second = $this->postJson(
            '/api/v1/staff/service-sessions/walk-in',
            $payload,
            $this->withIdempotencyKey($this->staffAuthHeaders($secondStaffId, 'runtime-walkin-second'), 'runtime-walkin-second'),
        );

        $first->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false');
        $second->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        self::assertSame(1, (int) DB::table('reservation_tables')->where('table_id', $tableId)->count());
        self::assertSame('Occupied', (string) DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_duplicate_order_item_mutation_replays_through_redis_without_duplicate_rows(): void
    {
        [$staffId, $orderId, $menuItemId] = $this->seedRuntimeActiveOrderWithMenu();
        $headers = $this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'runtime-order-item-replay'),
            'runtime-order-item-replay',
        );
        $payload = [
            'row_version' => 1,
            'items' => [[
                'menu_item_id' => $menuItemId,
                'qty' => 1,
                'note' => 'runtime smoke',
            ]],
        ];

        $this->useRuntimeRedisCache();

        $first = $this->postJson('/api/v1/staff/orders/'.$orderId.'/items', $payload, $headers);
        $second = $this->postJson('/api/v1/staff/orders/'.$orderId.'/items', $payload, $headers);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.items.0.item_id', $menuItemId);
        $second->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.items.0.item_id', $menuItemId);

        self::assertSame(1, (int) DB::table('reservation_order_items')
            ->where('order_id', $orderId)
            ->where('item_id', $menuItemId)
            ->count());
    }

    public function test_checkout_payment_replay_and_payload_mismatch_are_runtime_idempotent(): void
    {
        [$staffId, $orderId, $reservationId, $branchId] = $this->seedRuntimeActiveOrderWithCharge();
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
            'terminal_code' => 'RT-PAY',
        ]);
        $this->useRuntimeRedisCache();

        $headers = $this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'runtime-payment-replay'),
            'runtime-payment-replay',
        );
        $payload = [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'paid_amount' => 40000,
            'currency' => 'VND',
            'transaction_code' => 'RT-PAY-REPLAY-1',
            'row_version' => 1,
        ];

        $first = $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', $payload, $headers);
        $second = $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', $payload, $headers);
        $mismatch = $this->postJson('/api/v1/staff/orders/'.$orderId.'/pay', array_merge($payload, [
            'paid_amount' => 30000,
            'transaction_code' => 'RT-PAY-REPLAY-2',
        ]), $headers);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false');
        $second->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');
        $mismatch->assertStatus(409)
            ->assertJsonPath('error_code', 'idempotency_conflict')
            ->assertJsonPath('conflict_type', 'idempotency_payload_mismatch');

        self::assertSame(1, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Final')
            ->where('payment_provider', 'Cash')
            ->where('transaction_code', 'RT-PAY-REPLAY-1')
            ->count());
    }

    public function test_refund_over_cap_attempt_fails_against_mysql_lineage_state(): void
    {
        [$staffId, $reservationId, $depositPaymentId, $branchId] = $this->seedRuntimeCompletedReservationWithDeposit();
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchId,
            'status' => 'Open',
            'terminal_code' => 'RT-REFUND',
        ]);
        $this->useRuntimeRedisCache();

        $firstHeaders = $this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'runtime-refund-over-cap'),
            'runtime-refund-over-cap-a',
        );
        $secondHeaders = $this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'runtime-refund-over-cap'),
            'runtime-refund-over-cap-b',
        );

        $this->postJson('/api/v1/staff/reservations/'.$reservationId.'/refund', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'deposit',
            'refund_amount' => 70000,
            'currency' => 'VND',
            'transaction_code' => 'RT-REFUND-CAP-1',
            'row_version' => 1,
        ], $firstHeaders)->assertOk();

        $currentVersion = max(1, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('row_version'));

        $overRefund = $this->postJson('/api/v1/staff/reservations/'.$reservationId.'/refund', [
            'payment_method' => 'Cash',
            'payment_provider' => 'Cash',
            'refund_scope' => 'deposit',
            'refund_amount' => 40000,
            'currency' => 'VND',
            'transaction_code' => 'RT-REFUND-CAP-2',
            'row_version' => $currentVersion,
        ], $secondHeaders);

        $overRefund->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        self::assertSame(70000, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Refund')
            ->where('refund_of_payment_id', $depositPaymentId)
            ->sum('amount'));
        self::assertSame(1, (int) DB::table('payments')
            ->where('reservation_id', $reservationId)
            ->where('payment_type', 'Refund')
            ->where('refund_of_payment_id', $depositPaymentId)
            ->count());
    }

    public function test_one_open_cashier_shift_per_cashier_branch_currency(): void
    {
        $branchId = $this->runtimeDefaultBranchId();
        $staffId = $this->createUser(['role_name' => 'Cashier']);
        $this->assignRuntimeStaffBranch($staffId, $branchId);
        $headers = $this->staffAuthHeaders($staffId, 'runtime-cashier-shift');

        $this->useRuntimeRedisCache();

        $first = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'branch_id' => $branchId,
            'opening_float_amount' => 50000,
            'currency' => 'VND',
            'terminal_code' => 'RT-CASHIER-A',
        ], $this->withIdempotencyKey($headers, 'runtime-cashier-shift-a'));

        $second = $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'branch_id' => $branchId,
            'opening_float_amount' => 25000,
            'currency' => 'VND',
            'terminal_code' => 'RT-CASHIER-B',
        ], $this->withIdempotencyKey($headers, 'runtime-cashier-shift-b'));

        $first->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false');
        $second->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        self::assertSame(1, (int) DB::table('cashier_shifts')
            ->where('cashier_user_id', $staffId)
            ->where('branch_id', $branchId)
            ->where('currency', 'VND')
            ->where('status', 'Open')
            ->count());
    }

    public function test_kds_duplicate_dispatch_replays_without_duplicate_ticket(): void
    {
        $staffId = $this->createUser(['role_name' => 'Manager']);
        $scenario = $this->seedRuntimeRoutedKitchenOrder();
        $headers = $this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'runtime-kds-dispatch'),
            'runtime-kds-dispatch',
        );
        $payload = ['row_version' => 1];

        $this->useRuntimeRedisCache();

        $first = $this->postJson('/api/v1/staff/orders/'.$scenario['order_id'].'/kitchen/dispatch', $payload, $headers);
        $second = $this->postJson('/api/v1/staff/orders/'.$scenario['order_id'].'/kitchen/dispatch', $payload, $headers);

        $first->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('meta.created_count', 1);
        $second->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.0.ticket_id', $first->json('data.0.ticket_id'));

        self::assertSame(1, (int) DB::table('kitchen_order_item_tickets')
            ->where('order_id', $scenario['order_id'])
            ->where('order_item_id', $scenario['order_item_id'])
            ->count());
    }

    private function assertRuntimeServicesAvailable(): void
    {
        if (! app()->environment('testing')) {
            self::fail('Runtime smoke must run with APP_ENV=testing to avoid touching non-test data.');
        }

        $driver = (string) DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            self::fail(sprintf(
                'Runtime smoke requires DB_CONNECTION=mysql against a bootstrapped MySQL database; current driver is [%s]. Run composer bootstrap:booking before this lane.',
                $driver,
            ));
        }

        try {
            DB::select('SELECT 1 AS runtime_smoke_mysql_probe');
        } catch (Throwable $exception) {
            self::fail('Runtime smoke requires reachable MySQL. Start the repo runtime lane, run composer bootstrap:booking, then retry. Root cause: '.$exception->getMessage());
        }

        $missingTables = [];
        foreach ($this->requiredBookingTables() as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $missingTables[] = $table;
                }
            } catch (Throwable $exception) {
                self::fail('Runtime smoke could not inspect the MySQL schema. Run composer bootstrap:booking, then retry. Root cause: '.$exception->getMessage());
            }
        }

        if ($missingTables !== []) {
            self::fail(sprintf(
                'Runtime smoke requires the SQL-first booking schema. Missing table(s): %s. Run composer bootstrap:booking; do not use php artisan migrate for this repository.',
                implode(', ', array_slice($missingTables, 0, 10)),
            ));
        }

        $this->useRuntimeRedisCache();

        try {
            Cache::store('redis')->put('runtime-smoke:probe', 'ok', 10);
            self::assertSame('ok', Cache::store('redis')->get('runtime-smoke:probe'));
            Cache::store('redis')->forget('runtime-smoke:probe');

            $lock = Cache::store('redis')->lock('runtime-smoke:lock:probe', 10);
            if (! $lock->get()) {
                self::fail('Runtime smoke could not acquire a Redis cache lock.');
            }
            $lock->release();
        } catch (Throwable $exception) {
            self::fail('Runtime smoke requires reachable Redis cache and lock support. Start Redis or set REDIS_HOST/REDIS_PORT for the runtime lane. Root cause: '.$exception->getMessage());
        }
    }

    private function useRuntimeRedisCache(): void
    {
        config()->set('cache.default', 'redis');
        config()->set('cache.stores.redis', [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ]);
        config()->set('booking.require_redis_for_booking_api', true);
        config()->set('booking.realtime.enabled', true);
        config()->set('booking.realtime.cache_store', 'redis');
        config()->set('booking.realtime.recent_event_limit', 50);
        config()->set('booking.realtime.poll_hint_ms', 1500);
        config()->set('notifications.outbox.enabled', false);
        app('cache')->forgetDriver('redis');
    }

    private function runtimeDefaultBranchId(): int
    {
        $branchId = (int) (DB::table('branches')->where('is_default', 1)->value('branch_id') ?? 0);

        return $branchId > 0 ? $branchId : 1;
    }

    private function createRuntimeOpenBranch(): int
    {
        return $this->createBranch([
            'branch_code' => 'RT'.strtoupper(bin2hex(random_bytes(3))),
            'branch_name' => 'Runtime Smoke Open Branch',
            'timezone' => 'UTC',
            'business_hours' => $this->defaultBranchFixtureBusinessHours(),
            'closure_windows' => [],
            'booking_policy' => $this->defaultBranchFixtureBookingPolicy(),
        ]);
    }

    private function assignRuntimeStaffBranch(int $staffId, int $branchId, bool $primary = true): void
    {
        DB::table('staff_branch_assignments')->updateOrInsert([
            'user_id' => $staffId,
            'branch_id' => $branchId,
        ], [
            'is_primary' => $primary ? 1 : 0,
            'assigned_at' => now('UTC'),
            'revoked_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    private function flushRuntimeRedis(): void
    {
        if (! app()->environment('testing')) {
            self::fail('Refusing to flush Redis outside APP_ENV=testing.');
        }

        $this->useRuntimeRedisCache();
        Cache::store('redis')->flush();
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function seedRuntimeActiveOrderWithMenu(): array
    {
        $staffId = $this->createUser(['role_name' => 'Manager']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $menuItemId = $this->createMenuItem();
        $this->createMenuItemPrice([
            'item_id' => $menuItemId,
            'price' => '120000',
            'currency' => 'VND',
        ]);

        return [$staffId, $orderId, $menuItemId];
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function seedRuntimeActiveOrderWithCharge(): array
    {
        [$staffId, $orderId, $menuItemId] = $this->seedRuntimeActiveOrderWithMenu();
        $reservationId = (int) DB::table('reservation_orders')->where('order_id', $orderId)->value('reservation_id');
        $branchId = max(1, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('branch_id'));

        $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $menuItemId,
            'quantity' => 2,
            'unit_price' => '50000',
            'currency' => 'VND',
            'line_total' => '100000',
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        return [$staffId, $orderId, $reservationId, $branchId];
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function seedRuntimeCompletedReservationWithDeposit(): array
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $staffId = $this->createUser(['role_name' => 'Manager']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'deposit_required_amount' => '100000',
            'deposit_paid_amount' => '100000',
            'deposit_status' => 'Paid',
            'bill_currency' => 'VND',
            'row_version' => 1,
        ]);
        $branchId = max(1, (int) DB::table('reservations')->where('reservation_id', $reservationId)->value('branch_id'));
        $depositPaymentId = $this->createPayment([
            'reservation_id' => $reservationId,
            'branch_id' => $branchId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '100000',
            'currency' => 'VND',
            'transaction_code' => 'RT-DEP-REFUND-CAP',
            'payment_provider' => 'Cash',
        ]);

        return [$staffId, $reservationId, $depositPaymentId, $branchId];
    }

    /**
     * @return array{order_id:int,order_item_id:int}
     */
    private function seedRuntimeRoutedKitchenOrder(): array
    {
        $categoryId = $this->ensureMenuCategory('Runtime Smoke Kitchen');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'RT-KDS-ITEM',
            'name' => 'Runtime Smoke Kitchen Item',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'RT-KDS',
            'name' => 'Runtime Smoke KDS',
        ]);
        $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'Ordered',
            'row_version' => 1,
        ]);

        return [
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
        ];
    }
}
