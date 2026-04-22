<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\WaitingListStatus;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableHoldService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\FloorOperations\Application\UseCases\CheckIn\StaffCheckInService;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationCodeGenerator;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Application\Services\ReservationService;
use App\Modules\Waitlist\Application\Services\StaffWaitingListService;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffWaitingListSemiAutomationFlowTest extends TestCase
{
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
        $tableState = new RestaurantTableStateService;
        $conflicts = new TableTimeConflictService;
        $financialSync = new ReservationFinancialSyncService;
        $loyalty = new LoyaltyPointsService($financialSync, $runtime);
        $tableHoldService = new TableHoldService($locks, $tableState, $conflicts, $runtime);
        $reservationService = new ReservationService(
            $tableHoldService,
            $locks,
            new ReservationCodeGenerator,
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
        $this->app->instance(StaffWaitingListService::class, $waitingListService);
    }

    public function test_customer_decline_can_advance_queue_to_next_candidate_using_released_hold_context(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $sourceCustomerId = $this->createUser([
            'full_name' => 'Declined Source',
            'phone' => '0909111001',
        ]);
        $nextCustomerId = $this->createUser([
            'full_name' => 'Next Candidate',
            'phone' => '0909111002',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $sourceWaitingId = $this->createWaitingListEntry([
            'user_id' => $sourceCustomerId,
            'guest_name' => 'Declined Source',
            'phone' => '0909111001',
            'guest_count' => 4,
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
        ]);
        $nextWaitingId = $this->createWaitingListEntry([
            'user_id' => $nextCustomerId,
            'guest_name' => 'Next Candidate',
            'phone' => '0909111002',
            'guest_count' => 2,
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
            'requested_at' => $this->nowUtc()->copy()->addMinute(),
        ]);

        $staffHeaders = $this->staffAuthHeaders($staffId);
        $notify = $this->withHeaders($this->withIdempotencyKey('waiting-list-semi-auto-decline-notify', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/'.$sourceWaitingId.'/notify', [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notify->assertOk();

        $sourceCustomer = User::query()->findOrFail($sourceCustomerId);
        $decline = $this->actingAs($sourceCustomer)
            ->withHeaders(['Idempotency-Key' => 'cust-waiting-semi-auto-decline'])
            ->postJson('/api/v1/waiting-list/'.$sourceWaitingId.'/decline', [
                'row_version' => (int) $notify->json('data.row_version'),
            ]);
        $decline->assertOk();

        $advance = $this->withHeaders($this->withIdempotencyKey('waiting-list-semi-auto-advance', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/'.$sourceWaitingId.'/advance', [
                'row_version' => (int) $decline->json('data.row_version'),
            ]);

        $advance->assertOk()
            ->assertJsonPath('data.source_waiting_list.waiting_id', $sourceWaitingId)
            ->assertJsonPath('data.source_waiting_list.current_response_state', 'declined')
            ->assertJsonPath('data.automation.result', 'notified_next_candidate')
            ->assertJsonPath('data.advanced_waiting_list.waiting_id', $nextWaitingId)
            ->assertJsonPath('data.advanced_waiting_list.status', WaitingListStatus::Notified->value)
            ->assertJsonPath('data.advanced_waiting_list.invite_hold.has_active_hold', true);

        self::assertSame('Holding', DB::table('table_holds')->where('session_id', 'waiting-list:'.$nextWaitingId)->latest('created_at')->value('hold_status'));
    }

    public function test_staff_queue_surfaces_seat_action_for_arrival_confirmed_entry(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'full_name' => 'Arrival Confirmed',
            'phone' => '0909222001',
        ]);
        $customer = User::query()->findOrFail($customerId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'user_id' => $customerId,
            'guest_name' => 'Arrival Confirmed',
            'phone' => '0909222001',
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
        ]);

        $staffHeaders = $this->staffAuthHeaders($staffId);
        $notify = $this->withHeaders($this->withIdempotencyKey('waiting-list-semi-auto-arrival-notify', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/'.$waitingId.'/notify', [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notify->assertOk();

        $accept = $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => 'cust-waiting-semi-auto-arrival-accept'])
            ->postJson('/api/v1/waiting-list/'.$waitingId.'/accept', [
                'row_version' => (int) $notify->json('data.row_version'),
            ]);
        $accept->assertOk();

        $confirm = $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => 'cust-waiting-semi-auto-arrival-confirm'])
            ->postJson('/api/v1/waiting-list/'.$waitingId.'/confirm-arrival', [
                'row_version' => (int) $accept->json('data.row_version'),
            ]);
        $confirm->assertOk();

        $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/waiting-list?status=Notified&active_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.waiting_id', $waitingId)
            ->assertJsonPath('data.0.orchestration.actionable_state', 'seat_customer')
            ->assertJsonPath('data.0.orchestration.recommended_action', 'seat_current_customer')
            ->assertJsonPath('data.0.orchestration.actions.0.key', 'seat')
            ->assertJsonPath('data.0.orchestration.actions.0.enabled', true)
            ->assertJsonPath('meta.summary.ready_to_seat_count', 1);
    }

    public function test_expired_invite_can_be_advanced_without_leaving_stale_state(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $sourceCustomerId = $this->createUser([
            'full_name' => 'Expired Source',
            'phone' => '0909333001',
        ]);
        $nextCustomerId = $this->createUser([
            'full_name' => 'Expired Next',
            'phone' => '0909333002',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $sourceWaitingId = $this->createWaitingListEntry([
            'user_id' => $sourceCustomerId,
            'guest_name' => 'Expired Source',
            'phone' => '0909333001',
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
        ]);
        $nextWaitingId = $this->createWaitingListEntry([
            'user_id' => $nextCustomerId,
            'guest_name' => 'Expired Next',
            'phone' => '0909333002',
            'guest_count' => 2,
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
            'requested_at' => $this->nowUtc()->copy()->addMinute(),
        ]);

        $staffHeaders = $this->staffAuthHeaders($staffId);
        $notify = $this->withHeaders($this->withIdempotencyKey('waiting-list-semi-auto-expire-notify', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/'.$sourceWaitingId.'/notify', [
                'table_id' => $tableId,
                'hold_minutes' => 5,
                'row_version' => 1,
            ]);
        $notify->assertOk();

        DB::table('waiting_list')->where('waiting_id', $sourceWaitingId)->update([
            'notify_expires_at' => Carbon::now('UTC')->subMinute(),
            'updated_at' => Carbon::now('UTC'),
        ]);

        $advance = $this->withHeaders($this->withIdempotencyKey('waiting-list-semi-auto-expire-advance', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/'.$sourceWaitingId.'/advance', [
                'row_version' => (int) DB::table('waiting_list')->where('waiting_id', $sourceWaitingId)->value('row_version'),
            ]);

        $advance->assertOk()
            ->assertJsonPath('data.source_waiting_list.status', WaitingListStatus::Waiting->value)
            ->assertJsonPath('data.source_waiting_list.notify_expires_at', null)
            ->assertJsonPath('data.automation.source_transition', 'expired_entry_returned_to_waiting')
            ->assertJsonPath('data.automation.result', 'notified_next_candidate')
            ->assertJsonPath('data.advanced_waiting_list.waiting_id', $nextWaitingId)
            ->assertJsonPath('data.advanced_waiting_list.status', WaitingListStatus::Notified->value);

        self::assertSame('Cancelled', DB::table('table_holds')->where('session_id', 'waiting-list:'.$sourceWaitingId)->latest('created_at')->value('hold_status'));
        self::assertNull(DB::table('waiting_list')->where('waiting_id', $sourceWaitingId)->value('notified_at'));
        self::assertNull(DB::table('waiting_list')->where('waiting_id', $sourceWaitingId)->value('notify_expires_at'));
    }

    public function test_branch_flag_can_disable_advanced_waiting_list_automation_without_affecting_canonical_flows(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'WAIT-OFF',
            'branch_name' => 'Waiting Automation Off',
        ]);
        $this->upsertFeatureFlagOverride(
            'waiting_list.advanced_automation',
            false,
            'testing',
            $branchId,
            ['reason' => 'manual host operations only'],
        );

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $sourceCustomerId = $this->createUser([
            'full_name' => 'Flagged Source',
            'phone' => '0909555001',
        ]);
        $nextCustomerId = $this->createUser([
            'full_name' => 'Flagged Next',
            'phone' => '0909555002',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, [
            'branch_id' => $branchId,
            'status' => 'Available',
        ]);
        $sourceWaitingId = $this->createWaitingListEntry([
            'branch_id' => $branchId,
            'user_id' => $sourceCustomerId,
            'guest_name' => 'Flagged Source',
            'phone' => '0909555001',
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
        ]);
        $this->createWaitingListEntry([
            'branch_id' => $branchId,
            'user_id' => $nextCustomerId,
            'guest_name' => 'Flagged Next',
            'phone' => '0909555002',
            'guest_count' => 2,
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
            'requested_at' => $this->nowUtc()->copy()->addMinute(),
        ]);

        $staffHeaders = $this->staffAuthHeaders($staffId);
        $notify = $this->withHeaders($this->withIdempotencyKey('waiting-list-feature-flag-notify', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/'.$sourceWaitingId.'/notify', [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notify->assertOk();

        $sourceCustomer = User::query()->findOrFail($sourceCustomerId);
        $decline = $this->actingAs($sourceCustomer)
            ->withHeaders(['Idempotency-Key' => 'cust-waiting-feature-flag-decline'])
            ->postJson('/api/v1/waiting-list/'.$sourceWaitingId.'/decline', [
                'row_version' => (int) $notify->json('data.row_version'),
            ]);
        $decline->assertOk();

        $queue = $this->withHeaders($staffHeaders)
            ->getJson('/api/v1/staff/waiting-list?status=Cancelled&active_only=0&branch_id='.$branchId);

        $queue->assertOk()
            ->assertJsonPath('data.0.waiting_id', $sourceWaitingId)
            ->assertJsonPath('data.0.orchestration.actionable_state', 'advance_queue')
            ->assertJsonPath('data.0.orchestration.advance_queue.supported', false)
            ->assertJsonPath('data.0.orchestration.advance_queue.disabled_reason', 'Advanced waiting-list automation is disabled for this rollout. Use canonical notify and seat flows.')
            ->assertJsonPath('data.0.orchestration.actions.1.key', 'advance_queue')
            ->assertJsonPath('data.0.orchestration.actions.1.enabled', false)
            ->assertJsonPath('data.0.orchestration.actions.1.reason', 'feature_disabled');

        $this->withHeaders($this->withIdempotencyKey('waiting-list-feature-flag-advance', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/'.$sourceWaitingId.'/advance', [
                'row_version' => (int) $decline->json('data.row_version'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['feature_flag']);
    }

    public function test_duplicate_advance_with_stale_row_version_does_not_create_inconsistent_state(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $sourceCustomerId = $this->createUser([
            'full_name' => 'Replay Source',
            'phone' => '0909444001',
        ]);
        $nextCustomerId = $this->createUser([
            'full_name' => 'Replay Next',
            'phone' => '0909444002',
        ]);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $sourceWaitingId = $this->createWaitingListEntry([
            'user_id' => $sourceCustomerId,
            'guest_name' => 'Replay Source',
            'phone' => '0909444001',
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
        ]);
        $this->createWaitingListEntry([
            'user_id' => $nextCustomerId,
            'guest_name' => 'Replay Next',
            'phone' => '0909444002',
            'guest_count' => 2,
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
            'requested_at' => $this->nowUtc()->copy()->addMinute(),
        ]);

        $staffHeaders = $this->staffAuthHeaders($staffId);
        $notify = $this->withHeaders($this->withIdempotencyKey('waiting-list-semi-auto-replay-notify', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/'.$sourceWaitingId.'/notify', [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notify->assertOk();

        $sourceCustomer = User::query()->findOrFail($sourceCustomerId);
        $decline = $this->actingAs($sourceCustomer)
            ->withHeaders(['Idempotency-Key' => 'cust-waiting-semi-auto-replay-decline'])
            ->postJson('/api/v1/waiting-list/'.$sourceWaitingId.'/decline', [
                'row_version' => (int) $notify->json('data.row_version'),
            ]);
        $decline->assertOk();

        $staleRowVersion = (int) $decline->json('data.row_version');

        $this->withHeaders($this->withIdempotencyKey('waiting-list-semi-auto-replay-advance-1', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/'.$sourceWaitingId.'/advance', [
                'row_version' => $staleRowVersion,
            ])
            ->assertOk()
            ->assertJsonPath('data.automation.result', 'notified_next_candidate');

        $this->withHeaders($this->withIdempotencyKey('waiting-list-semi-auto-replay-advance-2', $staffHeaders))
            ->postJson('/api/v1/staff/waiting-list/'.$sourceWaitingId.'/advance', [
                'row_version' => $staleRowVersion,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('category_code', 'stale_write')
            ->assertJsonValidationErrors(['row_version']);
    }
}
