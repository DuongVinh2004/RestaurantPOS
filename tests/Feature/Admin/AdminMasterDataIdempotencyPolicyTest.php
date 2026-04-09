<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;

final class AdminMasterDataIdempotencyPolicyTest extends TestCase
{
    public function test_booking_config_requires_idempotency_only_for_admin_menu_master_data_flows(): void
    {
        $scopes = (array) config('booking.idempotency_required_scopes', []);

        $this->assertContains('admin.menu-categories.store', $scopes);
        $this->assertContains('admin.menu-categories.update', $scopes);
        $this->assertContains('admin.menu-items.store', $scopes);
        $this->assertContains('admin.menu-items.update', $scopes);
        $this->assertContains('admin.menu-item-prices.store', $scopes);
        $this->assertContains('admin.menu-item-prices.update', $scopes);

        $this->assertNotContains('admin.benefits-vouchers.store', $scopes);
        $this->assertNotContains('admin.benefits-vouchers.update', $scopes);
        $this->assertNotContains('admin.loyalty-tiers.store', $scopes);
        $this->assertNotContains('admin.loyalty-tiers.update', $scopes);
        $this->assertNotContains('admin.benefit-settings.upsert', $scopes);
        $this->assertNotContains('admin.restaurant-zones.rename', $scopes);
        $this->assertNotContains('admin.restaurant-tables.store', $scopes);
        $this->assertNotContains('admin.restaurant-tables.update', $scopes);
        $this->assertNotContains('admin.restaurant-tables.delete', $scopes);
        $this->assertNotContains('admin.settings-branches.store', $scopes);
        $this->assertNotContains('admin.settings-branches.update', $scopes);
        $this->assertNotContains('admin.settings-finance-tax-profile.upsert', $scopes);
    }
}
