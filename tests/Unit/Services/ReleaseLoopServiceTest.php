<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\ApiContract\Services\OpsGateArtifactService;
use App\Platform\Release\Services\ReleaseBuildMetadataService;
use App\Platform\Release\Services\ReleaseLoopService;
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
            'package_integrity',
            'backend_fe_contract',
            'backend_web_auth',
            'backend_golden_flows',
            'backend_doctor',
            'backend_deploy_preflight',
            'staff_web_test',
            'staff_web_build',
            'customer_web_contracts',
            'customer_web_lint',
            'customer_web_typecheck',
            'customer_web_test',
            'customer_web_build',
            'customer_web_e2e_smoke',
            'preview_deploy',
            'staff_web_live_smoke',
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
            'package_integrity',
            'backend_fe_contract',
            'backend_web_auth',
            'backend_golden_flows',
            'backend_doctor',
            'backend_deploy_preflight',
            'staff_web_test',
            'staff_web_build',
            'customer_web_contracts',
            'customer_web_lint',
            'customer_web_typecheck',
            'customer_web_test',
            'customer_web_build',
            'customer_web_e2e_smoke',
            'preview_deploy',
            'staff_web_live_smoke',
            'backend_launch_readiness',
        ], $service->stepKeys);
        $this->assertCount(1, (array) ($report['blocking_failures'] ?? []));
        $this->assertSame('backend_doctor', data_get($report, 'blocking_failures.0.step_key'));
        $this->assertSame('staff_web_test', data_get($report, 'steps.7.key'));
        $this->assertSame('backend_launch_readiness', data_get($report, 'steps.17.key'));
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
            (string) data_get($report, 'steps.15.summary')
        );
        $this->assertStringContainsString(
            'Preview deploy: no linked preview project detected',
            implode("\n", (array) ($report['warnings'] ?? []))
        );
        $this->assertSame('unavailable', data_get($report, 'release_handoff.launch_readiness.decision'));
        $this->assertStringContainsString(
            'no linked preview project detected',
            (string) data_get($report, 'release_handoff.promotion_blockers.0')
        );
        $this->assertSame('preview_proof', data_get($report, 'follow_up_actions.0.kind'));
        $this->assertStringContainsString(
            'booking:release-loop --target=staging --preview-url=<preview-url> --preview-label=vercel-preview --json',
            (string) data_get($report, 'follow_up_actions.0.commands.0')
        );
        $this->assertStringContainsString(
            '- Preview: `preview`',
            (string) file_get_contents(base_path((string) data_get($report, 'artifacts.markdown_path', '')))
        );
        $this->assertStringContainsString(
            '## Follow-up Actions',
            (string) file_get_contents(base_path((string) data_get($report, 'artifacts.markdown_path', '')))
        );
        $this->assertStringContainsString(
            '## Release Handoff',
            (string) file_get_contents(base_path((string) data_get($report, 'artifacts.markdown_path', '')))
        );
        $this->assertContains('docs/runbooks/booking-deploy-runbook.md', (array) data_get($report, 'rollback.docs', []));
        $this->assertStringContainsString(
            'Do not rely on `build/booking-release/latest-package.json` alone for rollback selection.',
            (string) file_get_contents(base_path((string) data_get($report, 'artifacts.markdown_path', '')))
        );
    }

    public function test_run_treats_warning_only_json_steps_as_warn_without_blocking_release_loop(): void
    {
        $service = new class(new OpsGateArtifactService, new ReleaseBuildMetadataService) extends ReleaseLoopService
        {
            protected function executeStep(array $definition, string $stepArtifactRoot): array
            {
                $key = (string) ($definition['key'] ?? '');

                if (in_array($key, ['backend_deploy_preflight', 'backend_launch_readiness'], true)) {
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
                    'summary' => 'ok=true',
                    'artifacts' => [],
                ];
            }

            protected function makeProcess(array|string $command, string $cwd, array $env = [], ?float $timeoutSeconds = null): Process
            {
                $commandString = is_string($command) ? $command : implode(' ', $command);

                if (str_contains($commandString, 'booking:deploy-check')) {
                    $payload = json_encode([
                        'ok' => false,
                        'mode' => 'preflight',
                        'report' => [
                            'ok' => true,
                            'errors' => [],
                            'warnings' => [
                                'ops.conversation_inbox: Conversation inbox backlog needs operator review before rollout.',
                            ],
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

                    return parent::makeProcess(
                        [PHP_BINARY, '-r', 'fwrite(STDOUT, '.var_export($payload, true).'); exit(1);'],
                        $cwd,
                        $env,
                        $timeoutSeconds,
                    );
                }

                if (str_contains($commandString, 'booking:launch-readiness')) {
                    $payload = json_encode([
                        'ok' => false,
                        'decision' => 'ready_with_warnings',
                        'exit_code' => 2,
                        'follow_up_actions' => [
                            [
                                'kind' => 'manual_evidence_template',
                                'label' => 'Scaffold operator-owned manual evidence template',
                                'runbook_path' => 'docs/runbooks/booking-launch-readiness.md',
                                'commands' => [
                                    'php artisan booking:manual-evidence:init --target=staging --candidate=20260408 --json',
                                ],
                                'notes' => [
                                    'Use the generated template for the next launch-readiness rerun.',
                                ],
                            ],
                        ],
                        'release_handoff' => [
                            'candidate' => [
                                'available' => true,
                                'package_basename' => 'restaurantpos-backend-release-generated',
                                'package_path' => 'build/booking-release/restaurantpos-backend-release-generated.tar.gz',
                            ],
                            'manual_evidence' => [
                                'provided' => false,
                                'path' => null,
                            ],
                            'archive_paths' => [
                                'build/booking-release/restaurantpos-backend-release-generated.tar.gz',
                                'build/booking-release/latest-package.json',
                            ],
                            'promotion_notes' => [
                                'Copy package_basename, package_path, and sidecar paths into the release ticket before promotion.',
                            ],
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

                    return parent::makeProcess(
                        [PHP_BINARY, '-r', 'fwrite(STDOUT, '.var_export($payload, true).'); exit(2);'],
                        $cwd,
                        $env,
                        $timeoutSeconds,
                    );
                }

                return parent::makeProcess($command, $cwd, $env, $timeoutSeconds);
            }
        };

        $report = $service->run(
            target: 'staging',
            previewUrl: 'https://preview.example.test',
            previewLabel: 'vercel-preview',
        );

        $this->assertTrue((bool) ($report['ok'] ?? false));
        $this->assertSame('pass', $report['decision'] ?? null);
        $this->assertSame(2, data_get($report, 'summary.warn_count'));
        $this->assertSame('warn', data_get($report, 'steps.6.status'));
        $this->assertSame(
            'ops.conversation_inbox: Conversation inbox backlog needs operator review before rollout.',
            data_get($report, 'steps.6.summary')
        );
        $this->assertSame('warn', data_get($report, 'steps.17.status'));
        $this->assertSame('decision=ready_with_warnings', data_get($report, 'steps.17.summary'));
        $this->assertSame([], (array) ($report['blocking_failures'] ?? []));
        $this->assertContains(
            'Backend deploy preflight: ops.conversation_inbox: Conversation inbox backlog needs operator review before rollout.',
            (array) ($report['warnings'] ?? [])
        );
        $this->assertContains(
            'Backend launch readiness: decision=ready_with_warnings',
            (array) ($report['warnings'] ?? [])
        );
        $this->assertSame('manual_evidence_template', data_get($report, 'follow_up_actions.0.kind'));
        $this->assertSame('observability', data_get($report, 'follow_up_actions.1.kind'));
        $this->assertSame(
            'restaurantpos-backend-release-generated',
            data_get($report, 'release_handoff.candidate.package_basename')
        );
        $this->assertContains(
            'build/booking-release/latest-package.json',
            (array) data_get($report, 'release_handoff.archive_paths', [])
        );
        $this->assertStringContainsString(
            'booking:manual-evidence:init --target=staging --candidate=20260408 --json',
            (string) data_get($report, 'follow_up_actions.0.commands.0')
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
        $this->assertStringContainsString('timed out after 1 seconds', (string) data_get($report, 'steps.6.summary'));
        $this->assertSame('staff_web_test', data_get($report, 'steps.7.key'));
        $this->assertContains('backend_launch_readiness', $service->stepKeys);
    }
}
