<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffBranchAssignmentContextTest extends TestCase
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
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.api_keys', []);
        config()->set('staff_capabilities.production_like_environments', ['production']);
        config()->set('staff_capabilities.deny_operational_role_branch_fallback_in_production_like', true);
    }

    public function test_same_role_staff_see_different_branches_from_staff_assignments(): void
    {
        $firstBranchId = $this->createBranch([
            'branch_code' => 'ASSIGN-A',
            'branch_name' => 'Assigned A',
        ]);
        $secondBranchId = $this->createBranch([
            'branch_code' => 'ASSIGN-B',
            'branch_name' => 'Assigned B',
        ]);

        $firstStaffId = $this->createUser(['role_name' => 'Staff']);
        $secondStaffId = $this->createUser(['role_name' => 'Staff']);
        $this->assignStaffBranch($firstStaffId, $firstBranchId);
        $this->assignStaffBranch($secondStaffId, $secondBranchId);

        $firstHeaders = $this->staffAuthHeaders($firstStaffId, 'staff-branch-assignment-a');
        $secondHeaders = $this->staffAuthHeaders($secondStaffId, 'staff-branch-assignment-b');

        $this->withHeaders($firstHeaders)
            ->getJson('/api/v1/staff/branches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch_id', $firstBranchId)
            ->assertJsonPath('meta.branch_access.access_source', 'staff_branch_assignments')
            ->assertJsonPath('meta.branch_access.current_branch_id', $firstBranchId);

        $this->withHeaders($secondHeaders)
            ->getJson('/api/v1/staff/branches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch_id', $secondBranchId)
            ->assertJsonPath('meta.branch_access.access_source', 'staff_branch_assignments')
            ->assertJsonPath('meta.branch_access.current_branch_id', $secondBranchId);

        $this->withHeaders($firstHeaders)
            ->getJson('/api/v1/staff/tables/board?branch_id='.$secondBranchId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('message', 'Branch not found.');
    }

    public function test_staff_without_branch_assignment_keeps_dev_role_scope_fallback_outside_production_like(): void
    {
        config()->set('app.env', 'testing');

        $staffId = $this->createUser(['role_name' => 'Staff']);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'NOASSIGN',
            'branch_name' => 'No Assignment Annex',
        ]);

        $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-no-assignment-fallback'))
            ->getJson('/api/v1/staff/branches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch_id', 1)
            ->assertJsonPath('meta.branch_access.access_source', 'role_branch_scopes')
            ->assertJsonPath('meta.branch_access.accessible_branch_ids', [1]);

        $this->withHeaders($this->staffAuthHeaders($staffId, 'staff-no-assignment-fallback'))
            ->getJson('/api/v1/staff/tables/board?branch_id='.$annexBranchId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_operational_staff_without_branch_assignment_has_no_default_branch_scope_in_production_like_mode(): void
    {
        config()->set('app.env', 'production');

        $branchContext = app(StaffBranchContextService::class);

        foreach (['Staff', 'Server', 'Waiter', 'Cashier', 'Kitchen'] as $roleName) {
            $staffId = $this->createUser(['role_name' => $roleName]);
            $context = $branchContext->branchAccessContext($staffId);

            self::assertSame([], $context['accessible_branch_ids'], $roleName);
            self::assertSame('explicit_branch_assignment_required', $context['access_source'], $roleName);
            self::assertNull($context['current_branch_id'], $roleName);
            self::assertFalse($context['has_default_branch_access'], $roleName);
            self::assertFalse($context['has_multi_branch_access'], $roleName);
        }
    }

    public function test_admin_wildcard_still_has_multi_branch_access_without_assignments(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        $firstBranchId = $this->createBranch([
            'branch_code' => 'ADMIN-A',
            'branch_name' => 'Admin A',
        ]);
        $secondBranchId = $this->createBranch([
            'branch_code' => 'ADMIN-B',
            'branch_name' => 'Admin B',
        ]);

        $response = $this->withHeaders($this->staffAuthHeaders($adminId, 'admin-branch-wildcard'))
            ->getJson('/api/v1/staff/branches');

        $response->assertOk()
            ->assertJsonPath('meta.branch_access.has_multi_branch_access', true)
            ->assertJsonPath('meta.branch_access.access_source', 'role_branch_scopes');

        $branchIds = collect($response->json('meta.branch_access.accessible_branch_ids'))
            ->map(static fn ($branchId): int => (int) $branchId)
            ->all();

        self::assertContains(1, $branchIds);
        self::assertContains($firstBranchId, $branchIds);
        self::assertContains($secondBranchId, $branchIds);
    }

    private function assignStaffBranch(int $staffId, int $branchId, bool $primary = true): void
    {
        DB::table('staff_branch_assignments')->insert([
            'user_id' => $staffId,
            'branch_id' => $branchId,
            'is_primary' => $primary ? 1 : 0,
            'assigned_at' => now('UTC'),
            'revoked_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }
}
