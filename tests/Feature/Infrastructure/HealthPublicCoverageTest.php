<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Platform\Metrics\Services\OperationalInsightsService;
use App\Platform\Health\Services\OpsHeartbeatService;
use App\Support\StaffCapabilityResolver;
use App\Support\StaffActorResolver;
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
    public function test_public_health_request_includes_base_runtime_checks_without_staff_context(): void
    {
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');

        $resolver = Mockery::mock(StaffActorResolver::class);
        $resolver->shouldReceive('resolveFromRequest')
            ->once()
            ->andReturn([
                'ok' => false,
                'status' => 401,
            ]);
        $this->app->instance(StaffActorResolver::class, $resolver);

        $heartbeat = Mockery::mock(OpsHeartbeatService::class);
        $heartbeat->shouldReceive('getLastRun')
            ->once()
            ->with('scheduler')
            ->andReturn(Carbon::now('UTC'));
        $this->app->instance(OpsHeartbeatService::class, $heartbeat);

        $ops = Mockery::mock(OperationalInsightsService::class);
        $ops->shouldNotReceive('snapshot');
        $this->app->instance(OperationalInsightsService::class, $ops);

        $capabilities = Mockery::mock(StaffCapabilityResolver::class);
        $capabilities->shouldNotReceive('resolveForActor');
        $this->app->instance(StaffCapabilityResolver::class, $capabilities);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.db.ok', true)
            ->assertJsonPath('checks.redis.ok', true)
            ->assertJsonPath('checks.redis.reason', null)
            ->assertJsonPath('checks.scheduler.ok', true)
            ->assertJsonPath('checks.scheduler.reason', null)
            ->assertJsonPath('checks.disk.ok', true)
            ->assertJsonMissingPath('checks.staff_api_keys')
            ->assertJsonMissingPath('checks.db.latency_ms')
            ->assertJsonMissingPath('checks.redis.set_get_ok')
            ->assertJsonMissingPath('checks.redis.lock_ok')
            ->assertJsonMissingPath('checks.scheduler.last_run_at_utc')
            ->assertJsonMissingPath('checks.scheduler.age_seconds')
            ->assertJsonMissingPath('checks.disk.free_bytes')
            ->assertJsonMissingPath('checks.disk.total_bytes')
            ->assertJsonMissingPath('meta.app_env');
    }
}
