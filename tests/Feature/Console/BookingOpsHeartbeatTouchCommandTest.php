<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class BookingOpsHeartbeatTouchCommandTest extends TestCase
{
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

        app('cache')->forgetDriver('redis');
    }

    #[Group('booking-smoke')]
    public function test_touch_command_primes_scheduler_heartbeat_and_allows_doctor_to_report_scheduler_ok(): void
    {
        $touchExitCode = Artisan::call('booking:ops-heartbeat:touch', [
            'name' => 'scheduler',
            '--json' => true,
        ]);

        $this->assertSame(0, $touchExitCode);

        $touchPayload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue((bool) ($touchPayload['ok'] ?? false));
        $this->assertSame('scheduler', $touchPayload['data']['heartbeat_name'] ?? null);
        $this->assertNotEmpty($touchPayload['data']['last_run_at_utc'] ?? null);

        $doctorExitCode = Artisan::call('booking:doctor', ['--json' => true]);
        $this->assertContains($doctorExitCode, [0, 1]);

        $doctorPayload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue((bool) ($doctorPayload['runtime']['scheduler']['ok'] ?? false));
        $this->assertStringContainsString('Last heartbeat', (string) ($doctorPayload['runtime']['scheduler']['message'] ?? ''));
    }
}
