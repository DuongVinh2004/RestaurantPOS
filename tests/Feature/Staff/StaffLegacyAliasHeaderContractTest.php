<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\Cashiering\Http\Controllers\Staff\CheckoutController;
use App\Modules\Cashiering\Http\Requests\Staff\CheckoutOrderRequest;
use App\Modules\Loyalty\Http\Controllers\Staff\LoyaltyLedgerController;
use App\Modules\Loyalty\Http\Requests\Staff\ReleaseReservationPointsRequest;
use App\Modules\Promotions\Http\Controllers\Staff\ReservationVoucherController;
use App\Modules\Promotions\Http\Requests\Staff\RemoveReservationVoucherRequest;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class StaffLegacyAliasHeaderContractTest extends TestCase
{
    public function test_checkout_alias_adds_deprecation_headers(): void
    {
        $controller = new class extends CheckoutController
        {
            public function __construct() {}

            public function store(int $order_id, CheckoutOrderRequest $request): JsonResponse
            {
                return response()->json(['ok' => true]);
            }
        };

        $response = $controller->checkout(15, new CheckoutOrderRequest);

        self::assertSame('true', $response->headers->get('Deprecation'));
        self::assertSame('/api/v1/staff/orders/{order_id}/checkout', $response->headers->get('X-Deprecated-Route-Alias'));
        self::assertSame('/api/v1/staff/orders/{order_id}/settlement/finalize', $response->headers->get('X-Canonical-Route'));
    }

    public function test_voucher_release_alias_adds_deprecation_headers(): void
    {
        $controller = new class extends ReservationVoucherController
        {
            public function __construct() {}

            public function remove(int $reservation_id, RemoveReservationVoucherRequest $request): JsonResponse
            {
                return response()->json(['ok' => true]);
            }
        };

        $response = $controller->release(21, new RemoveReservationVoucherRequest);

        self::assertSame('true', $response->headers->get('Deprecation'));
        self::assertSame('/api/v1/staff/reservations/{reservation_id}/voucher/release', $response->headers->get('X-Deprecated-Route-Alias'));
        self::assertSame('/api/v1/staff/reservations/{reservation_id}/voucher/remove', $response->headers->get('X-Canonical-Route'));
    }

    public function test_loyalty_release_alias_adds_deprecation_headers(): void
    {
        $controller = new class extends LoyaltyLedgerController
        {
            public function __construct() {}

            public function releaseReservation(int $reservation_id, ReleaseReservationPointsRequest $request): JsonResponse
            {
                return response()->json(['ok' => true]);
            }
        };

        $response = $controller->legacyReleaseReservation(31, new ReleaseReservationPointsRequest);

        self::assertSame('true', $response->headers->get('Deprecation'));
        self::assertSame('/api/v1/staff/reservations/{reservation_id}/loyalty/release', $response->headers->get('X-Deprecated-Route-Alias'));
        self::assertSame('/api/v1/staff/reservations/{reservation_id}/loyalty/redeem/release', $response->headers->get('X-Canonical-Route'));
    }
}
