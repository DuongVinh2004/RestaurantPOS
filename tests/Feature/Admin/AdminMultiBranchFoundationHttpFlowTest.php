<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminMultiBranchFoundationHttpFlowTest extends TestCase
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
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.env_fallback_allowed_environments', ['testing']);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_admin_can_manage_branches_and_switch_default_branch(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-branches-manage-key');

        $list = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/settings/branches');

        $list->assertOk()
            ->assertJsonPath('meta.action', 'admin_branches_index')
            ->assertJsonPath('data.0.branch_code', 'MAIN')
            ->assertJsonPath('data.0.is_default', true);

        $create = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-branch-create'))
            ->postJson('/api/v1/admin/settings/branches', [
                'branch_code' => 'HCM01',
                'branch_name' => 'Ho Chi Minh 01',
                'description' => 'Secondary operating branch',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'is_active' => true,
            ]);

        $create->assertCreated()
            ->assertJsonPath('meta.action', 'admin_branches_created')
            ->assertJsonPath('data.branch_code', 'HCM01')
            ->assertJsonPath('data.branch_name', 'Ho Chi Minh 01')
            ->assertJsonPath('data.is_default', false);

        $branchId = (int) $create->json('data.branch_id');
        $rowVersion = (int) $create->json('data.row_version');

        $update = $this->withHeaders($headers)
            ->patchJson('/api/v1/admin/settings/branches/'.$branchId, [
                'row_version' => $rowVersion,
                'is_default' => true,
                'description' => 'Promoted to default branch',
            ]);

        $update->assertOk()
            ->assertJsonPath('meta.action', 'admin_branches_updated')
            ->assertJsonPath('data.branch_id', $branchId)
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.description', 'Promoted to default branch');

        $show = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/settings/branches/'.$branchId);

        $show->assertOk()
            ->assertJsonPath('data.branch_id', $branchId)
            ->assertJsonPath('data.is_default', true);

        $mainBranch = $this->table('branches')->where('branch_code', 'MAIN')->first();
        self::assertNotNull($mainBranch);
        self::assertFalse((bool) $mainBranch->is_default);
        self::assertSame($adminId, $adminId);
    }

    public function test_non_admin_staff_cannot_access_branch_settings_routes(): void
    {
        $staffRoleId = $this->ensureRole('Staff');
        $staffId = $this->createUser(['role_id' => $staffRoleId, 'role_name' => 'Staff']);
        config()->set('staff_auth.allowed_role_ids', [$staffRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $staffRoleId => ['inventory.manage'],
        ]);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'non-admin-branch-settings'))
            ->getJson('/api/v1/admin/settings/branches');

        $response->assertForbidden()
            ->assertJsonPath('required_capability', 'settings.manage');
    }

    public function test_admin_branch_payload_accepts_and_returns_scheduling_policy_fields(): void
    {
        [, $headers] = $this->adminHeaders('admin-branches-policy-key');

        $payload = [
            'branch_code' => 'DNG02',
            'branch_name' => 'Da Nang 02',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
            'business_hours' => collect(range(0, 6))
                ->map(static fn (int $day): array => [
                    'day_of_week' => $day,
                    'periods' => [[
                        'start_time' => '09:00',
                        'end_time' => '21:00',
                    ]],
                ])
                ->all(),
            'closure_windows' => [[
                'start_local' => '2026-12-31 18:00:00',
                'end_local' => '2026-12-31 23:00:00',
                'type' => 'holiday',
                'reason' => 'Su kien cuoi nam',
            ]],
            'booking_policy' => [
                'reservation' => [
                    'min_lead_time_minutes' => 90,
                    'same_day_cutoff_time' => '18:00',
                ],
                'waiting_list' => [
                    'enabled' => false,
                ],
            ],
        ];

        $create = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-branch-policy-create'))
            ->postJson('/api/v1/admin/settings/branches', $payload);

        $create->assertCreated()
            ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh')
            ->assertJsonPath('data.business_hours.0.periods.0.start_time', '09:00')
            ->assertJsonPath('data.closure_windows.0.reason', 'Su kien cuoi nam')
            ->assertJsonPath('data.booking_policy.reservation.min_lead_time_minutes', 90)
            ->assertJsonPath('data.booking_policy.waiting_list.enabled', false);
    }

    public function test_admin_branch_update_rejects_stale_row_version_with_conflict_contract(): void
    {
        [, $headers] = $this->adminHeaders('admin-branches-stale-row-version-key');

        $create = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-branch-create-stale'))
            ->postJson('/api/v1/admin/settings/branches', [
                'branch_code' => 'STALE01',
                'branch_name' => 'Stale Branch',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'is_active' => true,
            ]);

        $create->assertCreated();

        $branchId = (int) $create->json('data.branch_id');
        $staleRowVersion = (int) $create->json('data.row_version');

        DB::table('branches')
            ->where('branch_id', $branchId)
            ->update(['row_version' => $staleRowVersion + 1]);

        $response = $this->withHeaders(array_merge($headers, [
            'X-Request-Id' => 'req-admin-branch-stale-row-version',
        ]))->patchJson('/api/v1/admin/settings/branches/'.$branchId, [
            'row_version' => $staleRowVersion,
            'description' => 'This update should be rejected as stale.',
        ]);

        $response->assertStatus(409)
            ->assertHeader('X-Request-Id', 'req-admin-branch-stale-row-version')
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('category_code', 'stale_write')
            ->assertJsonPath('request_id', 'req-admin-branch-stale-row-version')
            ->assertJsonPath('details.errors.row_version.0', 'Branch has been modified by another operation. Please reload and retry.');
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    private function adminHeaders(string $apiKey): array
    {
        $adminRoleId = $this->ensureRole('Admin');
        $adminId = $this->createUser(['role_id' => $adminRoleId, 'role_name' => 'Admin']);

        config()->set('staff_auth.allowed_role_ids', [$adminRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $adminRoleId => ['*'],
        ]);

        return [$adminId, $this->staffHeaders($adminId, $apiKey)];
    }
}
