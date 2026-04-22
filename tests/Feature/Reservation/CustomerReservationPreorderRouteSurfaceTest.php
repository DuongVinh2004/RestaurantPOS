<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CustomerReservationPreorderRouteSurfaceTest extends TestCase
{
    public function test_customer_preorder_canonical_routes_are_registered(): void
    {
        $expected = [
            ['GET', 'v1/reservations/{id}/preorder', 'App\\Modules\\Reservations\\Http\\Controllers\\CustomerReservationPreorderController@show'],
            ['POST', 'v1/reservations/{id}/preorder/preview', 'App\\Modules\\Reservations\\Http\\Controllers\\CustomerReservationPreorderController@preview'],
            ['PUT', 'v1/reservations/{id}/preorder', 'App\\Modules\\Reservations\\Http\\Controllers\\CustomerReservationPreorderController@replace'],
            ['DELETE', 'v1/reservations/{id}/preorder', 'App\\Modules\\Reservations\\Http\\Controllers\\CustomerReservationPreorderController@clear'],
        ];

        foreach ($expected as [$method, $uri, $action]) {
            $route = $this->findRoute($method, $uri);

            self::assertNotNull($route, sprintf('Expected customer preorder route [%s %s] is not registered.', $method, $uri));
            self::assertSame($action, $route->getActionName(), sprintf('Customer preorder route [%s %s] drifted to unexpected action.', $method, $uri));
        }
    }

    public function test_customer_preorder_legacy_alias_routes_are_kept_for_backward_compatibility(): void
    {
        $expected = [
            ['GET', 'v1/reservations/{id}/pre-order', 'App\\Modules\\Reservations\\Http\\Controllers\\CustomerReservationPreorderController@show'],
            ['POST', 'v1/reservations/{id}/pre-order/preview', 'App\\Modules\\Reservations\\Http\\Controllers\\CustomerReservationPreorderController@preview'],
            ['PUT', 'v1/reservations/{id}/pre-order', 'App\\Modules\\Reservations\\Http\\Controllers\\CustomerReservationPreorderController@replace'],
            ['DELETE', 'v1/reservations/{id}/pre-order', 'App\\Modules\\Reservations\\Http\\Controllers\\CustomerReservationPreorderController@clear'],
        ];

        foreach ($expected as [$method, $uri, $action]) {
            $route = $this->findRoute($method, $uri);

            self::assertNotNull($route, sprintf('Expected legacy customer preorder route [%s %s] is not registered.', $method, $uri));
            self::assertSame($action, $route->getActionName(), sprintf('Legacy customer preorder route [%s %s] drifted to unexpected action.', $method, $uri));
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
