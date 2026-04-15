<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminBenefitsAdminFoundationHttpFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
        Carbon::setTestNow(Carbon::parse('2026-03-21 12:00:00', 'UTC'));
    }

    public function test_admin_can_manage_voucher_master_data_and_linked_voucher_fields_are_guarded(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-benefits-voucher-key');

        $createResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/benefits/vouchers', [
                'code' => 'ADM-VC-10',
                'description' => 'Admin voucher',
                'discount_type' => 'Fixed',
                'discount_value' => '50000',
                'max_usage' => 10,
                'max_usage_per_user' => 1,
                'min_spend' => '200000',
                'is_active' => true,
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('meta.action', 'admin_voucher_created')
            ->assertJsonPath('data.code', 'ADM-VC-10')
            ->assertJsonPath('data.usage_summary.assigned_count', 0)
            ->assertJsonPath('data.created_by', $adminId);

        $voucherId = (int) $createResponse->json('data.voucher_id');
        $rowVersion = (int) $createResponse->json('data.row_version');

        $listResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/benefits/vouchers?q=ADM-VC-10');

        $listResponse
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_vouchers')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'ADM-VC-10');

        $updateResponse = $this->withHeaders($headers)
            ->patchJson('/api/v1/admin/benefits/vouchers/'.$voucherId, [
                'row_version' => $rowVersion,
                'description' => 'Admin voucher updated',
                'expiry_date' => Carbon::now('UTC')->addDays(10)->toIso8601String(),
                'is_active' => false,
            ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_voucher_updated')
            ->assertJsonPath('data.description', 'Admin voucher updated')
            ->assertJsonPath('data.availability.is_active', false);

        $createdLog = $this->assertAuditLogRecorded('master_data.voucher.created', 'voucher', $voucherId);
        self::assertSame($adminId, $createdLog->actor_user_id);
        self::assertSame('staff_user', $createdLog->actor_type);

        $updatedLog = $this->assertAuditLogRecorded('master_data.voucher.updated', 'voucher', $voucherId);
        self::assertSame($adminId, $updatedLog->actor_user_id);
        self::assertSame('staff_user', $updatedLog->actor_type);
        self::assertSame('Admin voucher updated', (string) data_get($updatedLog->after_json, 'description'));

        $userId = $this->createUser();
        $this->assignVoucher([
            'user_id' => $userId,
            'voucher_id' => $voucherId,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
        $freshVoucher = DB::table('vouchers')->where('voucher_id', $voucherId)->first();

        $guardedResponse = $this->withHeaders($headers)
            ->patchJson('/api/v1/admin/benefits/vouchers/'.$voucherId, [
                'row_version' => (int) $freshVoucher->row_version,
                'discount_value' => '99999.00',
            ]);

        $guardedResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_value']);
    }

    public function test_admin_can_manage_loyalty_tiers_and_cannot_deactivate_tier_with_current_users(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-benefits-loyalty-key');

        $createResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/benefits/loyalty-tiers', [
                'tier_code' => 'GOLD',
                'tier_name' => 'Gold',
                'min_points' => 1000,
                'benefits_json' => [
                    'priority_booking' => true,
                    'discount_percent' => 5,
                ],
                'is_active' => true,
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('meta.action', 'admin_loyalty_tier_created')
            ->assertJsonPath('data.tier_code', 'GOLD')
            ->assertJsonPath('data.benefits_json.discount_percent', 5);

        $tierId = (int) $createResponse->json('data.tier_id');
        $rowVersion = (int) $createResponse->json('data.row_version');

        $listResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/benefits/loyalty-tiers');

        $listResponse
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_loyalty_tiers');

        $updateResponse = $this->withHeaders($headers)
            ->patchJson('/api/v1/admin/benefits/loyalty-tiers/'.$tierId, [
                'row_version' => $rowVersion,
                'tier_name' => 'Gold Elite',
                'min_points' => 1500,
            ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.tier_name', 'Gold Elite')
            ->assertJsonPath('data.min_points', 1500);

        $createdLog = $this->assertAuditLogRecorded('master_data.loyalty_tier.created', 'loyalty_tier', $tierId);
        self::assertSame($adminId, $createdLog->actor_user_id);
        self::assertSame('staff_user', $createdLog->actor_type);

        $updatedLog = $this->assertAuditLogRecorded('master_data.loyalty_tier.updated', 'loyalty_tier', $tierId);
        self::assertSame($adminId, $updatedLog->actor_user_id);
        self::assertSame('staff_user', $updatedLog->actor_type);
        self::assertSame('Gold Elite', (string) data_get($updatedLog->after_json, 'tier_name'));

        $userId = $this->createUser(['current_tier_id' => $tierId]);
        self::assertGreaterThan(0, $userId);

        $freshTier = DB::table('loyalty_tiers')->where('tier_id', $tierId)->first();
        $guardedResponse = $this->withHeaders($headers)
            ->patchJson('/api/v1/admin/benefits/loyalty-tiers/'.$tierId, [
                'row_version' => (int) $freshTier->row_version,
                'is_active' => false,
            ]);

        $guardedResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_admin_can_list_and_update_whitelisted_benefit_runtime_settings_with_concurrency_guard(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-benefits-settings-key');

        $listResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/settings/benefits');

        $listResponse
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_benefit_runtime_settings')
            ->assertJsonPath('meta.count', 5);

        $createResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/settings/benefits', [
                'setting_key' => 'loyalty.min_redeem_points',
                'value' => 25,
            ]);

        $createResponse
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_benefit_runtime_setting_upserted')
            ->assertJsonPath('data.setting_key', 'loyalty.min_redeem_points')
            ->assertJsonPath('data.effective_value', 25)
            ->assertJsonPath('data.updated_by', $adminId);

        $updatedAt = (string) $createResponse->json('data.updated_at');
        self::assertSame(25, app(RuntimeSettingService::class)->int('loyalty.min_redeem_points', 1));

        $guardedResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/settings/benefits', [
                'setting_key' => 'loyalty.min_redeem_points',
                'value' => 30,
            ]);

        $guardedResponse
            ->assertStatus(409)
            ->assertJsonValidationErrors(['expected_updated_at']);

        $updateResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/settings/benefits', [
                'setting_key' => 'loyalty.min_redeem_points',
                'value' => 30,
                'expected_updated_at' => $updatedAt,
            ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.setting_key', 'loyalty.min_redeem_points')
            ->assertJsonPath('data.effective_value', 30)
            ->assertJsonPath('data.source', 'runtime');
    }

    public function test_non_admin_staff_is_forbidden_from_admin_benefits_routes(): void
    {
        $staffRoleId = $this->ensureRole('Staff');
        $staffId = $this->createUser(['role_id' => $staffRoleId, 'role_name' => 'Staff']);
        config()->set('staff_auth.allowed_role_ids', [$staffRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $staffRoleId => ['voucher.manage', 'loyalty.view'],
        ]);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'non-admin-benefits'))
            ->getJson('/api/v1/admin/benefits/vouchers');

        $response
            ->assertForbidden()
            ->assertJsonPath('required_capability', 'voucher.master_data.manage');
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

    /**
     * @return array<string,string>
     */
    private function staffHeaders(int $staffId, string $apiKey): array
    {
        return $this->staffAuthHeaders($staffId, $apiKey);
    }
}
