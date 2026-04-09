<?php

declare(strict_types=1);

namespace Tests\Feature\Menu;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerMenuCatalogHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_customer_can_list_grouped_menu_categories_with_visible_items(): void
    {
        $serviceTime = $this->nowUtc()->copy()->addHours(3);

        $starterCategoryId = $this->ensureMenuCategory('Starter');
        $mainCategoryId = $this->ensureMenuCategory('Main');

        $saladId = $this->createMenuItem([
            'category_id' => $starterCategoryId,
            'name' => 'Garden Salad',
            'code' => 'SALAD-01',
            'is_preorder_enabled' => 1,
            'preorder_cutoff_minutes' => 30,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $saladId,
            'price' => '85000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $steakId = $this->createMenuItem([
            'category_id' => $mainCategoryId,
            'name' => 'Pepper Steak',
            'code' => 'STEAK-01',
            'is_preorder_enabled' => 0,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $steakId,
            'price' => '250000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $hiddenId = $this->createMenuItem([
            'category_id' => $mainCategoryId,
            'name' => 'Hidden Item',
            'code' => 'HIDDEN-01',
            'is_available' => 0,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $hiddenId,
            'price' => '10000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/menu/categories?service_time=' . urlencode($serviceTime->toIso8601String()));

        $response->assertOk()
            ->assertJsonPath('meta.item_count', 2)
            ->assertJsonPath('data.0.name', 'Starter')
            ->assertJsonPath('data.0.items.0.name', 'Garden Salad')
            ->assertJsonPath('data.0.items.0.price.amount', '85000.00')
            ->assertJsonPath('data.1.name', 'Main')
            ->assertJsonPath('data.1.items.0.name', 'Pepper Steak');

        $this->assertStringNotContainsString('Hidden Item', $response->getContent());
    }

    public function test_customer_can_filter_menu_items_to_preorder_enabled_items(): void
    {
        $serviceTime = $this->nowUtc()->copy()->addHours(4);
        $categoryId = $this->ensureMenuCategory('Chef Specials');

        $phoId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Pho Bo',
            'code' => 'PHO-01',
            'is_preorder_enabled' => 1,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $phoId,
            'price' => '95000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $teaId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Iced Tea',
            'code' => 'TEA-01',
            'is_preorder_enabled' => 0,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $teaId,
            'price' => '25000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/menu/items?preorder_only=1&q=Pho&service_time=' . urlencode($serviceTime->toIso8601String()));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Pho Bo')
            ->assertJsonPath('data.0.preorder.enabled', true);

        $this->assertStringNotContainsString('Iced Tea', $response->getContent());
    }

    public function test_customer_can_show_visible_menu_item(): void
    {
        $serviceTime = $this->nowUtc()->copy()->addHours(3);
        $categoryId = $this->ensureMenuCategory('Customer Show');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Seafood Fried Rice',
            'code' => 'SEA-01',
            'is_available' => 1,
            'is_preorder_enabled' => 1,
            'preorder_cutoff_minutes' => 30,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '135000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/menu/items/' . $itemId . '?service_time=' . urlencode($serviceTime->toIso8601String()));

        $response->assertOk()
            ->assertJsonPath('data.item_id', $itemId)
            ->assertJsonPath('data.name', 'Seafood Fried Rice')
            ->assertJsonPath('data.price.amount', '135000.00')
            ->assertJsonPath('data.preorder.enabled', true);
    }

    public function test_customer_cannot_show_hidden_or_unpriced_menu_item(): void
    {
        $serviceTime = $this->nowUtc()->copy()->addHours(3);
        $categoryId = $this->ensureMenuCategory('Hidden Customer Show');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Secret Dish',
            'code' => 'SECRET-01',
            'is_available' => 0,
            'is_preorder_enabled' => 1,
            'preorder_cutoff_minutes' => 30,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '150000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/menu/items/' . $itemId . '?service_time=' . urlencode($serviceTime->toIso8601String()));

        $response->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_customer_can_preview_preorder_totals_for_valid_items(): void
    {
        $serviceTime = $this->nowUtc()->copy()->addHours(5);
        $categoryId = $this->ensureMenuCategory('Preorder');

        $rollId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Spring Roll',
            'code' => 'ROLL-01',
            'is_preorder_enabled' => 1,
            'preorder_cutoff_minutes' => 30,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $rollId,
            'price' => '45000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $riceId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Fried Rice',
            'code' => 'RICE-01',
            'is_preorder_enabled' => 1,
            'preorder_cutoff_minutes' => 45,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $riceId,
            'price' => '120000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/menu/preorder/preview', [
            'start_time' => $serviceTime->toIso8601String(),
            'pre_order_items' => [
                ['item_id' => $rollId, 'quantity' => 2],
                ['item_id' => $riceId, 'quantity' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.currency', 'VND')
            ->assertJsonPath('data.totals.item_count', 2)
            ->assertJsonPath('data.totals.quantity', 3)
            ->assertJsonPath('data.totals.subtotal', '210000.00')
            ->assertJsonPath('data.lines.0.name', 'Spring Roll')
            ->assertJsonPath('data.lines.1.name', 'Fried Rice');
    }

    public function test_preorder_preview_rejects_items_that_do_not_support_preorder(): void
    {
        $serviceTime = $this->nowUtc()->copy()->addHours(2);
        $categoryId = $this->ensureMenuCategory('Drinks');

        $teaId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Milk Tea',
            'code' => 'DRINK-01',
            'is_preorder_enabled' => 0,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $teaId,
            'price' => '55000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/menu/preorder/preview', [
            'start_time' => $serviceTime->toIso8601String(),
            'pre_order_items' => [
                ['item_id' => $teaId, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pre_order_items']);
    }
}
