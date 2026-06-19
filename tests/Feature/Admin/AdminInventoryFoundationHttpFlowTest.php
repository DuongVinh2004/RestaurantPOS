<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminInventoryFoundationHttpFlowTest extends TestCase
{
    use AssertsAuditTrail;
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

    public function test_admin_can_manage_ingredients_recipe_and_stock_foundation(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-inventory-manage-key');
        $categoryId = $this->ensureMenuCategory('Inventory Mains');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'INV-BOWL-01',
            'name' => 'Inventory Bowl',
            'is_available' => 1,
        ]);

        $createIngredient = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-ingredient-create'))
            ->postJson('/api/v1/admin/inventory/ingredients', [
                'code' => 'ING-RICE-01',
                'name' => 'Jasmine Rice',
                'unit_code' => 'g',
                'description' => 'Dry jasmine rice',
            ]);

        $createIngredient->assertCreated()
            ->assertJsonPath('data.code', 'ING-RICE-01')
            ->assertJsonPath('data.stock.on_hand', '0.000')
            ->assertJsonPath('data.recipe_usage_count', 0);

        $ingredientId = (int) $createIngredient->json('data.ingredient_id');
        $ingredientRowVersion = (int) $createIngredient->json('data.row_version');
        $brothIngredientId = $this->createIngredient([
            'code' => 'ING-BROTH-01',
            'name' => 'Broth Base',
            'unit_code' => 'ml',
        ]);

        $updateIngredient = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-ingredient-update'))
            ->patchJson('/api/v1/admin/inventory/ingredients/'.$ingredientId, [
                'row_version' => $ingredientRowVersion,
                'description' => 'Premium dry jasmine rice',
                'is_active' => true,
            ]);

        $updateIngredient->assertOk()
            ->assertJsonPath('data.description', 'Premium dry jasmine rice')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.row_version', $ingredientRowVersion + 1);

        $createdLog = $this->assertAuditLogRecorded('inventory.ingredient.created', 'ingredient', $ingredientId);
        self::assertSame($adminId, $createdLog->actor_user_id);
        self::assertSame('staff_user', $createdLog->actor_type);

        $updatedLog = $this->assertAuditLogRecorded('inventory.ingredient.updated', 'ingredient', $ingredientId);
        self::assertSame($adminId, $updatedLog->actor_user_id);
        self::assertSame('staff_user', $updatedLog->actor_type);
        self::assertSame('Premium dry jasmine rice', (string) data_get($updatedLog->after_json, 'description'));
        self::assertSame($ingredientRowVersion + 1, (int) data_get($updatedLog->after_json, 'row_version'));

        $syncRecipe = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-recipe-sync'))
            ->putJson('/api/v1/admin/inventory/menu-items/'.$itemId.'/recipe', [
                'row_version' => 1,
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'quantity' => '180.000',
                        'unit_code' => 'g',
                        'sort_order' => 10,
                    ],
                    [
                        'ingredient_id' => $brothIngredientId,
                        'quantity' => '250.000',
                        'unit_code' => 'ml',
                        'sort_order' => 20,
                        'notes' => 'Base broth portion',
                    ],
                ],
            ]);

        $syncRecipe->assertOk()
            ->assertJsonPath('meta.item.item_id', $itemId)
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('data.0.ingredient.ingredient_id', $ingredientId)
            ->assertJsonPath('data.1.ingredient.ingredient_id', $brothIngredientId);

        $showRecipe = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/menu-items/'.$itemId.'/recipe');

        $showRecipe->assertOk()
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('meta.row_version', 2)
            ->assertJsonPath('data.0.quantity', '180.000');

        $stockIn = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-stockin'))
            ->postJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements', [
                'movement_type' => 'StockIn',
                'quantity' => '12.500',
                'reference_type' => 'manual_count',
                'reference_id' => 'count-1',
            ]);

        $stockIn->assertCreated()
            ->assertJsonPath('data.movement_type', 'StockIn')
            ->assertJsonPath('data.quantity_delta', '12.500')
            ->assertJsonPath('meta.stock_on_hand', '12.500');

        $wastage = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-wastage'))
            ->postJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements', [
                'movement_type' => 'Wastage',
                'quantity' => '0.500',
                'notes' => 'Prep waste',
            ]);

        $wastage->assertCreated()
            ->assertJsonPath('data.quantity_delta', '-0.500')
            ->assertJsonPath('meta.stock_on_hand', '12.000');

        $movements = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements');

        $movements->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.ingredient.stock_on_hand', '12.000')
            ->assertJsonPath('data.0.movement_type', 'Wastage')
            ->assertJsonPath('data.0.quantity_delta', '-0.500');

        $showIngredient = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients/'.$ingredientId);

        $showIngredient->assertOk()
            ->assertJsonPath('data.stock.on_hand', '12.000')
            ->assertJsonPath('data.recipe_usage_count', 1);
    }

    public function test_non_admin_staff_cannot_access_admin_inventory_routes(): void
    {
        $staffRoleId = $this->ensureRole('Staff');
        $staffId = $this->createUser(['role_id' => $staffRoleId, 'role_name' => 'Staff']);

        config()->set('staff_auth.allowed_role_ids', [$staffRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $staffRoleId => ['reservation.manage'],
        ]);

        $response = $this->withHeaders($this->staffHeadersForTest($staffId, 'plain-staff-inventory-key'))
            ->getJson('/api/v1/admin/inventory/ingredients');

        $response->assertForbidden()
            ->assertJsonPath('required_capability', 'inventory.manage');
    }

    public function test_update_ingredient_with_current_row_version_succeeds_and_increments_row_version(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-row-version-current-key');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-RV-CURRENT',
            'name' => 'Current Version Rice',
            'unit_code' => 'kg',
            'row_version' => 4,
        ]);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-row-version-current'))
            ->patchJson('/api/v1/admin/inventory/ingredients/'.$ingredientId, [
                'row_version' => 4,
                'name' => 'Current Version Premium Rice',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Current Version Premium Rice')
            ->assertJsonPath('data.row_version', 5);

        $this->assertSame(
            5,
            (int) DB::table('ingredients')->where('ingredient_id', $ingredientId)->value('row_version')
        );
    }

    public function test_update_ingredient_with_stale_row_version_fails_without_changing_data(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-row-version-stale-key');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-RV-STALE',
            'name' => 'Stale Version Rice',
            'unit_code' => 'kg',
            'description' => 'Concurrent value',
            'row_version' => 2,
        ]);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-row-version-stale'))
            ->patchJson('/api/v1/admin/inventory/ingredients/'.$ingredientId, [
                'row_version' => 1,
                'description' => 'Stale client overwrite',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version')
            ->assertJsonPath('details.errors.row_version.0', 'The row_version is stale (row_version mismatch). Reload the resource and try again.');

        $ingredient = DB::table('ingredients')->where('ingredient_id', $ingredientId)->first();
        $this->assertSame('Concurrent value', (string) $ingredient->description);
        $this->assertSame(2, (int) $ingredient->row_version);
    }

    public function test_update_ingredient_validation_requires_row_version(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-row-version-required-key');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-RV-REQUIRED',
            'name' => 'Required Version Rice',
            'unit_code' => 'kg',
        ]);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-row-version-required'))
            ->patchJson('/api/v1/admin/inventory/ingredients/'.$ingredientId, [
                'description' => 'Missing row version',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['row_version']);
    }

    public function test_sync_recipe_with_stale_row_version_fails_without_changing_lines(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-recipe-row-version-stale-key');
        $categoryId = $this->ensureMenuCategory('Inventory Recipe Row Version');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'INV-RECIPE-RV',
            'name' => 'Inventory Recipe Row Version',
            'is_available' => 1,
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-RECIPE-RV',
            'name' => 'Recipe Version Rice',
            'unit_code' => 'kg',
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'quantity' => '1.000',
            'unit_code' => 'kg',
            'row_version' => 2,
        ]);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-recipe-row-version-stale'))
            ->putJson('/api/v1/admin/inventory/menu-items/'.$itemId.'/recipe', [
                'row_version' => 1,
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'quantity' => '2.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'stale_row_version');

        $line = DB::table('menu_item_recipes')
            ->where('item_id', $itemId)
            ->where('ingredient_id', $ingredientId)
            ->first();
        $this->assertSame('1.000', number_format((float) $line->quantity, 3, '.', ''));
        $this->assertSame(2, (int) $line->row_version);
    }

    public function test_sync_recipe_row_version_remains_monotonic_when_highest_version_line_is_removed(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-recipe-row-version-monotonic-key');
        $categoryId = $this->ensureMenuCategory('Inventory Recipe Monotonic');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'INV-RECIPE-MONO',
            'name' => 'Inventory Recipe Monotonic',
            'is_available' => 1,
        ]);
        $riceIngredientId = $this->createIngredient([
            'code' => 'ING-RECIPE-MONO-RICE',
            'name' => 'Recipe Monotonic Rice',
            'unit_code' => 'kg',
        ]);
        $oilIngredientId = $this->createIngredient([
            'code' => 'ING-RECIPE-MONO-OIL',
            'name' => 'Recipe Monotonic Oil',
            'unit_code' => 'l',
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $riceIngredientId,
            'quantity' => '1.000',
            'unit_code' => 'kg',
            'row_version' => 5,
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $oilIngredientId,
            'quantity' => '0.500',
            'unit_code' => 'l',
            'row_version' => 1,
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-recipe-row-version-monotonic'))
            ->putJson('/api/v1/admin/inventory/menu-items/'.$itemId.'/recipe', [
                'row_version' => 5,
                'lines' => [
                    [
                        'ingredient_id' => $oilIngredientId,
                        'quantity' => '0.750',
                        'unit_code' => 'l',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.row_version', 6)
            ->assertJsonPath('data.0.ingredient.ingredient_id', $oilIngredientId)
            ->assertJsonPath('data.0.row_version', 6);

        $this->assertSame(
            0,
            DB::table('menu_item_recipes')
                ->where('item_id', $itemId)
                ->where('ingredient_id', $riceIngredientId)
                ->count()
        );
    }

    public function test_inventory_uplift_feature_flag_can_disable_admin_inventory_surface(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-flag-off-key');
        $this->upsertFeatureFlagOverride(
            'inventory.uplift',
            false,
            'testing',
            null,
            ['reason' => 'inventory rollout paused'],
        );

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'feature_disabled')
            ->assertJsonPath('feature_key', 'inventory.uplift');
    }

    public function test_customer_menu_catalog_remains_unchanged_when_inventory_recipe_exists(): void
    {
        $serviceTime = $this->nowUtc()->copy()->addHours(2);
        $categoryId = $this->ensureMenuCategory('Inventory Public Menu');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Pho Inventory',
            'code' => 'PHO-INV-01',
            'is_available' => 1,
            'is_preorder_enabled' => 1,
            'preorder_cutoff_minutes' => 30,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '145000',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-PHO-01',
            'name' => 'Pho Noodles',
            'unit_code' => 'g',
        ]);
        $this->createMenuItemRecipeLine([
            'item_id' => $itemId,
            'ingredient_id' => $ingredientId,
            'quantity' => '200.000',
            'unit_code' => 'g',
        ]);
        $this->createIngredientStockMovement([
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '50.000',
            'unit_code' => 'g',
        ]);

        $response = $this->getJson('/api/v1/menu/items?service_time='.urlencode($serviceTime->toIso8601String()));

        $response->assertOk();

        $matchingItems = array_values(array_filter(
            (array) $response->json('data', []),
            static fn (array $item): bool => (int) ($item['item_id'] ?? 0) === $itemId
        ));

        self::assertGreaterThanOrEqual(1, (int) $response->json('meta.total'));
        self::assertCount(1, $matchingItems);
        self::assertSame('Pho Inventory', (string) data_get($matchingItems[0], 'name'));
        self::assertSame('145000', (string) data_get($matchingItems[0], 'price.amount'));
    }

    public function test_recipe_linkage_requires_matching_ingredient_unit_code(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-recipe-unit-key');
        $categoryId = $this->ensureMenuCategory('Inventory Unit Guard');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'INV-UNIT-01',
            'name' => 'Inventory Unit Guard',
            'is_available' => 1,
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-UNIT-KG',
            'name' => 'Rice Sack',
            'unit_code' => 'kg',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-recipe-unit-mismatch'))
            ->putJson('/api/v1/admin/inventory/menu-items/'.$itemId.'/recipe', [
                'row_version' => 1,
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'quantity' => '1.000',
                        'unit_code' => 'g',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.unit_code']);

        $this->assertSame(
            0,
            DB::table('menu_item_recipes')->where('item_id', $itemId)->count()
        );
    }

    public function test_manual_stock_movements_require_matching_ingredient_unit_code(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-movement-unit-key');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-STOCK-KG',
            'name' => 'Oil Drum',
            'unit_code' => 'kg',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-movement-unit-mismatch'))
            ->postJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements', [
                'movement_type' => 'StockIn',
                'quantity' => '2.500',
                'unit_code' => 'l',
                'reference_type' => 'manual_count',
                'reference_id' => 'mismatch-unit',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_code']);

        $this->assertSame(
            0,
            DB::table('ingredient_stock_movements')->where('ingredient_id', $ingredientId)->count()
        );
    }

    public function test_inventory_wastage_rejects_when_insufficient_stock(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-wastage-negative-key');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-WASTE-NEG',
            'name' => 'Waste Guard Rice',
            'unit_code' => 'kg',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-wastage-negative'))
            ->postJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements', [
                'movement_type' => 'Wastage',
                'quantity' => '0.500',
                'unit_code' => 'kg',
                'notes' => 'Prep waste without stock',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        $this->assertSame(
            0,
            DB::table('ingredient_stock_movements')
                ->where('ingredient_id', $ingredientId)
                ->count()
        );
    }

    public function test_inventory_adjustment_records_actor_on_stock_movement(): void
    {
        [$adminId, $headers] = $this->adminHeaders('admin-inventory-adjustment-actor-key');
        $ingredientId = $this->createIngredient([
            'code' => 'ING-ACTOR-ADJ',
            'name' => 'Actor Adjustment Rice',
            'unit_code' => 'kg',
        ]);

        $response = $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-adjustment-actor'))
            ->postJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements', [
                'movement_type' => 'AdjustmentIncrease',
                'quantity' => '1.250',
                'unit_code' => 'kg',
                'reference_type' => 'manual_count',
                'reference_id' => 'actor-adjustment-1',
                'notes' => 'Count correction',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.movement_type', 'AdjustmentIncrease')
            ->assertJsonPath('data.created_by', $adminId);

        $this->assertSame($adminId, (int) DB::table('ingredient_stock_movements')
            ->where('movement_id', (int) $response->json('data.movement_id'))
            ->value('created_by'));
    }

    public function test_branch_filtered_stock_views_and_implicit_branch_movements_use_isolated_stock_on_hand(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-branch-scope-key');
        $defaultBranchId = (int) (DB::table('branches')->where('is_default', 1)->value('branch_id') ?? 1);
        $branchId = $this->createBranch([
            'branch_code' => 'INV-BRANCH-B',
            'branch_name' => 'Inventory Branch B',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-BRANCH-STOCK',
            'name' => 'Branch Scoped Flour',
            'unit_code' => 'kg',
        ]);

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-branch-default-seed'))
            ->postJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements', [
                'movement_type' => 'StockIn',
                'quantity' => '5.000',
                'unit_code' => 'kg',
                'reference_type' => 'manual_count',
                'reference_id' => 'default-branch-seed',
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $defaultBranchId)
            ->assertJsonPath('meta.stock_on_hand', '5.000');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-branch-b-seed'))
            ->postJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements', [
                'movement_type' => 'StockIn',
                'branch_id' => $branchId,
                'quantity' => '11.000',
                'unit_code' => 'kg',
                'reference_type' => 'manual_count',
                'reference_id' => 'branch-b-seed',
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $branchId)
            ->assertJsonPath('meta.stock_on_hand', '11.000');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-admin-inventory-branch-default-topup'))
            ->postJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements', [
                'movement_type' => 'StockIn',
                'quantity' => '2.000',
                'unit_code' => 'kg',
                'reference_type' => 'manual_count',
                'reference_id' => 'default-branch-topup',
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $defaultBranchId)
            ->assertJsonPath('meta.stock_on_hand', '7.000');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements?branch_id='.$defaultBranchId)
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.ingredient.stock_on_hand', '7.000')
            ->assertJsonPath('data.0.branch_id', $defaultBranchId)
            ->assertJsonPath('data.1.branch_id', $defaultBranchId);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements?branch_id='.$branchId)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.ingredient.stock_on_hand', '11.000')
            ->assertJsonPath('data.0.branch_id', $branchId);
    }

    public function test_branch_limited_inventory_manager_cannot_read_or_adjust_out_of_scope_branch_stock(): void
    {
        $allowedBranchId = (int) (DB::table('branches')->where('is_default', 1)->value('branch_id') ?? 1);
        $deniedBranchId = $this->createBranch([
            'branch_code' => 'INV-DENY',
            'branch_name' => 'Inventory Denied Branch',
        ]);
        $ingredientId = $this->createIngredient([
            'code' => 'ING-BRANCH-DENY',
            'name' => 'Branch Deny Rice',
            'unit_code' => 'kg',
        ]);

        $this->createIngredientStockMovement([
            'branch_id' => $allowedBranchId,
            'ingredient_id' => $ingredientId,
            'quantity_delta' => '3.000',
            'unit_code' => 'kg',
        ]);
        $this->createIngredientStockMovement([
            'branch_id' => $deniedBranchId,
            'ingredient_id' => $ingredientId,
            'quantity_delta' => '9.000',
            'unit_code' => 'kg',
        ]);

        $roleId = $this->ensureRole('Inventory Manager');
        $staffId = $this->createUser(['role_id' => $roleId, 'role_name' => 'Inventory Manager']);

        config()->set('staff_auth.allowed_role_ids', [$roleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $roleId => ['inventory.manage'],
        ]);
        config()->set('staff_capabilities.role_id_branch_scopes', [
            $roleId => [(string) $allowedBranchId],
        ]);

        $headers = $this->staffHeadersForTest($staffId, 'branch-limited-inventory-key');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients/'.$ingredientId)
            ->assertOk()
            ->assertJsonPath('data.stock.on_hand', '3.000');

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.branch_id', $allowedBranchId);

        $this->withHeaders($headers)
            ->getJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements?branch_id='.$deniedBranchId)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        $this->withHeaders($this->withIdempotencyKey($headers, 'idem-inventory-denied-branch-adjust'))
            ->postJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements', [
                'branch_id' => $deniedBranchId,
                'movement_type' => 'StockIn',
                'quantity' => '1.000',
                'unit_code' => 'kg',
                'reference_type' => 'manual_count',
                'reference_id' => 'denied-branch',
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_missing_inventory_resources_return_standardized_not_found_envelope(): void
    {
        [, $headers] = $this->adminHeaders('admin-inventory-missing-resource-key');

        $this->withHeaders(array_merge($headers, [
            'X-Request-Id' => 'req-admin-inventory-ingredient-404',
        ]))
            ->getJson('/api/v1/admin/inventory/ingredients/999999')
            ->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-admin-inventory-ingredient-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-admin-inventory-ingredient-404');

        $movementHeaders = $this->withIdempotencyKey(array_merge($headers, [
            'X-Request-Id' => 'req-admin-inventory-movement-404',
        ]), 'idem-admin-inventory-movement-404');

        $this->withHeaders($movementHeaders)
            ->postJson('/api/v1/admin/inventory/ingredients/999999/movements', [
                'movement_type' => 'StockIn',
                'quantity' => '1.000',
                'reference_type' => 'manual_count',
                'reference_id' => 'missing-ingredient',
            ])
            ->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-admin-inventory-movement-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('request_id', 'req-admin-inventory-movement-404');
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

        return [$adminId, $this->staffHeadersForTest($adminId, $apiKey)];
    }

    /**
     * @return array<string,string>
     */
    private function staffHeadersForTest(int $staffId, string $apiKey): array
    {
        return $this->staffAuthHeaders($staffId, $apiKey);
    }
}
