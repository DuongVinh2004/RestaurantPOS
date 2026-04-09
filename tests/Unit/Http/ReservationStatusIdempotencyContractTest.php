<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReservationStatusIdempotencyContractTest extends TestCase
{
    #[Test]
    public function reservation_status_route_requires_idempotency_scope(): void
    {
        $route = app('router')->getRoutes()->match(
            Request::create('/api/v1/reservations/123/status', 'PATCH')
        );

        $middleware = $route->gatherMiddleware();

        $this->assertContains('idempotency:staff.reservation-status', $middleware);
        $this->assertContains('staff.capability:reservation.manage', $middleware);
    }

    #[Test]
    public function booking_config_declares_reservation_status_scope_as_required(): void
    {
        $scopes = config('booking.idempotency_required_scopes', []);

        $this->assertContains('staff.reservation-status', $scopes);
    }
}
