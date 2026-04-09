<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminKitchenRoutingFoundationHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.env_fallback_allowed_environments', ['testing']);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_admin_can_manage_kitchen_stations_and_category_routes(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-kitchen-manage-key');
        $hotCategoryId = $this->ensureMenuCategory('Kitchen Hot');
        $barCategoryId = $this->ensureMenuCategory('Kitchen Bar');

        $create = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-kitchen-station-create'))
            ->postJson('/api/v1/admin/kitchen/stations', [
                'code' => 'HOTLINE',
                'name' => 'Hot Line',
                'description' => 'Main hot pass',
                'output_mode' => 'Both',
                'printer_target' => 'printer://hot-pass',
                'is_active' => true,
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.code', 'HOTLINE')
            ->assertJsonPath('data.output_mode', 'Both')
            ->assertJsonPath('data.printer_target', 'printer://hot-pass')
            ->assertJsonPath('data.route_count', 0);

        $stationId = (int) $create->json('data.station_id');

        $sync = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-kitchen-routes-sync'))
            ->putJson('/api/v1/admin/kitchen/stations/'.$stationId.'/category-routes', [
                'routes' => [
                    [
                        'category_id' => $hotCategoryId,
                        'sort_order' => 10,
                    ],
                    [
                        'category_id' => $barCategoryId,
                        'sort_order' => 20,
                    ],
                ],
            ]);

        $sync->assertOk()
            ->assertJsonPath('meta.station.station_id', $stationId)
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('data.0.category.category_id', $hotCategoryId)
            ->assertJsonPath('data.1.category.category_id', $barCategoryId);

        $showRoutes = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/kitchen/stations/'.$stationId.'/category-routes');

        $showRoutes->assertOk()
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('data.0.sort_order', 10);

        $update = $this->withHeaders($headers)
            ->patchJson('/api/v1/admin/kitchen/stations/'.$stationId, [
                'name' => 'Hot Kitchen Line',
                'printer_target' => 'printer://kitchen-hotline',
            ]);

        $update->assertOk()
            ->assertJsonPath('data.name', 'Hot Kitchen Line')
            ->assertJsonPath('data.printer_target', 'printer://kitchen-hotline');

        $list = $this->withHeaders($headers)->getJson('/api/v1/admin/kitchen/stations');

        $list->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.station_id', $stationId)
            ->assertJsonPath('data.0.route_count', 2);

        self::assertSame($adminId, $adminId);
    }

    public function test_non_admin_staff_cannot_access_admin_kitchen_routes(): void
    {
        $staffRoleId = $this->ensureRole('Staff');
        $staffId = $this->createUser(['role_id' => $staffRoleId, 'role_name' => 'Staff']);

        config()->set('staff_auth.allowed_role_ids', [$staffRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $staffRoleId => ['order.manage'],
        ]);

        $response = $this->withHeaders($this->staffHeadersForTest($staffId, 'plain-staff-kitchen-admin-key'))
            ->getJson('/api/v1/admin/kitchen/stations');

        $response->assertForbidden()
            ->assertJsonPath('required_capability', 'settings.manage');
    }

    public function test_admin_cannot_deactivate_station_or_route_when_active_tickets_exist(): void
    {
        [, $headers] = $this->adminHeaders('admin-kitchen-active-ticket-guard-key');

        $categoryId = $this->ensureMenuCategory('Kitchen Active Guard');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'KDS-ACTIVE-01',
            'name' => 'Kitchen Active Guard Bowl',
        ]);
        $stationId = $this->createKitchenStation([
            'code' => 'ACTIVE-PASS',
            'name' => 'Active Pass',
            'output_mode' => 'Printer',
            'printer_target' => 'printer://active-pass',
        ]);
        $routeId = $this->createKitchenStationRoute([
            'station_id' => $stationId,
            'category_id' => $categoryId,
            'sort_order' => 10,
        ]);

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $orderItemId = $this->createOrderItem([
            'order_id' => $orderId,
            'item_id' => $itemId,
            'quantity' => 1,
            'status' => 'InProgress',
            'row_version' => 1,
        ]);
        $this->createKitchenOrderTicket([
            'route_id' => $routeId,
            'order_item_id' => $orderItemId,
            'ticket_status' => 'Fired',
            'fired_at' => $this->nowUtc(),
        ]);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/admin/kitchen/stations/'.$stationId, [
                'is_active' => false,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-kitchen-routes-disable-active'))
            ->putJson('/api/v1/admin/kitchen/stations/'.$stationId.'/category-routes', [
                'routes' => [
                    [
                        'category_id' => $categoryId,
                        'sort_order' => 10,
                        'is_active' => false,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['routes', 'category_id']);

        self::assertSame(1, (int) $this->table('kitchen_stations')->where('station_id', $stationId)->value('is_active'));
        self::assertSame(1, (int) $this->table('kitchen_station_category_routes')->where('route_id', $routeId)->value('is_active'));
    }

    public function test_missing_kitchen_station_routes_return_standardized_not_found_envelope(): void
    {
        [, $headers] = $this->adminHeaders('admin-kitchen-missing-resource-key');

        $this->withHeaders(array_merge($headers, [
            'X-Request-Id' => 'req-admin-kitchen-station-404',
        ]))
            ->getJson('/api/v1/admin/kitchen/stations/999999')
            ->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-admin-kitchen-station-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-admin-kitchen-station-404');

        $routeHeaders = $this->withIdempotencyKey(array_merge($headers, [
            'X-Request-Id' => 'req-admin-kitchen-route-404',
        ]), 'idem-admin-kitchen-route-404');

        $this->withHeaders($routeHeaders)
            ->putJson('/api/v1/admin/kitchen/stations/999999/category-routes', [
                'routes' => [
                    [
                        'category_id' => 999999,
                        'sort_order' => 10,
                    ],
                ],
            ])
            ->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-admin-kitchen-route-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-admin-kitchen-route-404');
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    private function adminHeaders(string $apiKey): array
    {
        $adminRoleId = $this->ensureRole('Admin');
        $adminId = $this->createUser(['role_id' => $adminRoleId, 'role_name' => 'Admin']);

        config()->set('staff_auth.allowed_role_ids', [$adminRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $adminRoleId => ['*'],
        ]);

        return [$adminId, $this->staffHeadersForTest($adminId, $apiKey)];
    }

    /**
     * @return array<string,string>
     */
    private function staffHeadersForTest(int $staffId, string $apiKey): array
    {
        return $this->staffAuthHeaders($staffId, $apiKey);
    }

    private function table(string $table)
    {
        return DB::table($table);
    }
}
