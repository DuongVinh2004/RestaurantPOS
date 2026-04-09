<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffTableReleaseHttpFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);

        $this->app->instance(ReservationLockService::class, $this->mockReservationLocks());
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_staff_can_release_idle_occupied_table_via_http_flow(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied', 'row_version' => 1]);

        $response = $this->withHeaders($this->withIdempotencyKey('staff-table-release-success', $this->staffAuthHeaders($staffId)))
            ->postJson('/api/v1/staff/tables/' . $tableId . '/release', [
                'row_version' => 1,
                'notes' => 'Manual release after cleanup',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.table_id', $tableId)
            ->assertJsonPath('data.status', 'Available')
            ->assertJsonPath('data.row_version', 2);

        self::assertSame('Available', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));

        $log = $this->assertAuditLogRecorded('table.released', 'restaurant_table', $tableId);
        self::assertSame($staffId, $log->actor_user_id);
        self::assertSame('staff_user', $log->actor_type);
        self::assertSame('Available', (string) data_get($log->after_json, 'status'));
        self::assertTrue((bool) data_get($log->summary_json, 'notes_present'));
    }

    public function test_release_rejects_table_with_active_checked_in_reservation(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied', 'row_version' => 1]);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $this->withHeaders($this->withIdempotencyKey('staff-table-release-blocked', $this->staffAuthHeaders($staffId)))
            ->postJson('/api/v1/staff/tables/' . $tableId . '/release', [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_release_rejects_table_when_active_reservation_branch_drift_exists(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'ANNEXREL',
            'branch_name' => 'Annex Release',
        ]);
        $tableId = $this->createRestaurantTable([
            'status' => 'Occupied',
            'row_version' => 1,
            'branch_id' => $annexBranchId,
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => 1,
            'status' => 'Confirmed',
            'checked_in_at' => null,
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $this->withHeaders($this->withIdempotencyKey('staff-table-release-branch-drift', $this->staffAuthHeaders($staffId)))
            ->postJson('/api/v1/staff/tables/' . $tableId . '/release', [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_release_rejects_table_when_live_active_order_exists_even_if_reservation_is_not_checked_in(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied', 'row_version' => 1]);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'checked_in_at' => null,
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);

        $this->withHeaders($this->withIdempotencyKey('staff-table-release-live-order-confirmed', $this->staffAuthHeaders($staffId)))
            ->postJson('/api/v1/staff/tables/' . $tableId . '/release', [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }
}
