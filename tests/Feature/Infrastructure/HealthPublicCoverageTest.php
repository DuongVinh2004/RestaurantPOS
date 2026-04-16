<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Platform\Health\Services\OpsHeartbeatService;
use App\Platform\Metrics\Services\OperationalInsightsService;
use Illuminate\Support\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class HealthPublicCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Group('booking-ops')]
    public function test_public_health_request_returns_minimal_payload_without_deep_operational_diagnostics(): void
    {
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');

        $heartbeat = Mockery::mock(OpsHeartbeatService::class);
        $heartbeat->shouldReceive('getLastRun')
            ->once()
            ->with('scheduler')
            ->andReturn(Carbon::now('UTC'));
        $this->app->instance(OpsHeartbeatService::class, $heartbeat);

        $ops = Mockery::mock(OperationalInsightsService::class);
        $ops->shouldNotReceive('snapshot');
        $this->app->instance(OperationalInsightsService::class, $ops);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', config('app.name', 'RestaurantPOS'))
            ->assertJsonStructure([
                'status',
                'service',
                'timestamp_utc',
            ])
            ->assertJsonMissingPath('checks')
            ->assertJsonMissingPath('meta')
            ->assertJsonMissingPath('request_id')
            ->assertJsonMissingPath('app_env');
    }
}
