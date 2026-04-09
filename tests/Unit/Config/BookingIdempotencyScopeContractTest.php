<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

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
            self::assertContains($scope, $scopes, 'Missing required idempotency scope: ' . $scope);
        }
    }
}
