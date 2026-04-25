<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BookingIdempotencyScopeContractTest extends TestCase
{
    public function test_booking_config_contains_required_customer_self_service_idempotency_scopes(): void
    {
        $scopes = config('booking.idempotency_required_scopes', []);

        $required = [
            'customer.reservations.cancel',
            'customer.reservations.reschedule',
            'customer.reservation-deposit.acknowledge',
            'customer.reservation-deposit.submit-intent',
            'customer.reservation-deposit.revoke-intent',
            'customer.privacy-requests.store',
            'customer.reservation-deposit-payment-sessions.create',
            'customer.reservation-deposit-payment-sessions.confirm',
            'customer.reservation-bill-payment-sessions.create',
            'customer.reservation-bill-payment-sessions.refresh',
            'customer.reservation-bill-payment-sessions.confirm',
            'customer.waiting-list.create',
            'customer.waiting-list.accept',
            'customer.waiting-list.decline',
            'customer.waiting-list.confirm-arrival',
            'customer.waiting-list.cancel',
        ];

        foreach ($required as $scope) {
            self::assertContains($scope, $scopes, 'Missing required idempotency scope: '.$scope);
        }
    }

    public function test_every_route_idempotency_scope_is_required_by_booking_config(): void
    {
        $configuredScopes = (array) config('booking.idempotency_required_scopes', []);
        $routeScopes = [];

        foreach (File::allFiles(base_path('routes')) as $file) {
            preg_match_all(
                '/idempotency:([A-Za-z0-9_.-]+)/',
                (string) file_get_contents($file->getPathname()),
                $matches
            );

            foreach ($matches[1] ?? [] as $scope) {
                $routeScopes[] = $scope;
            }
        }

        $routeScopes = array_values(array_unique($routeScopes));
        sort($routeScopes);

        self::assertNotEmpty($routeScopes, 'No route idempotency scopes were discovered.');

        foreach ($routeScopes as $scope) {
            self::assertContains($scope, $configuredScopes, 'Route idempotency scope is not required by booking config: '.$scope);
        }
    }
}
