<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\OpsGateArtifactService;
use App\Services\ReleaseBuildMetadataService;
use App\Services\ReleaseLoopService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReleaseLoopServiceTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/release_loop_service';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking_release.release_loop.artifact_root', $this->artifactRoot);
        Carbon::setTestNow(Carbon::parse('2026-04-08T17:20:00Z'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory(base_path($this->artifactRoot));

        parent::tearDown();
    }

    public function test_run_records_the_canonical_release_loop_and_writes_artifacts(): void
    {
        $service = new class(new OpsGateArtifactService, new class extends ReleaseBuildMetadataService
        {
            public function current(): array
            {
                return [
                    'commit_sha' => 'abc123',
                    'ref_name' => 'refs/heads/main',
                    'run_id' => 'run-42',
                ];
            }
        }) extends ReleaseLoopService
        {

            /** @var list<string> */
            public array $stepKeys = [];

            protected function executeStep(array $definition, string $stepArtifactRoot): array
            {
                $this->stepKeys[] = (string) ($definition['key'] ?? '');

                return [
                    'key' => (string) ($definition['key'] ?? ''),
                    'label' => (string) ($definition['label'] ?? ''),
                    'status' => 'pass',
                    'exit_code' => 0,
                    'command' => 'synthetic',
                    'cwd' => 'synthetic',
                    'duration_ms' => 1,
                    'summary' => 'ok=true',
                    'artifacts' => [
                        'log_path' => trim($stepArtifactRoot.'/'.($definition['key'] ?? 'step').'.log', '/'),
                    ],
                ];
            }
        };

        $report = $service->run(
            target: 'staging',
            manifestPath: 'storage/app/uat/scenario-pack.json',
            baseUrl: 'http://127.0.0.1:8000',
            previewUrl: 'https://preview.example.test',
            previewLabel: 'vercel-preview',
        );

        $this->assertTrue((bool) ($report['ok'] ?? false));
        $this->assertSame('pass', $report['decision'] ?? null);
        $this->assertSame([
            'contract_artifacts',
            'backend_fe_contract',
            'backend_web_auth',
            'backend_golden_flows',
            'backend_doctor',
            'backend_deploy_preflight',
            'frontend_test',
            'frontend_build',
            'preview_deploy',
            'frontend_live_smoke',
            'backend_launch_readiness',
        ], $service->stepKeys);
        $this->assertSame('https://preview.example.test', data_get($report, 'preview.url'));
        $this->assertSame('url-recorded', data_get($report, 'preview.status'));
        $this->assertSame('vercel', data_get($report, 'preview.provider'));
        $this->assertSame('missing-configuration', data_get($report, 'observability.status'));
        $this->assertContains('SENTRY_AUTH_TOKEN', (array) data_get($report, 'observability.missing_env', []));
        $this->assertSame($this->artifactRoot.'/reports', data_get($report, 'artifacts.reports_root'));
        $this->assertFileExists(base_path((string) data_get($report, 'artifacts.json_path', '')));
        $this->assertFileExists(base_path((string) data_get($report, 'artifacts.markdown_path', '')));
        $this->assertSame('abc123', data_get($report, 'meta.build_metadata.commit_sha'));
        $this->assertSame(
            $this->artifactRoot.'/steps/staging/20260408t172000z',
            data_get($report, 'paths.step_artifact_root')
        );
    }

    public function test_run_continues_collecting_safe_evidence_after_a_failed_step(): void
    {
        $service = new class(new OpsGateArtifactService, new ReleaseBuildMetadataService) extends ReleaseLoopService
        {
            /** @var list<string> */
            public array $stepKeys = [];

            protected function executeStep(array $definition, string $stepArtifactRoot): array
            {
                $key = (string) ($definition['key'] ?? '');
                $this->stepKeys[] = $key;

                if ($key === 'backend_doctor') {
                    return [
                        'key' => $key,
                        'label' => (string) ($definition['label'] ?? $key),
                        'status' => 'fail',
                        'exit_code' => 1,
                        'command' => 'synthetic',
                        'cwd' => 'synthetic',
                        'duration_ms' => 1,
                        'summary' => 'doctor failed',
                        'artifacts' => [],
                    ];
                }

                return [
                    'key' => $key,
                    'label' => (string) ($definition['label'] ?? $key),
                    'status' => 'pass',
                    'exit_code' => 0,
                    'command' => 'synthetic',
                    'cwd' => 'synthetic',
                    'duration_ms' => 1,
                    'summary' => 'ok',
                    'artifacts' => [],
                ];
            }
        };

        $report = $service->run(target: 'staging', skipPreview: true);

        $this->assertFalse((bool) ($report['ok'] ?? true));
        $this->assertSame('block', $report['decision'] ?? null);
        $this->assertSame([
            'contract_artifacts',
            'backend_fe_contract',
            'backend_web_auth',
            'backend_golden_flows',
            'backend_doctor',
            'backend_deploy_preflight',
            'frontend_test',
            'frontend_build',
            'preview_deploy',
            'frontend_live_smoke',
            'backend_launch_readiness',
        ], $service->stepKeys);
        $this->assertCount(1, (array) ($report['blocking_failures'] ?? []));
        $this->assertSame('backend_doctor', data_get($report, 'blocking_failures.0.step_key'));
        $this->assertSame('frontend_test', data_get($report, 'steps.6.key'));
        $this->assertSame('backend_launch_readiness', data_get($report, 'steps.10.key'));
        $this->assertSame('skipped', data_get($report, 'preview.status'));
        $this->assertStringContainsString(
            'Observability: Sentry release/runtime evidence unavailable;',
            implode("\n", (array) ($report['warnings'] ?? []))
        );
    }

    public function test_run_marks_missing_preview_linkage_as_an_explicit_external_blocker(): void
    {
        $service = new class(new OpsGateArtifactService, new ReleaseBuildMetadataService) extends ReleaseLoopService
        {
            protected function executeStep(array $definition, string $stepArtifactRoot): array
            {
                if (($definition['key'] ?? null) === 'preview_deploy') {
                    return parent::executeStep($definition, $stepArtifactRoot);
                }

                return [
                    'key' => (string) ($definition['key'] ?? ''),
                    'label' => (string) ($definition['label'] ?? ''),
                    'status' => 'pass',
                    'exit_code' => 0,
                    'command' => 'synthetic',
                    'cwd' => 'synthetic',
                    'duration_ms' => 1,
                    'summary' => 'ok=true',
                    'artifacts' => [],
                ];
            }
        };

        $report = $service->run(target: 'staging');

        $this->assertSame('unconfigured', data_get($report, 'preview.status'));
        $this->assertFalse((bool) data_get($report, 'preview.available', true));
        $this->assertFalse((bool) data_get($report, 'preview.linked_project_detected', true));
        $this->assertStringContainsString(
            'external platform blocker',
            (string) data_get($report, 'steps.8.summary')
        );
        $this->assertStringContainsString(
            'Preview deploy: no linked preview project detected',
            implode("\n", (array) ($report['warnings'] ?? []))
        );
        $this->assertStringContainsString(
            '- Preview: `preview`',
            (string) file_get_contents(base_path((string) data_get($report, 'artifacts.markdown_path', '')))
        );
        $this->assertContains('docs/runbooks/booking-deploy-runbook.md', (array) data_get($report, 'rollback.docs', []));
        $this->assertStringContainsString(
            'Do not rely on `build/booking-release/latest-package.json` alone for rollback selection.',
            (string) file_get_contents(base_path((string) data_get($report, 'artifacts.markdown_path', '')))
        );
    }

    public function test_run_records_timed_out_steps_without_crashing_the_release_loop(): void
    {
        $service = new class(new OpsGateArtifactService, new ReleaseBuildMetadataService) extends ReleaseLoopService
        {
            /** @var list<string> */
            public array $stepKeys = [];

            protected function executeStep(array $definition, string $stepArtifactRoot): array
            {
                $key = (string) ($definition['key'] ?? '');
                $this->stepKeys[] = $key;

                if ($key === 'backend_deploy_preflight') {
                    return parent::executeStep($definition, $stepArtifactRoot);
                }

                return [
                    'key' => $key,
                    'label' => (string) ($definition['label'] ?? $key),
                    'status' => 'pass',
                    'exit_code' => 0,
                    'command' => 'synthetic',
                    'cwd' => 'synthetic',
                    'duration_ms' => 1,
                    'summary' => 'ok',
                    'artifacts' => [],
                ];
            }

            protected function makeProcess(array|string $command, string $cwd, array $env = [], ?float $timeoutSeconds = null): Process
            {
                return parent::makeProcess(
                    [PHP_BINARY, '-r', 'usleep(250000);'],
                    $cwd,
                    $env,
                    0.05,
                );
            }
        };

        $report = $service->run(target: 'staging');

        $this->assertFalse((bool) ($report['ok'] ?? true));
        $this->assertSame('block', $report['decision'] ?? null);
        $this->assertSame('backend_deploy_preflight', data_get($report, 'blocking_failures.0.step_key'));
        $this->assertStringContainsString('timed out after 1 seconds', (string) data_get($report, 'steps.5.summary'));
        $this->assertSame('frontend_test', data_get($report, 'steps.6.key'));
        $this->assertContains('backend_launch_readiness', $service->stepKeys);
    }
}
