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

class HealthDetailedOpsCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Group('booking-ops')]
    public function test_staff_health_request_includes_extended_operational_sections_when_ops_capability_is_present(): void
    {
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');

        $resolvedUser = (object) [
            'role_id' => 2,
            'role' => (object) ['role_name' => 'Staff'],
        ];

        $resolver = Mockery::mock(StaffActorResolver::class);
        $resolver->shouldReceive('resolveFromRequest')
            ->once()
            ->andReturn([
                'ok' => true,
                'status' => 200,
                'mode' => 'mapped_key_fallback',
                'user' => $resolvedUser,
            ]);
        $this->app->instance(StaffActorResolver::class, $resolver);

        $capabilities = Mockery::mock(StaffCapabilityResolver::class);
        $capabilities->shouldReceive('resolveForActor')
            ->once()
            ->with(2, 'Staff')
            ->andReturn([
                'capabilities' => ['ops.view'],
            ]);
        $this->app->instance(StaffCapabilityResolver::class, $capabilities);

        $heartbeat = Mockery::mock(OpsHeartbeatService::class);
        $heartbeat->shouldReceive('getLastRun')
            ->once()
            ->with('scheduler')
            ->andReturn(Carbon::now('UTC'));
        $this->app->instance(OpsHeartbeatService::class, $heartbeat);

        $ops = Mockery::mock(OperationalInsightsService::class);
        $ops->shouldReceive('snapshot')
            ->once()
            ->andReturn([
                'notification_outbox' => ['status' => 'ok', 'reasons' => []],
                'payment_integrity' => ['status' => 'ok', 'reasons' => []],
                'voucher_locks' => ['status' => 'ok', 'reasons' => []],
                'session_linkage' => ['status' => 'ok', 'reasons' => []],
                'staff_api_keys' => ['status' => 'degraded', 'reasons' => ['staff_api_keys_expiring_soon']],
                'table_state_audit' => ['status' => 'ok', 'reasons' => []],
                'row_version_contract' => ['status' => 'ok', 'reasons' => []],
                'reporting_snapshots' => ['status' => 'degraded', 'reasons' => ['reporting_snapshot_stale']],
                'branch_defaults' => ['status' => 'ok', 'reasons' => []],
                'database_contract' => ['status' => 'ok', 'reasons' => []],
            ]);
        $this->app->instance(OperationalInsightsService::class, $ops);

        $response = $this->getJson('/api/v1/health', [
            'X-Staff-Key' => 'ops-key',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.staff_api_keys.status', 'degraded')
            ->assertJsonPath('checks.reporting_snapshots.status', 'degraded')
            ->assertJsonPath('checks.branch_defaults.status', 'ok')
            ->assertJsonPath('checks.row_version_contract.status', 'ok')
            ->assertJsonPath('meta.app_env', config('app.env'));
    }

    #[Group('booking-ops')]
    public function test_staff_health_request_without_ops_capability_receives_public_shape_only(): void
    {
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');

        $resolvedUser = (object) [
            'role_id' => 2,
            'role' => (object) ['role_name' => 'Staff'],
        ];

        $resolver = Mockery::mock(StaffActorResolver::class);
        $resolver->shouldReceive('resolveFromRequest')
            ->once()
            ->andReturn([
                'ok' => true,
                'status' => 200,
                'mode' => 'mapped_key_fallback',
                'user' => $resolvedUser,
            ]);
        $this->app->instance(StaffActorResolver::class, $resolver);

        $capabilities = Mockery::mock(StaffCapabilityResolver::class);
        $capabilities->shouldReceive('resolveForActor')
            ->once()
            ->with(2, 'Staff')
            ->andReturn([
                'capabilities' => ['reservation.manage'],
            ]);
        $this->app->instance(StaffCapabilityResolver::class, $capabilities);

        $heartbeat = Mockery::mock(OpsHeartbeatService::class);
        $heartbeat->shouldReceive('getLastRun')
            ->once()
            ->with('scheduler')
            ->andReturn(Carbon::now('UTC'));
        $this->app->instance(OpsHeartbeatService::class, $heartbeat);

        $ops = Mockery::mock(OperationalInsightsService::class);
        $ops->shouldNotReceive('snapshot');
        $this->app->instance(OperationalInsightsService::class, $ops);

        $response = $this->getJson('/api/v1/health', [
            'X-Staff-Key' => 'staff-key-without-ops',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonMissingPath('checks.staff_api_keys')
            ->assertJsonMissingPath('checks.reporting_snapshots')
            ->assertJsonMissingPath('checks.db.latency_ms')
            ->assertJsonMissingPath('checks.redis.set_get_ok')
            ->assertJsonMissingPath('checks.scheduler.last_run_at_utc')
            ->assertJsonMissingPath('meta.app_env');
    }
}
