<?php

declare(strict_types=1);

namespace Tests\Feature\DataLifecycle;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class DataLifecycleRouteSurfaceTest extends TestCase
{
    public function test_customer_and_admin_data_lifecycle_routes_are_registered(): void
    {
        $expected = [
            ['GET', 'v1/me/data-export', 'App\\Http\\Controllers\\Api\\CustomerDataLifecycleController@export'],
            ['GET', 'v1/me/privacy-requests', 'App\\Http\\Controllers\\Api\\CustomerDataLifecycleController@index'],
            ['POST', 'v1/me/privacy-requests', 'App\\Http\\Controllers\\Api\\CustomerDataLifecycleController@store'],
            ['GET', 'v1/admin/privacy/requests', 'App\\Http\\Controllers\\Api\\Admin\\AdminCustomerDataLifecycleController@index'],
            ['GET', 'v1/admin/privacy/customers/{user_id}/data-export', 'App\\Http\\Controllers\\Api\\Admin\\AdminCustomerDataLifecycleController@exportCustomerData'],
            ['POST', 'v1/admin/privacy/requests/{request_id}/review', 'App\\Http\\Controllers\\Api\\Admin\\AdminCustomerDataLifecycleController@review'],
        ];

        foreach ($expected as [$method, $uri, $action]) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(static fn (IlluminateRoute $route): bool => in_array($method, $route->methods(), true)
                    && in_array(trim($route->uri(), '/'), [trim($uri, '/'), 'api/' . trim($uri, '/')], true));

            self::assertNotNull($route, sprintf('Expected route [%s %s] is not registered.', $method, $uri));
            self::assertSame($action, $route->getActionName(), sprintf('Route [%s %s] drifted to unexpected action.', $method, $uri));
        }
    }
}
