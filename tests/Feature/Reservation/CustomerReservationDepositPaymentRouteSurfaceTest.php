<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CustomerReservationDepositPaymentRouteSurfaceTest extends TestCase
{
    public function test_customer_deposit_payment_mutation_routes_use_canonical_idempotency_scopes(): void
    {
        $expected = [
            [
                'method' => 'POST',
                'uri' => 'v1/reservations/{reservation_id}/deposit/payment-sessions',
                'action' => 'App\\Modules\\CheckoutPayments\\Http\\Controllers\\Customer\\CustomerReservationDepositPaymentController@store',
                'idempotency' => 'idempotency:customer.reservation-deposit-payment-sessions.create',
            ],
            [
                'method' => 'POST',
                'uri' => 'v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh',
                'action' => 'App\\Modules\\CheckoutPayments\\Http\\Controllers\\Customer\\CustomerReservationDepositPaymentController@refresh',
                'idempotency' => 'idempotency:customer.reservation-deposit-payment-sessions.refresh',
            ],
            [
                'method' => 'POST',
                'uri' => 'v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm',
                'action' => 'App\\Modules\\CheckoutPayments\\Http\\Controllers\\Customer\\CustomerReservationDepositPaymentController@confirm',
                'idempotency' => 'idempotency:customer.reservation-deposit-payment-sessions.confirm',
            ],
        ];

        foreach ($expected as $contract) {
            $route = $this->findRoute($contract['method'], $contract['uri']);

            self::assertNotNull($route, sprintf('Expected customer deposit payment route [%s %s] is not registered.', $contract['method'], $contract['uri']));
            self::assertSame($contract['action'], $route->getActionName());
            self::assertContains($contract['idempotency'], $route->gatherMiddleware());
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
