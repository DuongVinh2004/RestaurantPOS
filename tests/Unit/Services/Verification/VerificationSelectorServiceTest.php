<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Verification;

use App\Services\Verification\VerificationSelectorService;
use Tests\TestCase;

class VerificationSelectorServiceTest extends TestCase
{
    public function test_build_report_matches_checkout_domain_without_default_full_suite(): void
    {
        $service = new VerificationSelectorService;

        $report = $service->buildReport([
            'app/Services/Staff/StaffCheckoutService.php',
        ]);

        $domainKeys = array_map(static fn (array $domain): string => (string) $domain['key'], $report['domains']);
        $commands = array_map(static fn (array $command): string => (string) $command['command'], $report['commands']);
        $escalationKeys = array_map(static fn (array $item): string => (string) $item['key'], $report['escalations']);

        $this->assertContains('checkout_finance', $domainKeys);
        $this->assertContains('php artisan booking:round5-gate --json', $commands);
        $this->assertContains('payment_finance', $escalationKeys);
        $this->assertNotContains('php artisan test', $commands);
    }

    public function test_build_report_keeps_docs_only_changes_out_of_full_suite(): void
    {
        $service = new VerificationSelectorService;

        $report = $service->buildReport([
            'docs/runbooks/booking-ci-cd-runbook.md',
            'README.md',
        ]);

        $this->assertSame([], $report['commands']);
        $this->assertContains('restaurantpos-runbook-sync', $report['skills']);
        $this->assertContains('docs-only change set: no automated commands were selected; verify command names and runbook examples manually', $report['notes']);
    }

    public function test_build_report_collects_git_paths_with_base_and_uncommitted_state(): void
    {
        $service = new class extends VerificationSelectorService
        {
            /**
             * @param  list<string>  $arguments
             * @return array{exit_code: int, stdout: string, stderr: string}
             */
            protected function runGit(array $arguments): array
            {
                $key = implode(' ', $arguments);

                return match ($key) {
                    'rev-parse --is-inside-work-tree' => ['exit_code' => 0, 'stdout' => "true\n", 'stderr' => ''],
                    'diff --name-only --diff-filter=ACMR origin/main...HEAD' => ['exit_code' => 0, 'stdout' => "app/Services/Staff/StaffCheckoutService.php\n", 'stderr' => ''],
                    'diff --name-only --diff-filter=ACMR' => ['exit_code' => 0, 'stdout' => "routes/api.php\n", 'stderr' => ''],
                    'diff --cached --name-only --diff-filter=ACMR' => ['exit_code' => 0, 'stdout' => "composer.json\n", 'stderr' => ''],
                    'ls-files --others --exclude-standard' => ['exit_code' => 0, 'stdout' => "tests/Feature/Console/BookingVerifySelectCommandTest.php\n", 'stderr' => ''],
                    default => ['exit_code' => 1, 'stdout' => '', 'stderr' => 'unexpected git call'],
                };
            }
        };

        $report = $service->buildReport(base: 'origin/main');

        $this->assertSame('git', $report['source']['type']);
        $this->assertTrue($report['source']['included_uncommitted']);
        $this->assertContains('restaurantpos-git-aware-verify', $report['skills']);
        $this->assertContains('collected branch diff against origin/main', $report['notes']);
        $this->assertSame([
            'app/Services/Staff/StaffCheckoutService.php',
            'routes/api.php',
            'composer.json',
            'tests/Feature/Console/BookingVerifySelectCommandTest.php',
        ], $report['paths']);
    }

    public function test_build_report_matches_web_harness_contract_domain_and_commands(): void
    {
        $service = new VerificationSelectorService;

        $report = $service->buildReport([
            'app/Services/Harness/HarnessSuiteService.php',
            'routes/console/harness.php',
            'docs/runbooks/api-consumer-artifacts.md',
        ]);

        $domainKeys = array_map(static fn (array $domain): string => (string) $domain['key'], $report['domains']);
        $commands = array_map(static fn (array $command): string => (string) $command['command'], $report['commands']);

        $this->assertContains('web_harness_contracts', $domainKeys);
        $this->assertContains('restaurantpos-web-auth-session-contract', $report['skills']);
        $this->assertContains('restaurantpos-web-client-contracts', $report['skills']);
        $this->assertContains('php artisan booking:harness:fe-contract --json', $commands);
        $this->assertContains('php artisan booking:harness:web-auth --json', $commands);
    }

    public function test_build_report_falls_back_to_static_analysis_when_no_domain_matches_cleanly(): void
    {
        $service = new VerificationSelectorService;

        $report = $service->buildReport([
            'infra/fallback-proof.txt',
        ]);

        $commands = array_map(static fn (array $command): string => (string) $command['command'], $report['commands']);

        $this->assertSame([], $report['domains']);
        $this->assertContains('vendor/bin/phpstan analyse', $commands);
        $this->assertContains(
            'no domain-specific rule matched cleanly; selector escalated to static analysis instead of defaulting to the full suite',
            $report['notes']
        );
    }
}
