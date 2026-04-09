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
        $this->assertSame('X-Session-Id', data_get($payload, 'headers.session'));
        $this->assertSame('Staff auth session envelope (login/me/refresh)', data_get($payload, 'staff_startup.source'));
        $this->assertContains('data.startup.default_branch', (array) data_get($payload, 'staff_startup.fields', []));
        $this->assertFalse((bool) data_get($payload, 'contract.supports_credentials'));
        $this->assertSame(2, count((array) data_get($payload, 'frontends', [])));
        $this->assertContains('php artisan test tests/Unit/Http/CustomerOrStaffMiddlewareSessionContractTest.php tests/Feature/CorsContractTest.php', $verify);
    }
}
