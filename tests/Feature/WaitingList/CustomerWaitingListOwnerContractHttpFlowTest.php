<?php

declare(strict_types=1);

namespace Tests\Feature\WaitingList;

use App\Enums\WaitingListStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerWaitingListOwnerContractHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.allow_legacy_user_auth_tokens', false);
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_authenticated_owner_can_create_show_and_list_waiting_entries_with_canonical_owner_contract(): void
    {
        $ownerId = $this->createUser([
            'full_name' => 'Owner Customer',
            'phone' => '0909000111',
        ]);
        $otherId = $this->createUser([
            'full_name' => 'Other Customer',
            'phone' => '0909000222',
        ]);
        $ownerHeaders = $this->customerAuthHeaders($ownerId, 'sess-owner-contract-list');

        $otherWaitingId = $this->createWaitingListEntry([
            'user_id' => $otherId,
            'guest_name' => 'Other Entry',
        ]);

        $create = $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-waiting-create-1'))
            ->postJson('/api/v1/waiting-list', [
                'guest_count' => 3,
                'notes' => 'Near window please',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', WaitingListStatus::Waiting->value)
            ->assertJsonPath('data.guest_name', null)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.notes', 'Near window please');

        $createdData = $create->json('data');
        $this->assertCanonicalOwnerResource(
            $createdData,
            status: WaitingListStatus::Waiting->value,
            canAccept: false,
            canDecline: false,
            canConfirmArrival: false,
            canCancel: true,
            staffSeatRequired: false,
            nextStep: 'await_notification',
        );

        $waitingId = (int) $createdData['waiting_id'];

        $show = $this->withHeaders($ownerHeaders)->getJson('/api/v1/waiting-list/'.$waitingId);
        $show->assertOk()
            ->assertJsonPath('data.waiting_id', $waitingId)
            ->assertJsonPath('data.notes', 'Near window please');

        $this->assertCanonicalOwnerResource(
            $show->json('data'),
            status: WaitingListStatus::Waiting->value,
            canAccept: false,
            canDecline: false,
            canConfirmArrival: false,
            canCancel: true,
            staffSeatRequired: false,
            nextStep: 'await_notification',
        );

        $list = $this->withHeaders($ownerHeaders)->getJson('/api/v1/waiting-list');
        $list->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.waiting_id', $waitingId)
            ->assertJsonMissing(['waiting_id' => $otherWaitingId]);

        $this->assertArrayNotHasKey('meta', $list->json());
        $this->assertCanonicalOwnerResource(
            $list->json('data.0'),
            status: WaitingListStatus::Waiting->value,
            canAccept: false,
            canDecline: false,
            canConfirmArrival: false,
            canCancel: true,
            staffSeatRequired: false,
            nextStep: 'await_notification',
        );
    }

    public function test_guest_session_headers_are_rejected_for_owner_only_waiting_list_contract(): void
    {
        $sessionId = 'sess-owner-only-waiting-list';

        $this->withHeaders([
            'Idempotency-Key' => 'cust-owner-only-session-create-1',
            'X-Session-Id' => $sessionId,
        ])->postJson('/api/v1/waiting-list', [
            'session_id' => $sessionId,
            'guest_name' => 'Guest Session Customer',
            'phone' => '0909888777',
            'guest_count' => 2,
        ])->assertStatus(403)
            ->assertJsonPath('category_code', 'owner_scope_denied');
    }

    public function test_pre_resolved_staff_user_is_rejected_under_owner_only_waiting_list_contract(): void
    {
        $staffUserId = $this->createUser([
            'role_name' => 'Staff',
            'full_name' => 'Shift Lead',
            'phone' => '0909888666',
        ]);
        $staff = User::query()->findOrFail($staffUserId);

        $this->actingAs($staff)
            ->withHeaders(['Idempotency-Key' => 'cust-owner-only-staff-user-create-1'])
            ->postJson('/api/v1/waiting-list', [
                'guest_count' => 2,
                'notes' => 'Wrong-role should not pass owner contract',
            ])
            ->assertStatus(403)
            ->assertJsonPath('category_code', 'owner_scope_denied');
    }

    public function test_owner_can_accept_notified_entry_within_open_window_using_canonical_owner_contract(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $ownerId = $this->createUser([
            'full_name' => 'Accept Owner',
            'phone' => '0909000333',
        ]);
        $ownerHeaders = $this->customerAuthHeaders($ownerId, 'sess-owner-contract-accept');
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'guest_name' => 'Accept Owner',
            'phone' => '0909000333',
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
        ]);

        $notify = $this->withHeaders($this->withIdempotencyKey('owner-contract-notify-accept', $this->staffAuthHeaders($staffId)))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notify->assertOk();

        $accept = $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-contract-accept-1'))
            ->postJson("/api/v1/waiting-list/{$waitingId}/accept", [
                'row_version' => (int) $notify->json('data.row_version'),
            ]);

        $accept->assertOk()->assertJsonPath('data.status', WaitingListStatus::Notified->value);

        $this->assertCanonicalOwnerResource(
            $accept->json('data'),
            status: WaitingListStatus::Notified->value,
            canAccept: true,
            canDecline: true,
            canConfirmArrival: true,
            canCancel: true,
            staffSeatRequired: true,
            nextStep: 'await_staff_seating',
        );

        $record = DB::table('waiting_list')->where('waiting_id', $waitingId)->first();
        $this->assertSame(WaitingListStatus::Notified->value, (string) $record->status);
        $this->assertSame('Accepted', (string) $record->customer_response_status);
        $this->assertNotNull($record->customer_responded_at);
        $this->assertNull($record->customer_confirmed_arrival_at);
        $this->assertSame($ownerId, (int) $record->updated_by);
    }

    public function test_owner_decline_cancels_entry_instead_of_returning_it_to_waiting_and_releases_notify_hold(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $ownerId = $this->createUser([
            'full_name' => 'Decline Owner',
            'phone' => '0909000444',
        ]);
        $ownerHeaders = $this->customerAuthHeaders($ownerId, 'sess-owner-contract-decline');
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'guest_name' => 'Decline Owner',
            'phone' => '0909000444',
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
        ]);

        $notify = $this->withHeaders($this->withIdempotencyKey('owner-contract-notify-decline', $this->staffAuthHeaders($staffId)))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notify->assertOk();

        $decline = $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-contract-decline-1'))
            ->postJson("/api/v1/waiting-list/{$waitingId}/decline", [
                'row_version' => (int) $notify->json('data.row_version'),
            ]);

        $decline->assertOk()
            ->assertJsonPath('data.status', WaitingListStatus::Cancelled->value)
            ->assertJsonPath('data.cancel_reason', 'Declined by customer');

        $this->assertCanonicalOwnerResource(
            $decline->json('data'),
            status: WaitingListStatus::Cancelled->value,
            canAccept: false,
            canDecline: false,
            canConfirmArrival: false,
            canCancel: false,
            staffSeatRequired: false,
            nextStep: 'closed',
        );

        $record = DB::table('waiting_list')->where('waiting_id', $waitingId)->first();
        $this->assertSame(WaitingListStatus::Cancelled->value, (string) $record->status);
        $this->assertSame('Declined', (string) $record->customer_response_status);
        $this->assertNotNull($record->customer_responded_at);
        $this->assertNull($record->customer_confirmed_arrival_at);
        $this->assertSame('Cancelled', (string) DB::table('table_holds')->where('session_id', 'waiting-list:'.$waitingId)->latest('created_at')->value('hold_status'));
    }

    public function test_owner_confirm_arrival_returns_staff_seat_meta_without_legacy_lifecycle_payload_or_persisted_customer_response_fields(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $ownerId = $this->createUser([
            'full_name' => 'Arrival Owner',
            'phone' => '0909000555',
        ]);
        $ownerHeaders = $this->customerAuthHeaders($ownerId, 'sess-owner-contract-confirm');
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'guest_name' => 'Arrival Owner',
            'phone' => '0909000555',
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
        ]);

        $notify = $this->withHeaders($this->withIdempotencyKey('owner-contract-notify-confirm', $this->staffAuthHeaders($staffId)))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notify->assertOk();

        $confirm = $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-contract-confirm-1'))
            ->postJson("/api/v1/waiting-list/{$waitingId}/confirm-arrival", [
                'row_version' => (int) $notify->json('data.row_version'),
            ]);

        $confirm->assertOk()
            ->assertJsonPath('meta.action', 'await_staff_seating')
            ->assertJsonPath('meta.staff_seat_required', true)
            ->assertJsonPath('meta.message', 'Đã xác nhận tới nơi. Nhân viên sẽ thực hiện seat khi sẵn sàng.');

        $this->assertCanonicalOwnerResource(
            $confirm->json('data'),
            status: WaitingListStatus::Notified->value,
            canAccept: true,
            canDecline: true,
            canConfirmArrival: true,
            canCancel: true,
            staffSeatRequired: true,
            nextStep: 'await_staff_seating',
        );

        $record = DB::table('waiting_list')->where('waiting_id', $waitingId)->first();
        $this->assertSame(WaitingListStatus::Notified->value, (string) $record->status);
        $this->assertSame('Accepted', (string) $record->customer_response_status);
        $this->assertNotNull($record->customer_responded_at);
        $this->assertNotNull($record->customer_confirmed_arrival_at);
        $this->assertSame($ownerId, (int) $record->updated_by);
    }

    public function test_non_owner_show_and_mutation_follow_current_access_contract(): void
    {
        $ownerId = $this->createUser([
            'full_name' => 'Owner User',
            'phone' => '0909000666',
        ]);
        $otherId = $this->createUser([
            'full_name' => 'Other User',
            'phone' => '0909000777',
        ]);
        $ownerHeaders = $this->customerAuthHeaders($ownerId, 'sess-owner-contract-non-owner');
        $otherHeaders = $this->customerAuthHeaders($otherId, 'sess-other-contract-non-owner');

        $waitingId = $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'status' => WaitingListStatus::Notified->value,
            'notified_at' => Carbon::now('UTC')->subMinute(),
            'notify_expires_at' => Carbon::now('UTC')->addMinutes(9),
            'row_version' => 1,
        ]);

        $this->withHeaders($otherHeaders)
            ->getJson('/api/v1/waiting-list/'.$waitingId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        $this->withHeaders($this->withIdempotencyKey($otherHeaders, 'cust-owner-contract-non-owner-decline'))
            ->postJson('/api/v1/waiting-list/'.$waitingId.'/decline', [
                'row_version' => 1,
            ])
            ->assertNotFound();

        $this->withHeaders($ownerHeaders)
            ->getJson('/api/v1/waiting-list/'.$waitingId)
            ->assertOk();
    }

    public function test_owner_cannot_accept_or_confirm_arrival_after_notify_window_has_expired(): void
    {
        $ownerId = $this->createUser([
            'full_name' => 'Expired Owner',
            'phone' => '0909000888',
        ]);
        $ownerHeaders = $this->customerAuthHeaders($ownerId, 'sess-owner-contract-expired');

        $waitingId = $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'guest_name' => 'Expired Owner',
            'phone' => '0909000888',
            'status' => WaitingListStatus::Notified->value,
            'notified_at' => Carbon::now('UTC')->subMinutes(11),
            'notify_expires_at' => Carbon::now('UTC')->subMinute(),
            'row_version' => 1,
        ]);

        $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-contract-accept-expired'))
            ->postJson("/api/v1/waiting-list/{$waitingId}/accept", [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notify_window']);

        $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-contract-confirm-expired'))
            ->postJson("/api/v1/waiting-list/{$waitingId}/confirm-arrival", [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notify_window']);
    }

    public function test_owner_cannot_accept_or_confirm_when_entry_is_not_in_notified_state(): void
    {
        $ownerId = $this->createUser([
            'full_name' => 'Invalid State Owner',
            'phone' => '0909000999',
        ]);
        $ownerHeaders = $this->customerAuthHeaders($ownerId, 'sess-owner-contract-invalid-state');

        $waitingId = $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'guest_name' => 'Invalid State Owner',
            'phone' => '0909000999',
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
        ]);

        $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-contract-accept-invalid-state'))
            ->postJson("/api/v1/waiting-list/{$waitingId}/accept", [
                'row_version' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        DB::table('waiting_list')->where('waiting_id', $waitingId)->update([
            'status' => WaitingListStatus::Cancelled->value,
            'cancelled_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ]);

        $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-contract-confirm-invalid-state'))
            ->postJson("/api/v1/waiting-list/{$waitingId}/confirm-arrival", [
                'row_version' => (int) DB::table('waiting_list')->where('waiting_id', $waitingId)->value('row_version'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_owner_can_cancel_owned_waiting_entry_with_row_version(): void
    {
        $ownerId = $this->createUser([
            'full_name' => 'Cancel Owner',
            'phone' => '0909001010',
        ]);
        $ownerHeaders = $this->customerAuthHeaders($ownerId, 'sess-owner-contract-cancel');

        $waitingId = $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'guest_name' => 'Cancel Owner',
            'phone' => '0909001010',
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 3,
        ]);

        $cancel = $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-contract-cancel-1'))
            ->postJson('/api/v1/waiting-list/'.$waitingId.'/cancel', [
                'row_version' => 3,
                'cancel_reason' => 'Change of plans',
            ]);

        $cancel->assertOk()
            ->assertJsonPath('data.status', WaitingListStatus::Cancelled->value)
            ->assertJsonPath('data.cancel_reason', 'Change of plans')
            ->assertJsonPath('data.can_cancel', false);

        $this->assertCanonicalOwnerResource(
            $cancel->json('data'),
            status: WaitingListStatus::Cancelled->value,
            canAccept: false,
            canDecline: false,
            canConfirmArrival: false,
            canCancel: false,
            staffSeatRequired: false,
            nextStep: 'closed',
        );
    }

    public function test_owner_create_rejects_when_active_waiting_entry_already_exists(): void
    {
        $ownerId = $this->createUser([
            'full_name' => 'Dup Owner',
            'phone' => '0909001111',
        ]);
        $ownerHeaders = $this->customerAuthHeaders($ownerId, 'sess-owner-contract-duplicate');

        $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'status' => WaitingListStatus::Waiting->value,
        ]);

        $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-contract-create-duplicate-1'))
            ->postJson('/api/v1/waiting-list', [
                'guest_count' => 2,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['waiting_list']);
    }

    public function test_expired_customer_access_session_is_rejected_for_owner_only_waiting_list_routes_even_with_session_header(): void
    {
        $ownerId = $this->createUser([
            'full_name' => 'Expired Auth Owner',
            'phone' => '0909001212',
        ]);
        $ownerHeaders = $this->customerAuthHeaders($ownerId, 'sess-owner-contract-expired-access');
        $token = (string) ($ownerHeaders['X-Customer-Token'] ?? '');

        DB::table('customer_access_sessions')
            ->where('token_hash', hash('sha256', $token))
            ->update([
                'expires_at' => Carbon::now('UTC')->subMinute(),
                'updated_at' => Carbon::now('UTC'),
            ]);

        $this->withHeaders($ownerHeaders)
            ->getJson('/api/v1/waiting-list')
            ->assertStatus(401)
            ->assertJsonPath('category_code', 'authentication_required');

        $this->withHeaders($this->withIdempotencyKey($ownerHeaders, 'cust-owner-contract-expired-access-create'))
            ->postJson('/api/v1/waiting-list', [
                'guest_count' => 2,
                'notes' => 'Expired owner auth must not downgrade into session flow.',
            ])
            ->assertStatus(401)
            ->assertJsonPath('category_code', 'authentication_required');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->withHeaders(['Idempotency-Key' => 'cust-owner-contract-create-unauth-1'])
            ->postJson('/api/v1/waiting-list', [
                'guest_count' => 2,
                'guest_name' => 'Guest User',
                'phone' => '0909001222',
            ])
            ->assertStatus(401)
            ->assertJsonPath('category_code', 'authentication_required');
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function assertCanonicalOwnerResource(
        array $data,
        string $status,
        bool $canAccept,
        bool $canDecline,
        bool $canConfirmArrival,
        bool $canCancel,
        bool $staffSeatRequired,
        string $nextStep,
    ): void {
        $this->assertArrayHasKey('waiting_id', $data);
        $this->assertSame($status, $data['status']);
        $this->assertSame($canAccept, (bool) ($data['can_accept'] ?? null));
        $this->assertSame($canDecline, (bool) ($data['can_decline'] ?? null));
        $this->assertSame($canConfirmArrival, (bool) ($data['can_confirm_arrival'] ?? null));
        $this->assertSame($canCancel, (bool) ($data['can_cancel'] ?? null));
        $this->assertSame($staffSeatRequired, (bool) ($data['staff_seat_required'] ?? null));
        $this->assertSame($nextStep, $data['next_step'] ?? null);

        $this->assertSame(
            $data['notify_window']['is_open'] ?? null,
            $data['window']['is_notified_window_open'] ?? null,
        );
        $this->assertSame($canAccept, (bool) ($data['notify_window']['is_open'] ?? null));
        $this->assertSame($canAccept, (bool) ($data['available_actions']['accept'] ?? null));
        $this->assertSame($canDecline, (bool) ($data['available_actions']['decline'] ?? null));
        $this->assertSame($canConfirmArrival, (bool) ($data['available_actions']['confirm_arrival'] ?? null));
        $this->assertSame($canCancel, (bool) ($data['available_actions']['cancel'] ?? null));
        $this->assertSame(true, (bool) ($data['arrival_confirmation']['supported'] ?? false));
        $this->assertSame($staffSeatRequired, (bool) ($data['arrival_confirmation']['staff_seat_required'] ?? null));

        $this->assertLegacyLifecycleFieldsAreAbsent($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function assertLegacyLifecycleFieldsAreAbsent(array $data): void
    {
        $this->assertArrayNotHasKey('current_response_state', $data);
        $this->assertArrayNotHasKey('invite_window', $data);
        $this->assertArrayNotHasKey('response', $data);
        $this->assertArrayNotHasKey('invite_lifecycle', $data);
        $this->assertArrayNotHasKey('actions', $data);
    }
}
