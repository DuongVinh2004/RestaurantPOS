<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\RequireStaffCapability;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class SecurityAuthRbacBranchIsolationLadderTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();

        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('booking.customer_session_exact_link_access_hours', 24);
        config()->set('booking.customer_session_legacy_access_hours', 0);

        $this->app->instance(ReservationLockService::class, $this->mockReservationLocks());
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_security_ladder_staff_invalid_key_is_denied(): void
    {
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', false);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => 'security-ladder-invalid-key',
        ])->getJson('/api/v1/auth/staff/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('category_code', 'authentication_required');
    }

    public function test_security_ladder_staff_env_fallback_is_disabled_in_prod_like_config(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);

        config()->set('app.env', 'production');
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.api_keys', ['security-ladder-prod-fallback-key' => $staffId]);
        config()->set('staff_auth.env_fallback_allowed_environments', ['production']);
        config()->set('staff_auth.production_like_environments', ['production']);
        config()->set('staff_auth.deny_env_fallback_in_production_like', true);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => 'security-ladder-prod-fallback-key',
        ])->getJson('/api/v1/auth/staff/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('category_code', 'authentication_required');
    }

    public function test_security_ladder_staff_role_mismatch_is_denied(): void
    {
        $customerId = $this->createUser(['role_name' => 'Customer']);

        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allowed_role_ids', [
            $this->ensureRole('Admin'),
            $this->ensureRole('Staff'),
        ]);
        config()->set('staff_auth.api_keys', ['security-ladder-customer-as-staff' => $customerId]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => 'security-ladder-customer-as-staff',
        ])->getJson('/api/v1/staff/branches')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('category_code', 'policy_denied')
            ->assertJsonMissingPath('required_capability');
    }

    public function test_security_ladder_missing_capability_is_denied(): void
    {
        $kitchenId = $this->createUser(['role_name' => 'Kitchen']);

        $this->withHeaders($this->staffAuthHeaders($kitchenId, 'security-ladder-kitchen-key'))
            ->getJson('/api/v1/staff/cashier/shifts')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'cashier.shift.manage')
            ->assertJsonPath('state_reason', 'missing_required_capability');
    }

    public function test_security_ladder_unknown_capability_respects_enforce_known_flag(): void
    {
        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
        ]);

        config()->set('staff_capabilities.enforce_known_capabilities', true);
        config()->set('staff_capabilities.known_capabilities', ['reservation.manage']);

        $enforcedRequest = Request::create('/__testing__/security-ladder/staff-capability', 'GET');
        $enforcedRequest->attributes->set('staff_actor_role_name', 'Admin');

        $enforced = app(RequireStaffCapability::class)->handle(
            $enforcedRequest,
            fn () => response()->json(['ok' => true]),
            'security.unknown',
        );

        /** @var array<string,mixed> $enforcedPayload */
        $enforcedPayload = json_decode((string) $enforced->getContent(), true);

        self::assertSame(500, $enforced->getStatusCode());
        self::assertSame('staff_capability_not_registered', $enforcedPayload['error_code'] ?? null);
        self::assertSame('capability_contract_missing', $enforcedPayload['state_reason'] ?? null);

        config()->set('staff_capabilities.enforce_known_capabilities', false);

        $unenforcedRequest = Request::create('/__testing__/security-ladder/staff-capability', 'GET');
        $unenforcedRequest->attributes->set('staff_actor_role_name', 'Admin');

        $unenforced = app(RequireStaffCapability::class)->handle(
            $unenforcedRequest,
            fn () => response()->json(['ok' => true]),
            'security.unknown',
        );

        /** @var array<string,mixed> $unenforcedPayload */
        $unenforcedPayload = json_decode((string) $unenforced->getContent(), true);

        self::assertSame(200, $unenforced->getStatusCode());
        self::assertSame(['ok' => true], $unenforcedPayload);
    }

    public function test_security_ladder_staff_branch_a_cannot_access_branch_b_reservation_detail(): void
    {
        $allowedBranchId = $this->createBranch([
            'branch_code' => 'SEC-LAD-A',
            'branch_name' => 'Security Ladder A',
        ]);
        $deniedBranchId = $this->createBranch([
            'branch_code' => 'SEC-LAD-B',
            'branch_name' => 'Security Ladder B',
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'email' => 'security-ladder-denied@example.test',
        ]);
        $this->assignStaffBranch($staffId, $allowedBranchId);

        $reservationId = $this->createReservation([
            'branch_id' => $deniedBranchId,
            'user_id' => $customerId,
            'notes' => 'security ladder denied reservation detail',
        ]);
        $this->attachReservationTable(
            $reservationId,
            $this->createRestaurantTableWithSeats(4, ['branch_id' => $deniedBranchId]),
        );

        $response = $this->withHeaders($this->staffAuthHeaders($staffId, 'security-ladder-branch-reservation'))
            ->getJson('/api/v1/reservations/'.$reservationId);

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');

        self::assertStringNotContainsString('security-ladder-denied@example.test', (string) $response->getContent());
    }

    public function test_security_ladder_staff_branch_a_cannot_release_or_probe_branch_b_table_state(): void
    {
        $allowedBranchId = $this->createBranch([
            'branch_code' => 'SEC-TABLE-A',
            'branch_name' => 'Security Table A',
        ]);
        $deniedBranchId = $this->createBranch([
            'branch_code' => 'SEC-TABLE-B',
            'branch_name' => 'Security Table B',
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->assignStaffBranch($staffId, $allowedBranchId);

        $tableId = $this->createRestaurantTable([
            'branch_id' => $deniedBranchId,
            'status' => 'Occupied',
            'row_version' => 1,
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey(
            $this->staffAuthHeaders($staffId, 'security-ladder-branch-table'),
            'security-ladder-branch-table-release',
        ))->postJson('/api/v1/staff/tables/'.$tableId.'/release', [
            'row_version' => 1,
        ]);

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('category_code', 'not_found');

        $payload = (string) json_encode($response->json(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        self::assertStringNotContainsString('Cannot release table', $payload);
        self::assertStringNotContainsString('active service context', $payload);
        self::assertStringNotContainsString('reservation', $payload);
        self::assertStringNotContainsString('order', $payload);
    }

    public function test_security_ladder_customer_cannot_view_another_customers_reservation(): void
    {
        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $viewerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'notes' => 'security ladder foreign reservation',
        ]);

        $this->withHeaders($this->customerAuthHeaders($viewerId, 'security-ladder-viewer-session'))
            ->getJson('/api/v1/reservations/'.$reservationId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonMissingPath('data');
    }

    public function test_security_ladder_customer_session_hold_access_stays_valid_for_allowed_self_service_flow(): void
    {
        $sessionId = 'security-ladder-self-service-session';
        $branchId = $this->createBranch([
            'branch_code' => 'SEC-SESS',
            'branch_name' => 'Security Session Branch',
        ]);
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable(['branch_id' => $branchId]);
        $start = $this->nowUtc()->copy()->addHour();
        $end = $start->copy()->addHours(2);
        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $this->createTableHold([
            'hold_id' => 'security-ladder-linked-hold',
            'branch_id' => $branchId,
            'session_id' => $sessionId,
            'user_id' => $customerId,
            'confirmed_reservation_id' => $reservationId,
            'hold_status' => 'Confirmed',
            'start_time' => $start,
            'end_time' => $end,
            'expire_at' => $this->nowUtc()->copy()->addHour(),
        ], [$tableId]);

        $this->getJson('/api/v1/reservations/'.$reservationId.'?session_id='.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.access_scope', 'session')
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.user.email', null)
            ->assertJsonPath('data.user.phone', null);
    }

    private function assignStaffBranch(int $staffId, int $branchId): void
    {
        DB::table('staff_branch_assignments')->insert([
            'user_id' => $staffId,
            'branch_id' => $branchId,
            'is_primary' => 1,
            'assigned_at' => $this->nowUtc(),
            'revoked_at' => null,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);
    }
}
