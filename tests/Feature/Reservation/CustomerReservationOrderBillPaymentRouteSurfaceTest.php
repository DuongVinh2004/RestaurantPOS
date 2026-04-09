<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CustomerReservationOrderBillPaymentRouteSurfaceTest extends TestCase
{
    public function test_customer_bill_payment_mutation_routes_use_canonical_idempotency_scopes(): void
    {
        $expected = [
            [
                'method' => 'POST',
                'uri' => 'v1/reservations/{reservation_id}/bill/payment-sessions',
                'action' => 'App\\Http\\Controllers\\Api\\CustomerReservationBillPaymentController@store',
                'idempotency' => 'idempotency:customer.reservation-bill-payment-sessions.create',
            ],
            [
                'method' => 'POST',
                'uri' => 'v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh',
                'action' => 'App\\Http\\Controllers\\Api\\CustomerReservationBillPaymentController@refresh',
                'idempotency' => 'idempotency:customer.reservation-bill-payment-sessions.refresh',
            ],
            [
                'method' => 'POST',
                'uri' => 'v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm',
                'action' => 'App\\Http\\Controllers\\Api\\CustomerReservationBillPaymentController@confirm',
                'idempotency' => 'idempotency:customer.reservation-bill-payment-sessions.confirm',
            ],
        ];

        foreach ($expected as $contract) {
            $route = $this->findRoute($contract['method'], $contract['uri']);

            self::assertNotNull(
                $route,
                sprintf('Expected customer bill payment route [%s %s] is not registered.', $contract['method'], $contract['uri'])
            );
            self::assertSame(
                $contract['action'],
                $route->getActionName(),
                sprintf('Customer bill payment route [%s %s] drifted to unexpected action.', $contract['method'], $contract['uri'])
            );

            $middleware = $route->gatherMiddleware();

            self::assertContains(
                $contract['idempotency'],
                $middleware,
                sprintf('Customer bill payment route [%s %s] drifted from canonical idempotency scope.', $contract['method'], $contract['uri'])
            );

            self::assertFalse(
                in_array(str_replace('-sessions', '-session', $contract['idempotency']), $middleware, true),
                sprintf('Customer bill payment route [%s %s] still contains legacy singular idempotency scope drift.', $contract['method'], $contract['uri'])
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
            $candidates[] = 'api/' . $normalized;
        }

        return array_values(array_unique($candidates));
    }
}
