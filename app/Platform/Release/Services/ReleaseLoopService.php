<?php

declare(strict_types=1);

namespace App\Platform\Release\Services;

use App\Platform\ApiContract\Services\OpsGateArtifactService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ReleaseLoopService
{
    public function __construct(
        private readonly OpsGateArtifactService $opsGateArtifactService,
        private readonly ReleaseBuildMetadataService $releaseBuildMetadataService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $target = 'staging',
        ?string $manualEvidencePath = null,
        ?string $packageId = null,
        bool $overwritePackage = false,
        ?string $manifestPath = null,
        bool $bootstrapUat = false,
        ?string $baseUrl = null,
        ?string $previewCommand = null,
        ?string $previewUrl = null,
        ?string $previewLabel = null,
        bool $skipPreview = false,
        string $staffWebDir = 'staff-web',
        string $customerWebDir = 'customer-web',
    ): array {
        $evaluatedAt = now('UTC');
        $artifactRoot = trim((string) config('booking_release.release_loop.artifact_root', 'storage/app/booking_release/release_loop'), '/');
        $scopeKey = trim($target) !== '' ? trim($target) : 'staging';
        $scopeSlug = Str::slug($scopeKey, '-') ?: 'staging';
        $runSlug = $evaluatedAt->copy()->utc()->format('Ymd\THis\Z');
        $stepArtifactRoot = trim(sprintf('%s/steps/%s/%s', $artifactRoot, $scopeSlug, strtolower($runSlug)), '/');

        File::ensureDirectoryExists(base_path($stepArtifactRoot));

        $staffWebPath = $this->resolvePath($staffWebDir);
        $customerWebPath = $this->resolvePath($customerWebDir);
        $resolvedManifestPath = $this->resolveManifestPath($manifestPath, $bootstrapUat);
        $resolvedManualEvidencePath = $this->resolveOptionalPath($manualEvidencePath);
        $resolvedBaseUrl = $baseUrl !== null && trim($baseUrl) !== '' ? trim($baseUrl) : null;
        $resolvedPreviewCommand = $previewCommand !== null && trim($previewCommand) !== '' ? trim($previewCommand) : null;
        $resolvedPreviewUrl = $previewUrl !== null && trim($previewUrl) !== '' ? trim($previewUrl) : null;
        $resolvedPreviewLabel = $previewLabel !== null && trim($previewLabel) !== ''
            ? trim($previewLabel)
            : 'preview';
        $previewContext = $this->resolvePreviewContext(
            previewCommand: $resolvedPreviewCommand,
            previewUrl: $resolvedPreviewUrl,
            previewLabel: $resolvedPreviewLabel,
            skipPreview: $skipPreview,
            staffWebPath: $staffWebPath,
            customerWebPath: $customerWebPath,
        );
        $observabilityContext = $this->resolveObservabilityContext();

        $steps = [];
        foreach ($this->buildStepDefinitions(
            target: $scopeKey,
            manualEvidencePath: $resolvedManualEvidencePath,
            packageId: $packageId,
            overwritePackage: $overwritePackage,
            manifestPath: $resolvedManifestPath,
            bootstrapUat: $bootstrapUat,
            baseUrl: $resolvedBaseUrl,
            previewCommand: $resolvedPreviewCommand,
            previewUrl: $resolvedPreviewUrl,
            previewLabel: $resolvedPreviewLabel,
            previewContext: $previewContext,
            skipPreview: $skipPreview,
            staffWebPath: $staffWebPath,
            customerWebPath: $customerWebPath,
            stepArtifactRoot: $stepArtifactRoot,
        ) as $stepDefinition) {
            $step = $this->executeStep($stepDefinition, $stepArtifactRoot);
            $steps[] = $step;
        }

        $summary = [
            'step_count' => count($steps),
            'pass_count' => count(array_filter($steps, static fn (array $step): bool => ($step['status'] ?? null) === 'pass')),
            'warn_count' => count(array_filter($steps, static fn (array $step): bool => ($step['status'] ?? null) === 'warn')),
            'skip_count' => count(array_filter($steps, static fn (array $step): bool => ($step['status'] ?? null) === 'skip')),
            'fail_count' => count(array_filter($steps, static fn (array $step): bool => ($step['status'] ?? null) === 'fail')),
        ];

        $blockingFailures = array_values(array_map(
            static fn (array $step): array => [
                'step_key' => (string) ($step['key'] ?? ''),
                'step_label' => (string) ($step['label'] ?? ''),
                'message' => (string) ($step['summary'] ?? ''),
            ],
            array_values(array_filter($steps, static fn (array $step): bool => ($step['status'] ?? null) === 'fail'))
        ));

        $warnings = [];
        foreach ($steps as $step) {
            if (in_array(($step['status'] ?? null), ['skip', 'warn'], true)) {
                $warnings[] = sprintf('%s: %s', (string) ($step['label'] ?? $step['key'] ?? 'step'), (string) ($step['summary'] ?? 'skipped'));
            }
        }
        if (! (bool) ($observabilityContext['configured'] ?? false)) {
            $warnings[] = 'Observability: '.(string) ($observabilityContext['reason'] ?? 'missing configuration');
        }
        $followUpActions = $this->buildFollowUpActions($scopeKey, $steps, $previewContext, $observabilityContext);
        $releaseHandoff = $this->buildReleaseHandoff($steps, $previewContext, $observabilityContext);

        $report = [
            'ok' => $blockingFailures === [],
            'decision' => $blockingFailures === [] ? 'pass' : 'block',
            'target' => [
                'key' => $scopeKey,
                'label' => Str::headline($scopeKey),
            ],
            'summary' => $summary,
            'steps' => $steps,
            'blocking_failures' => $blockingFailures,
            'warnings' => $warnings,
            'follow_up_actions' => $followUpActions,
            'release_handoff' => $releaseHandoff,
            'preview' => [
                'label' => $resolvedPreviewLabel,
                'url' => $resolvedPreviewUrl,
                'command' => $resolvedPreviewCommand,
                'skipped' => $skipPreview,
                'provider' => $previewContext['provider'],
                'status' => $previewContext['status'],
                'available' => $previewContext['available'],
                'linked_project_detected' => $previewContext['linked_project_detected'],
                'linked_project_paths' => $previewContext['linked_project_paths'],
                'reason' => $previewContext['reason'],
            ],
            'observability' => $observabilityContext,
            'paths' => [
                'staff_web_dir' => $this->relativePath($staffWebPath),
                'customer_web_dir' => $this->relativePath($customerWebPath),
                'manifest_path' => $resolvedManifestPath !== null ? $this->relativePath($resolvedManifestPath) : null,
                'manual_evidence_path' => $resolvedManualEvidencePath !== null ? $this->relativePath($resolvedManualEvidencePath) : null,
                'step_artifact_root' => $stepArtifactRoot,
            ],
            'rollback' => [
                'commands' => [
                    'php artisan booking:release-manifest --verify-frozen --json',
                    'php artisan booking:deploy-check --mode=preflight --strict',
                    'php artisan booking:launch-readiness --target=staging --json',
                ],
                'docs' => [
                    'docs/runbooks/booking-release-packaging-runbook.md',
                    'docs/runbooks/booking-launch-readiness.md',
                    'docs/runbooks/booking-ci-cd-runbook.md',
                    'docs/runbooks/booking-deploy-runbook.md',
                ],
                'note' => 'Rollback by redeploying the previously archived known-good immutable package together with its .metadata.json, .inventory.json, .checksums.sha256, and .package.sha256 sidecars, then re-running the preflight/readiness gates before reopening traffic. Do not rely on latest-package.json alone for rollback selection.',
            ],
            'meta' => [
                'evaluated_at_utc' => $evaluatedAt->toIso8601String(),
                'build_metadata' => $this->releaseBuildMetadataService->current(),
                'base_url' => $resolvedBaseUrl,
                'bootstrap_uat' => $bootstrapUat,
                'overwrite_package' => $overwritePackage,
                'package_id' => $packageId,
            ],
        ];

        return $this->opsGateArtifactService->writeReport(
            artifactRoot: $artifactRoot,
            reportPrefix: 'release-loop',
            scopeKey: $scopeKey,
            payload: $report,
            markdown: $this->renderMarkdown($report),
            evaluatedAt: $evaluatedAt,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildStepDefinitions(
        string $target,
        ?string $manualEvidencePath,
        ?string $packageId,
        bool $overwritePackage,
        ?string $manifestPath,
        bool $bootstrapUat,
        ?string $baseUrl,
        ?string $previewCommand,
        ?string $previewUrl,
        string $previewLabel,
        array $previewContext,
        bool $skipPreview,
        string $staffWebPath,
        string $customerWebPath,
        string $stepArtifactRoot,
    ): array {
        $rootPath = base_path();
        $manifestCliPath = $manifestPath !== null ? $this->pathForRepoCommand($manifestPath) : null;
        $manualEvidenceCliPath = $manualEvidencePath !== null ? $this->pathForRepoCommand($manualEvidencePath) : null;
        $definitions = [
            [
                'key' => 'contract_artifacts',
                'label' => 'Contract artifacts',
                'command' => ['composer', 'api:artifacts'],
                'cwd' => $rootPath,
            ],
            [
                'key' => 'package_integrity',
                'label' => 'Package integrity',
                'command' => ['node', 'scripts/release/check-package-integrity.mjs', '--json'],
                'cwd' => $rootPath,
                'capture_json' => true,
            ],
            [
                'key' => 'backend_fe_contract',
                'label' => 'Backend FE contract harness',
                'command' => $this->buildArtisanCommand('booking:harness:fe-contract', [
                    '--json',
                    ...$manifestCliPath !== null ? ['--uat-manifest='.$manifestCliPath] : [],
                ]),
                'cwd' => $rootPath,
                'capture_json' => true,
            ],
            [
                'key' => 'backend_web_auth',
                'label' => 'Backend web auth harness',
                'command' => $this->buildArtisanCommand('booking:harness:web-auth', ['--json']),
                'cwd' => $rootPath,
                'capture_json' => true,
            ],
            [
                'key' => 'backend_golden_flows',
                'label' => 'Backend golden-flow harness',
                'command' => $this->buildArtisanCommand('booking:harness:golden-flows', array_values(array_filter([
                    '--json',
                    $manifestCliPath !== null ? '--manifest-path='.$manifestCliPath : null,
                    $bootstrapUat ? '--bootstrap-uat' : null,
                    $baseUrl !== null ? '--base-url='.$baseUrl : null,
                ]))),
                'cwd' => $rootPath,
                'capture_json' => true,
            ],
            [
                'key' => 'backend_doctor',
                'label' => 'Backend doctor',
                'command' => $this->buildArtisanCommand('booking:doctor', ['--json']),
                'cwd' => $rootPath,
                'capture_json' => true,
            ],
            [
                'key' => 'backend_deploy_preflight',
                'label' => 'Backend deploy preflight',
                'command' => $this->buildArtisanCommand('booking:deploy-check', ['--mode=preflight', '--strict', '--json']),
                'cwd' => $rootPath,
                'capture_json' => true,
            ],
            [
                'key' => 'staff_web_test',
                'label' => 'Staff-web tests',
                'command' => ['npm', 'run', 'test'],
                'cwd' => $staffWebPath,
            ],
            [
                'key' => 'staff_web_build',
                'label' => 'Staff-web build',
                'command' => ['npm', 'run', 'build'],
                'cwd' => $staffWebPath,
            ],
            [
                'key' => 'customer_web_contracts',
                'label' => 'Customer-web contracts',
                'command' => ['npm', 'run', 'verify:contracts'],
                'cwd' => $customerWebPath,
            ],
            [
                'key' => 'customer_web_lint',
                'label' => 'Customer-web lint',
                'command' => ['npm', 'run', 'lint'],
                'cwd' => $customerWebPath,
            ],
            [
                'key' => 'customer_web_typecheck',
                'label' => 'Customer-web typecheck',
                'command' => ['npm', 'run', 'typecheck'],
                'cwd' => $customerWebPath,
            ],
            [
                'key' => 'customer_web_test',
                'label' => 'Customer-web tests',
                'command' => ['npm', 'run', 'test'],
                'cwd' => $customerWebPath,
            ],
            [
                'key' => 'customer_web_build',
                'label' => 'Customer-web build',
                'command' => ['npm', 'run', 'build'],
                'cwd' => $customerWebPath,
            ],
            [
                'key' => 'customer_web_e2e_smoke',
                'label' => 'Customer-web Playwright smoke',
                'command' => ['npm', 'run', 'test:e2e:smoke'],
                'cwd' => $customerWebPath,
            ],
            [
                'key' => 'preview_deploy',
                'label' => 'Preview deploy',
                'command' => $previewCommand,
                'cwd' => $rootPath,
                'preview_url' => $previewUrl,
                'preview_label' => $previewLabel,
                'preview_context' => $previewContext,
                'skip_preview' => $skipPreview,
                'kind' => 'preview',
            ],
            [
                'key' => 'staff_web_live_smoke',
                'label' => 'Staff-web live smoke',
                'command' => ['npm', 'run', 'smoke:live'],
                'cwd' => $staffWebPath,
                'kind' => 'live_smoke',
                'env' => $this->buildSmokeEnvironment(
                    target: $target,
                    manifestPath: $manifestPath,
                    baseUrl: $baseUrl,
                    previewUrl: $previewUrl,
                    previewLabel: $previewLabel,
                    evidenceDir: base_path(trim($stepArtifactRoot.'/staff-web-smoke', '/')),
                ),
            ],
            [
                'key' => 'heartbeat_refresh',
                'label' => 'Heartbeat refresh',
                'command' => $this->buildArtisanCommand('booking:ops-heartbeat:touch', ['scheduler', '--json']),
                'cwd' => $rootPath,
                'capture_json' => true,
                // Refresh the scheduler heartbeat immediately before launch-readiness so that
                // the doctor probe inside launch-readiness always finds a fresh heartbeat,
                // regardless of how long the preceding steps (e.g. frontend test suites) ran.
                // On real CI the scheduler daemon keeps the heartbeat alive automatically;
                // on local-dev machines the release loop must touch it explicitly.
            ],
            [
                'key' => 'backend_launch_readiness',
                'label' => 'Backend launch readiness',
                'command' => $this->buildArtisanCommand('booking:launch-readiness', array_values(array_filter([
                    '--target='.$target,
                    '--json',
                    $manualEvidenceCliPath !== null ? '--manual-evidence='.$manualEvidenceCliPath : null,
                    $packageId !== null ? '--package-id='.$packageId : null,
                    $overwritePackage ? '--overwrite-package' : null,
                ]))),
                'cwd' => $rootPath,
                'capture_json' => true,
            ],
        ];

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function executeStep(array $definition, string $stepArtifactRoot): array
    {
        if (($definition['kind'] ?? null) === 'preview') {
            return $this->executePreviewStep($definition);
        }

        if (($definition['kind'] ?? null) === 'live_smoke') {
            return $this->executeLiveSmokeStep($definition, $stepArtifactRoot);
        }

        return $this->executeCommandStep($definition, $stepArtifactRoot);
    }

    /**
     * Runs the staff-web live smoke step, ensuring an ephemeral backend HTTP server is
     * available at 127.0.0.1:8000 if one is not already listening. The server is started
     * before the smoke run and terminated immediately after, so the release loop is
     * fully self-contained with no prerequisite about a running server.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function executeLiveSmokeStep(array $definition, string $stepArtifactRoot): array
    {
        $serveHost = '127.0.0.1';
        $servePort = 8000;
        $alreadyListening = $this->isPortListening($serveHost, $servePort);
        $ephemeralServer = null;

        if (! $alreadyListening) {
            $ephemeralServer = $this->startEphemeralBackendServer($serveHost, $servePort);
        }

        try {
            return $this->executeCommandStep($definition, $stepArtifactRoot);
        } finally {
            if ($ephemeralServer !== null && $ephemeralServer->isRunning()) {
                $this->stopEphemeralBackendServer($ephemeralServer);
            }
        }
    }

    /**
     * Starts `php artisan serve` as a background process and waits until the health
     * endpoint responds (or the deadline is reached).
     */
    private function startEphemeralBackendServer(string $host, int $port): ?Process
    {
        $server = new Process(
            [PHP_BINARY, 'artisan', 'serve', "--host={$host}", "--port={$port}"],
            base_path(),
            null,
            null,
            null, // no timeout: we manage the lifecycle ourselves
        );

        $server->start();

        // Wait up to 15 seconds for the server to accept connections.
        $deadline = microtime(true) + 15;
        $ready = false;
        while (microtime(true) < $deadline) {
            if ($this->isPortListening($host, $port)) {
                $ready = true;
                break;
            }
            usleep(300_000); // 300 ms
        }

        if (! $ready) {
            // Server failed to start — terminate it and return null so the smoke step
            // runs anyway (it will fail with a network error, which is the correct outcome).
            $server->stop(3);
            return null;
        }

        return $server;
    }

    /**
     * Gracefully terminates the ephemeral backend server process.
     */
    private function stopEphemeralBackendServer(Process $server): void
    {
        $server->stop(5);
    }

    /**
     * Returns true if a TCP listener is accepting connections on the given host:port.
     */
    private function isPortListening(string $host, int $port): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);
        if ($socket !== false) {
            fclose($socket);
            return true;
        }
        return false;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function executeCommandStep(array $definition, string $stepArtifactRoot): array
    {
        $cwd = (string) ($definition['cwd'] ?? base_path());
        $key = (string) ($definition['key'] ?? 'step');
        $label = (string) ($definition['label'] ?? $key);
        $logRelativePath = trim($stepArtifactRoot.'/'.Str::slug($key, '-').'.log', '/');
        $jsonRelativePath = trim($stepArtifactRoot.'/'.Str::slug($key, '-').'.json', '/');
        File::ensureDirectoryExists(dirname(base_path($logRelativePath)));

        $startedAt = microtime(true);
        $process = $this->makeProcess(
            $definition['command'] ?? [],
            $cwd,
            (array) ($definition['env'] ?? []),
        );
        $timedOut = false;
        $timeoutSummary = null;
        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            $timedOut = true;
            $timeoutSummary = $this->timeoutSummary($exception, $label);
        }
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();
        $logContents = rtrim($stdout);
        if (trim($stderr) !== '') {
            $logContents = trim($logContents) !== ''
                ? $logContents.PHP_EOL.PHP_EOL.'[stderr]'.PHP_EOL.$stderr
                : '[stderr]'.PHP_EOL.$stderr;
        }
        if ($timedOut && $timeoutSummary !== null) {
            $logContents = trim($logContents) !== ''
                ? $logContents.PHP_EOL.PHP_EOL.'[timeout]'.PHP_EOL.$timeoutSummary
                : '[timeout]'.PHP_EOL.$timeoutSummary;
        }
        File::put(base_path($logRelativePath), rtrim($logContents).PHP_EOL);

        $artifacts = [
            'log_path' => $logRelativePath,
        ];

        $decodedJson = null;
        if ((bool) ($definition['capture_json'] ?? false) && trim($stdout) !== '') {
            File::put(base_path($jsonRelativePath), rtrim($stdout).PHP_EOL);
            $artifacts['json_path'] = $jsonRelativePath;
            $decoded = json_decode($stdout, true);
            if (is_array($decoded)) {
                $decodedJson = $decoded;
            }
        }

        $status = $this->determineStepStatus($decodedJson, $process, $timedOut);

        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'exit_code' => $timedOut ? ($process->getExitCode() ?? 124) : $process->getExitCode(),
            'command' => $this->displayCommand($definition['command'] ?? []),
            'cwd' => $this->relativePath($cwd),
            'duration_ms' => $durationMs,
            'summary' => $timedOut && $timeoutSummary !== null
                ? $timeoutSummary
                : $this->summarizeOutput($decodedJson, $stdout, $stderr, $process->getExitCode()),
            'artifacts' => $artifacts,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function executePreviewStep(array $definition): array
    {
        $key = (string) ($definition['key'] ?? 'preview');
        $label = (string) ($definition['label'] ?? 'Preview deploy');
        $previewUrl = $definition['preview_url'] ?? null;
        $previewLabel = (string) ($definition['preview_label'] ?? 'preview');
        $previewCommand = $definition['command'] ?? null;
        $previewContext = (array) ($definition['preview_context'] ?? []);
        $previewReason = trim((string) ($previewContext['reason'] ?? ''));

        if ((bool) ($definition['skip_preview'] ?? false)) {
            return [
                'key' => $key,
                'label' => $label,
                'status' => 'skip',
                'exit_code' => 0,
                'command' => $previewCommand !== null ? (string) $previewCommand : '',
                'cwd' => $this->relativePath((string) ($definition['cwd'] ?? base_path())),
                'duration_ms' => 0,
                'summary' => $previewReason !== '' ? $previewReason : 'preview stage skipped by flag',
                'artifacts' => [],
            ];
        }

        if ($previewCommand === null || trim((string) $previewCommand) === '') {
            if ($previewUrl !== null && trim((string) $previewUrl) !== '') {
                return [
                    'key' => $key,
                    'label' => $label,
                    'status' => 'pass',
                    'exit_code' => 0,
                    'command' => '',
                    'cwd' => $this->relativePath((string) ($definition['cwd'] ?? base_path())),
                    'duration_ms' => 0,
                    'summary' => sprintf('%s URL recorded: %s', $previewLabel, trim((string) $previewUrl)),
                    'artifacts' => [],
                ];
            }

            return [
                'key' => $key,
                'label' => $label,
                'status' => 'skip',
                'exit_code' => 0,
                'command' => '',
                'cwd' => $this->relativePath((string) ($definition['cwd'] ?? base_path())),
                'duration_ms' => 0,
                'summary' => $previewReason !== '' ? $previewReason : 'no preview command or preview URL configured',
                'artifacts' => [],
            ];
        }

        $process = $this->makeProcess(
            (string) $previewCommand,
            (string) ($definition['cwd'] ?? base_path()),
            [],
            $this->configuredTimeoutSeconds('preview_timeout_seconds'),
        );

        $startedAt = microtime(true);
        $timedOut = false;
        $timeoutSummary = null;
        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            $timedOut = true;
            $timeoutSummary = $this->timeoutSummary($exception, $label);
        }
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($timedOut && $timeoutSummary !== null) {
            return [
                'key' => $key,
                'label' => $label,
                'status' => 'fail',
                'exit_code' => $process->getExitCode() ?? 124,
                'command' => (string) $previewCommand,
                'cwd' => $this->relativePath((string) ($definition['cwd'] ?? base_path())),
                'duration_ms' => $durationMs,
                'summary' => $timeoutSummary,
                'artifacts' => [],
            ];
        }

        $summary = trim($process->getOutput()) !== ''
            ? $this->firstMeaningfulLine($process->getOutput())
            : sprintf('%s command finished with exit code %d', $previewLabel, (int) $process->getExitCode());

        if (! $process->isSuccessful()) {
            $summary = trim($process->getErrorOutput()) !== ''
                ? $this->firstMeaningfulLine($process->getErrorOutput())
                : $summary;
        } elseif ($previewUrl !== null && trim((string) $previewUrl) !== '') {
            $summary = sprintf('%s ready at %s', $previewLabel, trim((string) $previewUrl));
        }

        return [
            'key' => $key,
            'label' => $label,
            'status' => $process->isSuccessful() ? 'pass' : 'fail',
            'exit_code' => $process->getExitCode(),
            'command' => (string) $previewCommand,
            'cwd' => $this->relativePath((string) ($definition['cwd'] ?? base_path())),
            'duration_ms' => $durationMs,
            'summary' => $summary,
            'artifacts' => [],
        ];
    }

    /**
     * @param  array<string, string>  $env
     * @param  array<int, string>|string  $command
     */
    protected function makeProcess(array|string $command, string $cwd, array $env = [], ?float $timeoutSeconds = null): Process
    {
        $process = is_string($command)
            ? Process::fromShellCommandline($command, $cwd, $env)
            : new Process($command, $cwd, $env);

        $resolvedTimeout = $timeoutSeconds ?? $this->configuredTimeoutSeconds('step_timeout_seconds');
        $process->setTimeout($resolvedTimeout > 0 ? $resolvedTimeout : null);

        return $process;
    }

    /**
     * @return list<string>
     */
    private function buildArtisanCommand(string $command, array $arguments = []): array
    {
        return [
            PHP_BINARY,
            'artisan',
            $command,
            ...$arguments,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildSmokeEnvironment(
        string $target,
        ?string $manifestPath,
        ?string $baseUrl,
        ?string $previewUrl,
        string $previewLabel,
        string $evidenceDir,
    ): array {
        $environment = [
            'STAFF_WEB_SMOKE_TARGET' => $target,
            'STAFF_WEB_SMOKE_EVIDENCE_DIR' => $evidenceDir,
            'STAFF_WEB_SMOKE_PREVIEW_LABEL' => $previewLabel,
        ];

        if ($manifestPath !== null) {
            $environment['STAFF_WEB_SMOKE_MANIFEST_PATH'] = $manifestPath;
        }

        if ($baseUrl !== null) {
            $environment['STAFF_WEB_SMOKE_API_URL'] = rtrim($baseUrl, '/').'/api/v1';
        }

        if ($previewUrl !== null) {
            $environment['STAFF_WEB_SMOKE_PREVIEW_URL'] = $previewUrl;
        }

        return $environment;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePreviewContext(
        ?string $previewCommand,
        ?string $previewUrl,
        string $previewLabel,
        bool $skipPreview,
        string $staffWebPath,
        string $customerWebPath,
    ): array {
        $previewLinkPaths = array_values(array_unique(array_filter(array_map(
            static fn (mixed $path): string => is_string($path) ? trim($path) : '',
            array_merge(
                (array) config('booking_release.release_loop.preview_link_paths', []),
                [
                    '.vercel/project.json',
                    $this->relativePath(rtrim($customerWebPath, '/\\').'/.vercel/project.json'),
                    $this->relativePath(rtrim($staffWebPath, '/\\').'/.vercel/project.json'),
                ],
            ),
        ), static fn (string $path): bool => $path !== '')));

        $linkedProjectPaths = [];
        foreach ($previewLinkPaths as $previewLinkPath) {
            $resolvedPreviewLinkPath = $this->resolvePath($previewLinkPath);
            if (File::exists($resolvedPreviewLinkPath)) {
                $linkedProjectPaths[] = $this->relativePath($resolvedPreviewLinkPath);
            }
        }

        $linkedProjectDetected = $linkedProjectPaths !== [];
        $previewProvider = $this->resolvePreviewProvider(
            previewLabel: $previewLabel,
            linkedProjectDetected: $linkedProjectDetected,
            previewCommand: $previewCommand,
            previewUrl: $previewUrl,
        );
        $available = $linkedProjectDetected || $previewCommand !== null || $previewUrl !== null;

        if ($skipPreview) {
            return [
                'label' => $previewLabel,
                'preview_url' => $previewUrl,
                'provider' => $previewProvider,
                'status' => 'skipped',
                'available' => $available,
                'linked_project_detected' => $linkedProjectDetected,
                'linked_project_paths' => $linkedProjectPaths,
                'reason' => 'preview stage skipped by flag; no preview deployment proof was collected in this run',
            ];
        }

        if ($previewUrl !== null) {
            return [
                'label' => $previewLabel,
                'preview_url' => $previewUrl,
                'provider' => $previewProvider,
                'status' => 'url-recorded',
                'available' => true,
                'linked_project_detected' => $linkedProjectDetected,
                'linked_project_paths' => $linkedProjectPaths,
                'reason' => sprintf('%s URL recorded from explicit input; runtime logs and release tagging still depend on the target platform.', $previewLabel),
            ];
        }

        if ($previewCommand !== null) {
            return [
                'label' => $previewLabel,
                'preview_url' => $previewUrl,
                'provider' => $previewProvider,
                'status' => $linkedProjectDetected ? 'command-configured' : 'command-configured-unlinked',
                'available' => true,
                'linked_project_detected' => $linkedProjectDetected,
                'linked_project_paths' => $linkedProjectPaths,
                'reason' => $linkedProjectDetected
                    ? sprintf('linked preview project detected at %s; release-loop preview proof depends on the supplied preview command output.', implode(', ', $linkedProjectPaths))
                    : 'preview command supplied without linked project metadata; release-loop preview proof will rely on command output only',
            ];
        }

        if ($linkedProjectDetected) {
            return [
                'label' => $previewLabel,
                'preview_url' => $previewUrl,
                'provider' => $previewProvider,
                'status' => 'linked-project-detected',
                'available' => true,
                'linked_project_detected' => true,
                'linked_project_paths' => $linkedProjectPaths,
                'reason' => sprintf('linked preview project detected at %s, but no preview URL or preview command was supplied.', implode(', ', $linkedProjectPaths)),
            ];
        }

        return [
            'label' => $previewLabel,
            'preview_url' => $previewUrl,
            'provider' => $previewProvider,
            'status' => 'unconfigured',
            'available' => false,
            'linked_project_detected' => false,
            'linked_project_paths' => [],
            'reason' => 'no linked preview project detected and no preview URL/command was supplied; preview proof remains an external platform blocker',
        ];
    }

    private function resolvePreviewProvider(
        string $previewLabel,
        bool $linkedProjectDetected,
        ?string $previewCommand,
        ?string $previewUrl,
    ): string {
        $normalizedLabel = Str::lower(trim($previewLabel));

        if ($linkedProjectDetected || str_contains($normalizedLabel, 'vercel')) {
            return 'vercel';
        }

        if ($previewCommand !== null || $previewUrl !== null) {
            return 'custom';
        }

        return 'none';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveObservabilityContext(): array
    {
        $provider = trim((string) config('booking_release.release_loop.observability.provider', 'sentry'));
        $requiredEnv = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            (array) config('booking_release.release_loop.observability.required_env', []),
        ), static fn (string $value): bool => $value !== ''));
        $releaseEnv = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            (array) config('booking_release.release_loop.observability.release_env', []),
        ), static fn (string $value): bool => $value !== ''));
        $environmentEnv = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            (array) config('booking_release.release_loop.observability.environment_env', []),
        ), static fn (string $value): bool => $value !== ''));

        $presentEnv = [];
        $missingEnv = [];
        foreach ($requiredEnv as $name) {
            if ($this->environmentValue($name) !== null) {
                $presentEnv[] = $name;
            } else {
                $missingEnv[] = $name;
            }
        }

        $buildMetadata = $this->releaseBuildMetadataService->current();
        $releaseTag = $this->firstPresentEnvironmentValue($releaseEnv);
        $commitSha = trim((string) ($buildMetadata['commit_sha'] ?? ''));
        if ($releaseTag === null && $commitSha !== '') {
            $releaseTag = $commitSha;
        }

        $environment = $this->firstPresentEnvironmentValue($environmentEnv);
        $configured = $missingEnv === [];

        return [
            'provider' => $provider !== '' ? $provider : 'sentry',
            'configured' => $configured,
            'status' => $configured ? 'configured' : 'missing-configuration',
            'present_env' => $presentEnv,
            'missing_env' => $missingEnv,
            'release' => $releaseTag,
            'environment' => $environment,
            'reason' => $configured
                ? 'Sentry release/runtime evidence can be attached to this candidate build.'
                : sprintf(
                    'Sentry release/runtime evidence unavailable; missing env: %s',
                    $missingEnv !== [] ? implode(', ', $missingEnv) : 'configuration not detected'
                ),
        ];
    }

    private function resolveManifestPath(?string $manifestPath, bool $bootstrapUat): ?string
    {
        $resolved = $this->resolveOptionalPath($manifestPath);

        if ($resolved !== null) {
            return $resolved;
        }

        return $bootstrapUat ? base_path('storage/app/uat/scenario-pack.json') : null;
    }

    private function resolveOptionalPath(?string $path): ?string
    {
        $candidate = trim((string) ($path ?? ''));
        if ($candidate === '') {
            return null;
        }

        return $this->resolvePath($candidate);
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:\\\\|\\\\\\\\|\\/)/', $path) === 1) {
            return $path;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }

    private function relativePath(string $path): string
    {
        $normalizedBase = str_replace('\\', '/', base_path());
        $normalizedPath = str_replace('\\', '/', $path);

        if (str_starts_with($normalizedPath, $normalizedBase.'/')) {
            return substr($normalizedPath, strlen($normalizedBase) + 1);
        }

        if ($normalizedPath === $normalizedBase) {
            return '.';
        }

        return $normalizedPath;
    }

    private function pathForRepoCommand(string $path): string
    {
        $relative = $this->relativePath($path);

        return $relative !== '.' ? $relative : $path;
    }

    /**
     * @param  array<string, mixed>|null  $decodedJson
     */
    private function summarizeOutput(?array $decodedJson, string $stdout, string $stderr, ?int $exitCode): string
    {
        if (is_array($decodedJson)) {
            $decision = trim((string) ($decodedJson['decision'] ?? data_get($decodedJson, 'readiness.decision', '')));
            if ($decision !== '') {
                return 'decision='.$decision;
            }

            $warningSummary = $this->warningOnlySummary($decodedJson);
            if ($warningSummary !== null) {
                return $warningSummary;
            }

            if (array_key_exists('ok', $decodedJson)) {
                return 'ok='.(($decodedJson['ok'] ?? false) ? 'true' : 'false');
            }

            $summary = $decodedJson['summary'] ?? null;
            if (is_array($summary) && $summary !== []) {
                $key = (string) array_key_first($summary);
                $value = $summary[$key];

                return sprintf('%s=%s', $key, is_scalar($value) ? (string) $value : json_encode($value));
            }
        }

        $source = trim($stderr) !== '' ? $stderr : $stdout;
        $line = $this->firstMeaningfulLine($source);

        if ($line !== '') {
            return $line;
        }

        return sprintf('exit_code=%d', (int) $exitCode);
    }

    private function determineStepStatus(?array $decodedJson, Process $process, bool $timedOut): string
    {
        if ($timedOut) {
            return 'fail';
        }

        if ($process->isSuccessful()) {
            return 'pass';
        }

        if (is_array($decodedJson) && $this->isWarningOnlyResult($decodedJson)) {
            return 'warn';
        }

        return 'fail';
    }

    /**
     * @param  array<string, mixed>  $decodedJson
     */
    private function isWarningOnlyResult(array $decodedJson): bool
    {
        $decision = Str::lower(trim((string) ($decodedJson['decision'] ?? data_get($decodedJson, 'readiness.decision', ''))));
        if (in_array($decision, ['ready_with_warnings', 'warn', 'warning'], true)) {
            return true;
        }

        $exitCode = $decodedJson['exit_code'] ?? null;
        if (is_numeric($exitCode) && (int) $exitCode === 2) {
            return true;
        }

        $reportOk = data_get($decodedJson, 'report.ok');
        $reportErrors = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            (array) data_get($decodedJson, 'report.errors', []),
        ), static fn (string $value): bool => $value !== ''));
        $reportWarnings = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            (array) data_get($decodedJson, 'report.warnings', []),
        ), static fn (string $value): bool => $value !== ''));

        return ($decodedJson['ok'] ?? null) === false
            && $reportOk === true
            && $reportErrors === []
            && $reportWarnings !== [];
    }

    /**
     * @param  array<string, mixed>  $decodedJson
     */
    private function warningOnlySummary(array $decodedJson): ?string
    {
        if (! $this->isWarningOnlyResult($decodedJson)) {
            return null;
        }

        $reportWarnings = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            (array) data_get($decodedJson, 'report.warnings', []),
        ), static fn (string $value): bool => $value !== ''));

        if ($reportWarnings !== []) {
            return $reportWarnings[0];
        }

        $exitCode = $decodedJson['exit_code'] ?? null;
        if (is_numeric($exitCode)) {
            return 'exit_code='.(string) ((int) $exitCode);
        }

        return 'warning-only result';
    }

    private function environmentValue(string $key): ?string
    {
        $candidates = [
            getenv($key),
            $_ENV[$key] ?? null,
            $_SERVER[$key] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $trimmed = trim($candidate);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     */
    private function firstPresentEnvironmentValue(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->environmentValue($key);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function configuredTimeoutSeconds(string $key): float
    {
        $timeout = config('booking_release.release_loop.'.$key, 300);

        return is_numeric($timeout) ? (float) $timeout : 300.0;
    }

    private function timeoutSummary(ProcessTimedOutException $exception, string $label): string
    {
        return sprintf(
            '%s timed out after %d seconds',
            $label,
            (int) ceil($exception->getExceededTimeout())
        );
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, mixed>  $previewContext
     * @param  array<string, mixed>  $observabilityContext
     * @return list<array<string, mixed>>
     */
    private function buildFollowUpActions(
        string $target,
        array $steps,
        array $previewContext,
        array $observabilityContext,
    ): array {
        $actions = [];
        $launchReadiness = $this->launchReadinessPayload($steps);

        foreach ((array) ($launchReadiness['follow_up_actions'] ?? []) as $action) {
            if (is_array($action)) {
                $actions[] = $action;
            }
        }

        $previewStatus = (string) ($previewContext['status'] ?? '');
        if (! in_array($previewStatus, ['url-recorded', 'command-configured'], true)) {
            $actions[] = [
                'kind' => 'preview_proof',
                'label' => 'Record preview deployment proof',
                'reason' => (string) ($previewContext['reason'] ?? 'Preview deployment proof is still missing.'),
                'runbook_path' => 'docs/runbooks/booking-launch-readiness.md',
                'commands' => [
                    sprintf(
                        'php artisan booking:release-loop --target=%s --preview-url=<preview-url> --preview-label=vercel-preview --json',
                        $target
                    ),
                ],
                'notes' => [
                    'Provide the real preview URL or an explicit preview command so the release-loop artifact can archive preview proof together with the backend and split-web evidence.',
                ],
            ];
        }

        if (! (bool) ($observabilityContext['configured'] ?? false)) {
            $missingEnv = array_values(array_filter(array_map(
                static fn (mixed $value): string => is_string($value) ? trim($value) : '',
                (array) ($observabilityContext['missing_env'] ?? []),
            ), static fn (string $value): bool => $value !== ''));

            $actions[] = [
                'kind' => 'observability',
                'label' => 'Provide Sentry release/runtime evidence context',
                'reason' => (string) ($observabilityContext['reason'] ?? 'Observability configuration is incomplete.'),
                'runbook_path' => 'docs/runbooks/booking-ci-cd-runbook.md',
                'commands' => [
                    sprintf('php artisan booking:release-loop --target=%s --json', $target),
                ],
                'notes' => [
                    $missingEnv !== []
                        ? 'Set the missing env vars before rerunning: '.implode(', ', $missingEnv).'.'
                        : 'Populate the required Sentry environment before rerunning the release loop.',
                ],
            ];
        }

        return $actions;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, mixed>  $previewContext
     * @param  array<string, mixed>  $observabilityContext
     * @return array<string, mixed>
     */
    private function buildReleaseHandoff(
        array $steps,
        array $previewContext,
        array $observabilityContext,
    ): array {
        $launchReadiness = $this->launchReadinessPayload($steps);
        $handoff = is_array($launchReadiness)
            ? (array) ($launchReadiness['release_handoff'] ?? [])
            : [];
        $archivePaths = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            array_merge(
                (array) ($handoff['archive_paths'] ?? []),
                array_values(array_filter([
                    data_get($launchReadiness, 'artifacts.json_path'),
                    data_get($launchReadiness, 'artifacts.markdown_path'),
                ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''))
            )
        ), static fn (string $value): bool => $value !== '')));

        $promotionBlockers = [];
        if (! in_array((string) ($previewContext['status'] ?? ''), ['url-recorded', 'command-configured'], true)) {
            $promotionBlockers[] = (string) ($previewContext['reason'] ?? 'Preview deployment proof is still missing.');
        }
        if (! (bool) ($observabilityContext['configured'] ?? false)) {
            $promotionBlockers[] = (string) ($observabilityContext['reason'] ?? 'Observability configuration is incomplete.');
        }

        $promotionNotes = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            array_merge(
                (array) ($handoff['promotion_notes'] ?? []),
                ['Archive the release-loop JSON/Markdown artifacts from the current run together with the candidate package and launch-readiness report.']
            )
        ), static fn (string $value): bool => $value !== ''));

        return [
            'candidate' => (array) ($handoff['candidate'] ?? []),
            'manual_evidence' => (array) ($handoff['manual_evidence'] ?? []),
            'launch_readiness' => [
                'decision' => is_array($launchReadiness) ? (string) ($launchReadiness['decision'] ?? 'unknown') : 'unavailable',
                'json_path' => is_array($launchReadiness) ? trim((string) data_get($launchReadiness, 'artifacts.json_path', '')) : '',
                'markdown_path' => is_array($launchReadiness) ? trim((string) data_get($launchReadiness, 'artifacts.markdown_path', '')) : '',
            ],
            'preview' => [
                'status' => (string) ($previewContext['status'] ?? 'unknown'),
                'url' => trim((string) ($previewContext['preview_url'] ?? '')),
                'label' => trim((string) ($previewContext['label'] ?? 'preview')),
            ],
            'observability' => [
                'status' => (string) ($observabilityContext['status'] ?? 'unknown'),
                'release' => trim((string) ($observabilityContext['release'] ?? '')),
                'missing_env' => array_values((array) ($observabilityContext['missing_env'] ?? [])),
            ],
            'archive_paths' => $archivePaths,
            'promotion_blockers' => $promotionBlockers,
            'promotion_notes' => $promotionNotes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return array<string, mixed>|null
     */
    private function launchReadinessPayload(array $steps): ?array
    {
        foreach ($steps as $step) {
            if (($step['key'] ?? null) !== 'backend_launch_readiness') {
                continue;
            }

            $jsonPath = trim((string) data_get($step, 'artifacts.json_path', ''));
            if ($jsonPath === '') {
                return null;
            }

            $resolvedJsonPath = $this->resolvePath($jsonPath);
            if (! File::exists($resolvedJsonPath)) {
                return null;
            }

            $decoded = json_decode((string) File::get($resolvedJsonPath), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function previewDisplay(array $preview): string
    {
        $url = trim((string) ($preview['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        $label = trim((string) ($preview['label'] ?? ''));

        return $label !== '' ? $label : 'not-configured';
    }

    private function firstMeaningfulLine(string $output): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return '';
    }

    /**
     * @param  array<int, string>|string  $command
     */
    private function displayCommand(array|string $command): string
    {
        if (is_string($command)) {
            return $command;
        }

        return implode(' ', array_map(static function (string $segment): string {
            return str_contains($segment, ' ') ? '"'.$segment.'"' : $segment;
        }, $command));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderMarkdown(array $report): string
    {
        $lines = [
            '# Backend + Split-Web Release Loop',
            '',
            sprintf('- Decision: `%s`', (string) ($report['decision'] ?? 'unknown')),
            sprintf('- Target: `%s`', (string) data_get($report, 'target.key', 'staging')),
            sprintf('- Evaluated at: `%s`', (string) data_get($report, 'meta.evaluated_at_utc', '')),
            sprintf('- Preview: `%s`', $this->previewDisplay((array) data_get($report, 'preview', []))),
            sprintf('- Preview status: `%s`', (string) data_get($report, 'preview.status', 'unknown')),
            sprintf('- Observability: `%s`', (string) data_get($report, 'observability.status', 'unknown')),
            '',
            '## Steps',
            '',
            '| Step | Status | Summary |',
            '| --- | --- | --- |',
        ];

        foreach ((array) ($report['steps'] ?? []) as $step) {
            $lines[] = sprintf(
                '| %s | %s | %s |',
                (string) ($step['label'] ?? $step['key'] ?? ''),
                strtoupper((string) ($step['status'] ?? 'unknown')),
                str_replace('|', '\\|', (string) ($step['summary'] ?? ''))
            );
        }

        $blockingFailures = (array) ($report['blocking_failures'] ?? []);
        if ($blockingFailures !== []) {
            $lines[] = '';
            $lines[] = '## Blocking Failures';
            $lines[] = '';
            foreach ($blockingFailures as $failure) {
                $lines[] = sprintf('- `%s`: %s', (string) ($failure['step_key'] ?? 'step'), (string) ($failure['message'] ?? ''));
            }
        }

        $warnings = (array) ($report['warnings'] ?? []);
        if ($warnings !== []) {
            $lines[] = '';
            $lines[] = '## Warnings';
            $lines[] = '';
            foreach ($warnings as $warning) {
                $lines[] = '- '.$warning;
            }
        }

        $previewReason = trim((string) data_get($report, 'preview.reason', ''));
        $observabilityReason = trim((string) data_get($report, 'observability.reason', ''));
        $releaseTag = trim((string) data_get($report, 'observability.release', ''));
        if ($previewReason !== '' || $observabilityReason !== '' || $releaseTag !== '') {
            $lines[] = '';
            $lines[] = '## Evidence Context';
            $lines[] = '';
            if ($previewReason !== '') {
                $lines[] = '- Preview: '.$previewReason;
            }
            if ($observabilityReason !== '') {
                $lines[] = '- Observability: '.$observabilityReason;
            }
            if ($releaseTag !== '') {
                $lines[] = '- Release tag candidate: `'.$releaseTag.'`';
            }
        }

        $releaseHandoff = (array) ($report['release_handoff'] ?? []);
        $candidate = (array) ($releaseHandoff['candidate'] ?? []);
        $manualEvidence = (array) ($releaseHandoff['manual_evidence'] ?? []);
        $lines[] = '';
        $lines[] = '## Release Handoff';
        $lines[] = '';
        $lines[] = '| Field | Value |';
        $lines[] = '| --- | --- |';
        $lines[] = sprintf(
            '| package_basename | `%s` |',
            (string) (($candidate['package_basename'] ?? null) ?: 'not-available')
        );
        $lines[] = sprintf('| package_path | `%s` |', (string) ($candidate['package_path'] ?? ''));
        $lines[] = sprintf(
            '| manual_evidence | `%s` |',
            (string) (($manualEvidence['path'] ?? null) ?: 'not-supplied')
        );
        $lines[] = sprintf(
            '| launch_readiness | `%s` |',
            (string) data_get($releaseHandoff, 'launch_readiness.decision', 'unavailable')
        );
        $lines[] = sprintf('| preview_status | `%s` |', (string) data_get($releaseHandoff, 'preview.status', 'unknown'));
        $lines[] = sprintf('| observability_status | `%s` |', (string) data_get($releaseHandoff, 'observability.status', 'unknown'));

        $archivePaths = (array) ($releaseHandoff['archive_paths'] ?? []);
        $lines[] = '';
        $lines[] = 'Archive with this candidate:';
        if ($archivePaths === []) {
            $lines[] = '- None recorded yet.';
        } else {
            foreach ($archivePaths as $path) {
                $lines[] = sprintf('- `%s`', (string) $path);
            }
        }

        $promotionBlockers = (array) ($releaseHandoff['promotion_blockers'] ?? []);
        if ($promotionBlockers !== []) {
            $lines[] = '';
            $lines[] = 'Promotion blockers:';
            foreach ($promotionBlockers as $blocker) {
                $lines[] = '- '.(string) $blocker;
            }
        }
        foreach ((array) ($releaseHandoff['promotion_notes'] ?? []) as $note) {
            $lines[] = '- '.(string) $note;
        }

        $lines[] = '';
        $lines[] = '## Follow-up Actions';
        $lines[] = '';
        if ((array) ($report['follow_up_actions'] ?? []) === []) {
            $lines[] = '- None.';
        } else {
            $lines[] = '| Kind | Label | Runbook | Commands / Notes |';
            $lines[] = '| --- | --- | --- | --- |';
            foreach ((array) ($report['follow_up_actions'] ?? []) as $action) {
                $details = [];
                foreach ((array) ($action['commands'] ?? []) as $command) {
                    $details[] = '`'.str_replace('|', '\\|', (string) $command).'`';
                }
                foreach ((array) ($action['notes'] ?? []) as $note) {
                    $details[] = str_replace('|', '\\|', (string) $note);
                }

                $lines[] = sprintf(
                    '| %s | %s | `%s` | %s |',
                    strtoupper((string) ($action['kind'] ?? 'action')),
                    str_replace('|', '\\|', (string) ($action['label'] ?? '')),
                    str_replace('|', '\\|', (string) ($action['runbook_path'] ?? 'docs/runbooks/booking-launch-readiness.md')),
                    $details !== [] ? implode('<br>', $details) : '-'
                );
            }
        }

        $lines[] = '';
        $lines[] = '## Rollback';
        $lines[] = '';
        $lines[] = '- Redeploy the previously archived known-good immutable package together with its `.metadata.json`, `.inventory.json`, `.checksums.sha256`, and `.package.sha256` sidecars.';
        $lines[] = '- Do not rely on `build/booking-release/latest-package.json` alone for rollback selection.';
        foreach ((array) data_get($report, 'rollback.commands', []) as $command) {
            $lines[] = '- `'.$command.'`';
        }

        return implode(PHP_EOL, $lines);
    }
}
