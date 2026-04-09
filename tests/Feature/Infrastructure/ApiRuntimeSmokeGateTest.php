<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Services\RouteInventoryGateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class ApiRuntimeSmokeGateTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->flush();
    }

    public function test_locked_runtime_smoke_requests_hit_expected_boundary_statuses(): void
    {
        $definition = app(RouteInventoryGateService::class)->definition();

        foreach ($definition['smoke_requests'] as $request) {
            $response = $this->json(
                $request['method'],
                $request['uri'],
                [],
                ['Accept' => 'application/json']
            );

            self::assertContains(
                $response->getStatusCode(),
                $request['allowed_statuses'],
                sprintf(
                    'Smoke request [%s %s] returned unexpected status [%d]. Allowed: [%s].',
                    $request['method'],
                    $request['uri'],
                    $response->getStatusCode(),
                    implode(', ', $request['allowed_statuses']),
                )
            );
        }
    }
}
