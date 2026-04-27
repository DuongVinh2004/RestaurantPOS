<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\Health\Services\BookingDoctorService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class BookingDoctorCommandTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/doctor_command';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:testtesttesttesttesttesttesttesttest=');
        config()->set('booking.idempotency_ttl_hours', 24);
        config()->set('booking.idempotency_required_scopes', ['reservations', 'staff.checkout']);
        config()->set('booking.scheduler_heartbeat_ttl_seconds', 300);
        config()->set('booking.scheduler_heartbeat_stale_seconds', 180);
        config()->set('booking.reservation_lock_ttl_seconds', 60);
        config()->set('booking.reservation_lock_wait_seconds', 10);
        config()->set('booking.reservation_lock_prefix', 'booking:lock:table');
        config()->set('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation');
        config()->set('booking.require_redis_for_booking_api', true);
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        config()->set('database.redis.default', [
            'host' => '127.0.0.1',
            'port' => 6379,
        ]);
        config()->set('notifications.outbox.enabled', false);
        config()->set('booking.loyalty_enabled', true);
        config()->set('booking.loyalty_redeem_amount_per_point', 1000);
        config()->set('booking.loyalty_earn_amount_per_point', 10000);
        config()->set('booking.loyalty_min_redeem_points', 1);
        config()->set('staff_auth.api_keys', ['staff-key' => 2]);
        config()->set('staff_auth.allowed_role_ids', [1, 2]);
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.header', 'X-Customer-Token');
        config()->set('customer_auth.allowed_purposes', ['VerifyEmail']);
        config()->set('customer_auth.allowed_role_ids', [3]);
        config()->set('booking_ops_artifacts.doctor.artifact_root', $this->artifactRoot);

        app('cache')->forgetDriver('redis');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->artifactRoot));

        parent::tearDown();
    }

    #[Group('booking-smoke')]
    public function test_booking_doctor_supports_json_output_even_when_runtime_checks_fail(): void
    {
        $exitCode = Artisan::call('booking:doctor', ['--json' => true]);
        $output = Artisan::output();
        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertContains($exitCode, [0, 1]);
        $this->assertStringContainsString('"validation"', $output);
        $this->assertStringContainsString('"runtime"', $output);
        $this->assertSame($this->artifactRoot.'/reports', $payload['artifacts']['reports_root'] ?? null);
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['json_path'] ?? '')));
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['markdown_path'] ?? '')));
    }

    #[Group('booking-smoke')]
    public function test_booking_doctor_json_preserves_dependency_blocked_runtime_metadata(): void
    {
        $this->app->instance(BookingDoctorService::class, new class extends BookingDoctorService
        {
            public function __construct() {}

            public function inspect(bool $strict = false): array
            {
                return [
                    'ok' => false,
                    'validation' => [
                        'ok' => true,
                        'errors' => [],
                        'warnings' => [],
                        'checks' => [],
                    ],
                    'runtime' => [
                        'db' => [
                            'ok' => false,
                            'message' => 'mysql unavailable',
                            'status' => 'fail',
                            'dependency' => null,
                        ],
                        'scheduler' => [
                            'ok' => false,
                            'message' => 'Blocked by runtime.redis failure; scheduler heartbeat is stored in Redis and could not be read.',
                            'status' => 'blocked_dependency',
                            'dependency' => 'redis',
                        ],
                        'outbox' => [
                            'ok' => false,
                            'message' => 'Blocked by runtime.db failure; notification outbox health is database-backed and could not be inspected.',
                            'status' => 'blocked_dependency',
                            'dependency' => 'db',
                        ],
                    ],
                    'meta' => [
                        'strict' => $strict,
                        'timestamp_utc' => '2026-04-22T16:45:00Z',
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:doctor', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('blocked_dependency', data_get($payload, 'runtime.scheduler.status'));
        $this->assertSame('redis', data_get($payload, 'runtime.scheduler.dependency'));
        $this->assertSame('blocked_dependency', data_get($payload, 'runtime.outbox.status'));
        $this->assertSame('db', data_get($payload, 'runtime.outbox.dependency'));
    }

    #[Group('booking-smoke')]
    public function test_booking_doctor_reports_scheduler_ok_when_heartbeat_is_fresh(): void
    {
        Cache::store('redis')->put(
            'ops:heartbeat:scheduler',
            now('UTC')->subSeconds(5)->toIso8601String(),
            300
        );

        $exitCode = Artisan::call('booking:doctor', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertContains($exitCode, [0, 1]);
        $this->assertTrue((bool) data_get($payload, 'runtime.scheduler.ok'));
        $this->assertSame('pass', data_get($payload, 'runtime.scheduler.status'));
        $this->assertGreaterThanOrEqual(0, data_get($payload, 'runtime.scheduler.meta.age_seconds'));
        $this->assertLessThanOrEqual(180, data_get($payload, 'runtime.scheduler.meta.age_seconds'));
    }

    #[Group('booking-smoke')]
    public function test_booking_doctor_fails_scheduler_when_heartbeat_is_stale(): void
    {
        Cache::store('redis')->put(
            'ops:heartbeat:scheduler',
            now('UTC')->subSeconds(240)->toIso8601String(),
            300
        );

        $exitCode = Artisan::call('booking:doctor', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertFalse((bool) data_get($payload, 'runtime.scheduler.ok'));
        $this->assertSame('fail', data_get($payload, 'runtime.scheduler.status'));
        $this->assertGreaterThan(180, data_get($payload, 'runtime.scheduler.meta.age_seconds'));
        $this->assertStringContainsString('stale threshold', (string) data_get($payload, 'runtime.scheduler.message'));
    }
}
