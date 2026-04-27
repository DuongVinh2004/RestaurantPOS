<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminFinanceTaxInvoiceFoundationHttpFlowTest extends TestCase
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

    public function test_admin_can_show_and_update_finance_tax_profile_with_concurrency_guard(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-finance-settings-key');

        $show = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/settings/finance/tax-profile');

        $show->assertOk()
            ->assertJsonPath('meta.action', 'admin_finance_tax_profile_show')
            ->assertJsonPath('data.effective_profile.tax_code', 'VAT10')
            ->assertJsonPath('data.effective_profile.prices_include_tax', true);

        $createPayload = [
            'tax_code' => 'VAT08',
            'tax_name' => 'VAT 8%',
            'tax_rate_percentage' => 8,
            'prices_include_tax' => true,
            'invoice_prefix' => 'HDDT',
            'seller_name' => 'Restaurant POS Test',
            'seller_tax_id' => '0301234567',
            'seller_address' => '123 Nguyen Hue',
        ];
        if ($show->json('data.updated_at') !== null) {
            $createPayload['expected_updated_at'] = (string) $show->json('data.updated_at');
        }

        $create = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-finance-profile-upsert-a'))
            ->postJson('/api/v1/admin/settings/finance/tax-profile', $createPayload);

        $create->assertOk()
            ->assertJsonPath('meta.action', 'admin_finance_tax_profile_upserted')
            ->assertJsonPath('data.source', 'runtime')
            ->assertJsonPath('data.effective_profile.tax_code', 'VAT08')
            ->assertJsonPath('data.effective_profile.tax_rate_percentage', 8.0)
            ->assertJsonPath('data.updated_by', $adminId);

        $updatedAt = (string) $create->json('data.updated_at');

        $guarded = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-finance-profile-upsert-b'))
            ->postJson('/api/v1/admin/settings/finance/tax-profile', [
                'tax_code' => 'VAT05',
                'tax_name' => 'VAT 5%',
                'tax_rate_percentage' => 5,
                'prices_include_tax' => true,
                'invoice_prefix' => 'INV',
                'seller_name' => 'Restaurant POS Test',
            ]);

        $guarded->assertStatus(422)
            ->assertJsonValidationErrors(['expected_updated_at']);

        $update = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-finance-profile-upsert-c'))
            ->postJson('/api/v1/admin/settings/finance/tax-profile', [
                'tax_code' => 'VAT05',
                'tax_name' => 'VAT 5%',
                'tax_rate_percentage' => 5,
                'prices_include_tax' => true,
                'invoice_prefix' => 'INV',
                'seller_name' => 'Restaurant POS Test',
                'expected_updated_at' => $updatedAt,
            ]);

        $update->assertOk()
            ->assertJsonPath('data.effective_profile.tax_code', 'VAT05')
            ->assertJsonPath('data.effective_profile.invoice_prefix', 'INV');
    }

    public function test_non_admin_staff_cannot_access_finance_tax_profile_settings_routes(): void
    {
        $staffRoleId = $this->ensureRole('Staff');
        $staffId = $this->createUser(['role_id' => $staffRoleId, 'role_name' => 'Staff']);
        config()->set('staff_auth.allowed_role_ids', [$staffRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $staffRoleId => ['settlement.manage'],
        ]);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'non-admin-finance-settings'))
            ->getJson('/api/v1/admin/settings/finance/tax-profile');

        $response->assertForbidden()
            ->assertJsonPath('required_capability', 'settings.manage');
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
