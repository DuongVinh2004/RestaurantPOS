<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Http\Middleware\RequireStaffCapability;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StaffLegacyRouteAliasContractTest extends TestCase
{
    public function test_staff_capability_alias_is_registered(): void
    {
        self::assertSame(RequireStaffCapability::class, app('router')->getMiddleware()['staff.capability'] ?? null);
    }

    public function test_legacy_staff_alias_routes_bind_to_wrapper_actions_and_canonical_replay_map(): void
    {
        self::assertSame('checkout', $this->findRouteByUriSuffix('v1/staff/orders/{order_id}/checkout')?->getActionMethod());
        self::assertSame('release', $this->findRouteByUriSuffix('v1/staff/reservations/{reservation_id}/voucher/release')?->getActionMethod());
        self::assertSame('legacyReleaseReservation', $this->findRouteByUriSuffix('v1/staff/reservations/{reservation_id}/loyalty/release')?->getActionMethod());

        self::assertSame([
            'v1/staff/orders/{order_id}/close' => 'v1/staff/orders/{order_id}/bill-snapshot',
            'v1/staff/orders/{order_id}/checkout' => 'v1/staff/orders/{order_id}/settlement/finalize',
            'v1/staff/reservations/{reservation_id}/voucher/release' => 'v1/staff/reservations/{reservation_id}/voucher/remove',
            'v1/staff/reservations/{reservation_id}/loyalty/release' => 'v1/staff/reservations/{reservation_id}/loyalty/redeem/release',
        ], config('booking.idempotency_route_aliases'));
    }

    private function findRouteByUriSuffix(string $suffix): ?IlluminateRoute
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $suffix || str_ends_with($route->uri(), '/' . $suffix)) {
                return $route;
            }
        }

        return null;
    }
}
