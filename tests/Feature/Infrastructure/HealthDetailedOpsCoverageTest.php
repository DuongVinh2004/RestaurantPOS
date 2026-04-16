<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Platform\Health\Services\OpsHeartbeatService;
use App\Platform\Metrics\Services\OperationalInsightsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class HealthDetailedOpsCoverageTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Group('booking-ops')]
    public function test_anonymous_callers_cannot_access_detailed_health_or_redis_diagnostics(): void
    {
        $this->getJson('/api/v1/health/detailed')
            ->assertUnauthorized();

        $this->getJson('/api/v1/health/redis')
            ->assertUnauthorized();
    }

    #[Group('booking-ops')]
    public function test_authenticated_staff_without_ops_health_capability_is_forbidden_from_detailed_health_routes(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);

        $response = $this->withHeaders($this->staffAuthHeaders($staffId, 'health-staff'))
            ->get('/api/v1/health/detailed');

        $response->assertForbidden()
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'ops.health.view');

        $redisResponse = $this->withHeaders($this->staffAuthHeaders($staffId, 'health-staff-redis'))
            ->get('/api/v1/health/redis');

        $redisResponse->assertForbidden()
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'ops.health.view');
    }

    #[Group('booking-ops')]
    public function test_detailed_health_route_allows_explicitly_authorized_operator_and_returns_internal_checks(): void
    {
        config()->set('staff_capabilities.role_capabilities.Operator', ['ops.health.view']);

        $operatorId = $this->createUser(['role_name' => 'Operator']);

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
                'kitchen_kds' => ['status' => 'degraded', 'reasons' => ['kitchen_ticket_stuck_detected'], 'stuck_ticket_count' => 1],
                'branch_defaults' => ['status' => 'ok', 'reasons' => []],
                'database_contract' => ['status' => 'ok', 'reasons' => []],
            ]);
        $this->app->instance(OperationalInsightsService::class, $ops);

        $response = $this->withHeaders($this->staffAuthHeaders($operatorId, 'health-operator'))
            ->getJson('/api/v1/health/detailed');

        $response->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.staff_api_keys.status', 'degraded')
            ->assertJsonPath('checks.reporting_snapshots.status', 'degraded')
            ->assertJsonPath('checks.kitchen_kds.status', 'degraded')
            ->assertJsonPath('checks.kitchen_kds.stuck_ticket_count', 1)
            ->assertJsonPath('checks.branch_defaults.status', 'ok')
            ->assertJsonPath('checks.db.ok', true)
            ->assertJsonPath('checks.redis.set_get_ok', true)
            ->assertJsonPath('checks.redis.lock_ok', true)
            ->assertJsonPath('meta.app_env', config('app.env'))
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'db',
                    'redis',
                    'scheduler',
                    'disk',
                    'notification_outbox',
                    'payment_integrity',
                    'voucher_locks',
                    'session_linkage',
                    'staff_api_keys',
                    'table_state_audit',
                    'row_version_contract',
                    'reporting_snapshots',
                    'kitchen_kds',
                    'branch_defaults',
                    'database_contract',
                ],
                'meta' => [
                    'request_id',
                    'timestamp_utc',
                    'app_env',
                ],
            ]);
    }
}
