<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AdminRestaurantMasterDataRouteSurfaceTest extends TestCase
{
    public function test_admin_restaurant_master_data_routes_are_registered_to_runtime_surface(): void
    {
        $expected = [
            ['GET', 'v1/admin/restaurant/zones', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\RestaurantZoneController@index'],
            ['POST', 'v1/admin/restaurant/zones/rename', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\RestaurantZoneController@rename'],
            ['GET', 'v1/admin/restaurant/tables', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\RestaurantTableController@index'],
            ['GET', 'v1/admin/restaurant/tables/export', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataExportController@export'],
            ['POST', 'v1/admin/restaurant/tables/import', 'App\\Modules\\MasterDataExchange\\Http\\Controllers\\Admin\\MasterDataImportController@import'],
            ['POST', 'v1/admin/restaurant/tables', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\RestaurantTableController@store'],
            ['GET', 'v1/admin/restaurant/tables/{id}', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\RestaurantTableController@show'],
            ['PATCH', 'v1/admin/restaurant/tables/{id}', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\RestaurantTableController@update'],
            ['DELETE', 'v1/admin/restaurant/tables/{id}', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\RestaurantTableController@destroy'],
            ['GET', 'v1/admin/restaurant/table-templates', 'App\\Modules\\BranchScheduling\\Http\\Controllers\\Admin\\TableTemplateController@index'],
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
            $candidates[] = 'api/'.$normalized;
        }

        return array_values(array_unique($candidates));
    }
}
