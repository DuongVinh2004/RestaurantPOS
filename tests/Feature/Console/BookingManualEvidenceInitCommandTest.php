<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class BookingManualEvidenceInitCommandTest extends TestCase
{
    private string $outputRoot = 'storage/framework/testing/manual_evidence_init';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->outputRoot));

        parent::tearDown();
    }

    #[Group('booking-smoke')]
    public function test_booking_manual_evidence_init_scaffolds_the_staging_template_as_json(): void
    {
        $outputPath = $this->outputRoot.'/staging-template.json';

        $exitCode = Artisan::call('booking:manual-evidence:init', [
            '--target' => 'staging',
            '--candidate' => '20260420-r1',
            '--output' => $outputPath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $template = json_decode((string) File::get(base_path($outputPath)), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue((bool) ($payload['ok'] ?? false));
        $this->assertSame('staging', $payload['target'] ?? null);
        $this->assertSame('20260420-r1', $payload['candidate'] ?? null);
        $this->assertSame($outputPath, $payload['output_path'] ?? null);
        $this->assertSame([
            'uat_scenario_pack_replay',
            'disaster_recovery_restore_evidence',
            'performance_verification_report',
            'payment_provider_external_e2e',
            'notification_provider_external_e2e',
        ], $payload['check_keys'] ?? null);
        $this->assertSame('missing', data_get($template, 'checks.uat_scenario_pack_replay.status'));
        $this->assertSame('missing', data_get($template, 'checks.disaster_recovery_restore_evidence.status'));
        $this->assertSame('', data_get($template, 'checks.performance_verification_report.performed_by'));
        $this->assertFalse((bool) data_get($template, 'guidance.uat_scenario_pack_replay.required_for_target'));
        $this->assertFalse((bool) data_get($template, 'guidance.disaster_recovery_restore_evidence.required_for_target'));
        $this->assertFalse((bool) data_get($template, 'guidance.payment_provider_external_e2e.required_for_target'));
        $this->assertSame(
            'Canonical UAT scenario pack replay',
            data_get($template, 'guidance.uat_scenario_pack_replay.label')
        );
        $this->assertSame(
            'docs/runbooks/uat-demo-scenario-pack.md',
            data_get($template, 'guidance.uat_scenario_pack_replay.runbook_path')
        );
        $this->assertContains(
            'pwsh -File .\\scripts\\uat\\Invoke-UatScenario.ps1 -Scenario all',
            (array) data_get($template, 'guidance.uat_scenario_pack_replay.operator_commands', [])
        );
    }

    #[Group('booking-smoke')]
    public function test_booking_manual_evidence_init_scaffolds_all_limited_production_checks(): void
    {
        $outputPath = $this->outputRoot.'/limited-production-template.json';

        $exitCode = Artisan::call('booking:manual-evidence:init', [
            '--target' => 'limited-production',
            '--candidate' => 'candidate-20260420',
            '--output' => $outputPath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $template = json_decode((string) File::get(base_path($outputPath)), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue((bool) ($payload['ok'] ?? false));
        $this->assertSame(6, $payload['check_count'] ?? null);
        $this->assertSame([
            'uat_scenario_pack_replay',
            'disaster_recovery_restore_evidence',
            'performance_verification_report',
            'payment_provider_external_e2e',
            'notification_provider_external_e2e',
            'concurrency_rehearsal',
        ], $payload['check_keys'] ?? null);
        $this->assertTrue((bool) data_get($template, 'guidance.disaster_recovery_restore_evidence.required_for_target'));
        $this->assertTrue((bool) data_get($template, 'guidance.payment_provider_external_e2e.required_for_target'));
        $this->assertTrue((bool) data_get($template, 'guidance.concurrency_rehearsal.required_for_target'));
        $this->assertSame(
            'docs/runbooks/booking-performance-verification.md',
            data_get($template, 'guidance.concurrency_rehearsal.runbook_path')
        );
        $this->assertContains(
            'php artisan booking:performance-verify --profile=staging --run --base-url=<base-url> --scenario=reservation_create_race --scenario=checkout_preview_load',
            (array) data_get($template, 'guidance.concurrency_rehearsal.operator_commands', [])
        );
        $this->assertStringContainsString(
            '--manual-evidence='.$outputPath,
            (string) ($payload['next_command'] ?? '')
        );
    }

    #[Group('booking-smoke')]
    public function test_booking_manual_evidence_init_requires_overwrite_before_replacing_existing_file(): void
    {
        $outputPath = base_path($this->outputRoot.'/existing-template.json');
        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, "{\"checks\":{}}\n");

        $exitCode = Artisan::call('booking:manual-evidence:init', [
            '--target' => 'staging',
            '--candidate' => 'collision',
            '--output' => $outputPath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertFalse((bool) ($payload['ok'] ?? true));
        $this->assertStringContainsString('already exists', implode(' | ', (array) ($payload['issues'] ?? [])));
        $this->assertSame("{\"checks\":{}}\n", (string) File::get($outputPath));
    }

    #[Group('booking-smoke')]
    public function test_booking_manual_evidence_init_can_write_multiple_templates_under_the_same_directory(): void
    {
        $stagingPath = $this->outputRoot.'/batch/staging.json';
        $limitedPath = $this->outputRoot.'/batch/limited.json';

        $firstExitCode = Artisan::call('booking:manual-evidence:init', [
            '--target' => 'staging',
            '--candidate' => '20260420-a',
            '--output' => $stagingPath,
            '--json' => true,
        ]);
        $secondExitCode = Artisan::call('booking:manual-evidence:init', [
            '--target' => 'limited-production',
            '--candidate' => '20260420-b',
            '--output' => $limitedPath,
            '--json' => true,
        ]);

        $this->assertSame(0, $firstExitCode);
        $this->assertSame(0, $secondExitCode);
        $this->assertFileExists(base_path($stagingPath));
        $this->assertFileExists(base_path($limitedPath));
    }
}
