<?php

declare(strict_types=1);

namespace Tests\Feature\Menu;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CustomerMenuCatalogRouteSurfaceTest extends TestCase
{
    public function test_public_menu_routes_are_bound_to_customer_menu_catalog_controller(): void
    {
        $expected = [
            ['GET', 'v1/menu/categories', 'App\\Http\\Controllers\\Api\\CustomerMenuCatalogController@categories'],
            ['GET', 'v1/menu/items', 'App\\Http\\Controllers\\Api\\CustomerMenuCatalogController@items'],
            ['GET', 'v1/menu/items/{id}', 'App\\Http\\Controllers\\Api\\CustomerMenuCatalogController@show'],
            ['POST', 'v1/menu/preorder/preview', 'App\\Http\\Controllers\\Api\\CustomerMenuCatalogController@previewPreorder'],
        ];

        foreach ($expected as [$method, $uri, $action]) {
            $route = $this->findRoute($method, $uri);

            self::assertNotNull($route, sprintf('Expected public menu route [%s %s] is not registered.', $method, $uri));
            self::assertSame($action, $route->getActionName(), sprintf('Public menu route [%s %s] drifted to unexpected action.', $method, $uri));
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
