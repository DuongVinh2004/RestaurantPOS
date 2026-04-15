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
            ['GET', 'v1/admin/menu/categories', 'App\\Http\\Controllers\\Api\\Admin\\AdminMenuCategoryController@index'],
            ['GET', 'v1/admin/menu/categories/export', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@export'],
            ['POST', 'v1/admin/menu/categories/import', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@import'],
            ['POST', 'v1/admin/menu/categories', 'App\\Http\\Controllers\\Api\\Admin\\AdminMenuCategoryController@store'],
            ['GET', 'v1/admin/menu/items', 'App\\Http\\Controllers\\Api\\Admin\\AdminMenuItemController@index'],
            ['GET', 'v1/admin/menu/items/export', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@export'],
            ['POST', 'v1/admin/menu/items/import', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@import'],
            ['POST', 'v1/admin/menu/items', 'App\\Http\\Controllers\\Api\\Admin\\AdminMenuItemController@store'],
            ['GET', 'v1/admin/menu/prices/export', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@export'],
            ['POST', 'v1/admin/menu/prices/import', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@import'],
            ['POST', 'v1/admin/menu/items/{item_id}/prices', 'App\\Http\\Controllers\\Api\\Admin\\AdminMenuItemPriceController@store'],
            ['PUT', 'v1/admin/menu/prices/{price_id}', 'App\\Http\\Controllers\\Api\\Admin\\AdminMenuItemPriceController@update'],
            ['GET', 'v1/admin/benefits/vouchers', 'App\\Modules\\BenefitsLoyalty\\Http\\Controllers\\Admin\\AdminVoucherController@index'],
            ['GET', 'v1/admin/benefits/vouchers/export', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@export'],
            ['POST', 'v1/admin/benefits/vouchers/import', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@import'],
            ['POST', 'v1/admin/benefits/vouchers', 'App\\Modules\\BenefitsLoyalty\\Http\\Controllers\\Admin\\AdminVoucherController@store'],
            ['PATCH', 'v1/admin/benefits/vouchers/{id}', 'App\\Modules\\BenefitsLoyalty\\Http\\Controllers\\Admin\\AdminVoucherController@update'],
            ['GET', 'v1/admin/benefits/loyalty-tiers', 'App\\Modules\\BenefitsLoyalty\\Http\\Controllers\\Admin\\AdminLoyaltyTierController@index'],
            ['GET', 'v1/admin/benefits/loyalty-tiers/export', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@export'],
            ['POST', 'v1/admin/benefits/loyalty-tiers/import', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@import'],
            ['POST', 'v1/admin/benefits/loyalty-tiers', 'App\\Modules\\BenefitsLoyalty\\Http\\Controllers\\Admin\\AdminLoyaltyTierController@store'],
            ['PATCH', 'v1/admin/benefits/loyalty-tiers/{id}', 'App\\Modules\\BenefitsLoyalty\\Http\\Controllers\\Admin\\AdminLoyaltyTierController@update'],
            ['GET', 'v1/admin/settings/benefits', 'App\\Modules\\BenefitsLoyalty\\Http\\Controllers\\Admin\\AdminBenefitSettingController@index'],
            ['POST', 'v1/admin/settings/benefits', 'App\\Modules\\BenefitsLoyalty\\Http\\Controllers\\Admin\\AdminBenefitSettingController@upsert'],
            ['GET', 'v1/admin/settings/branches', 'App\\Http\\Controllers\\Api\\Admin\\AdminBranchController@index'],
            ['GET', 'v1/admin/settings/branches/export', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@export'],
            ['POST', 'v1/admin/settings/branches/import', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@import'],
            ['POST', 'v1/admin/settings/branches', 'App\\Http\\Controllers\\Api\\Admin\\AdminBranchController@store'],
            ['GET', 'v1/admin/settings/branches/{id}', 'App\\Http\\Controllers\\Api\\Admin\\AdminBranchController@show'],
            ['PATCH', 'v1/admin/settings/branches/{id}', 'App\\Http\\Controllers\\Api\\Admin\\AdminBranchController@update'],
            ['GET', 'v1/admin/settings/finance/tax-profile', 'App\\Http\\Controllers\\Api\\Admin\\AdminFinanceSettingsController@showTaxProfile'],
            ['POST', 'v1/admin/settings/finance/tax-profile', 'App\\Http\\Controllers\\Api\\Admin\\AdminFinanceSettingsController@upsertTaxProfile'],
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
            collect($routeActions)->contains(static fn (string $action): bool => str_starts_with($action, 'App\\Http\\Controllers\\Api\\Admin\\AdminMenuController@')),
            'Legacy AdminMenuController routes should not remain registered after the menu foundation split.'
        );

        foreach ([
            'app/Http/Controllers/Api/Admin/AdminMenuController.php',
            'app/Services/Admin/AdminMenuService.php',
            'app/Http/Requests/Admin/CreateAdminMenuCategoryRequest.php',
            'app/Http/Requests/Admin/CreateAdminMenuItemPriceRequest.php',
            'app/Http/Requests/Admin/CreateAdminMenuItemRequest.php',
            'app/Http/Requests/Admin/ListAdminMenuCategoriesRequest.php',
            'app/Http/Requests/Admin/ListAdminMenuItemsRequest.php',
            'app/Http/Requests/Admin/UpdateAdminMenuCategoryRequest.php',
            'app/Http/Requests/Admin/UpdateAdminMenuItemRequest.php',
            'app/Http/Resources/AdminMenuCategoryResource.php',
            'app/Http/Resources/AdminMenuItemPriceResource.php',
            'app/Http/Resources/AdminMenuItemResource.php',
        ] as $legacyPath) {
            self::assertTrue(
                file_exists(base_path($legacyPath)),
                sprintf('Legacy admin menu compatibility artifact [%s] should remain available while runtime routes stay on the split controller stack.', $legacyPath)
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
