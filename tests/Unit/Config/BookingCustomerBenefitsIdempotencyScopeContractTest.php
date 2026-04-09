<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

final class BookingCustomerBenefitsIdempotencyScopeContractTest extends TestCase
{
    public function test_booking_config_requires_idempotency_for_customer_benefits_mutations(): void
    {
        $scopes = (array) config('booking.idempotency_required_scopes', []);

        $this->assertContains('customer.reservation-voucher.apply', $scopes);
        $this->assertContains('customer.reservation-voucher.remove', $scopes);
        $this->assertContains('customer.reservation-loyalty.redeem', $scopes);
        $this->assertContains('customer.reservation-loyalty.release', $scopes);
    }
}
