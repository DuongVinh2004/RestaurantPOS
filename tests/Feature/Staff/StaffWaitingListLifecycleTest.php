<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\BenefitsLoyalty\Application\Services\LoyaltyPointsService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationCodeGenerator;
use App\Modules\CheckoutPayments\Application\Services\ReservationFinancialSyncService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Application\Services\ReservationService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use App\Modules\FloorOps\Application\Services\StaffCheckInService;
use App\Modules\WaitingList\Application\Services\StaffWaitingListService;
use App\Modules\BranchScheduling\Application\Services\TableHoldService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffWaitingListLifecycleTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        $this->bindWaitingListRuntime();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bindWaitingListRuntime(): void
    {
        $locks = $this->mockReservationLocks();
        $notifications = $this->mockNotificationOutbox();
        $runtime = $this->mockRuntimeSettings();
        $tableState = new RestaurantTableStateService();
        $conflicts = new TableTimeConflictService();
        $financialSync = new ReservationFinancialSyncService();
        $loyalty = new LoyaltyPointsService($financialSync, $runtime);
        $tableHoldService = new TableHoldService($locks, $tableState, $conflicts, $runtime);
        $reservationService = new ReservationService(
            $tableHoldService,
            $locks,
            new ReservationCodeGenerator(),
            $notifications,
            $loyalty,
            $tableState,
            $conflicts,
            $financialSync,
        );
        $checkInService = new StaffCheckInService($locks, $notifications, $tableState, $conflicts, $runtime);
        $waitingListService = new StaffWaitingListService($notifications, $locks, $reservationService, $checkInService, $runtime);

        $this->app->instance(NotificationOutboxService::class, $notifications);
        $this->app->instance(ReservationLockService::class, $locks);
        $this->app->instance(RuntimeSettingService::class, $runtime);
        $this->app->instance(RestaurantTableStateService::class, $tableState);
        $this->app->instance(TableTimeConflictService::class, $conflicts);
        $this->app->instance(ReservationFinancialSyncService::class, $financialSync);
        $this->app->instance(LoyaltyPointsService::class, $loyalty);
        $this->app->instance(TableHoldService::class, $tableHoldService);
        $this->app->instance(ReservationService::class, $reservationService);
        $this->app->instance(StaffCheckInService::class, $checkInService);
        $this->app->instance(StaffWaitingListService::class, $waitingListService);
    }

    public function test_staff_can_create_notify_and_seat_waiting_list_entry_via_http_flow(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $guestUserId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $createResponse = $this->withHeaders($this->withIdempotencyKey('waiting-list-create', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list', [
                'user_id' => $guestUserId,
                'guest_count' => 2,
                'guest_name' => 'Walk-in VIP',
                'phone' => '0901234567',
                'notes' => 'Front door queue',
            ]);

        $createResponse->assertCreated();
        $waitingId = (int) $createResponse->json('data.waiting_id');

        $notifyResponse = $this->withHeaders($this->withIdempotencyKey('waiting-list-notify', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);

        $notifyResponse->assertOk()
            ->assertJsonPath('data.status', 'Notified')
            ->assertJsonPath('data.row_version', 2);

        $seatResponse = $this->withHeaders($this->withIdempotencyKey('waiting-list-seat', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/seat", [
                'user_id' => $guestUserId,
                'service_minutes' => 120,
                'row_version' => 2,
            ]);

        $seatResponse->assertOk()
            ->assertJsonPath('data.waiting_list.status', 'Seated')
            ->assertJsonPath('data.waiting_list.row_version', 3)
            ->assertJsonPath('data.reservation.user_id', $guestUserId);

        self::assertSame('Seated', DB::table('waiting_list')->where('waiting_id', $waitingId)->value('status'));
        self::assertSame('Cancelled', DB::table('table_holds')->where('session_id', 'waiting-list:' . $waitingId)->latest('created_at')->value('hold_status'));
        self::assertSame('Occupied', DB::table('restaurant_tables')->where('table_id', $tableId)->value('status'));

        $createdLog = $this->assertAuditLogRecorded('waiting_list.created', 'waiting_list', $waitingId);
        self::assertSame($staffId, $createdLog->actor_user_id);
        self::assertSame('staff_user', $createdLog->actor_type);
        $this->assertAuditSubjectRecorded($createdLog, 'user', $guestUserId, 'customer');

        $notifiedLog = $this->assertAuditLogRecorded('waiting_list.notified', 'waiting_list', $waitingId);
        self::assertSame('Notified', (string) data_get($notifiedLog->after_json, 'status'));
        $this->assertAuditSubjectRecorded($notifiedLog, 'restaurant_table', $tableId, 'table');

        $reservationId = (int) $seatResponse->json('data.reservation.reservation_id');
        $seatedLog = $this->assertAuditLogRecorded('waiting_list.seated', 'waiting_list', $waitingId);
        self::assertSame('Seated', (string) data_get($seatedLog->after_json, 'status'));
        $this->assertAuditSubjectRecorded($seatedLog, 'restaurant_table', $tableId, 'table');
        $this->assertAuditSubjectRecorded($seatedLog, 'reservation', $reservationId, 'reservation');
    }

    public function test_staff_create_rejects_waiting_list_when_branch_policy_disables_it(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'WL-OFF',
            'booking_policy' => [
                'waiting_list' => [
                    'enabled' => false,
                ],
            ],
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $response = $this->withHeaders($this->withIdempotencyKey('waiting-list-disabled-branch', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list', [
                'branch_id' => $branchId,
                'guest_count' => 2,
                'guest_name' => 'Blocked Queue',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('details.errors.branch_id.0', 'Waiting list is disabled for the selected branch.');
    }

    public function test_staff_can_cancel_notified_waiting_list_entry_and_release_hold(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry();
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $notifyResponse = $this->withHeaders($this->withIdempotencyKey('waiting-list-notify-before-cancel', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notifyResponse->assertOk();

        DB::table('waiting_list')->where('waiting_id', $waitingId)->update([
            'customer_response_status' => 'Accepted',
            'customer_responded_at' => Carbon::now('UTC')->subMinute(),
            'customer_confirmed_arrival_at' => Carbon::now('UTC')->subSeconds(30),
            'updated_at' => Carbon::now('UTC'),
        ]);

        $currentRowVersion = (int) DB::table('waiting_list')->where('waiting_id', $waitingId)->value('row_version');

        $cancelResponse = $this->withHeaders($this->withIdempotencyKey('waiting-list-cancel', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/cancel", [
                'cancel_reason' => 'guest_left',
                'row_version' => $currentRowVersion,
            ]);

        $cancelResponse->assertOk()
            ->assertJsonPath('data.status', 'Cancelled')
            ->assertJsonPath('data.row_version', $currentRowVersion + 1)
            ->assertJsonPath('data.response.status', null)
            ->assertJsonPath('data.response.confirmed_arrival_at', null)
            ->assertJsonPath('data.invite_window.expires_at', null);

        self::assertSame('Cancelled', DB::table('waiting_list')->where('waiting_id', $waitingId)->value('status'));
        self::assertSame('guest_left', DB::table('waiting_list')->where('waiting_id', $waitingId)->value('cancel_reason'));
        self::assertNull(DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_response_status'));
        self::assertNull(DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_responded_at'));
        self::assertNull(DB::table('waiting_list')->where('waiting_id', $waitingId)->value('customer_confirmed_arrival_at'));
        self::assertNull(DB::table('waiting_list')->where('waiting_id', $waitingId)->value('notify_expires_at'));
        self::assertSame('Cancelled', DB::table('table_holds')->where('session_id', 'waiting-list:' . $waitingId)->latest('created_at')->value('hold_status'));

        $log = $this->assertAuditLogRecorded('waiting_list.cancelled', 'waiting_list', $waitingId);
        self::assertSame($staffId, $log->actor_user_id);
        self::assertSame('staff_user', $log->actor_type);
        self::assertSame('guest_left', (string) data_get($log->summary_json, 'cancel_reason'));
    }

    public function test_waiting_list_notify_rejects_table_with_insufficient_capacity(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTableWithSeats(2, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'guest_count' => 4,
        ]);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $response = $this->withHeaders($this->withIdempotencyKey('waiting-list-notify-capacity', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['table_id']);
        self::assertSame('Waiting', DB::table('waiting_list')->where('waiting_id', $waitingId)->value('status'));
    }

    public function test_waiting_list_notify_rejects_table_already_held_by_another_session(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry();
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $this->createTableHold([
            'start_time' => $this->nowUtc()->copy()->subMinutes(1),
            'end_time' => $this->nowUtc()->copy()->addMinutes(30),
            'expire_at' => $this->nowUtc()->copy()->addMinutes(30),
            'hold_status' => 'Holding',
            'session_id' => 'someone-else',
        ], [$tableId]);

        $response = $this->withHeaders($this->withIdempotencyKey('waiting-list-notify-held', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['table_id']);
        self::assertSame('Waiting', DB::table('waiting_list')->where('waiting_id', $waitingId)->value('status'));
    }

    public function test_waiting_list_notify_rejects_stale_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'row_version' => 2,
        ]);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $response = $this->withHeaders($this->withIdempotencyKey('waiting-list-notify-row-version', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('category_code', 'stale_write')
            ->assertJsonValidationErrors(['row_version']);
        self::assertSame('Waiting', DB::table('waiting_list')->where('waiting_id', $waitingId)->value('status'));
    }

    public function test_waiting_list_seat_rejects_stale_row_version(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $guestUserId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'user_id' => $guestUserId,
            'row_version' => 2,
        ]);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $notifyResponse = $this->withHeaders($this->withIdempotencyKey('waiting-list-notify-before-seat-row-version', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 2,
            ]);
        $notifyResponse->assertOk();

        $response = $this->withHeaders($this->withIdempotencyKey('waiting-list-seat-row-version', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/seat", [
                'user_id' => $guestUserId,
                'service_minutes' => 120,
                'row_version' => 1,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('category_code', 'stale_write')
            ->assertJsonValidationErrors(['row_version']);
        self::assertSame('Notified', DB::table('waiting_list')->where('waiting_id', $waitingId)->value('status'));
    }


    public function test_expire_notified_entries_returns_entry_to_waiting_and_cancels_hold(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'user_id' => $customerId,
            'status' => 'Waiting',
            'row_version' => 1,
        ]);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $notifyResponse = $this->withHeaders($this->withIdempotencyKey('waiting-list-expire-notify', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notifyResponse->assertOk();

        DB::table('waiting_list')->where('waiting_id', $waitingId)->update([
            'customer_response_status' => 'Accepted',
            'customer_responded_at' => Carbon::now('UTC')->subMinute(),
            'customer_confirmed_arrival_at' => Carbon::now('UTC')->subSeconds(30),
            'updated_at' => Carbon::now('UTC'),
        ]);

        $expired = app(StaffWaitingListService::class)->expireNotifiedEntries(Carbon::parse((string) $notifyResponse->json('data.notify_expires_at'))->addSecond());
        self::assertSame(1, $expired);

        $record = DB::table('waiting_list')->where('waiting_id', $waitingId)->first();
        self::assertSame('Waiting', (string) $record->status);
        self::assertNull($record->notified_at);
        self::assertNull($record->notify_expires_at);
        self::assertNull($record->customer_response_status);
        self::assertNull($record->customer_responded_at);
        self::assertNull($record->customer_confirmed_arrival_at);
        self::assertSame('Cancelled', DB::table('table_holds')->where('session_id', 'waiting-list:' . $waitingId)->latest('created_at')->value('hold_status'));
    }

    public function test_waiting_list_seat_rejects_expired_notify_window_even_if_hold_is_still_open(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $guestUserId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'user_id' => $guestUserId,
            'row_version' => 1,
        ]);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $notifyResponse = $this->withHeaders($this->withIdempotencyKey('waiting-list-expired-seat-notify', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notifyResponse->assertOk();

        DB::table('waiting_list')->where('waiting_id', $waitingId)->update([
            'notify_expires_at' => Carbon::now('UTC')->subMinute(),
            'updated_at' => Carbon::now('UTC'),
        ]);
        DB::table('table_holds')->where('session_id', 'waiting-list:' . $waitingId)->update([
            'expire_at' => Carbon::now('UTC')->addMinutes(5),
            'end_time' => Carbon::now('UTC')->addMinutes(5),
            'updated_at' => Carbon::now('UTC'),
        ]);

        $currentRowVersion = (int) DB::table('waiting_list')->where('waiting_id', $waitingId)->value('row_version');

        $response = $this->withHeaders($this->withIdempotencyKey('waiting-list-expired-seat', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/seat", [
                'user_id' => $guestUserId,
                'service_minutes' => 120,
                'row_version' => $currentRowVersion,
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['notify_window']);
        self::assertSame('Notified', DB::table('waiting_list')->where('waiting_id', $waitingId)->value('status'));
    }

    public function test_renotify_resets_customer_response_state_and_replaces_hold(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableIdOne = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $tableIdTwo = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry();
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $notifyOne = $this->withHeaders($this->withIdempotencyKey('waiting-list-renotify-1', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableIdOne,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notifyOne->assertOk();

        DB::table('waiting_list')->where('waiting_id', $waitingId)->update([
            'customer_response_status' => 'Accepted',
            'customer_responded_at' => Carbon::now('UTC')->subMinute(),
            'customer_confirmed_arrival_at' => Carbon::now('UTC')->subSeconds(30),
            'updated_at' => Carbon::now('UTC'),
        ]);

        $notifyTwo = $this->withHeaders($this->withIdempotencyKey('waiting-list-renotify-2', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableIdTwo,
                'hold_minutes' => 10,
                'row_version' => (int) DB::table('waiting_list')->where('waiting_id', $waitingId)->value('row_version'),
            ]);

        $notifyTwo->assertOk()
            ->assertJsonPath('data.status', 'Notified')
            ->assertJsonPath('data.response.status', null)
            ->assertJsonPath('data.response.confirmed_arrival_at', null)
            ->assertJsonPath('data.invite_hold.has_active_hold', true);

        self::assertSame('Cancelled', DB::table('table_holds')->where('session_id', 'waiting-list:' . $waitingId)->orderBy('created_at')->first()->hold_status);
        $activeHoldId = DB::table('table_holds')
            ->where('session_id', 'waiting-list:' . $waitingId)
            ->whereIn('hold_status', ['Holding', 'Pending', 'Confirmed'])
            ->value('hold_id');

        self::assertNotNull($activeHoldId);
        self::assertSame($tableIdTwo, (int) DB::table('table_hold_details')->where('hold_id', $activeHoldId)->value('table_id'));
    }

    public function test_staff_waiting_list_index_includes_hold_lifecycle_orchestration_and_summary_meta(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'user_id' => $customerId,
            'status' => 'Waiting',
        ]);
        $staffHeaders = $this->staffAuthHeaders($staffId);

        $notifyResponse = $this->withHeaders($this->withIdempotencyKey('waiting-list-index-notify', $staffHeaders))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notifyResponse->assertOk();

        DB::table('waiting_list')->where('waiting_id', $waitingId)->update([
            'customer_response_status' => 'Accepted',
            'customer_responded_at' => Carbon::now('UTC')->subMinute(),
            'customer_confirmed_arrival_at' => Carbon::now('UTC')->subSeconds(30),
            'updated_at' => Carbon::now('UTC'),
        ]);

        $response = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/waiting-list?status=Notified&active_only=1');

        $response->assertOk()
            ->assertJsonPath('meta.summary.ready_to_seat_count', 1)
            ->assertJsonPath('data.0.waiting_id', $waitingId)
            ->assertJsonPath('data.0.current_response_state', 'arrival_confirmed')
            ->assertJsonPath('data.0.invite_hold.has_active_hold', true)
            ->assertJsonPath('data.0.orchestration.actionable_state', 'seat_customer');
    }

}
