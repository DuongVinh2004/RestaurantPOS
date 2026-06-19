<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Modules\Catalog\Application\UseCases\PolicyPreview\MenuPreorderPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminMenuManagementHttpFlowTest extends TestCase
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
        config()->set('staff_auth.allow_role_name_fallback', true);
        config()->set('staff_auth.allowed_role_ids', []);
        config()->set('staff_auth.allowed_role_names', ['Admin', 'Staff']);
    }

    public function test_admin_can_create_update_and_show_menu_master_data_with_current_price(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        config()->set('staff_auth.api_keys', ['admin-menu-key' => $adminId]);

        $categoryResponse = $this->withHeaders($this->staffHeaders('admin-menu-key'))
            ->postJson('/api/v1/admin/menu/categories', [
                'name' => 'Seasonal Specials',
                'description' => 'Rotating chef picks',
                'sort_order' => 15,
                'is_deleted' => false,
            ]);

        $categoryResponse->assertCreated()
            ->assertJsonPath('data.name', 'Seasonal Specials');

        $categoryId = (int) $categoryResponse->json('data.category_id');

        $itemResponse = $this->withHeaders($this->staffHeaders('admin-menu-key'))
            ->postJson('/api/v1/admin/menu/items', [
                'category_id' => $categoryId,
                'code' => 'SEASONAL-STEAK',
                'name' => 'Seasonal Steak',
                'description' => 'Dry aged beef with pepper sauce',
                'is_available' => true,
                'is_preorder_enabled' => true,
                'preorder_quota_per_day' => 6,
                'preorder_cutoff_minutes' => 120,
            ]);

        $itemResponse->assertCreated()
            ->assertJsonPath('data.code', 'SEASONAL-STEAK')
            ->assertJsonPath('data.category.category_id', $categoryId);

        $itemId = (int) $itemResponse->json('data.item_id');

        $activeFrom = Carbon::now('UTC')->subDay()->startOfMinute();
        $futureFrom = Carbon::now('UTC')->addDay()->startOfMinute();

        $activePriceResponse = $this->withHeaders($this->staffHeaders('admin-menu-key'))
            ->postJson(sprintf('/api/v1/admin/menu/items/%d/prices', $itemId), [
                'price' => '185000',
                'currency' => 'VND',
                'effective_from' => $activeFrom->toIso8601String(),
            ]);

        $activePriceResponse->assertCreated()
            ->assertJsonPath('data.price', '185000');

        $futurePriceResponse = $this->withHeaders($this->staffHeaders('admin-menu-key'))
            ->postJson(sprintf('/api/v1/admin/menu/items/%d/prices', $itemId), [
                'price' => '195000',
                'currency' => 'VND',
                'effective_from' => $futureFrom->toIso8601String(),
            ]);

        $futurePriceResponse->assertCreated();
        $futurePriceId = (int) $futurePriceResponse->json('data.price_id');

        $updateFuturePriceResponse = $this->withHeaders($this->staffHeaders('admin-menu-key'))
            ->putJson(sprintf('/api/v1/admin/menu/prices/%d', $futurePriceId), [
                'price' => '197000',
                'currency' => 'VND',
                'effective_from' => $futureFrom->toIso8601String(),
            ]);

        $updateFuturePriceResponse->assertOk()
            ->assertJsonPath('data.price', '197000');

        $showItemResponse = $this->withHeaders($this->staffHeaders('admin-menu-key'))
            ->getJson(sprintf('/api/v1/admin/menu/items/%d', $itemId));

        $showItemResponse->assertOk()
            ->assertJsonPath('data.name', 'Seasonal Steak')
            ->assertJsonPath('data.current_price.price', '185000')
            ->assertJsonCount(2, 'data.prices');

        $listItemsResponse = $this->withHeaders($this->staffHeaders('admin-menu-key'))
            ->getJson('/api/v1/admin/menu/items?category_id='.$categoryId);

        $listItemsResponse->assertOk()
            ->assertJsonPath('data.0.item_id', $itemId)
            ->assertJsonPath('data.0.current_price.price', '185000');
    }

    public function test_staff_without_menu_manage_capability_is_forbidden(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        config()->set('staff_auth.api_keys', ['staff-menu-key' => $staffId]);

        $response = $this->withHeaders($this->staffHeaders('staff-menu-key'))
            ->getJson('/api/v1/admin/menu/categories');

        $response->assertForbidden()
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'menu.manage')
            ->assertJsonPath('staff_role_name', 'Staff');
    }

    public function test_admin_cannot_create_overlapping_effective_price_rows_for_same_item(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        config()->set('staff_auth.api_keys', ['admin-menu-key' => $adminId]);

        $itemId = $this->createMenuItem([
            'name' => 'Overlap Soup',
            'is_available' => 1,
            'is_preorder_enabled' => 1,
            'preorder_quota_per_day' => 10,
            'preorder_cutoff_minutes' => 30,
        ]);

        $start = Carbon::now('UTC')->subDay()->startOfMinute();
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '99000',
            'currency' => 'VND',
            'effective_from' => $start,
            'effective_to' => null,
        ]);

        $response = $this->withHeaders($this->staffHeaders('admin-menu-key'))
            ->postJson(sprintf('/api/v1/admin/menu/items/%d/prices', $itemId), [
                'price' => '105000',
                'currency' => 'VND',
                'effective_from' => $start->copy()->addHour()->toIso8601String(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');
    }

    public function test_preorder_policy_service_reads_effective_price_rows_created_by_admin_foundation(): void
    {
        $service = app(MenuPreorderPolicyService::class);

        $itemId = $this->createMenuItem([
            'name' => 'Policy Duck',
            'is_available' => 1,
            'is_preorder_enabled' => 1,
            'preorder_quota_per_day' => 5,
            'preorder_cutoff_minutes' => 15,
        ]);

        $serviceStart = Carbon::now('UTC')->addHours(3)->startOfMinute();
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '215000',
            'currency' => 'VND',
            'effective_from' => $serviceStart->copy()->subDay(),
            'effective_to' => null,
        ]);

        $prepared = $service->prepareRequestedItems([
            ['item_id' => $itemId, 'quantity' => 2],
        ], $serviceStart);

        $this->assertSame($itemId, (int) $prepared['rows'][0]['item_id']);
        $this->assertSame(2, (int) $prepared['rows'][0]['quantity']);
        $this->assertSame('215000', number_format((float) $prepared['price_rows']->get($itemId)->price, 0, '.', ''));
        $this->assertSame('VND', (string) $prepared['price_rows']->get($itemId)->currency);
    }

    public function test_booking_config_declares_admin_menu_idempotency_scopes(): void
    {
        $scopes = (array) config('booking.idempotency_required_scopes', []);

        $this->assertContains('admin.menu-categories.store', $scopes);
        $this->assertContains('admin.menu-categories.update', $scopes);
        $this->assertContains('admin.menu-items.store', $scopes);
        $this->assertContains('admin.menu-items.update', $scopes);
        $this->assertContains('admin.menu-item-prices.store', $scopes);
        $this->assertContains('admin.menu-item-prices.update', $scopes);
    }

    /**
     * @return array<string,string>
     */
    private function staffHeaders(string $staffKey): array
    {
        return [
            'X-Staff-Key' => $staffKey,
            'Accept' => 'application/json',
            'Idempotency-Key' => 'idem-'.$staffKey.'-'.bin2hex(random_bytes(6)),
        ];
    }
}
