<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminRestaurantMasterDataHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        Carbon::setTestNow(Carbon::parse('2026-03-21 12:00:00', 'UTC'));
    }

    public function test_admin_can_list_zones_tables_and_templates(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $mainZone = 'Admin Main Test';
        $patioZone = 'Admin Patio Test';
        $mainTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'ADM-MAIN-01', 'zone' => $mainZone]);
        $this->createRestaurantTableWithSeats(2, ['table_code' => 'ADM-PATIO-01', 'zone' => $patioZone]);

        $zonesResponse = $this->withHeaders($this->staffHeaders($adminId, 'admin-zones-list'))
            ->getJson('/api/v1/admin/restaurant/zones');

        $zonesResponse
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_restaurant_zones');

        self::assertGreaterThanOrEqual(2, (int) $zonesResponse->json('meta.summary.total_zones'));

        $zones = collect($zonesResponse->json('data'))->keyBy('zone_label');
        self::assertSame(1, (int) data_get($zones[$mainZone], 'table_count'));
        self::assertContains($mainTableId, data_get($zones[$mainZone], 'table_ids', []));
        self::assertSame(1, (int) data_get($zones[$patioZone], 'table_count'));

        $tablesResponse = $this->withHeaders($this->staffHeaders($adminId, 'admin-tables-list'))
            ->getJson('/api/v1/admin/restaurant/tables?zone='.rawurlencode($mainZone));

        $tablesResponse
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_restaurant_tables')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.table_code', 'ADM-MAIN-01')
            ->assertJsonPath('data.0.template.seats', 4);

        $templatesResponse = $this->withHeaders($this->staffHeaders($adminId, 'admin-templates-list'))
            ->getJson('/api/v1/admin/restaurant/table-templates');

        $templatesResponse
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_restaurant_table_templates');
        self::assertGreaterThanOrEqual(1, count($templatesResponse->json('data')));
    }

    public function test_admin_can_rename_zone_across_existing_tables(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $sourceZone = 'Admin Rename Source';
        $targetZone = 'Admin Rename Target';
        $firstTableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'REN-01', 'zone' => $sourceZone]);
        $secondTableId = $this->createRestaurantTableWithSeats(6, ['table_code' => 'REN-02', 'zone' => $sourceZone]);
        $firstBefore = (int) DB::table('restaurant_tables')->where('table_id', $firstTableId)->value('row_version');
        $secondBefore = (int) DB::table('restaurant_tables')->where('table_id', $secondTableId)->value('row_version');

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffHeaders($adminId, 'admin-zone-rename'),
            'idem-admin-zone-rename'
        ))
            ->postJson('/api/v1/admin/restaurant/zones/rename', [
                'from_zone' => $sourceZone,
                'to_zone' => $targetZone,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_restaurant_zone_renamed')
            ->assertJsonPath('data.from_zone', $sourceZone)
            ->assertJsonPath('data.to_zone', $targetZone)
            ->assertJsonPath('data.affected_table_count', 2);

        self::assertSame($targetZone, DB::table('restaurant_tables')->where('table_id', $firstTableId)->value('zone'));
        self::assertSame($targetZone, DB::table('restaurant_tables')->where('table_id', $secondTableId)->value('zone'));
        self::assertGreaterThan($firstBefore, (int) DB::table('restaurant_tables')->where('table_id', $firstTableId)->value('row_version'));
        self::assertGreaterThan($secondBefore, (int) DB::table('restaurant_tables')->where('table_id', $secondTableId)->value('row_version'));
    }

    public function test_admin_can_show_single_table_details(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'ADM-SHOW-01', 'zone' => 'Main']);

        $response = $this->withHeaders($this->staffHeaders($adminId, 'admin-table-show'))
            ->getJson('/api/v1/admin/restaurant/tables/'.$tableId);

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_restaurant_table_show')
            ->assertJsonPath('data.table_id', $tableId)
            ->assertJsonPath('data.table_code', 'ADM-SHOW-01')
            ->assertJsonPath('data.guards.can_change_zone', true);
    }

    public function test_admin_cannot_change_zone_linkage_while_live_order_exists(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'ADM-LIVE-01', 'zone' => 'Main']);
        $reservationId = $this->createReservation([
            'status' => 'Completed',
            'start_time' => Carbon::parse('2026-03-21 10:00:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:00:00', 'UTC'),
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);

        $table = DB::table('restaurant_tables')->where('table_id', $tableId)->first();

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffHeaders($adminId, 'admin-table-live-order-guard'),
            'idem-admin-table-live-order-guard'
        ))
            ->patchJson('/api/v1/admin/restaurant/tables/'.$tableId, [
                'row_version' => (int) $table->row_version,
                'zone' => 'Patio',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['zone']);
    }

    public function test_admin_cannot_rename_zone_while_any_table_in_zone_has_live_operational_links(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $sourceZone = 'Admin Live Source';
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'ADM-ZONE-LIVE-01', 'zone' => $sourceZone]);
        $reservationId = $this->createReservation([
            'status' => 'Completed',
            'start_time' => Carbon::parse('2026-03-21 10:00:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 11:00:00', 'UTC'),
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffHeaders($adminId, 'admin-zone-live-guard'),
            'idem-admin-zone-live-guard'
        ))
            ->postJson('/api/v1/admin/restaurant/zones/rename', [
                'from_zone' => $sourceZone,
                'to_zone' => 'Admin Live Target',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_zone']);
    }

    public function test_admin_can_create_and_update_table_metadata_with_row_version_guard(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $templateId = $this->seedTableTemplate('ADM-TPL-08', 8);

        $createResponse = $this->withHeaders($this->withIdempotencyKey(
            $this->staffHeaders($adminId, 'admin-table-create'),
            'idem-admin-table-create'
        ))
            ->postJson('/api/v1/admin/restaurant/tables', [
                'table_code' => 'ADM-CREATE-01',
                'template_id' => $templateId,
                'zone' => 'Garden',
                'pos_x' => 10,
                'pos_y' => 20,
                'status' => 'Available',
                'description' => 'Garden booth',
                'price' => '0.00',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('meta.action', 'admin_restaurant_table_created')
            ->assertJsonPath('data.table_code', 'ADM-CREATE-01')
            ->assertJsonPath('data.template.template_id', $templateId)
            ->assertJsonPath('data.row_version', 1);

        $tableId = (int) $createResponse->json('data.table_id');
        $rowVersion = (int) $createResponse->json('data.row_version');

        $updateResponse = $this->withHeaders($this->withIdempotencyKey(
            $this->staffHeaders($adminId, 'admin-table-update'),
            'idem-admin-table-update'
        ))
            ->patchJson('/api/v1/admin/restaurant/tables/'.$tableId, [
                'row_version' => $rowVersion,
                'zone' => 'Garden VIP',
                'description' => 'Garden booth updated',
                'pos_x' => 11,
                'pos_y' => 22,
                'status' => 'Blocked',
            ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_restaurant_table_updated')
            ->assertJsonPath('data.zone', 'Garden VIP')
            ->assertJsonPath('data.description', 'Garden booth updated')
            ->assertJsonPath('data.position.x', 11)
            ->assertJsonPath('data.status', 'Blocked');

        self::assertGreaterThan($rowVersion, (int) $updateResponse->json('data.row_version'));
    }

    public function test_admin_cannot_change_template_or_archive_table_while_operationally_linked(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'ADM-LINKED-01', 'zone' => 'Main']);
        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 13:00:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 15:00:00', 'UTC'),
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $table = DB::table('restaurant_tables')->where('table_id', $tableId)->first();
        $newTemplateId = $this->seedTableTemplate('ADM-TPL-10', 10);

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffHeaders($adminId, 'admin-table-linked-guard'),
            'idem-admin-table-linked-guard'
        ))
            ->patchJson('/api/v1/admin/restaurant/tables/'.$tableId, [
                'row_version' => (int) $table->row_version,
                'template_id' => $newTemplateId,
                'is_deleted' => true,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_non_admin_staff_is_forbidden_from_admin_table_master_data_routes(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->createRestaurantTableWithSeats(4, ['table_code' => 'ADM-FORBID-01', 'zone' => 'Main']);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'admin-table-forbidden'))
            ->getJson('/api/v1/admin/restaurant/tables');

        $response->assertForbidden();
    }

    public function test_admin_cannot_change_table_metadata_when_confirmed_hold_is_still_active_even_if_expire_at_has_elapsed(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'ADM-HOLD-GUARD-01', 'zone' => 'Main']);

        $this->createTableHold([
            'hold_status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 12:30:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 14:00:00', 'UTC'),
            'expire_at' => Carbon::parse('2026-03-21 11:45:00', 'UTC'),
            'confirmed_reservation_id' => $this->createReservation([
                'status' => 'Reserved',
                'start_time' => Carbon::parse('2026-03-21 12:30:00', 'UTC'),
                'end_time' => Carbon::parse('2026-03-21 14:00:00', 'UTC'),
            ]),
        ], [$tableId]);

        $table = DB::table('restaurant_tables')->where('table_id', $tableId)->first();
        $newTemplateId = $this->seedTableTemplate('ADM-TPL-HOLD-06', 6);

        $this->withHeaders($this->withIdempotencyKey(
            $this->staffHeaders($adminId, 'admin-table-confirmed-hold-guard'),
            'idem-admin-table-confirmed-hold-guard'
        ))
            ->patchJson('/api/v1/admin/restaurant/tables/'.$tableId, [
                'row_version' => (int) $table->row_version,
                'template_id' => $newTemplateId,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_admin_cannot_rename_zone_when_confirmed_hold_is_still_active_even_if_expire_at_has_elapsed(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['table_code' => 'ADM-ZONE-HOLD-01', 'zone' => 'Main']);

        $this->createTableHold([
            'hold_status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-21 12:30:00', 'UTC'),
            'end_time' => Carbon::parse('2026-03-21 14:00:00', 'UTC'),
            'expire_at' => Carbon::parse('2026-03-21 11:40:00', 'UTC'),
            'confirmed_reservation_id' => $this->createReservation([
                'status' => 'Reserved',
                'start_time' => Carbon::parse('2026-03-21 12:30:00', 'UTC'),
                'end_time' => Carbon::parse('2026-03-21 14:00:00', 'UTC'),
            ]),
        ], [$tableId]);

        $this->withHeaders($this->withIdempotencyKey(
            $this->staffHeaders($adminId, 'admin-zone-confirmed-hold-guard'),
            'idem-admin-zone-confirmed-hold-guard'
        ))
            ->postJson('/api/v1/admin/restaurant/zones/rename', [
                'from_zone' => 'Main',
                'to_zone' => 'VIP',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_zone']);
    }

    private function seedTableTemplate(string $templateCode, int $seats): int
    {
        DB::table('table_templates')->updateOrInsert([
            'template_code' => $templateCode,
        ], [
            'seats' => $seats,
            'description' => 'Template '.$templateCode,
        ]);

        return (int) DB::table('table_templates')->where('template_code', $templateCode)->value('template_id');
    }

    private function createRestaurantTableWithSeats(int $seats, array $overrides = []): int
    {
        $templateId = $this->seedTableTemplate('TPL-'.$seats.'-'.substr(md5((string) microtime(true).random_int(1, 999999)), 0, 6), $seats);
        $overrides['template_id'] = $overrides['template_id'] ?? $templateId;

        return $this->createRestaurantTable($overrides);
    }

    /**
     * @return array<string,string>
     */
    private function staffHeaders(int $staffId, string $apiKey): array
    {
        return $this->staffAuthHeaders($staffId, $apiKey);
    }
}
