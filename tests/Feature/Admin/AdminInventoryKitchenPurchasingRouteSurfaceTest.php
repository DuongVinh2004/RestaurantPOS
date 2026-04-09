<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AdminInventoryKitchenPurchasingRouteSurfaceTest extends TestCase
{
    public function test_admin_inventory_kitchen_and_purchasing_routes_are_registered(): void
    {
        $expected = [
            ['GET', 'v1/admin/inventory/ingredients', 'App\Http\Controllers\Api\Admin\AdminInventoryController@listIngredients'],
            ['POST', 'v1/admin/inventory/ingredients', 'App\Http\Controllers\Api\Admin\AdminInventoryController@createIngredient'],
            ['GET', 'v1/admin/inventory/ingredients/{id}', 'App\Http\Controllers\Api\Admin\AdminInventoryController@showIngredient'],
            ['PATCH', 'v1/admin/inventory/ingredients/{id}', 'App\Http\Controllers\Api\Admin\AdminInventoryController@updateIngredient'],
            ['GET', 'v1/admin/inventory/menu-items/{id}/recipe', 'App\Http\Controllers\Api\Admin\AdminInventoryController@showMenuItemRecipe'],
            ['PUT', 'v1/admin/inventory/menu-items/{id}/recipe', 'App\Http\Controllers\Api\Admin\AdminInventoryController@upsertMenuItemRecipe'],
            ['GET', 'v1/admin/inventory/ingredients/{id}/movements', 'App\Http\Controllers\Api\Admin\AdminInventoryController@listIngredientMovements'],
            ['POST', 'v1/admin/inventory/ingredients/{id}/movements', 'App\Http\Controllers\Api\Admin\AdminInventoryController@createIngredientMovement'],
            ['GET', 'v1/admin/inventory/suppliers', 'App\Http\Controllers\Api\Admin\AdminPurchasingController@listSuppliers'],
            ['POST', 'v1/admin/inventory/suppliers', 'App\Http\Controllers\Api\Admin\AdminPurchasingController@createSupplier'],
            ['GET', 'v1/admin/inventory/suppliers/{id}', 'App\Http\Controllers\Api\Admin\AdminPurchasingController@showSupplier'],
            ['PATCH', 'v1/admin/inventory/suppliers/{id}', 'App\Http\Controllers\Api\Admin\AdminPurchasingController@updateSupplier'],
            ['GET', 'v1/admin/inventory/purchase-orders', 'App\Http\Controllers\Api\Admin\AdminPurchasingController@listPurchaseOrders'],
            ['POST', 'v1/admin/inventory/purchase-orders', 'App\Http\Controllers\Api\Admin\AdminPurchasingController@createPurchaseOrder'],
            ['GET', 'v1/admin/inventory/purchase-orders/{id}', 'App\Http\Controllers\Api\Admin\AdminPurchasingController@showPurchaseOrder'],
            ['PATCH', 'v1/admin/inventory/purchase-orders/{id}', 'App\Http\Controllers\Api\Admin\AdminPurchasingController@updatePurchaseOrder'],
            ['GET', 'v1/admin/inventory/purchase-orders/{id}/receipts', 'App\Http\Controllers\Api\Admin\AdminPurchasingController@listPurchaseOrderReceipts'],
            ['POST', 'v1/admin/inventory/purchase-orders/{id}/receipts', 'App\Http\Controllers\Api\Admin\AdminPurchasingController@createPurchaseOrderReceipt'],
            ['GET', 'v1/admin/kitchen/stations', 'App\Http\Controllers\Api\Admin\AdminKitchenRoutingController@index'],
            ['POST', 'v1/admin/kitchen/stations', 'App\Http\Controllers\Api\Admin\AdminKitchenRoutingController@store'],
            ['GET', 'v1/admin/kitchen/stations/{station_id}', 'App\Http\Controllers\Api\Admin\AdminKitchenRoutingController@show'],
            ['PATCH', 'v1/admin/kitchen/stations/{station_id}', 'App\Http\Controllers\Api\Admin\AdminKitchenRoutingController@update'],
            ['GET', 'v1/admin/kitchen/stations/{station_id}/category-routes', 'App\Http\Controllers\Api\Admin\AdminKitchenRoutingController@routes'],
            ['PUT', 'v1/admin/kitchen/stations/{station_id}/category-routes', 'App\Http\Controllers\Api\Admin\AdminKitchenRoutingController@syncRoutes'],
        ];

        foreach ($expected as [$method, $uri, $action]) {
            $route = $this->findRoute($method, $uri);
            self::assertNotNull($route, sprintf('Expected route [%s %s] is not registered.', $method, $uri));
            self::assertSame($action, $route->getActionName(), sprintf('Route [%s %s] drifted to unexpected action.', $method, $uri));
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
            $candidates[] = 'api/' . $normalized;
        }

        return array_values(array_unique($candidates));
    }
}
