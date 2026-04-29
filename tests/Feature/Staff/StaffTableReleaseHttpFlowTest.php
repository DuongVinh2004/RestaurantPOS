<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
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
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_same_branch_table_release_still_succeeds_when_releasable(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied', 'row_version' => 1]);

        $response = $this->withHeaders($this->withIdempotencyKey('staff-table-release-success', $this->staffAuthHeaders($staffId)))
            ->postJson('/api/v1/staff/tables/'.$tableId.'/release', [
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

    public function test_release_rejects_stale_table_row_version_without_changing_state(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied', 'row_version' => 2]);

        $this->withHeaders($this->withIdempotencyKey('staff-table-release-stale-row-version', $this->staffAuthHeaders($staffId)))
            ->postJson('/api/v1/staff/tables/'.$tableId.'/release', [
                'row_version' => 1,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('category_code', 'stale_write')
            ->assertJsonPath('details.errors.row_version.0', 'Data changed (row_version mismatch). Reload and try again.');

        $table = DB::table('restaurant_tables')->where('table_id', $tableId)->first();
        self::assertSame('Occupied', (string) $table->status);
        self::assertSame(2, (int) $table->row_version);
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
            ->postJson('/api/v1/staff/tables/'.$tableId.'/release', [
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
        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $annexBranchId]);

        $this->withHeaders($this->withIdempotencyKey('staff-table-release-branch-drift', $this->staffAuthHeaders($staffId)))
            ->postJson('/api/v1/staff/tables/'.$tableId.'/release', [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_release_rejects_current_window_confirmed_reservation_even_without_checkin_timestamp(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied', 'row_version' => 1]);
        $start = $this->nowUtc()->copy()->subMinutes(15);
        $end = $this->nowUtc()->copy()->addMinutes(45);
        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'checked_in_at' => null,
            'start_time' => $start,
            'end_time' => $end,
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $response = $this->withHeaders($this->withIdempotencyKey('staff-table-release-active-window', $this->staffAuthHeaders($staffId)))
            ->postJson('/api/v1/staff/tables/'.$tableId.'/release', [
                'row_version' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['table_id'])
            ->assertJsonPath(
                'details.errors.table_id.0',
                'Cannot release table while reservations are still in an active service context: '
                .$reservationId
                .'. Complete check-in/checkout or close the reservation flow first.'
            );

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_same_branch_table_release_still_blocks_active_order(): void
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
            ->postJson('/api/v1/staff/tables/'.$tableId.'/release', [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_out_of_branch_table_release_does_not_disclose_empty_table_state(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'RELHIDE',
            'branch_name' => 'Release Hidden Branch',
        ]);
        $tableId = $this->createRestaurantTable([
            'status' => 'Occupied',
            'row_version' => 1,
            'branch_id' => $annexBranchId,
        ]);

        $this->assertOutOfBranchReleaseDenied($staffId, $tableId, 'staff-table-release-hidden-empty');

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_out_of_branch_table_release_does_not_disclose_active_reservation_state(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'RELHIDERSV',
            'branch_name' => 'Release Hidden Reservation Branch',
        ]);
        $tableId = $this->createRestaurantTable([
            'status' => 'Occupied',
            'row_version' => 1,
            'branch_id' => $annexBranchId,
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $annexBranchId,
            'status' => 'Confirmed',
            'checked_in_at' => null,
            'start_time' => $this->nowUtc()->copy()->subMinutes(15),
            'end_time' => $this->nowUtc()->copy()->addMinutes(45),
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $this->assertOutOfBranchReleaseDenied($staffId, $tableId, 'staff-table-release-hidden-reservation');

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_out_of_branch_table_release_does_not_disclose_active_order_state(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'RELHIDEORD',
            'branch_name' => 'Release Hidden Order Branch',
        ]);
        $tableId = $this->createRestaurantTable([
            'status' => 'Occupied',
            'row_version' => 1,
            'branch_id' => $annexBranchId,
        ]);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'branch_id' => $annexBranchId,
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

        $this->assertOutOfBranchReleaseDenied($staffId, $tableId, 'staff-table-release-hidden-order');

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    public function test_out_of_branch_table_release_does_not_disclose_active_service_session_state(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'RELHIDESVC',
            'branch_name' => 'Release Hidden Service Branch',
        ]);
        $tableId = $this->createRestaurantTable([
            'status' => 'Occupied',
            'row_version' => 1,
            'branch_id' => $annexBranchId,
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $annexBranchId,
            'status' => 'Reserved',
            'checked_in_at' => $this->nowUtc(),
            'row_version' => 1,
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $this->assertOutOfBranchReleaseDenied($staffId, $tableId, 'staff-table-release-hidden-service');

        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));
    }

    private function assertOutOfBranchReleaseDenied(int $staffId, int $tableId, string $idempotencyKey): void
    {
        $response = $this->withHeaders($this->withIdempotencyKey($idempotencyKey, $this->staffAuthHeaders($staffId)))
            ->postJson('/api/v1/staff/tables/'.$tableId.'/release', [
                'row_version' => 1,
            ]);

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('category_code', 'not_found');

        $payload = (string) json_encode($response->json(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        self::assertStringNotContainsString('Cannot release table', $payload);
        self::assertStringNotContainsString('active service context', $payload);
        self::assertStringNotContainsString('live order', $payload);
        self::assertStringNotContainsString('reservation', $payload);
    }
}
