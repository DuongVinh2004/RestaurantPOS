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
            ['GET', 'v1/admin/restaurant/zones', 'App\\Http\\Controllers\\Api\\Admin\\AdminRestaurantZoneController@index'],
            ['POST', 'v1/admin/restaurant/zones/rename', 'App\\Http\\Controllers\\Api\\Admin\\AdminRestaurantZoneController@rename'],
            ['GET', 'v1/admin/restaurant/tables', 'App\\Http\\Controllers\\Api\\Admin\\AdminRestaurantTableController@index'],
            ['GET', 'v1/admin/restaurant/tables/export', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@export'],
            ['POST', 'v1/admin/restaurant/tables/import', 'App\\Modules\\AdminMasterDataBulk\\Http\\Controllers\\Admin\\AdminMasterDataBulkController@import'],
            ['POST', 'v1/admin/restaurant/tables', 'App\\Http\\Controllers\\Api\\Admin\\AdminRestaurantTableController@store'],
            ['GET', 'v1/admin/restaurant/tables/{id}', 'App\\Http\\Controllers\\Api\\Admin\\AdminRestaurantTableController@show'],
            ['PATCH', 'v1/admin/restaurant/tables/{id}', 'App\\Http\\Controllers\\Api\\Admin\\AdminRestaurantTableController@update'],
            ['DELETE', 'v1/admin/restaurant/tables/{id}', 'App\\Http\\Controllers\\Api\\Admin\\AdminRestaurantTableController@destroy'],
            ['GET', 'v1/admin/restaurant/table-templates', 'App\\Http\\Controllers\\Api\\Admin\\AdminRestaurantTableController@templates'],
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
