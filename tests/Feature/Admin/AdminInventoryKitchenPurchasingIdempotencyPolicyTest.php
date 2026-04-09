<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;

final class AdminInventoryKitchenPurchasingIdempotencyPolicyTest extends TestCase
{
    public function test_booking_config_declares_inventory_kitchen_and_purchasing_idempotency_scopes(): void
    {
        $scopes = config('booking.idempotency_required_scopes', []);

        $this->assertIsArray($scopes);
        $this->assertNotContains('admin.inventory-ingredients.store', $scopes);
        $this->assertNotContains('admin.inventory-ingredients.update', $scopes);
        $this->assertNotContains('admin.inventory-menu-item-recipe.sync', $scopes);
        $this->assertNotContains('admin.inventory-movements.store', $scopes);
        $this->assertContains('admin.inventory-suppliers.store', $scopes);
        $this->assertContains('admin.inventory-purchase-orders.store', $scopes);
        $this->assertContains('admin.inventory-purchase-order-receipts.store', $scopes);
        $this->assertContains('admin.kitchen-stations.store', $scopes);
        $this->assertContains('admin.kitchen-station-category-routes.sync', $scopes);
    }
}
