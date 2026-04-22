<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Reservations\Application\Services\CustomerReservationPreorderService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Http\Controllers\CustomerReservationPreorderController;
use Illuminate\Http\Request;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class CustomerReservationPreorderLegacyRouteDeprecationHeadersTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_legacy_preorder_show_alias_adds_deprecation_headers(): void
    {
        $service = Mockery::mock(CustomerReservationPreorderService::class);
        $sessionAccess = Mockery::mock(ReservationSessionAccessWorkflow::class);

        $reservation = new Reservation;
        $reservation->forceFill([
            'reservation_id' => 88,
            'reservation_code' => 'RSV-88',
            'status' => 'Confirmed',
            'row_version' => 3,
        ]);

        $service->shouldReceive('showAccessiblePreorder')
            ->once()
            ->with(88, 501, null)
            ->andReturn([
                'reservation' => $reservation,
                'pre_order' => [
                    'present' => false,
                    'order_id' => null,
                    'order_row_version' => null,
                    'order_status' => null,
                    'service_time' => '2026-03-30T12:00:00Z',
                    'currency' => 'VND',
                    'lines' => [],
                    'totals' => [
                        'item_count' => 0,
                        'quantity' => 0,
                        'subtotal' => '0.00',
                    ],
                    'normalized_pre_order_items' => [],
                ],
                'management_policy' => [
                    'can_manage' => true,
                    'reservation_status' => 'Confirmed',
                    'cutoff_minutes' => 60,
                    'service_start' => '2026-03-30T12:00:00Z',
                    'manage_until' => '2026-03-30T11:00:00Z',
                    'reasons' => [],
                ],
            ]);

        $controller = new CustomerReservationPreorderController($service, $sessionAccess);

        $request = Request::create('/api/v1/reservations/88/pre-order', 'GET');
        $request->setUserResolver(static function (): User {
            $user = new User;
            $user->forceFill(['user_id' => 501]);

            return $user;
        });
        $request->attributes->set('customer_actor_user_id', 501);

        $response = $controller->show(88, $request);

        $this->assertSame('true', $response->headers->get('Deprecation'));
        $this->assertSame('/api/v1/reservations/88/pre-order', $response->headers->get('X-Deprecated-Route-Alias'));
        $this->assertSame('/api/v1/reservations/88/preorder', $response->headers->get('X-Canonical-Route'));
    }
}
