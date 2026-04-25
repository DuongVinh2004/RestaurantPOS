<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingHarnessWebAuthCommandTest extends TestCase
{
    public function test_booking_harness_web_auth_reports_split_web_contract_and_verify_commands(): void
    {
        config()->set('cors.allowed_origins', [
            'http://localhost:3000',
            'http://localhost:5173',
        ]);

        $exitCode = Artisan::call('booking:harness:web-auth', [
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $verify = (array) ($payload['verify'] ?? []);

        $this->assertSame(0, $exitCode);
        $this->assertTrue((bool) ($payload['ok'] ?? false));
        $this->assertSame('X-Customer-Token', data_get($payload, 'headers.customer_auth'));
        $this->assertSame('X-Staff-Key', data_get($payload, 'headers.staff_auth'));
        $this->assertSame('X-Staff-CSRF', data_get($payload, 'headers.staff_csrf'));
        $this->assertSame('X-Session-Id', data_get($payload, 'headers.session'));
        $this->assertSame('Staff auth session envelope (login/me/refresh)', data_get($payload, 'staff_startup.source'));
        $this->assertContains('data.startup.default_branch', (array) data_get($payload, 'staff_startup.fields', []));
        $this->assertFalse((bool) data_get($payload, 'contract.supports_credentials'));
        $this->assertFalse((bool) data_get($payload, 'contract.staff_browser_session_cookie_enabled'));
        $this->assertSame('staff_web_refresh', data_get($payload, 'contract.staff_refresh_cookie_name'));
        $this->assertSame('X-Staff-CSRF', data_get($payload, 'contract.staff_csrf_header'));
        $this->assertSame(2, count((array) data_get($payload, 'frontends', [])));
        $this->assertTrue($this->checkPassed($payload, 'staff_csrf_header_allowed'));
        $this->assertContains('php artisan test tests/Unit/Http/CustomerOrStaffMiddlewareSessionContractTest.php tests/Feature/CorsContractTest.php', $verify);
    }

    public function test_booking_harness_web_auth_accepts_credentials_when_staff_refresh_cookie_rollout_is_enabled(): void
    {
        config()->set('staff_auth.browser_session.enabled', true);
        config()->set('cors.supports_credentials', true);
        config()->set('cors.allowed_origins', [
            'http://localhost:3000',
            'http://localhost:5173',
        ]);

        $exitCode = Artisan::call('booking:harness:web-auth', [
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue((bool) ($payload['ok'] ?? false));
        $this->assertTrue((bool) data_get($payload, 'contract.supports_credentials'));
        $this->assertTrue((bool) data_get($payload, 'contract.staff_browser_session_cookie_enabled'));
        $this->assertTrue($this->checkPassed($payload, 'browser_credentials_disabled'));
        $this->assertTrue($this->checkPassed($payload, 'staff_csrf_header_allowed'));
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function checkPassed(array $payload, string $key): bool
    {
        $check = collect((array) ($payload['checks'] ?? []))
            ->first(fn (array $entry): bool => (string) ($entry['key'] ?? '') === $key);

        return (bool) ($check['ok'] ?? false);
    }
}
