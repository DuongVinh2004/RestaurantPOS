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
            ['GET', 'v1/admin/inventory/ingredients', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\InventoryAdjustmentController@listIngredients'],
            ['POST', 'v1/admin/inventory/ingredients', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\InventoryAdjustmentController@createIngredient'],
            ['GET', 'v1/admin/inventory/ingredients/{id}', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\InventoryAdjustmentController@showIngredient'],
            ['PATCH', 'v1/admin/inventory/ingredients/{id}', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\InventoryAdjustmentController@updateIngredient'],
            ['GET', 'v1/admin/inventory/menu-items/{id}/recipe', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\InventoryAdjustmentController@showMenuItemRecipe'],
            ['PUT', 'v1/admin/inventory/menu-items/{id}/recipe', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\InventoryAdjustmentController@upsertMenuItemRecipe'],
            ['GET', 'v1/admin/inventory/ingredients/{id}/movements', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\InventoryAdjustmentController@listIngredientMovements'],
            ['POST', 'v1/admin/inventory/ingredients/{id}/movements', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\InventoryAdjustmentController@createIngredientMovement'],
            ['GET', 'v1/admin/inventory/suppliers', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController@listSuppliers'],
            ['POST', 'v1/admin/inventory/suppliers', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController@createSupplier'],
            ['GET', 'v1/admin/inventory/suppliers/{id}', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController@showSupplier'],
            ['PATCH', 'v1/admin/inventory/suppliers/{id}', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController@updateSupplier'],
            ['GET', 'v1/admin/inventory/purchase-orders', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController@listPurchaseOrders'],
            ['POST', 'v1/admin/inventory/purchase-orders', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController@createPurchaseOrder'],
            ['GET', 'v1/admin/inventory/purchase-orders/{id}', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController@showPurchaseOrder'],
            ['PATCH', 'v1/admin/inventory/purchase-orders/{id}', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController@updatePurchaseOrder'],
            ['GET', 'v1/admin/inventory/purchase-orders/{id}/receipts', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController@listPurchaseOrderReceipts'],
            ['POST', 'v1/admin/inventory/purchase-orders/{id}/receipts', 'App\Modules\InventoryProcurement\Http\Controllers\Admin\ProcurementController@createPurchaseOrderReceipt'],
            ['GET', 'v1/admin/kitchen/stations', 'App\Modules\KitchenDispatch\Http\Controllers\Admin\KitchenStationController@index'],
            ['POST', 'v1/admin/kitchen/stations', 'App\Modules\KitchenDispatch\Http\Controllers\Admin\KitchenStationController@store'],
            ['GET', 'v1/admin/kitchen/stations/{station_id}', 'App\Modules\KitchenDispatch\Http\Controllers\Admin\KitchenStationController@show'],
            ['PATCH', 'v1/admin/kitchen/stations/{station_id}', 'App\Modules\KitchenDispatch\Http\Controllers\Admin\KitchenStationController@update'],
            ['GET', 'v1/admin/kitchen/stations/{station_id}/category-routes', 'App\Modules\KitchenDispatch\Http\Controllers\Admin\KitchenCategoryRouteController@index'],
            ['PUT', 'v1/admin/kitchen/stations/{station_id}/category-routes', 'App\Modules\KitchenDispatch\Http\Controllers\Admin\KitchenCategoryRouteController@update'],
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

