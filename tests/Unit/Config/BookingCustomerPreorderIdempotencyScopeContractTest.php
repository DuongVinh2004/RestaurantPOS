<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class BookingCustomerPreorderIdempotencyScopeContractTest extends TestCase
{
    public function test_booking_config_contains_required_customer_preorder_idempotency_scopes(): void
    {
        $scopes = config('booking.idempotency_required_scopes', []);

        self::assertContains('customer.reservations.preorder.replace', $scopes);
        self::assertContains('customer.reservations.preorder.clear', $scopes);
    }
}
