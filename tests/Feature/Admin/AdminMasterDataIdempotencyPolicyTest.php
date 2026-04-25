<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;

final class AdminMasterDataIdempotencyPolicyTest extends TestCase
{
    public function test_booking_config_requires_idempotency_for_admin_master_data_and_settings_mutations(): void
    {
        $scopes = (array) config('booking.idempotency_required_scopes', []);

        $this->assertContains('admin.menu-categories.store', $scopes);
        $this->assertContains('admin.menu-categories.update', $scopes);
        $this->assertContains('admin.menu-items.store', $scopes);
        $this->assertContains('admin.menu-items.update', $scopes);
        $this->assertContains('admin.menu-item-prices.store', $scopes);
        $this->assertContains('admin.menu-item-prices.update', $scopes);

        $this->assertContains('admin.benefits-vouchers.store', $scopes);
        $this->assertContains('admin.benefits-vouchers.update', $scopes);
        $this->assertContains('admin.loyalty-tiers.store', $scopes);
        $this->assertContains('admin.loyalty-tiers.update', $scopes);
        $this->assertContains('admin.benefit-settings.upsert', $scopes);
        $this->assertContains('admin.restaurant-zones.rename', $scopes);
        $this->assertContains('admin.restaurant-tables.store', $scopes);
        $this->assertContains('admin.restaurant-tables.update', $scopes);
        $this->assertContains('admin.restaurant-tables.delete', $scopes);
        $this->assertContains('admin.settings-branches.store', $scopes);
        $this->assertContains('admin.settings-branches.update', $scopes);
        $this->assertContains('admin.settings-finance-tax-profile.upsert', $scopes);
    }
}
