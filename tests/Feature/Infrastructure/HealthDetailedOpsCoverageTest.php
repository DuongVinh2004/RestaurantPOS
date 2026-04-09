<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Services\OperationalInsightsService;
use App\Services\OpsHeartbeatService;
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
    public function test_staff_health_request_includes_extended_operational_sections(): void
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
                'ok' => true,
                'status' => 200,
                'mode' => 'mapped_key_fallback',
            ]);
        $this->app->instance(StaffActorResolver::class, $resolver);

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
            ->assertJsonPath('checks.row_version_contract.status', 'ok');
    }
}
