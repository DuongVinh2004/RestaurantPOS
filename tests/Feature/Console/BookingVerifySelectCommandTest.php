<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingVerifySelectCommandTest extends TestCase
{
    public function test_booking_verify_select_supports_json_output_for_explicit_paths(): void
    {
        $exitCode = Artisan::call('booking:verify-select', [
            '--json' => true,
            '--path' => ['app/Services/Staff/StaffCheckoutService.php'],
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $domainKeys = array_map(static fn (array $domain): string => (string) $domain['key'], $payload['domains']);
        $commands = array_map(static fn (array $command): string => (string) $command['command'], $payload['commands']);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['ok']);
        $this->assertSame(['app/Services/Staff/StaffCheckoutService.php'], $payload['paths']);
        $this->assertContains('checkout_finance', $domainKeys);
        $this->assertContains('php artisan booking:round5-gate --json', $commands);
    }

    public function test_booking_verify_select_reports_error_when_no_paths_and_git_is_unavailable(): void
    {
        $exitCode = Artisan::call('booking:verify-select', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertFalse($payload['ok']);
        $this->assertSame('verification_selection_failed', $payload['error']);
    }

    public function test_booking_verify_select_matches_web_harness_contract_domain(): void
    {
        $exitCode = Artisan::call('booking:verify-select', [
            '--json' => true,
            '--path' => [
                'app/Services/Harness/HarnessSuiteService.php',
                'routes/console/harness.php',
            ],
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $domainKeys = array_map(static fn (array $domain): string => (string) $domain['key'], $payload['domains']);
        $commands = array_map(static fn (array $command): string => (string) $command['command'], $payload['commands']);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['ok']);
        $this->assertContains('web_harness_contracts', $domainKeys);
        $this->assertContains('restaurantpos-web-auth-session-contract', $payload['skills']);
        $this->assertContains('restaurantpos-web-client-contracts', $payload['skills']);
        $this->assertContains('php artisan booking:harness:fe-contract --json', $commands);
        $this->assertContains('php artisan booking:harness:web-auth --json', $commands);
    }
}
