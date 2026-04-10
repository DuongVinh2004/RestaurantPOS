<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class StaffCoreReadModelsRouteSurfaceTest extends TestCase
{
    public function test_staff_core_read_model_routes_are_registered(): void
    {
        $expected = [
            ['GET', 'v1/staff/branches', 'App\\Http\\Controllers\\Api\\Staff\\StaffBranchContextController@index'],
            ['GET', 'v1/staff/menu/items', 'App\\Http\\Controllers\\Api\\Staff\\StaffMenuCatalogController@index'],
            ['GET', 'v1/staff/reservations/{reservation_id}', 'App\\Http\\Controllers\\Api\\Staff\\StaffReservationInboxController@show'],
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
