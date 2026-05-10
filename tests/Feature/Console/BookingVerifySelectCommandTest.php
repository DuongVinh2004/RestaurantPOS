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
            '--path' => ['app/Modules/Cashiering/Application/Workflows/OrderSettlementWorkflow.php'],
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $domainKeys = array_map(static fn (array $domain): string => (string) $domain['key'], $payload['domains']);
        $commands = array_map(static fn (array $command): string => (string) $command['command'], $payload['commands']);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['ok']);
        $this->assertSame(['app/Modules/Cashiering/Application/Workflows/OrderSettlementWorkflow.php'], $payload['paths']);
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
                'app/Platform/Harness/HarnessSuiteService.php',
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

    public function test_booking_verify_select_matches_staff_web_frontend_domain(): void
    {
        $exitCode = Artisan::call('booking:verify-select', [
            '--json' => true,
            '--path' => [
                'staff-web/src/app/layout/StaffAppShell.tsx',
            ],
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $domainKeys = array_map(static fn (array $domain): string => (string) $domain['key'], $payload['domains']);
        $commands = array_map(static fn (array $command): string => (string) $command['command'], $payload['commands']);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['ok']);
        $this->assertContains('staff_web_frontend', $domainKeys);
        $this->assertContains('restaurantpos-staff-web-react', $payload['skills']);
        $this->assertContains('npm --prefix staff-web run test', $commands);
        $this->assertContains('npm --prefix staff-web run build', $commands);
    }

    public function test_booking_verify_select_matches_release_control_plane_domain(): void
    {
        $exitCode = Artisan::call('booking:verify-select', [
            '--json' => true,
            '--path' => [
                'config/booking_release.php',
                'scripts/release/check-package-integrity.mjs',
            ],
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $domainKeys = array_map(static fn (array $domain): string => (string) $domain['key'], $payload['domains']);
        $commands = array_map(static fn (array $command): string => (string) $command['command'], $payload['commands']);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['ok']);
        $this->assertContains('ops_release_contract', $domainKeys);
        $this->assertContains('node scripts/release/check-package-integrity.mjs --json', $commands);
        $this->assertContains('php artisan booking:release-manifest --json', $commands);
        $this->assertContains(
            'php artisan test tests/Unit/Services/ReleaseArtifactManifestServiceTest.php tests/Unit/Services/ReleasePackageServiceTest.php',
            $commands
        );
    }
}
