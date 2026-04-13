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

    public function test_booking_verify_select_uses_static_analysis_fallback_when_no_domain_matches_cleanly(): void
    {
        $exitCode = Artisan::call('booking:verify-select', [
            '--json' => true,
            '--path' => ['infra/fallback-proof.txt'],
        ]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $commands = array_map(static fn (array $command): string => (string) $command['command'], $payload['commands']);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['ok']);
        $this->assertSame(['infra/fallback-proof.txt'], $payload['paths']);
        $this->assertSame([], $payload['domains']);
        $this->assertContains('vendor/bin/phpstan analyse', $commands);
        $this->assertContains(
            'no domain-specific rule matched cleanly; selector escalated to static analysis instead of defaulting to the full suite',
            $payload['notes']
        );
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
