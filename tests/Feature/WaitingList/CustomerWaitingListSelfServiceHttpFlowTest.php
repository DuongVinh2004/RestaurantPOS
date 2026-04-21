<?php

declare(strict_types=1);

namespace Tests\Feature\WaitingList;

use App\Enums\WaitingListStatus;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerWaitingListSelfServiceHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('customer_auth.enabled', false);
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_legacy_guest_session_self_service_contract_is_rejected_under_owner_only_waiting_list_flow(): void
    {
        $sessionId = 'sess-legacy-self-service-rejected';

        $this->withHeaders([
            'Idempotency-Key' => 'cust-waiting-legacy-session-create',
            'X-Session-Id' => $sessionId,
        ])->postJson('/api/v1/waiting-list', [
            'session_id' => $sessionId,
            'guest_name' => 'Legacy Guest Session',
            'phone' => '0909888777',
            'guest_count' => 2,
        ])->assertStatus(403)
            ->assertJsonPath('category_code', 'owner_scope_denied');
    }

    public function test_owner_resource_uses_canonical_shape_and_omits_legacy_self_service_lifecycle_fields(): void
    {
        $ownerId = $this->createUser([
            'full_name' => 'Owner Canonical',
            'phone' => '0909002111',
        ]);
        $owner = User::query()->findOrFail($ownerId);

        $create = $this->actingAs($owner)
            ->withHeaders(['Idempotency-Key' => 'cust-waiting-canonical-shape'])
            ->postJson('/api/v1/waiting-list', [
                'guest_count' => 3,
                'notes' => 'Canonical owner-only contract',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', WaitingListStatus::Waiting->value)
            ->assertJsonPath('data.next_step', 'await_notification')
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.available_actions.cancel', true)
            ->assertJsonPath('data.notify_window.is_open', false)
            ->assertJsonPath('data.window.is_notified_window_open', false)
            ->assertJsonMissingPath('data.current_response_state')
            ->assertJsonMissingPath('data.invite_window')
            ->assertJsonMissingPath('data.response')
            ->assertJsonMissingPath('data.invite_lifecycle')
            ->assertJsonMissingPath('data.actions');
    }

    public function test_owner_decline_canonical_semantics_cancel_entry_instead_of_returning_to_waiting(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $ownerId = $this->createUser([
            'full_name' => 'Decline Canonical',
            'phone' => '0909002222',
        ]);
        $owner = User::query()->findOrFail($ownerId);
        $tableId = $this->createRestaurantTableWithSeats(4, ['status' => 'Available']);
        $waitingId = $this->createWaitingListEntry([
            'user_id' => $ownerId,
            'guest_name' => 'Decline Canonical',
            'phone' => '0909002222',
            'status' => WaitingListStatus::Waiting->value,
            'row_version' => 1,
        ]);

        $notify = $this->withHeaders($this->withIdempotencyKey('legacy-file-notify-decline', $this->staffAuthHeaders($staffId)))
            ->postJson("/api/v1/staff/waiting-list/{$waitingId}/notify", [
                'table_id' => $tableId,
                'hold_minutes' => 10,
                'row_version' => 1,
            ]);
        $notify->assertOk();

        $decline = $this->actingAs($owner)
            ->withHeaders(['Idempotency-Key' => 'cust-waiting-canonical-decline'])
            ->postJson("/api/v1/waiting-list/{$waitingId}/decline", [
                'row_version' => (int) $notify->json('data.row_version'),
            ]);

        $decline->assertOk()
            ->assertJsonPath('data.status', WaitingListStatus::Cancelled->value)
            ->assertJsonPath('data.cancel_reason', 'Declined by customer')
            ->assertJsonPath('data.next_step', 'closed')
            ->assertJsonMissingPath('data.current_response_state')
            ->assertJsonMissingPath('data.response');

        $record = DB::table('waiting_list')->where('waiting_id', $waitingId)->first();

        $this->assertSame(WaitingListStatus::Cancelled->value, (string) $record->status);
        $this->assertSame('Declined', (string) $record->customer_response_status);
        $this->assertNotNull($record->customer_responded_at);
        $this->assertNull($record->customer_confirmed_arrival_at);
    }
}
