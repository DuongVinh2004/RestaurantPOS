<?php

declare(strict_types=1);

namespace Tests\Feature\Staff\FloorOperations;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

/**
 * Tests for the read-only Staff Command Center endpoint.
 */
class StaffCommandCenterHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/staff/operations/command-center');
        $response->assertStatus(401);
    }

    public function test_authenticated_staff_gets_command_center_response(): void
    {
        $userId = $this->createUser(['role_name' => 'Admin']);

        $response = $this->withHeaders($this->staffAuthHeaders($userId))
            ->getJson('/api/v1/staff/operations/command-center');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'summary' => [
                    'open_actions',
                    'high_priority',
                    'deposit_pending',
                    'preorder_pending',
                    'payment_pending',
                    'reservation_upcoming',
                ],
                'actions',
            ],
            'meta' => ['limit'],
        ]);

        // Summary values should be non-negative integers
        $summary = $response->json('data.summary');
        $this->assertGreaterThanOrEqual(0, $summary['open_actions']);
        $this->assertGreaterThanOrEqual(0, $summary['high_priority']);
    }

    public function test_empty_state_does_not_crash(): void
    {
        $userId = $this->createUser(['role_name' => 'Admin']);

        $response = $this->withHeaders($this->staffAuthHeaders($userId))
            ->getJson('/api/v1/staff/operations/command-center?horizon_hours=1&limit=1');

        $response->assertStatus(200);
        $actions = $response->json('data.actions');
        $this->assertIsArray($actions);
    }

    public function test_deposit_pending_action_appears_for_matching_reservation(): void
    {
        $userId = $this->createUser(['role_name' => 'Admin']);

        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'deposit_status' => 'Pending',
            'deposit_required_amount' => '200000',
            'deposit_paid_amount' => '0',
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(3),
        ]);

        $response = $this->withHeaders($this->staffAuthHeaders($userId))
            ->getJson('/api/v1/staff/operations/command-center?type=deposit_pending');

        $response->assertStatus(200);

        $actions = $response->json('data.actions');
        $depositActions = array_filter($actions, fn ($a) => $a['type'] === 'deposit_pending');
        $this->assertNotEmpty($depositActions, 'Expected at least one deposit_pending action');

        $found = collect($depositActions)->firstWhere('entity_id', $reservationId);
        $this->assertNotNull($found, "Expected deposit_pending action for reservation #{$reservationId}");
    }

    public function test_waiting_list_pending_appears_for_active_entry(): void
    {
        $userId = $this->createUser(['role_name' => 'Admin']);

        $this->createWaitingListEntry([
            'status' => 'Waiting',
            'guest_name' => 'Pending Guest',
            'guest_count' => 2,
        ]);

        $response = $this->withHeaders($this->staffAuthHeaders($userId))
            ->getJson('/api/v1/staff/operations/command-center?type=waiting_list_pending');

        $response->assertStatus(200);

        $actions = $response->json('data.actions');
        $waitingActions = array_filter($actions, fn ($a) => $a['type'] === 'waiting_list_pending');
        $this->assertNotEmpty($waitingActions, 'Expected at least one waiting_list_pending action');
    }

    public function test_type_filter_restricts_actions(): void
    {
        $userId = $this->createUser(['role_name' => 'Admin']);

        $response = $this->withHeaders($this->staffAuthHeaders($userId))
            ->getJson('/api/v1/staff/operations/command-center?type=reservation_upcoming');

        $response->assertStatus(200);

        $actions = $response->json('data.actions');
        foreach ($actions as $action) {
            $this->assertEquals('reservation_upcoming', $action['type'], 'Filter should restrict to only the requested type');
        }
    }

    public function test_priority_filter_restricts_actions(): void
    {
        $userId = $this->createUser(['role_name' => 'Admin']);

        $response = $this->withHeaders($this->staffAuthHeaders($userId))
            ->getJson('/api/v1/staff/operations/command-center?priority=high');

        $response->assertStatus(200);

        $actions = $response->json('data.actions');
        foreach ($actions as $action) {
            $this->assertEquals('high', $action['priority'], 'Filter should restrict to only high priority');
        }
    }

    public function test_limit_is_respected(): void
    {
        $userId = $this->createUser(['role_name' => 'Admin']);

        $response = $this->withHeaders($this->staffAuthHeaders($userId))
            ->getJson('/api/v1/staff/operations/command-center?limit=2');

        $response->assertStatus(200);

        $actions = $response->json('data.actions');
        $this->assertLessThanOrEqual(2, count($actions), 'Actions should not exceed the limit');
        $this->assertEquals(2, $response->json('meta.limit'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Create a basic waiting list entry.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createWaitingListEntry(array $overrides = []): int
    {
        $defaults = [
            'branch_id' => 1,
            'guest_name' => 'Test Guest',
            'guest_count' => 2,
            'status' => 'Waiting',
            'requested_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        return (int) DB::table('waiting_list')->insertGetId(array_merge($defaults, $overrides));
    }
}
