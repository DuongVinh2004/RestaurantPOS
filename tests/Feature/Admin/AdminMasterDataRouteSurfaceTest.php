<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AdminMasterDataRouteSurfaceTest extends TestCase
{
    public function test_admin_menu_benefits_and_settings_routes_are_registered_to_runtime_surface(): void
    {
        $expected = [
            ['GET', 'v1/admin/menu/categories', 'App\\Modules\\Catalog\\Http\\Controllers\\Admin\\MenuCategoryController@index'],
            ['GET', 'v1/admin/menu/categories/export', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataExportController@export'],
            ['POST', 'v1/admin/menu/categories/import', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataImportController@import'],
            ['POST', 'v1/admin/menu/categories', 'App\\Modules\\Catalog\\Http\\Controllers\\Admin\\MenuCategoryController@store'],
            ['GET', 'v1/admin/menu/items', 'App\\Modules\\Catalog\\Http\\Controllers\\Admin\\MenuItemController@index'],
            ['GET', 'v1/admin/menu/items/export', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataExportController@export'],
            ['POST', 'v1/admin/menu/items/import', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataImportController@import'],
            ['POST', 'v1/admin/menu/items', 'App\\Modules\\Catalog\\Http\\Controllers\\Admin\\MenuItemController@store'],
            ['GET', 'v1/admin/menu/prices/export', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataExportController@export'],
            ['POST', 'v1/admin/menu/prices/import', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataImportController@import'],
            ['POST', 'v1/admin/menu/items/{item_id}/prices', 'App\\Modules\\Catalog\\Http\\Controllers\\Admin\\MenuItemPriceController@store'],
            ['PUT', 'v1/admin/menu/prices/{price_id}', 'App\\Modules\\Catalog\\Http\\Controllers\\Admin\\MenuItemPriceController@update'],
            ['GET', 'v1/admin/benefits/vouchers', 'App\\Modules\\Promotions\\Http\\Controllers\\Admin\\VoucherController@index'],
            ['GET', 'v1/admin/benefits/vouchers/export', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataExportController@export'],
            ['POST', 'v1/admin/benefits/vouchers/import', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataImportController@import'],
            ['POST', 'v1/admin/benefits/vouchers', 'App\\Modules\\Promotions\\Http\\Controllers\\Admin\\VoucherController@store'],
            ['PATCH', 'v1/admin/benefits/vouchers/{id}', 'App\\Modules\\Promotions\\Http\\Controllers\\Admin\\VoucherController@update'],
            ['GET', 'v1/admin/benefits/loyalty-tiers', 'App\\Modules\\Loyalty\\Http\\Controllers\\Admin\\LoyaltyTierController@index'],
            ['GET', 'v1/admin/benefits/loyalty-tiers/export', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataExportController@export'],
            ['POST', 'v1/admin/benefits/loyalty-tiers/import', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataImportController@import'],
            ['POST', 'v1/admin/benefits/loyalty-tiers', 'App\\Modules\\Loyalty\\Http\\Controllers\\Admin\\LoyaltyTierController@store'],
            ['PATCH', 'v1/admin/benefits/loyalty-tiers/{id}', 'App\\Modules\\Loyalty\\Http\\Controllers\\Admin\\LoyaltyTierController@update'],
            ['GET', 'v1/admin/settings/benefits', 'App\\Modules\\Promotions\\Http\\Controllers\\Admin\\BenefitSettingController@index'],
            ['POST', 'v1/admin/settings/benefits', 'App\\Modules\\Promotions\\Http\\Controllers\\Admin\\BenefitSettingController@upsert'],
            ['GET', 'v1/admin/settings/branches', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\BranchController@index'],
            ['GET', 'v1/admin/settings/branches/export', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataExportController@export'],
            ['POST', 'v1/admin/settings/branches/import', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataImportController@import'],
            ['POST', 'v1/admin/settings/branches', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\BranchController@store'],
            ['GET', 'v1/admin/settings/branches/{id}', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\BranchController@show'],
            ['PATCH', 'v1/admin/settings/branches/{id}', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\BranchController@update'],
            ['GET', 'v1/admin/settings/finance/tax-profile', 'App\\Modules\\Billing\\Http\\Controllers\\Admin\\FinanceTaxProfileController@showTaxProfile'],
            ['POST', 'v1/admin/settings/finance/tax-profile', 'App\\Modules\\Billing\\Http\\Controllers\\Admin\\FinanceTaxProfileController@upsertTaxProfile'],
        ];

        foreach ($expected as [$method, $uri, $action]) {
            $route = $this->findRoute($method, $uri);

            self::assertNotNull($route, sprintf('Expected route [%s %s] is not registered.', $method, $uri));
            self::assertSame($action, $route->getActionName(), sprintf('Route [%s %s] drifted to unexpected action.', $method, $uri));
        }
    }

    public function test_legacy_admin_menu_compatibility_layer_remains_available_without_runtime_routes(): void
    {
        $routeActions = collect(Route::getRoutes()->getRoutes())
            ->map(static fn (IlluminateRoute $route): string => $route->getActionName())
            ->values()
            ->all();

        self::assertFalse(
            collect($routeActions)->contains(static fn (string $action): bool => str_starts_with($action, 'App\\Modules\\Catalog\\Http\\Controllers\\Admin\\LegacyMenuController@')),
            'Legacy LegacyMenuController routes should not remain registered after the menu foundation split.'
        );

        foreach ([
            'app/Modules/Catalog/Http/Controllers/Admin/LegacyMenuController.php',
            'app/Modules/Catalog/Application/UseCases/Management/LegacyMenuService.php',
            'app/Modules/Catalog/Http/Requests/Admin/CreateMenuCategoryRequest.php',
            'app/Modules/Catalog/Http/Requests/Admin/CreateMenuItemPriceRequest.php',
            'app/Modules/Catalog/Http/Requests/Admin/CreateMenuItemRequest.php',
            'app/Modules/Catalog/Http/Requests/Admin/ListMenuCategoriesRequest.php',
            'app/Modules/Catalog/Http/Requests/Admin/ListMenuItemsRequest.php',
            'app/Modules/Catalog/Http/Requests/Admin/LegacyUpdateMenuCategoryRequest.php',
            'app/Modules/Catalog/Http/Requests/Admin/LegacyUpdateMenuItemRequest.php',
            'app/Modules/Catalog/Http/Resources/Admin/LegacyMenuCategoryResource.php',
            'app/Modules/Catalog/Http/Resources/Admin/LegacyMenuItemPriceResource.php',
            'app/Modules/Catalog/Http/Resources/Admin/LegacyMenuItemResource.php',
        ] as $legacyPath) {
            self::assertTrue(
                file_exists(base_path($legacyPath)),
                sprintf('Module-local legacy admin menu compatibility artifact [%s] should remain available while runtime routes stay on the split controller stack.', $legacyPath)
            );
        }

        foreach ([
            'app/Http/Controllers/Api/Admin/LegacyMenuController.php',
            'app/Services/Admin/LegacyMenuService.php',
            'app/Http/Requests/Admin/CreateMenuCategoryRequest.php',
            'app/Http/Resources/MenuCategoryResource.php',
        ] as $legacyTopLevelPath) {
            self::assertFalse(
                file_exists(base_path($legacyTopLevelPath)),
                sprintf('Legacy admin menu business artifact [%s] should not remain in top-level lanes after the Catalog module move.', $legacyTopLevelPath)
            );
        }
    }

    private function findRoute(string $method, string $uri): ?IlluminateRoute
    {
        $normalizedCandidates = $this->uriCandidates($uri);

        return collect(Route::getRoutes()->getRoutes())
            ->first(static fn (IlluminateRoute $route): bool => in_array($method, $route->methods(), true)
                && in_array(trim($route->uri(), '/'), $normalizedCandidates, true));
    }

    /**
     * @return list<string>
     */
    private function uriCandidates(string $uri): array
    {
        $normalized = trim($uri, '/');

        $candidates = [$normalized];

        if (! str_starts_with($normalized, 'api/')) {
            $candidates[] = 'api/'.$normalized;
        }

        return array_values(array_unique($candidates));
    }
}
