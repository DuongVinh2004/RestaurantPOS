<?php

declare(strict_types=1);

namespace App\Platform\Release\Services;

use App\Enums\PaymentSessionScope;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\PaymentProviderRolloutConfig;
use App\Platform\ApiContract\Services\OpsGateArtifactService;
use App\Platform\ApiContract\Services\RouteInventoryGateService;
use App\Platform\Health\Services\BookingDoctorService;
use App\Platform\Metrics\Services\OperationalAlertService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class LaunchReadinessService
{
    public function __construct(
        private readonly BookingDoctorService $bookingDoctorService,
        private readonly BookingDeploySafetyService $bookingDeploySafetyService,
        private readonly RouteInventoryGateService $routeInventoryGateService,
        private readonly CoreOpsGateService $coreOpsGateService,
        private readonly RoundFiveGateService $roundFiveGateService,
        private readonly OperationalAlertService $operationalAlertService,
        private readonly ReleaseArtifactManifestService $releaseArtifactManifestService,
        private readonly ReleasePackageService $releasePackageService,
        private readonly OpsGateArtifactService $opsGateArtifactService,
        private readonly PaymentProviderRolloutConfig $paymentProviderRolloutConfig,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(
        string $target = 'staging',
        ?string $manualEvidencePath = null,
        ?string $packageId = null,
        bool $overwritePackage = false,
        int $paymentSampleLimit = 10,
    ): array {
        $evaluatedAt = now('UTC');
        $targetConfig = $this->resolveTarget($target);
        $manualEvidence = $this->loadManualEvidence($manualEvidencePath);

        $doctor = $this->bookingDoctorService->inspect(strict: true);
        $deploy = $this->bookingDeploySafetyService->inspect('preflight');
        $runtimeBaselineBlocked = $this->runtimeBaselineBlocked($doctor);
        $routeGate = $this->routeInventoryGateService->inspect();
        $releaseManifest = $this->releaseArtifactManifestService->snapshot();
        $frozenManifest = $this->releaseArtifactManifestService->inspectFrozenSnapshot($releaseManifest);
        $coreOps = $runtimeBaselineBlocked
            ? $this->skippedSource('Skipped because booking:doctor reported runtime dependency blockers.')
            : $this->coreOpsGateService->run(false);
        $roundFive = $runtimeBaselineBlocked
            ? $this->skippedSource('Skipped because booking:doctor reported runtime dependency blockers.')
            : $this->roundFiveGateService->run(false);
        $alertPayload = $runtimeBaselineBlocked
            ? $this->skippedSource('Skipped because booking:doctor reported runtime dependency blockers.')
            : [
                'snapshot' => $this->operationalAlertService->snapshot($evaluatedAt, max(1, $paymentSampleLimit)),
                'alerts' => [],
            ];
        if (! $runtimeBaselineBlocked) {
            $alertPayload['alerts'] = $this->operationalAlertService->buildAlerts((array) ($alertPayload['snapshot'] ?? []), $evaluatedAt);
        }
        $releasePackage = $runtimeBaselineBlocked
            ? $this->skippedSource('Skipped because booking:doctor reported runtime dependency blockers.')
            : $this->releasePackageService->package($packageId, verifyFrozen: true, overwrite: $overwritePackage);

        $sources = [
            'booking:doctor' => $doctor,
            'booking:deploy-check --mode=preflight' => $deploy,
            'booking:route-gate' => $routeGate,
            'booking:core-ops-gate' => $coreOps,
            'booking:round5-gate' => $roundFive,
            'booking:alert-check' => $alertPayload,
            'booking:release-manifest' => $releaseManifest,
            'booking:release-manifest --verify-frozen' => $frozenManifest,
            'booking:package-release --verify-frozen' => $releasePackage,
            'config/feature_flags.php' => $this->buildDayOneFeatureFlagSource($target),
        ];

        $checks = [];
        foreach ((array) config('booking_launch_readiness.matrix', []) as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $checks[] = $this->evaluateAutomatedCheck($definition, $sources);
        }

        $manualChecks = $this->evaluateManualChecks($target, $manualEvidence);
        $groups = $this->summarizeGroups(
            (array) config('booking_launch_readiness.groups', []),
            array_merge($checks, $manualChecks),
        );
        $blockingFailures = $this->flattenFindings(array_merge($checks, $manualChecks), 'blocking');
        $majorWarnings = $this->flattenFindings(array_merge($checks, $manualChecks), 'major');
        $baseline = $this->buildBaselineBuckets($checks, $manualChecks, $runtimeBaselineBlocked);
        $informationalFindings = $this->buildInformationalFindings($checks, $manualChecks, $manualEvidence);
        $followUpActions = $this->buildFollowUpActions($target, $manualChecks, $manualEvidence, $evaluatedAt);
        $releaseHandoff = $this->buildReleaseHandoff(
            $manualEvidence,
            $manualChecks,
            (array) ($sources['booking:package-release --verify-frozen'] ?? []),
            (array) ($sources['booking:release-manifest'] ?? []),
        );

        $decision = 'ready';
        $exitCode = 0;

        if ($blockingFailures !== []) {
            $decision = 'not_ready';
            $exitCode = 1;
        } elseif ($majorWarnings !== []) {
            $decision = 'ready_with_warnings';
            $exitCode = 2;
        }

        $report = [
            'ok' => ($exitCode === 0),
            'decision' => $decision,
            'exit_code' => $exitCode,
            'target' => [
                'key' => $target,
                'label' => (string) ($targetConfig['label'] ?? $target),
            ],
            'summary' => [
                'group_count' => count($groups),
                'automated_check_count' => count($checks),
                'manual_check_count' => count($manualChecks),
                'blocking_failure_count' => count($blockingFailures),
                'major_warning_count' => count($majorWarnings),
                'informational_count' => count($informationalFindings),
            ],
            'groups' => $groups,
            'checks' => $checks,
            'manual_checks' => $manualChecks,
            'baseline' => $baseline,
            'blocking_failures' => $blockingFailures,
            'major_warnings' => $majorWarnings,
            'informational_findings' => $informationalFindings,
            'follow_up_actions' => $followUpActions,
            'release_handoff' => $releaseHandoff,
            'manual_evidence' => $manualEvidence,
            'automation_gaps' => array_values((array) config('booking_launch_readiness.automation_gaps', [])),
            'integrated_sources' => $this->buildIntegratedSources($manualChecks),
            'sources' => $sources,
            'meta' => [
                'evaluated_at_utc' => $evaluatedAt->toIso8601String(),
                'payment_sample_limit' => max(1, $paymentSampleLimit),
                'runtime_baseline_blocked' => $runtimeBaselineBlocked,
            ],
        ];

        return $this->writeArtifacts($report, $target, $evaluatedAt);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function writeArtifacts(array $report, string $target, Carbon $evaluatedAt): array
    {
        return $this->opsGateArtifactService->writeReport(
            artifactRoot: trim((string) config('booking_launch_readiness.artifact_root', 'storage/app/booking_release/launch_readiness')),
            reportPrefix: 'launch-readiness',
            scopeKey: $target,
            payload: $report,
            markdown: $this->renderMarkdown($report),
            evaluatedAt: $evaluatedAt,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $manualChecks
     * @return array<int, array{source: string, role: string}>
     */
    private function buildIntegratedSources(array $manualChecks): array
    {
        $sources = [
            ['source' => 'booking:doctor', 'role' => 'Environment/runtime baseline'],
            ['source' => 'booking:deploy-check --mode=preflight', 'role' => 'Deploy preflight guardrail'],
            ['source' => 'config/feature_flags.php', 'role' => 'Day-1 feature flag posture'],
            ['source' => 'booking:route-gate', 'role' => 'Locked API surface check'],
            ['source' => 'booking:core-ops-gate', 'role' => 'Core booking flow verification'],
            ['source' => 'booking:round5-gate', 'role' => 'Financial flow verification'],
            ['source' => 'booking:alert-check', 'role' => 'Operational alert snapshot'],
            ['source' => 'booking:release-manifest', 'role' => 'Release artifact integrity'],
            ['source' => 'booking:package-release --verify-frozen', 'role' => 'Immutable package integrity'],
        ];

        $seen = [];

        foreach ($manualChecks as $check) {
            $source = trim((string) ($check['source'] ?? ''));
            if ($source === '') {
                continue;
            }

            $key = trim((string) ($check['key'] ?? ''));
            $identifier = $key !== '' ? $key : $source;
            if (isset($seen[$identifier])) {
                continue;
            }

            $sources[] = [
                'source' => $source,
                'role' => sprintf('Manual evidence: %s', (string) ($check['label'] ?? $check['key'] ?? 'manual check')),
            ];
            $seen[$identifier] = true;
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $sources
     * @return array<string, mixed>
     */
    private function evaluateAutomatedCheck(array $definition, array $sources): array
    {
        $key = (string) ($definition['key'] ?? '');
        $sourceKey = (string) ($definition['source'] ?? '');
        $source = $sourceKey !== '' && isset($sources[$sourceKey]) && is_array($sources[$sourceKey])
            ? (array) $sources[$sourceKey]
            : [];

        if (($source['skipped'] ?? false) === true) {
            return array_merge($definition, [
                'automation' => 'automated',
                'status' => 'skip',
                'summary' => (string) ($source['skip_reason'] ?? 'Skipped.'),
                'findings' => [],
                'evidence' => [
                    'skipped' => true,
                    'reason' => (string) ($source['skip_reason'] ?? 'Skipped.'),
                ],
            ]);
        }

        $result = match ($key) {
            'doctor_environment_runtime' => $this->evaluateDoctorCheck((array) ($sources['booking:doctor'] ?? [])),
            'deploy_preflight_guardrail' => $this->evaluateDeployCheck((array) ($sources['booking:deploy-check --mode=preflight'] ?? [])),
            'day1_feature_flag_posture' => $this->evaluateDayOneFeatureFlagPostureCheck((array) ($sources['config/feature_flags.php'] ?? [])),
            'route_inventory_contract' => $this->evaluateRouteGateCheck((array) ($sources['booking:route-gate'] ?? [])),
            'openapi_release_contract' => $this->evaluateOpenApiContractCheck((array) ($sources['booking:release-manifest'] ?? [])),
            'core_ops_flow_gate' => $this->evaluateGateSuiteCheck((array) ($sources['booking:core-ops-gate'] ?? []), 'Core ops gate'),
            'round5_financial_gate' => $this->evaluateGateSuiteCheck((array) ($sources['booking:round5-gate'] ?? []), 'Round 5 gate'),
            'operational_alert_snapshot' => $this->evaluateOperationalAlertsCheck((array) ($sources['booking:alert-check'] ?? [])),
            'release_manifest_snapshot' => $this->evaluateReleaseManifestSnapshotCheck((array) ($sources['booking:release-manifest'] ?? [])),
            'release_manifest_frozen' => $this->evaluateFrozenManifestCheck((array) ($sources['booking:release-manifest --verify-frozen'] ?? [])),
            'release_package_integrity' => $this->evaluateReleasePackageCheck((array) ($sources['booking:package-release --verify-frozen'] ?? [])),
            default => [
                'status' => 'warn',
                'summary' => sprintf('No evaluator is defined for matrix check [%s].', $key),
                'findings' => [
                    [
                        'severity' => 'major',
                        'message' => sprintf('Matrix check [%s] is configured but has no evaluator implementation.', $key),
                    ],
                ],
                'evidence' => [],
            ],
        };

        return array_merge($definition, [
            'automation' => 'automated',
            'status' => (string) ($result['status'] ?? 'warn'),
            'summary' => (string) ($result['summary'] ?? ''),
            'findings' => array_values((array) ($result['findings'] ?? [])),
            'evidence' => (array) ($result['evidence'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $doctor
     */
    private function runtimeBaselineBlocked(array $doctor): bool
    {
        foreach ((array) ($doctor['runtime'] ?? []) as $check) {
            if (is_array($check) && ! ($check['ok'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{skipped: bool, skip_reason: string}
     */
    private function skippedSource(string $reason): array
    {
        return [
            'skipped' => true,
            'skip_reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $doctor
     * @return array<string, mixed>
     */
    private function evaluateDoctorCheck(array $doctor): array
    {
        $findings = [];

        foreach ((array) (($doctor['validation'] ?? [])['errors'] ?? []) as $message) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $message,
            ];
        }

        foreach ((array) (($doctor['validation'] ?? [])['warnings'] ?? []) as $message) {
            $findings[] = [
                'severity' => 'major',
                'message' => (string) $message,
            ];
        }

        foreach ((array) ($doctor['runtime'] ?? []) as $name => $runtimeCheck) {
            if (! ($runtimeCheck['ok'] ?? false)) {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf('runtime.%s: %s', $name, (string) ($runtimeCheck['message'] ?? 'runtime probe failed.')),
                ];
            }
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'booking:doctor passed with no validation or runtime failures.',
            warnSummary: 'booking:doctor passed the blocking baseline but emitted warnings that should be reviewed.',
            failSummary: 'booking:doctor found blocking validation or runtime failures.',
            evidence: [
                'validation_ok' => (bool) (($doctor['validation'] ?? [])['ok'] ?? false),
                'runtime' => (array) ($doctor['runtime'] ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $deploy
     * @return array<string, mixed>
     */
    private function evaluateDeployCheck(array $deploy): array
    {
        $deployReport = $this->unwrapDeployReport($deploy);
        $findings = [];
        foreach ((array) ($deployReport['errors'] ?? []) as $message) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $message,
            ];
        }

        foreach ((array) ($deployReport['warnings'] ?? []) as $message) {
            $findings[] = [
                'severity' => 'major',
                'message' => (string) $message,
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'booking:deploy-check preflight reported no blocking issues.',
            warnSummary: 'booking:deploy-check preflight is green on blockers but emitted warnings.',
            failSummary: 'booking:deploy-check preflight reported blocking rollout issues.',
            evidence: [
                'mode' => (string) ($deployReport['mode'] ?? $deploy['mode'] ?? 'preflight'),
                'summary' => (array) ($deployReport['summary'] ?? []),
            ],
        );
    }

    /**
     * @return array{target: string, expectations: list<array<string, mixed>>, wildcard_environment: string}
     */
    private function buildDayOneFeatureFlagSource(string $target): array
    {
        $expectations = [];
        foreach ((array) config('booking_launch_readiness.day1_feature_flags', []) as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $requiredFor = array_values(array_map('strval', (array) ($definition['required_for'] ?? [])));
            if (! in_array($target, $requiredFor, true)) {
                continue;
            }

            $expectations[] = $definition;
        }

        return [
            'target' => $target,
            'expectations' => $expectations,
            'wildcard_environment' => trim((string) config('feature_flags.wildcard_environment', '*')) ?: '*',
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function evaluateDayOneFeatureFlagPostureCheck(array $source): array
    {
        $target = (string) ($source['target'] ?? 'unknown');
        $wildcardEnvironment = trim((string) ($source['wildcard_environment'] ?? '*')) ?: '*';
        $expectations = array_values(array_filter((array) ($source['expectations'] ?? []), 'is_array'));
        $registry = (array) config('feature_flags.features', []);
        $findings = [];
        $flagEvidence = [];

        if ($expectations === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => sprintf('No day-1 feature flag posture expectations are configured for target [%s].', $target),
            ];
        }

        foreach ($expectations as $definition) {
            $featureKey = strtolower(trim((string) ($definition['feature_key'] ?? '')));
            if ($featureKey === '') {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => 'A day-1 feature flag expectation is missing feature_key.',
                ];

                continue;
            }

            $expectedDefault = (bool) ($definition['expected_default_enabled'] ?? false);
            $feature = $registry[$featureKey] ?? null;
            $defaults = is_array($feature['defaults'] ?? null) ? (array) $feature['defaults'] : [];
            $hasWildcardDefault = array_key_exists($wildcardEnvironment, $defaults);
            $actualWildcardDefault = $hasWildcardDefault ? (bool) $defaults[$wildcardEnvironment] : null;
            $killSwitch = is_array($feature) ? (bool) ($feature['kill_switch'] ?? false) : false;
            $safeDefault = is_array($feature) ? (bool) ($feature['safe_default'] ?? false) : false;

            $flagEvidence[] = [
                'feature_key' => $featureKey,
                'expected_default_enabled' => $expectedDefault,
                'actual_wildcard_default_enabled' => $actualWildcardDefault,
                'wildcard_environment' => $wildcardEnvironment,
                'kill_switch' => $killSwitch,
                'safe_default' => $safeDefault,
                'launch_scope' => (string) ($definition['launch_scope'] ?? ''),
                'reason' => (string) ($definition['reason'] ?? ''),
            ];

            if (! is_array($feature)) {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf('Day-1 feature flag [%s] is not registered in config/feature_flags.php.', $featureKey),
                ];

                continue;
            }

            if (! $killSwitch) {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf('Day-1 feature flag [%s] must remain kill-switchable.', $featureKey),
                ];
            }

            if (! $hasWildcardDefault) {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf('Day-1 feature flag [%s] is missing an explicit [%s] default.', $featureKey, $wildcardEnvironment),
                ];
            } elseif ($actualWildcardDefault !== $expectedDefault) {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf(
                        'Day-1 feature flag [%s] has wildcard default [%s], expected [%s].',
                        $featureKey,
                        $actualWildcardDefault ? 'enabled' : 'disabled',
                        $expectedDefault ? 'enabled' : 'disabled'
                    ),
                ];
            }

            if (! $expectedDefault && $safeDefault !== false) {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf('Day-1 feature flag [%s] must keep safe_default=false while it is outside launch scope.', $featureKey),
                ];
            }
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: sprintf('Day-1 feature flag posture matches %d target expectation(s).', count($flagEvidence)),
            warnSummary: 'Day-1 feature flag posture emitted warnings that should be reviewed.',
            failSummary: 'Day-1 feature flag posture is unsafe for the target.',
            evidence: [
                'target' => $target,
                'wildcard_environment' => $wildcardEnvironment,
                'flags' => $flagEvidence,
            ],
        );
    }

    /**
     * Launch readiness integrates the deploy service directly, but keep support for the
     * wrapped console-command payload shape to avoid future contract drift.
     *
     * @param  array<string, mixed>  $deploy
     * @return array<string, mixed>
     */
    private function unwrapDeployReport(array $deploy): array
    {
        $nestedReport = $deploy['report'] ?? null;
        if (is_array($nestedReport)) {
            return $nestedReport;
        }

        return $deploy;
    }

    /**
     * @param  array<string, mixed>  $routeGate
     * @return array<string, mixed>
     */
    private function evaluateRouteGateCheck(array $routeGate): array
    {
        $findings = [];
        foreach ((array) ($routeGate['checks'] ?? []) as $name => $check) {
            if ($check['ok'] ?? false) {
                continue;
            }

            $findings[] = [
                'severity' => (($check['severity'] ?? 'error') === 'warning') ? 'major' : 'blocking',
                'message' => sprintf('%s: %s', $name, (string) ($check['message'] ?? 'route inventory drift detected.')),
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'booking:route-gate confirms the locked route inventory.',
            warnSummary: 'booking:route-gate emitted warnings that should be reviewed.',
            failSummary: 'booking:route-gate detected runtime API surface drift.',
            evidence: [
                'summary' => (array) ($routeGate['summary'] ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function evaluateOpenApiContractCheck(array $manifest): array
    {
        $artifact = (array) (($manifest['artifacts'] ?? [])['openapi_v1_spec'] ?? []);
        $findings = [];

        if (! ($artifact['exists'] ?? false)) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'openapi_v1_spec: required OpenAPI release artifact is missing.',
            ];
        }

        foreach ((array) ($artifact['missing_fragments'] ?? []) as $fragment) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => sprintf('openapi_v1_spec: missing required fragment [%s].', (string) $fragment),
            ];
        }

        foreach ((array) ($artifact['semantic_issues'] ?? []) as $issue) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $issue,
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'The frozen OpenAPI release artifact is present and satisfies the contract fragments.',
            warnSummary: 'The OpenAPI release artifact emitted warnings that should be reviewed.',
            failSummary: 'The frozen OpenAPI release artifact is missing or inconsistent.',
            evidence: [
                'artifact' => $artifact,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $gate
     * @return array<string, mixed>
     */
    private function evaluateGateSuiteCheck(array $gate, string $label): array
    {
        $findings = [];
        foreach ((array) ($gate['tests'] ?? []) as $test) {
            if ($test['ok'] ?? false) {
                continue;
            }

            $findings[] = [
                'severity' => 'blocking',
                'message' => sprintf(
                    '%s: %s failed (%s).',
                    $label,
                    (string) ($test['key'] ?? $test['path'] ?? 'unknown_test'),
                    trim((string) ($test['output_tail'] ?? 'no output'))
                ),
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: sprintf('%s passed all canonical tests.', $label),
            warnSummary: sprintf('%s emitted warnings that should be reviewed.', $label),
            failSummary: sprintf('%s failed one or more canonical tests.', $label),
            evidence: [
                'suite' => (string) ($gate['suite'] ?? ''),
                'summary' => (array) ($gate['summary'] ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $alertPayload
     * @return array<string, mixed>
     */
    private function evaluateOperationalAlertsCheck(array $alertPayload): array
    {
        $alerts = array_values((array) ($alertPayload['alerts'] ?? []));
        $findings = [];

        foreach ($alerts as $alert) {
            $severity = strtolower((string) ($alert['severity'] ?? 'warning'));
            $findings[] = [
                'severity' => $severity === 'critical' ? 'blocking' : 'major',
                'message' => sprintf(
                    '%s [%s]: %s',
                    (string) ($alert['section'] ?? 'unknown'),
                    $severity,
                    (string) ($alert['message'] ?? 'operational alert generated')
                ),
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'booking:alert-check produced no actionable alerts.',
            warnSummary: 'booking:alert-check produced warning-level alerts that need review.',
            failSummary: 'booking:alert-check produced critical alerts that block rollout.',
            evidence: [
                'alert_count' => count($alerts),
                'snapshot_sections' => array_keys((array) ($alertPayload['snapshot'] ?? [])),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function evaluateReleaseManifestSnapshotCheck(array $manifest): array
    {
        $findings = [];
        foreach ((array) ($manifest['issues'] ?? []) as $issue) {
            $findings[] = [
                'severity' => (($manifest['status'] ?? 'fail') === 'warning') ? 'major' : 'blocking',
                'message' => (string) $issue,
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'booking:release-manifest confirms the release artifact set is internally consistent.',
            warnSummary: 'booking:release-manifest completed with warnings that should be reviewed.',
            failSummary: 'booking:release-manifest detected broken or missing release artifacts.',
            evidence: [
                'status' => (string) ($manifest['status'] ?? 'unknown'),
                'patches' => (array) ($manifest['patches'] ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $frozenManifest
     * @return array<string, mixed>
     */
    private function evaluateFrozenManifestCheck(array $frozenManifest): array
    {
        $findings = [];
        if (! ($frozenManifest['ok'] ?? false)) {
            foreach ((array) ($frozenManifest['issues'] ?? []) as $issue) {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => (string) $issue,
                ];
            }

            if ($findings === []) {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf(
                        'Frozen manifest verification failed with status [%s].',
                        (string) ($frozenManifest['status'] ?? 'unknown')
                    ),
                ];
            }
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'The live release manifest matches the frozen snapshot.',
            warnSummary: 'The frozen release manifest check emitted warnings that should be reviewed.',
            failSummary: 'The frozen release manifest snapshot is stale or missing.',
            evidence: [
                'status' => (string) ($frozenManifest['status'] ?? 'unknown'),
                'path' => (string) ($frozenManifest['path'] ?? ''),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $releasePackage
     * @return array<string, mixed>
     */
    private function evaluateReleasePackageCheck(array $releasePackage): array
    {
        $findings = [];
        foreach ((array) ($releasePackage['issues'] ?? []) as $issue) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $issue,
            ];
        }

        foreach ((array) ($releasePackage['warnings'] ?? []) as $warning) {
            $findings[] = [
                'severity' => 'major',
                'message' => (string) $warning,
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'booking:package-release built the immutable release package and sidecars successfully.',
            warnSummary: 'booking:package-release built the package but emitted warnings that should be reviewed.',
            failSummary: 'booking:package-release failed to build the immutable release artifact.',
            evidence: [
                'package_id' => (string) ($releasePackage['package_id'] ?? ''),
                'package_path' => (string) ($releasePackage['package_path'] ?? ''),
                'package_exists' => (bool) ($releasePackage['package_exists'] ?? false),
            ],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @return array<int, array<string, mixed>>
     */
    private function summarizeGroups(array $groupLabels, array $checks): array
    {
        $groups = [];

        foreach ($groupLabels as $groupKey => $label) {
            $groupChecks = array_values(array_filter($checks, static fn (array $check): bool => (string) ($check['group'] ?? '') === (string) $groupKey));
            $blockingFailureCount = count($this->flattenFindings($groupChecks, 'blocking'));
            $majorWarningCount = count($this->flattenFindings($groupChecks, 'major'));

            $status = 'pass';
            if ($blockingFailureCount > 0) {
                $status = 'fail';
            } elseif ($majorWarningCount > 0) {
                $status = 'warn';
            }

            $groups[] = [
                'key' => (string) $groupKey,
                'label' => (string) $label,
                'status' => $status,
                'check_count' => count($groupChecks),
                'blocking_failure_count' => $blockingFailureCount,
                'major_warning_count' => $majorWarningCount,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @return array<int, array<string, mixed>>
     */
    private function flattenFindings(array $checks, string $severity): array
    {
        $findings = [];

        foreach ($checks as $check) {
            foreach ((array) ($check['findings'] ?? []) as $finding) {
                if ((string) ($finding['severity'] ?? '') !== $severity) {
                    continue;
                }

                $findings[] = [
                    'group' => (string) ($check['group'] ?? ''),
                    'check_key' => (string) ($check['key'] ?? ''),
                    'check_label' => (string) ($check['label'] ?? ''),
                    'source' => (string) ($check['source'] ?? ''),
                    'message' => (string) ($finding['message'] ?? ''),
                    'severity' => $severity,
                ];
            }
        }

        return $findings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @param  array<int, array<string, mixed>>  $manualChecks
     * @return array<string, array<string, mixed>>
     */
    private function buildBaselineBuckets(array $checks, array $manualChecks, bool $runtimeBaselineBlocked): array
    {
        $launchPathChecks = array_values(array_filter(
            $checks,
            static fn (array $check): bool => in_array((string) ($check['group'] ?? ''), [
                'booking_core_flows',
                'feature_flag_posture',
                'payment_checkout_financial_flows',
                'notifications_alerts',
            ], true)
        ));
        $nonLaunchChecks = array_values(array_filter(
            $checks,
            static fn (array $check): bool => (string) ($check['group'] ?? '') === 'api_surface_contract'
        ));
        $artifactChecks = array_values(array_filter(
            $checks,
            static fn (array $check): bool => in_array((string) ($check['key'] ?? ''), [
                'openapi_release_contract',
                'release_manifest_snapshot',
                'release_manifest_frozen',
                'release_package_integrity',
            ], true)
        ));
        $externalChecks = array_values(array_filter(
            $checks,
            static fn (array $check): bool => (string) ($check['group'] ?? '') === 'environment_runtime'
        ));

        return [
            'launch_path_diff' => $this->summarizeCheckBucket(
                label: 'Launch-path checks',
                checks: $launchPathChecks,
                clearSummary: 'Launch-path automated checks did not report drift.',
                warningSummary: 'Launch-path automated checks reported warnings that should be reviewed before promotion.',
                failSummary: 'Launch-path automated checks reported drift or hard failures.',
                blockedSummary: 'Launch-path automated checks are blocked by external runtime prerequisites; no launch-path drift is proven yet.',
                treatSkippedChecksAsExternal: $runtimeBaselineBlocked,
            ),
            'non_launch_diff' => $this->summarizeCheckBucket(
                label: 'Non-launch contract checks',
                checks: $nonLaunchChecks,
                clearSummary: 'Non-launch contract checks did not report drift.',
                warningSummary: 'Non-launch contract checks reported warnings that should be reviewed.',
                failSummary: 'Non-launch contract checks reported drift.',
            ),
            'artifact_drift' => $this->summarizeArtifactDriftBucket($artifactChecks),
            'external_blockers' => $this->summarizeExternalBlockersBucket($externalChecks, $checks, $runtimeBaselineBlocked),
            'manual_blockers' => $this->summarizeManualBlockersBucket($manualChecks),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @return array<string, mixed>
     */
    private function summarizeCheckBucket(
        string $label,
        array $checks,
        string $clearSummary,
        string $warningSummary,
        string $failSummary,
        ?string $blockedSummary = null,
        bool $treatSkippedChecksAsExternal = false,
    ): array {
        $passCheckKeys = [];
        $warnCheckKeys = [];
        $failCheckKeys = [];
        $skipCheckKeys = [];
        $skippedReasons = [];
        $blockingFindings = [];
        $majorWarnings = [];

        foreach ($checks as $check) {
            $key = (string) ($check['key'] ?? '');
            $status = (string) ($check['status'] ?? 'unknown');

            match ($status) {
                'pass' => $passCheckKeys[] = $key,
                'warn' => $warnCheckKeys[] = $key,
                'fail' => $failCheckKeys[] = $key,
                'skip' => $skipCheckKeys[] = $key,
                default => null,
            };

            if ($status === 'skip') {
                $skippedReasons[$key] = trim((string) data_get($check, 'evidence.reason', $check['summary'] ?? 'Skipped.'));
            }

            foreach ((array) ($check['findings'] ?? []) as $finding) {
                if (! is_array($finding)) {
                    continue;
                }

                $row = [
                    'check_key' => $key,
                    'check_label' => (string) ($check['label'] ?? $key),
                    'message' => (string) ($finding['message'] ?? ''),
                ];

                if ((string) ($finding['severity'] ?? '') === 'blocking') {
                    $blockingFindings[] = $row;
                }

                if ((string) ($finding['severity'] ?? '') === 'major') {
                    $majorWarnings[] = $row;
                }
            }
        }

        if ($checks === []) {
            return [
                'label' => $label,
                'status' => 'not_applicable',
                'summary' => 'No checks were mapped into this baseline bucket.',
                'pass_check_keys' => [],
                'warn_check_keys' => [],
                'fail_check_keys' => [],
                'skip_check_keys' => [],
                'skipped_reasons' => [],
                'blocking_findings' => [],
                'major_warnings' => [],
            ];
        }

        $status = 'clear';
        $summary = $clearSummary;

        if ($blockingFindings !== [] || $failCheckKeys !== []) {
            $status = 'drift_detected';
            $summary = $failSummary;
        } elseif ($majorWarnings !== [] || $warnCheckKeys !== []) {
            $status = 'warnings';
            $summary = $warningSummary;
        } elseif ($treatSkippedChecksAsExternal && $skipCheckKeys !== []) {
            $status = 'blocked_external';
            $summary = $blockedSummary ?? 'Checks are blocked by external prerequisites.';
        }

        return [
            'label' => $label,
            'status' => $status,
            'summary' => $summary,
            'pass_check_keys' => $passCheckKeys,
            'warn_check_keys' => $warnCheckKeys,
            'fail_check_keys' => $failCheckKeys,
            'skip_check_keys' => $skipCheckKeys,
            'skipped_reasons' => $skippedReasons,
            'blocking_findings' => $blockingFindings,
            'major_warnings' => $majorWarnings,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @return array<string, mixed>
     */
    private function summarizeArtifactDriftBucket(array $checks): array
    {
        $bucket = $this->summarizeCheckBucket(
            label: 'Artifact drift',
            checks: $checks,
            clearSummary: 'Frozen release artifacts are aligned; no artifact refresh is required for this baseline.',
            warningSummary: 'Artifact checks emitted warnings that should be reviewed before release truth is reused.',
            failSummary: 'Artifact drift was detected in the frozen release chain.',
        );

        $touchRequired = in_array((string) ($bucket['status'] ?? 'clear'), ['warnings', 'drift_detected'], true);
        $skipCheckKeys = array_values((array) ($bucket['skip_check_keys'] ?? []));

        $bucket['touch_required'] = $touchRequired;
        $bucket['touch_paths'] = [
            'build/api-consumer/**',
            'storage/app/booking_release/**',
        ];
        $bucket['pending_external_check_keys'] = $skipCheckKeys;
        $bucket['recommended_owner_hint'] = $touchRequired
            ? 'Keep any artifact refresh isolated to the dedicated freeze/release-truth lane before opening parallel work.'
            : 'Do not touch the frozen artifact chain in downstream lanes unless a new contract or release-truth change reopens it.';

        if (! $touchRequired && $skipCheckKeys !== []) {
            $bucket['summary'] = 'Frozen release artifacts are aligned. Package-level verification is still blocked by external runtime prerequisites, so no artifact refresh is justified from this result alone.';
        }

        return $bucket;
    }

    /**
     * @param  array<int, array<string, mixed>>  $externalChecks
     * @param  array<int, array<string, mixed>>  $allChecks
     * @return array<string, mixed>
     */
    private function summarizeExternalBlockersBucket(array $externalChecks, array $allChecks, bool $runtimeBaselineBlocked): array
    {
        $bucket = $this->summarizeCheckBucket(
            label: 'External blockers',
            checks: $externalChecks,
            clearSummary: 'No external runtime blockers were detected.',
            warningSummary: 'External runtime checks emitted warnings that should be reviewed.',
            failSummary: 'External runtime prerequisites are blocking this readiness result.',
        );
        $doctorRuntimeBreakdown = $this->extractDoctorRuntimeBreakdown($externalChecks);
        if ($doctorRuntimeBreakdown !== []) {
            $bucket = array_merge($bucket, $doctorRuntimeBreakdown);
        }

        $downstreamBlockedChecks = [];
        foreach ($allChecks as $check) {
            if ((string) ($check['status'] ?? '') !== 'skip') {
                continue;
            }

            $reason = trim((string) data_get($check, 'evidence.reason', $check['summary'] ?? ''));
            if ($reason === '' || ! str_contains(strtolower($reason), 'runtime dependency blockers')) {
                continue;
            }

            $downstreamBlockedChecks[] = [
                'check_key' => (string) ($check['key'] ?? ''),
                'check_label' => (string) ($check['label'] ?? $check['key'] ?? ''),
                'reason' => $reason,
            ];
        }

        $bucket['runtime_baseline_blocked'] = $runtimeBaselineBlocked;
        $bucket['blocked_dependent_checks'] = $downstreamBlockedChecks;

        if ($runtimeBaselineBlocked) {
            $bucket['status'] = 'blocking';
            $summaryParts = [
                'External runtime prerequisites are blocking launch-readiness and preventing downstream launch-path/package checks from running.',
            ];

            $rootRuntimeKeys = array_values((array) ($bucket['root_runtime_check_keys'] ?? []));
            if ($rootRuntimeKeys !== []) {
                $summaryParts[] = 'Root blockers: '.implode(', ', array_map(
                    static fn (string $key): string => 'runtime.'.$key,
                    $rootRuntimeKeys,
                )).'.';
            }

            $dependencyBlockedRuntimeKeys = array_values((array) ($bucket['dependency_blocked_runtime_check_keys'] ?? []));
            if ($dependencyBlockedRuntimeKeys !== []) {
                $summaryParts[] = 'Dependency-blocked checks: '.implode(', ', array_map(
                    static fn (string $key): string => 'runtime.'.$key,
                    $dependencyBlockedRuntimeKeys,
                )).'.';
            }

            $bucket['summary'] = implode(' ', $summaryParts);
        }

        return $bucket;
    }

    /**
     * @param  array<int, array<string, mixed>>  $externalChecks
     * @return array<string, mixed>
     */
    private function extractDoctorRuntimeBreakdown(array $externalChecks): array
    {
        foreach ($externalChecks as $check) {
            if ((string) ($check['key'] ?? '') !== 'doctor_environment_runtime') {
                continue;
            }

            $runtime = (array) data_get($check, 'evidence.runtime', []);
            if ($runtime === []) {
                return [];
            }

            $rootRuntimeChecks = [];
            $dependencyBlockedRuntimeChecks = [];

            foreach ($runtime as $runtimeKey => $runtimeCheck) {
                if (! is_array($runtimeCheck) || ($runtimeCheck['ok'] ?? false)) {
                    continue;
                }

                $row = [
                    'runtime_key' => (string) $runtimeKey,
                    'message' => (string) ($runtimeCheck['message'] ?? ''),
                ];

                if ((string) ($runtimeCheck['status'] ?? 'fail') === 'blocked_dependency') {
                    $row['dependency'] = (string) ($runtimeCheck['dependency'] ?? '');
                    $dependencyBlockedRuntimeChecks[] = $row;

                    continue;
                }

                $rootRuntimeChecks[] = $row;
            }

            return [
                'root_runtime_checks' => $rootRuntimeChecks,
                'root_runtime_check_keys' => array_values(array_map(
                    static fn (array $row): string => (string) ($row['runtime_key'] ?? ''),
                    $rootRuntimeChecks,
                )),
                'dependency_blocked_runtime_checks' => $dependencyBlockedRuntimeChecks,
                'dependency_blocked_runtime_check_keys' => array_values(array_map(
                    static fn (array $row): string => (string) ($row['runtime_key'] ?? ''),
                    $dependencyBlockedRuntimeChecks,
                )),
            ];
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $manualChecks
     * @return array<string, mixed>
     */
    private function summarizeManualBlockersBucket(array $manualChecks): array
    {
        $requiredMissing = [];
        $recommendedMissing = [];

        foreach ($manualChecks as $check) {
            $row = [
                'check_key' => (string) ($check['key'] ?? ''),
                'check_label' => (string) ($check['label'] ?? $check['key'] ?? ''),
                'status' => (string) ($check['status'] ?? 'unknown'),
                'summary' => (string) ($check['summary'] ?? ''),
            ];

            if ((string) ($check['severity'] ?? '') === 'blocking' && (string) ($check['status'] ?? '') !== 'pass') {
                $requiredMissing[] = $row;
            }

            if ((string) ($check['severity'] ?? '') !== 'blocking' && (string) ($check['status'] ?? '') !== 'pass') {
                $recommendedMissing[] = $row;
            }
        }

        $status = 'clear';
        $summary = 'No manual evidence blockers are currently open.';

        if ($requiredMissing !== []) {
            $status = 'blocking';
            $summary = 'Required manual evidence is still blocking promotion.';
        } elseif ($recommendedMissing !== []) {
            $status = 'warnings';
            $summary = 'Recommended manual evidence is still outstanding.';
        }

        return [
            'label' => 'Manual blockers',
            'status' => $status,
            'summary' => $summary,
            'required_missing' => $requiredMissing,
            'recommended_missing' => $recommendedMissing,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @param  array<int, array<string, mixed>>  $manualChecks
     * @param  array<string, mixed>  $manualEvidence
     * @return array<int, array<string, mixed>>
     */
    private function buildInformationalFindings(array $checks, array $manualChecks, array $manualEvidence): array
    {
        $findings = [];
        foreach (array_merge($checks, $manualChecks) as $check) {
            if ((string) ($check['status'] ?? 'pass') !== 'pass') {
                continue;
            }

            $findings[] = [
                'group' => (string) ($check['group'] ?? ''),
                'check_key' => (string) ($check['key'] ?? ''),
                'check_label' => (string) ($check['label'] ?? ''),
                'message' => (string) ($check['summary'] ?? ''),
            ];
        }

        foreach ((array) config('booking_launch_readiness.automation_gaps', []) as $gap) {
            if (! is_array($gap)) {
                continue;
            }

            $findings[] = [
                'group' => (string) ($gap['group'] ?? ''),
                'check_key' => (string) ($gap['key'] ?? ''),
                'check_label' => (string) ($gap['key'] ?? ''),
                'message' => (string) ($gap['description'] ?? ''),
            ];
        }

        if (($manualEvidence['provided'] ?? false) && ((array) ($manualEvidence['issues'] ?? [])) === []) {
            $findings[] = [
                'group' => 'release_artifact_integrity',
                'check_key' => 'manual_evidence_file',
                'check_label' => 'Manual evidence file',
                'message' => sprintf('Manual evidence file loaded from [%s].', (string) ($manualEvidence['resolved_path'] ?? '')),
            ];
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function evaluateManualChecks(string $target, array $manualEvidence): array
    {
        $checks = [];

        foreach ((array) config('booking_launch_readiness.manual_checks', []) as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $requiredFor = array_values(array_map('strval', (array) ($definition['required_for'] ?? [])));
            $recommendedFor = array_values(array_map('strval', (array) ($definition['recommended_for'] ?? [])));

            if (! in_array($target, array_merge($requiredFor, $recommendedFor), true)) {
                continue;
            }

            $checkKey = (string) ($definition['key'] ?? '');
            $evidence = (array) (($manualEvidence['checks'] ?? [])[$checkKey] ?? []);
            $evidenceStatus = strtolower(trim((string) ($evidence['status'] ?? 'missing')));
            $required = in_array($target, $requiredFor, true);

            $status = 'pass';
            $findings = [];
            $summary = 'Manual evidence recorded and schema-valid.';

            if ($evidenceStatus !== 'pass') {
                $status = $required ? 'fail' : 'warn';
                $severity = $required ? 'blocking' : 'major';
                $summary = $required
                    ? 'Required manual evidence is missing or failed.'
                    : 'Recommended manual evidence has not been captured yet.';

                $findings[] = [
                    'severity' => $severity,
                    'message' => $evidenceStatus === 'fail'
                        ? sprintf('Manual check [%s] was recorded as failed.', (string) ($definition['label'] ?? $definition['key'] ?? 'manual_check'))
                        : sprintf('Manual check [%s] has no passing evidence yet.', (string) ($definition['label'] ?? $definition['key'] ?? 'manual_check')),
                ];
            } else {
                $schemaFindings = $this->validateManualEvidenceSchema($checkKey, $definition, $evidence);

                if ($schemaFindings !== []) {
                    $status = $required ? 'fail' : 'warn';
                    $summary = 'Manual evidence failed schema validation.';
                    $findings = array_map(
                        static fn (string $message): array => [
                            'severity' => $required ? 'blocking' : 'major',
                            'message' => $message,
                        ],
                        $schemaFindings,
                    );
                } else {
                    $summary = $this->manualEvidencePassSummary($checkKey, $evidence);
                }
            }

            $checks[] = array_merge($definition, [
                'automation' => 'manual',
                'severity' => $required ? 'blocking' : 'major',
                'status' => $status,
                'summary' => $summary,
                'findings' => $findings,
                'evidence' => $evidence,
            ]);
        }

        return $checks;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    private function validateManualEvidenceSchema(string $checkKey, array $definition, array $evidence): array
    {
        $findings = [];

        foreach ((array) ($evidence['_validation_issues'] ?? []) as $issue) {
            $message = trim((string) $issue);
            if ($message !== '') {
                $findings[] = $message;
            }
        }

        $status = strtolower(trim((string) ($evidence['status'] ?? '')));
        if (! in_array($status, ['pass', 'fail', 'missing'], true)) {
            $findings[] = sprintf('Manual check [%s] has unsupported status [%s].', $checkKey, $status);
        }

        if ($status === 'pass') {
            $this->requireNonEmptyEvidenceString($evidence, 'performed_by', $findings, $checkKey);
            $this->requireValidEvidenceTimestamp($evidence, 'performed_at_utc', $findings, $checkKey);
        }

        return array_values(array_unique(array_merge(
            $findings,
            match ($checkKey) {
                'payment_provider_external_e2e' => $this->validatePaymentProviderReadinessEvidence($evidence),
                'disaster_recovery_restore_evidence' => $this->validateDisasterRecoveryRestoreEvidence($evidence),
                'performance_verification_report' => $this->validatePerformanceVerificationEvidence($evidence),
                'uat_scenario_pack_replay' => $this->validateUatScenarioReplayEvidence($evidence),
                default => $this->validateConfiguredManualEvidenceSchema($checkKey, (array) ($definition['evidence_schema'] ?? []), $evidence),
            },
        )));
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    private function validatePaymentProviderReadinessEvidence(array $evidence): array
    {
        $findings = [];
        $configuredSelfPayEnabled = (bool) config('booking.payment_providers.customer_self_pay.enabled', false);
        $evidenceSelfPayEnabled = $this->evidenceBoolean($evidence, 'customer_self_pay_enabled');

        if ($evidenceSelfPayEnabled === null) {
            $findings[] = 'Payment evidence must declare customer_self_pay_enabled as a boolean.';
        } elseif ($evidenceSelfPayEnabled !== $configuredSelfPayEnabled) {
            $findings[] = sprintf(
                'Payment evidence customer_self_pay_enabled [%s] does not match configured customer self-pay state [%s].',
                $evidenceSelfPayEnabled ? 'true' : 'false',
                $configuredSelfPayEnabled ? 'true' : 'false',
            );
        }

        $providerMode = strtolower(trim((string) ($evidence['provider_mode'] ?? '')));
        if ($providerMode === '') {
            $findings[] = 'Payment evidence must include provider_mode.';
        }

        if (! $configuredSelfPayEnabled) {
            if (! in_array($providerMode, ['disabled', 'staff_settlement_only'], true)) {
                $findings[] = 'When customer self-pay is disabled, provider_mode must be disabled or staff_settlement_only.';
            }

            if ($this->evidenceBoolean($evidence, 'staff_settlement_day1_path_confirmed') !== true) {
                $findings[] = 'Payment evidence must confirm staff_settlement_day1_path_confirmed when customer self-pay is disabled.';
            }

            return $findings;
        }

        $this->requireNonEmptyEvidenceString($evidence, 'provider_code', $findings, 'payment_provider_external_e2e');

        if (! in_array($providerMode, ['sandbox', 'live'], true)) {
            $findings[] = 'When customer self-pay is enabled, provider_mode must be sandbox or live.';
        }

        foreach (PaymentSessionScope::cases() as $scope) {
            $scopeStatus = $this->paymentProviderRolloutConfig->customerSelfPayStatus($scope);
            if (! ($scopeStatus['ok'] ?? false)) {
                $findings[] = sprintf(
                    'Configured customer self-pay scope [%s] is not operational: %s.',
                    $scope->value,
                    (string) ($scopeStatus['reason_code'] ?? $scopeStatus['message'] ?? 'not_ready'),
                );
            }
        }

        foreach (['deposit', 'bill'] as $scope) {
            if (! $this->evidenceCoversKey($evidence['scopes'] ?? $evidence['scope_evidence'] ?? [], $scope)) {
                $findings[] = sprintf('Payment evidence must include passing %s scope coverage.', $scope);
            }
        }

        foreach ([
            'callback_webhook_tested',
            'signature_validation_tested',
            'idempotency_replay_tested',
            'failure_cancel_path_tested',
            'settlement_reconciliation_tested',
        ] as $field) {
            if ($this->evidenceBoolean($evidence, $field) !== true) {
                $findings[] = sprintf('Payment evidence must set %s=true.', $field);
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    private function validateDisasterRecoveryRestoreEvidence(array $evidence): array
    {
        $findings = [];

        foreach ([
            'restored_dump_identifier',
            'restore_target',
            'verification_command',
            'verification_result',
        ] as $field) {
            $this->requireNonEmptyEvidenceString($evidence, $field, $findings, 'disaster_recovery_restore_evidence');
        }

        $result = strtolower(trim((string) ($evidence['verification_result'] ?? '')));
        if ($result !== '' && ! in_array($result, ['pass', 'passed', 'ok', 'success'], true)) {
            $findings[] = 'Disaster recovery evidence verification_result must be pass, passed, ok, or success.';
        }

        $this->requireRecentEvidenceTimestamp(
            $evidence,
            'performed_at_utc',
            max(1, (int) config('booking_launch_readiness.manual_evidence_max_age_days.disaster_recovery_restore_evidence', 14)),
            $findings,
            'disaster_recovery_restore_evidence',
        );

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    private function validatePerformanceVerificationEvidence(array $evidence): array
    {
        $findings = [];

        foreach ([
            'report_path',
            'profile',
            'evidence_level',
            'verification_result',
            'evaluated_at_utc',
        ] as $field) {
            $this->requireNonEmptyEvidenceString($evidence, $field, $findings, 'performance_verification_report');
        }

        if (strtolower(trim((string) ($evidence['profile'] ?? ''))) !== 'staging') {
            $findings[] = 'Performance evidence for limited production must use the staging profile, not local smoke timing.';
        }

        if (strtolower(trim((string) ($evidence['evidence_level'] ?? ''))) !== 'release_candidate') {
            $findings[] = 'Performance evidence_level must be release_candidate.';
        }

        if ($this->evidenceBoolean($evidence, 'local_smoke_only') !== false) {
            $findings[] = 'Performance evidence must declare local_smoke_only=false.';
        }

        $result = strtolower(trim((string) ($evidence['verification_result'] ?? '')));
        if ($result !== '' && ! in_array($result, ['pass', 'passed', 'ok', 'success'], true)) {
            $findings[] = 'Performance evidence verification_result must be pass, passed, ok, or success.';
        }

        $requiredSurfaces = array_values(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) config('booking_launch_readiness.performance_required_surfaces', [])
        ));
        foreach ($requiredSurfaces as $surface) {
            if ($surface !== '' && ! $this->evidenceCoversKey($evidence['scenario_matrix'] ?? [], $surface)) {
                $findings[] = sprintf('Performance evidence must include passing launch-critical surface [%s].', $surface);
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    private function validateUatScenarioReplayEvidence(array $evidence): array
    {
        $findings = [];

        foreach ([
            'scenario_pack_version',
            'replayed_at_utc',
            'verification_result',
        ] as $field) {
            $this->requireNonEmptyEvidenceString($evidence, $field, $findings, 'uat_scenario_pack_replay');
        }

        $this->requireValidEvidenceTimestamp($evidence, 'replayed_at_utc', $findings, 'uat_scenario_pack_replay');

        $result = strtolower(trim((string) ($evidence['verification_result'] ?? '')));
        if ($result !== '' && ! in_array($result, ['pass', 'passed', 'ok', 'success'], true)) {
            $findings[] = 'UAT evidence verification_result must be pass, passed, ok, or success.';
        }

        $requiredScenarios = array_values(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) config('booking_launch_readiness.uat_required_scenarios', [])
        ));
        foreach ($requiredScenarios as $scenario) {
            if ($scenario !== '' && ! $this->evidenceCoversKey($evidence['scenario_results'] ?? [], $scenario)) {
                $findings[] = sprintf('UAT evidence must include passing scenario [%s].', $scenario);
            }
        }

        if ($this->evidenceBoolean($evidence, 'production_artifact_contains_demo_credentials') !== false) {
            $findings[] = 'UAT evidence must declare production_artifact_contains_demo_credentials=false.';
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    private function validateConfiguredManualEvidenceSchema(string $checkKey, array $schema, array $evidence): array
    {
        $findings = [];

        foreach ((array) ($schema['required_string_fields'] ?? []) as $field) {
            $this->requireNonEmptyEvidenceString($evidence, (string) $field, $findings, $checkKey);
        }

        foreach ((array) ($schema['required_boolean_fields'] ?? []) as $field) {
            if ($this->evidenceBoolean($evidence, (string) $field) === null) {
                $findings[] = sprintf('Manual check [%s] must include boolean field [%s].', $checkKey, (string) $field);
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  list<string>  $findings
     */
    private function requireNonEmptyEvidenceString(array $evidence, string $field, array &$findings, string $checkKey): void
    {
        $value = data_get($evidence, $field);
        if (! is_string($value) || trim($value) === '') {
            $findings[] = sprintf('Manual check [%s] must include non-empty string field [%s].', $checkKey, $field);
        }
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  list<string>  $findings
     */
    private function requireValidEvidenceTimestamp(array $evidence, string $field, array &$findings, string $checkKey): void
    {
        $value = trim((string) data_get($evidence, $field, ''));
        if ($value === '') {
            $findings[] = sprintf('Manual check [%s] must include timestamp field [%s].', $checkKey, $field);

            return;
        }

        try {
            Carbon::parse($value);
        } catch (\Throwable) {
            $findings[] = sprintf('Manual check [%s] field [%s] must be a valid timestamp.', $checkKey, $field);
        }
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  list<string>  $findings
     */
    private function requireRecentEvidenceTimestamp(array $evidence, string $field, int $maxAgeDays, array &$findings, string $checkKey): void
    {
        $this->requireValidEvidenceTimestamp($evidence, $field, $findings, $checkKey);

        try {
            $timestamp = Carbon::parse((string) data_get($evidence, $field))->utc();
        } catch (\Throwable) {
            return;
        }

        if ($timestamp->lt(now('UTC')->subDays($maxAgeDays))) {
            $findings[] = sprintf(
                'Manual check [%s] field [%s] is older than %d day(s).',
                $checkKey,
                $field,
                $maxAgeDays,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function evidenceBoolean(array $evidence, string $field): ?bool
    {
        $value = data_get($evidence, $field);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', 'yes', '1'], true)) {
                return true;
            }

            if (in_array($normalized, ['false', 'no', '0'], true)) {
                return false;
            }
        }

        return null;
    }

    private function evidenceCoversKey(mixed $value, string $requiredKey): bool
    {
        if (is_string($value)) {
            return trim($value) === $requiredKey;
        }

        if (! is_array($value)) {
            return false;
        }

        if (array_is_list($value)) {
            foreach ($value as $row) {
                if (is_string($row) && trim($row) === $requiredKey) {
                    return true;
                }

                if (is_array($row)) {
                    $key = trim((string) ($row['key'] ?? $row['scenario_key'] ?? $row['surface_key'] ?? $row['scope'] ?? ''));
                    if ($key === $requiredKey && $this->rowIndicatesPass($row)) {
                        return true;
                    }
                }
            }

            return false;
        }

        if (array_key_exists($requiredKey, $value)) {
            $row = $value[$requiredKey];

            if (is_bool($row)) {
                return $row;
            }

            if (is_string($row)) {
                return in_array(strtolower(trim($row)), ['pass', 'passed', 'ok', 'success', 'covered'], true);
            }

            if (is_array($row)) {
                return $this->rowIndicatesPass($row);
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIndicatesPass(array $row): bool
    {
        foreach (['status', 'result', 'verification_result'] as $field) {
            $value = strtolower(trim((string) ($row[$field] ?? '')));
            if ($value !== '') {
                return in_array($value, ['pass', 'passed', 'ok', 'success', 'covered'], true);
            }
        }

        return ($row['passed'] ?? $row['covered'] ?? null) === true;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function manualEvidencePassSummary(string $checkKey, array $evidence): string
    {
        return match ($checkKey) {
            'payment_provider_external_e2e' => (bool) ($evidence['customer_self_pay_enabled'] ?? false)
                ? 'Customer self-pay provider evidence is schema-valid for deposit and bill scopes.'
                : 'Customer self-pay is disabled and staff settlement evidence is schema-valid.',
            'disaster_recovery_restore_evidence' => 'Disaster recovery restore evidence is schema-valid and recent enough for limited production.',
            'performance_verification_report' => 'Staging performance evidence covers the launch-critical matrix.',
            'uat_scenario_pack_replay' => 'UAT replay evidence covers the day-1 golden scenario pack.',
            default => 'Manual evidence recorded and schema-valid.',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $manualChecks
     * @param  array<string, mixed>  $manualEvidence
     * @return array<int, array<string, mixed>>
     */
    private function buildFollowUpActions(string $target, array $manualChecks, array $manualEvidence, Carbon $evaluatedAt): array
    {
        $actions = [];
        $manualEvidenceCandidate = $this->manualEvidenceCandidate($evaluatedAt);
        $manualEvidenceResolvedPath = is_string($manualEvidence['resolved_path'] ?? null) && trim((string) ($manualEvidence['resolved_path'] ?? '')) !== ''
            ? (string) $manualEvidence['resolved_path']
            : $this->defaultManualEvidencePath($target, $manualEvidenceCandidate);
        $manualEvidencePath = $this->displayPath(
            is_string($manualEvidence['resolved_path'] ?? null) && trim((string) ($manualEvidence['resolved_path'] ?? '')) !== ''
                ? (string) $manualEvidence['resolved_path']
                : $manualEvidenceResolvedPath
        );
        $defaultTemplateExists = ! (bool) ($manualEvidence['provided'] ?? false) && File::exists($manualEvidenceResolvedPath);

        if (! (bool) ($manualEvidence['provided'] ?? false)) {
            $actions[] = [
                'kind' => 'manual_evidence_template',
                'label' => $defaultTemplateExists
                    ? 'Use existing operator-owned manual evidence template'
                    : 'Scaffold operator-owned manual evidence template',
                'reason' => $defaultTemplateExists
                    ? sprintf('No manual evidence file was supplied, but an operator-owned template already exists at %s.', $manualEvidencePath)
                    : 'No manual evidence file was supplied for this launch-readiness run.',
                'runbook_path' => 'docs/runbooks/booking-launch-readiness.md',
                'commands' => $defaultTemplateExists ? [
                    sprintf(
                        'php artisan booking:launch-readiness --target=%s --manual-evidence=%s --json',
                        $target,
                        $manualEvidencePath
                    ),
                ] : [
                    $this->buildManualEvidenceInitCommand($target, $manualEvidenceCandidate, $manualEvidencePath),
                ],
                'notes' => $defaultTemplateExists ? [
                    sprintf('Update the existing template at %s with the pending operator evidence before the next promotion review.', $manualEvidencePath),
                    sprintf(
                        'Run `%s` only if you intentionally want to reset %s to a blank template.',
                        $this->buildManualEvidenceInitCommand($target, $manualEvidenceCandidate, $manualEvidencePath, overwrite: true),
                        $manualEvidencePath
                    ),
                ] : [
                    sprintf('Use the generated template at %s to record operator evidence before the next promotion review.', $manualEvidencePath),
                ],
                'manual_evidence_path' => $manualEvidencePath,
                'template_exists' => $defaultTemplateExists,
            ];
        }

        foreach ((array) ($manualEvidence['issues'] ?? []) as $issue) {
            $actions[] = [
                'kind' => 'manual_evidence_issue',
                'label' => 'Repair manual evidence file',
                'reason' => (string) $issue,
                'runbook_path' => 'docs/runbooks/booking-launch-readiness.md',
                'commands' => [
                    $this->buildManualEvidenceInitCommand($target, $manualEvidenceCandidate, $manualEvidencePath, overwrite: true),
                ],
                'notes' => [
                    sprintf('Re-run booking:launch-readiness with --manual-evidence=%s after repairing the file.', $manualEvidencePath),
                ],
                'manual_evidence_path' => $manualEvidencePath,
            ];
        }

        foreach ($manualChecks as $check) {
            if (! in_array((string) ($check['status'] ?? ''), ['warn', 'fail'], true)) {
                continue;
            }

            $commands = array_values(array_filter(array_map(
                static fn (mixed $value): string => is_string($value) ? trim($value) : '',
                (array) ($check['operator_commands'] ?? []),
            ), static fn (string $value): bool => $value !== ''));
            $notes = array_values(array_filter(array_map(
                static fn (mixed $value): string => is_string($value) ? trim($value) : '',
                (array) ($check['operator_notes'] ?? []),
            ), static fn (string $value): bool => $value !== ''));
            $findings = array_values(array_filter(array_map(
                static fn (mixed $finding): string => is_array($finding)
                    ? trim((string) ($finding['message'] ?? ''))
                    : '',
                (array) ($check['findings'] ?? []),
            ), static fn (string $value): bool => $value !== ''));

            $actions[] = [
                'kind' => 'manual_check',
                'check_key' => (string) ($check['key'] ?? ''),
                'label' => (string) ($check['label'] ?? $check['key'] ?? 'manual_check'),
                'status' => (string) ($check['status'] ?? 'unknown'),
                'severity' => (string) ($check['severity'] ?? 'major'),
                'reason' => $findings[0] ?? (string) ($check['summary'] ?? 'Manual evidence is still required.'),
                'runbook_path' => (string) ($check['runbook_path'] ?? 'docs/runbooks/booking-launch-readiness.md'),
                'commands' => $commands,
                'notes' => array_merge($notes, [
                    sprintf(
                        'Record the result under %s in %s, then rerun `php artisan booking:launch-readiness --target=%s --manual-evidence=%s --json`.',
                        (string) ($check['key'] ?? 'manual_check'),
                        $manualEvidencePath,
                        $target,
                        $manualEvidencePath
                    ),
                ]),
                'manual_evidence_path' => $manualEvidencePath,
            ];
        }

        return $actions;
    }

    private function manualEvidenceCandidate(Carbon $evaluatedAt): string
    {
        return $evaluatedAt->copy()->utc()->format('Ymd');
    }

    private function defaultManualEvidencePath(string $target, string $candidate): string
    {
        return base_path(sprintf('storage/app/booking_release/manual_evidence/%s-%s.json', $target, $candidate));
    }

    private function buildManualEvidenceInitCommand(
        string $target,
        string $candidate,
        string $outputPath,
        bool $overwrite = false,
    ): string {
        $command = [
            'php artisan booking:manual-evidence:init',
            '--target='.$target,
            '--candidate='.$candidate,
            '--output='.$outputPath,
        ];

        if ($overwrite) {
            $command[] = '--overwrite';
        }

        $command[] = '--json';

        return implode(' ', $command);
    }

    /**
     * @param  array<int, array<string, mixed>>  $manualChecks
     * @param  array<string, mixed>  $package
     * @param  array<string, mixed>  $releaseManifest
     * @return array<string, mixed>
     */
    private function buildReleaseHandoff(array $manualEvidence, array $manualChecks, array $package, array $releaseManifest): array
    {
        $manualEvidencePath = null;
        if (is_string($manualEvidence['resolved_path'] ?? null) && trim((string) ($manualEvidence['resolved_path'] ?? '')) !== '') {
            $manualEvidencePath = $this->displayPath((string) $manualEvidence['resolved_path']);
        }

        $candidateArchivePaths = [];
        foreach ([
            $package['package_path'] ?? null,
            data_get($package, 'sidecars.metadata_path'),
            data_get($package, 'sidecars.inventory_path'),
            data_get($package, 'sidecars.checksums_path'),
            data_get($package, 'sidecars.package_sha256_path'),
            data_get($package, 'sidecars.latest_pointer_path'),
            data_get($package, 'release_manifest.snapshot_path'),
            data_get($releaseManifest, 'snapshot_path'),
        ] as $path) {
            if (! is_string($path)) {
                continue;
            }

            $trimmed = trim($path);
            if ($trimmed !== '') {
                $candidateArchivePaths[] = $trimmed;
            }
        }
        $candidateArchivePaths = array_values(array_unique($candidateArchivePaths));

        $requiredChecks = array_values(array_filter(
            $manualChecks,
            static fn (array $check): bool => (string) ($check['severity'] ?? 'major') === 'blocking'
        ));
        $recommendedChecks = array_values(array_filter(
            $manualChecks,
            static fn (array $check): bool => (string) ($check['severity'] ?? 'major') !== 'blocking'
        ));
        $requiredPassCount = count(array_filter(
            $requiredChecks,
            static fn (array $check): bool => (string) ($check['status'] ?? 'unknown') === 'pass'
        ));
        $recommendedPassCount = count(array_filter(
            $recommendedChecks,
            static fn (array $check): bool => (string) ($check['status'] ?? 'unknown') === 'pass'
        ));

        $archivePaths = $candidateArchivePaths;
        if ($manualEvidencePath !== null) {
            $archivePaths[] = $manualEvidencePath;
        }

        $promotionNotes = [];
        if ((bool) ($package['ok'] ?? false)) {
            $promotionNotes[] = 'Copy package_basename, package_path, and sidecar paths into the release ticket before promotion.';
        } else {
            $promotionNotes[] = 'Package the candidate successfully before using this launch-readiness result as promotion evidence.';
        }
        if ($manualEvidencePath !== null) {
            $promotionNotes[] = sprintf('Archive the operator-owned manual evidence JSON at `%s` with the same candidate record.', $manualEvidencePath);
        } elseif ($manualChecks !== []) {
            $promotionNotes[] = 'Attach the operator-owned manual evidence JSON to the same candidate record before limited-production sign-off.';
        }
        $promotionNotes[] = 'Retain the previous known-good immutable package plus matching sidecars separately; `latest-package.json` is only a pointer to the newest build.';

        return [
            'candidate' => [
                'available' => (bool) ($package['ok'] ?? false),
                'package_id' => trim((string) ($package['package_id'] ?? '')),
                'package_basename' => trim((string) ($package['package_basename'] ?? '')),
                'package_path' => trim((string) ($package['package_path'] ?? '')),
                'package_sha256' => trim((string) ($package['package_sha256'] ?? '')),
                'package_bytes' => is_numeric($package['package_bytes'] ?? null) ? (int) $package['package_bytes'] : null,
                'release_manifest_snapshot_path' => trim((string) (data_get($package, 'release_manifest.snapshot_path', data_get($releaseManifest, 'snapshot_path', '')))),
                'sidecars' => [
                    'metadata_path' => trim((string) data_get($package, 'sidecars.metadata_path', '')),
                    'inventory_path' => trim((string) data_get($package, 'sidecars.inventory_path', '')),
                    'checksums_path' => trim((string) data_get($package, 'sidecars.checksums_path', '')),
                    'package_sha256_path' => trim((string) data_get($package, 'sidecars.package_sha256_path', '')),
                    'latest_pointer_path' => trim((string) data_get($package, 'sidecars.latest_pointer_path', '')),
                ],
            ],
            'manual_evidence' => [
                'provided' => (bool) ($manualEvidence['provided'] ?? false),
                'path' => $manualEvidencePath,
                'required_check_count' => count($requiredChecks),
                'required_pass_count' => $requiredPassCount,
                'recommended_check_count' => count($recommendedChecks),
                'recommended_pass_count' => $recommendedPassCount,
                'missing_required_check_keys' => array_values(array_map(
                    static fn (array $check): string => (string) ($check['key'] ?? ''),
                    array_values(array_filter(
                        $requiredChecks,
                        static fn (array $check): bool => (string) ($check['status'] ?? 'unknown') !== 'pass'
                    ))
                )),
                'missing_recommended_check_keys' => array_values(array_map(
                    static fn (array $check): string => (string) ($check['key'] ?? ''),
                    array_values(array_filter(
                        $recommendedChecks,
                        static fn (array $check): bool => (string) ($check['status'] ?? 'unknown') !== 'pass'
                    ))
                )),
            ],
            'archive_paths' => array_values(array_unique(array_filter(
                array_map(static fn (mixed $value): string => is_string($value) ? trim($value) : '', $archivePaths),
                static fn (string $value): bool => $value !== ''
            ))),
            'rollback_kit' => [
                'required_paths' => $candidateArchivePaths,
                'note' => 'Archive the promoted candidate tarball together with its .metadata.json, .inventory.json, .checksums.sha256, .package.sha256, and the frozen release manifest snapshot. Keep the previous known-good package and sidecars separately for rollback; do not treat latest-package.json as the rollback decision record.',
            ],
            'promotion_notes' => $promotionNotes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTarget(string $target): array
    {
        $targets = (array) config('booking_launch_readiness.targets', []);
        if (isset($targets[$target]) && is_array($targets[$target])) {
            return $targets[$target];
        }

        return (array) ($targets['staging'] ?? ['label' => 'serious-staging']);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManualEvidence(?string $manualEvidencePath): array
    {
        if ($manualEvidencePath === null || trim($manualEvidencePath) === '') {
            return [
                'provided' => false,
                'resolved_path' => null,
                'checks' => [],
                'issues' => [],
            ];
        }

        $candidate = trim($manualEvidencePath);
        $resolvedPath = $this->resolvePath($candidate);
        if (! File::exists($resolvedPath)) {
            return [
                'provided' => true,
                'resolved_path' => $resolvedPath,
                'checks' => [],
                'issues' => [sprintf('Manual evidence file [%s] does not exist.', $candidate)],
            ];
        }

        try {
            $decoded = json_decode((string) File::get($resolvedPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            return [
                'provided' => true,
                'resolved_path' => $resolvedPath,
                'checks' => [],
                'issues' => [sprintf('Manual evidence file [%s] is not valid JSON: %s', $candidate, $exception->getMessage())],
            ];
        }

        $checks = [];
        foreach ((array) ($decoded['checks'] ?? []) as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $sanitizedRow = $this->sanitizeManualEvidenceValue($row);
            $sanitizedRow = is_array($sanitizedRow) ? $sanitizedRow : [];
            $validationIssues = $this->sensitiveManualEvidenceKeyIssues($row, sprintf('checks.%s', (string) $key));

            $checks[(string) $key] = array_merge($sanitizedRow, [
                'status' => strtolower(trim((string) ($row['status'] ?? 'missing'))),
                'performed_by' => trim((string) ($row['performed_by'] ?? '')),
                'performed_at_utc' => trim((string) ($row['performed_at_utc'] ?? '')),
                'notes' => trim((string) ($row['notes'] ?? '')),
            ]);

            if ($validationIssues !== []) {
                $checks[(string) $key]['_validation_issues'] = array_values(array_merge(
                    (array) ($checks[(string) $key]['_validation_issues'] ?? []),
                    $validationIssues,
                ));
            }
        }

        return [
            'provided' => true,
            'resolved_path' => $resolvedPath,
            'checks' => $checks,
            'issues' => [],
        ];
    }

    private function sanitizeManualEvidenceValue(mixed $value, string $key = ''): mixed
    {
        if ($key !== '' && $this->isSensitiveManualEvidenceKey($key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitizeManualEvidenceValue($childValue, (string) $childKey);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return mb_substr($value, 0, 2000);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function sensitiveManualEvidenceKeyIssues(array $row, string $path): array
    {
        $issues = [];

        foreach ($row as $key => $value) {
            $key = (string) $key;
            $currentPath = $path.'.'.$key;

            if ($this->isSensitiveManualEvidenceKey($key)) {
                $issues[] = sprintf(
                    'Manual evidence contains sensitive-looking field [%s]. Store only non-secret proof references, not provider credentials or secrets.',
                    $currentPath,
                );
            }

            if (is_array($value)) {
                $issues = array_merge($issues, $this->sensitiveManualEvidenceKeyIssues($value, $currentPath));
            }
        }

        return $issues;
    }

    private function isSensitiveManualEvidenceKey(string $key): bool
    {
        return preg_match('/(^|[_\\-.])(secret|password|credential|api[_-]?key|access[_-]?token|refresh[_-]?token|bearer|authorization)($|[_\\-.])/i', $key) === 1;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\\\')) {
            return $path;
        }

        return base_path($path);
    }

    private function displayPath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedBasePath = rtrim(str_replace('\\', '/', base_path()), '/');

        if (str_starts_with($normalizedPath, $normalizedBasePath.'/')) {
            return ltrim(substr($normalizedPath, strlen($normalizedBasePath)), '/');
        }

        return $normalizedPath;
    }

    /**
     * @param  array<int, array{severity: string, message: string}>  $findings
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function resultFromFindings(
        array $findings,
        string $passSummary,
        string $warnSummary,
        string $failSummary,
        array $evidence = [],
    ): array {
        $hasBlocking = collect($findings)->contains(static fn (array $finding): bool => ($finding['severity'] ?? '') === 'blocking');
        $hasMajor = collect($findings)->contains(static fn (array $finding): bool => ($finding['severity'] ?? '') === 'major');

        if ($hasBlocking) {
            return [
                'status' => 'fail',
                'summary' => $failSummary,
                'findings' => $findings,
                'evidence' => $evidence,
            ];
        }

        if ($hasMajor) {
            return [
                'status' => 'warn',
                'summary' => $warnSummary,
                'findings' => $findings,
                'evidence' => $evidence,
            ];
        }

        return [
            'status' => 'pass',
            'summary' => $passSummary,
            'findings' => [],
            'evidence' => $evidence,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderMarkdown(array $report): string
    {
        $lines = [];
        $lines[] = '# Booking Launch Readiness';
        $lines[] = '';
        $lines[] = sprintf('- Evaluated at: `%s`', (string) (($report['meta'] ?? [])['evaluated_at_utc'] ?? ''));
        $lines[] = sprintf('- Target: `%s`', (string) (($report['target'] ?? [])['label'] ?? ($report['target'] ?? [])['key'] ?? 'staging'));
        $lines[] = sprintf('- Decision: `%s`', strtoupper((string) ($report['decision'] ?? 'unknown')));
        $lines[] = sprintf('- Exit code: `%s`', (string) ($report['exit_code'] ?? '1'));
        $lines[] = '';
        $lines[] = '## Baseline Buckets';
        $lines[] = '';
        $lines[] = '| Bucket | Status | Summary |';
        $lines[] = '| --- | --- | --- |';
        foreach ((array) ($report['baseline'] ?? []) as $bucketKey => $bucket) {
            if (! is_array($bucket)) {
                continue;
            }

            $lines[] = sprintf(
                '| %s | %s | %s |',
                str_replace('|', '\|', (string) ($bucket['label'] ?? $bucketKey)),
                strtoupper((string) ($bucket['status'] ?? 'unknown')),
                str_replace('|', '\|', (string) ($bucket['summary'] ?? ''))
            );
        }
        $artifactDrift = (array) (($report['baseline'] ?? [])['artifact_drift'] ?? []);
        $lines[] = '';
        $lines[] = sprintf(
            '- Artifact chain touch required: `%s`',
            ((bool) ($artifactDrift['touch_required'] ?? false)) ? 'yes' : 'no'
        );
        foreach ((array) ($artifactDrift['touch_paths'] ?? []) as $path) {
            $lines[] = sprintf('- Artifact chain path: `%s`', (string) $path);
        }
        $recommendedOwnerHint = trim((string) ($artifactDrift['recommended_owner_hint'] ?? ''));
        if ($recommendedOwnerHint !== '') {
            $lines[] = '- '.$recommendedOwnerHint;
        }
        foreach ((array) ($artifactDrift['pending_external_check_keys'] ?? []) as $checkKey) {
            $lines[] = sprintf('- Pending external artifact check: `%s`', (string) $checkKey);
        }
        $lines[] = '';
        $lines[] = '## Group Summary';
        $lines[] = '';
        $lines[] = '| Group | Status | Blocking | Warnings |';
        $lines[] = '| --- | --- | ---: | ---: |';
        foreach ((array) ($report['groups'] ?? []) as $group) {
            $lines[] = sprintf(
                '| %s | %s | %d | %d |',
                (string) ($group['label'] ?? $group['key'] ?? ''),
                strtoupper((string) ($group['status'] ?? 'unknown')),
                (int) ($group['blocking_failure_count'] ?? 0),
                (int) ($group['major_warning_count'] ?? 0),
            );
        }

        $lines[] = '';
        $lines[] = '## Integrated Sources';
        $lines[] = '';
        $lines[] = '| Source | Role |';
        $lines[] = '| --- | --- |';
        foreach ((array) ($report['integrated_sources'] ?? []) as $source) {
            $lines[] = sprintf(
                '| `%s` | %s |',
                str_replace('|', '\|', (string) ($source['source'] ?? '')),
                str_replace('|', '\|', (string) ($source['role'] ?? ''))
            );
        }

        $lines[] = '';
        $lines[] = '## Checks';
        $lines[] = '';
        $lines[] = '| Group | Check | Source | Status | Severity | Summary |';
        $lines[] = '| --- | --- | --- | --- | --- | --- |';
        foreach (array_merge((array) ($report['checks'] ?? []), (array) ($report['manual_checks'] ?? [])) as $check) {
            $lines[] = sprintf(
                '| %s | %s | `%s` | %s | %s | %s |',
                $this->groupLabel((string) ($check['group'] ?? '')),
                str_replace('|', '\|', (string) ($check['label'] ?? $check['key'] ?? '')),
                (string) ($check['source'] ?? ''),
                strtoupper((string) ($check['status'] ?? 'unknown')),
                strtoupper((string) ($check['severity'] ?? 'info')),
                str_replace('|', '\|', (string) ($check['summary'] ?? ''))
            );
        }

        $lines[] = '';
        $lines[] = '## Blocking Failures';
        $lines[] = '';
        if ((array) ($report['blocking_failures'] ?? []) === []) {
            $lines[] = '- None.';
        } else {
            foreach ((array) ($report['blocking_failures'] ?? []) as $finding) {
                $lines[] = sprintf('- [%s] %s', (string) ($finding['check_label'] ?? $finding['check_key'] ?? 'unknown'), (string) ($finding['message'] ?? ''));
            }
        }

        $lines[] = '';
        $lines[] = '## Major Warnings';
        $lines[] = '';
        if ((array) ($report['major_warnings'] ?? []) === []) {
            $lines[] = '- None.';
        } else {
            foreach ((array) ($report['major_warnings'] ?? []) as $finding) {
                $lines[] = sprintf('- [%s] %s', (string) ($finding['check_label'] ?? $finding['check_key'] ?? 'unknown'), (string) ($finding['message'] ?? ''));
            }
        }

        $lines[] = '';
        $lines[] = '## Manual Checks';
        $lines[] = '';
        $manualEvidence = (array) ($report['manual_evidence'] ?? []);
        $manualEvidencePath = trim((string) ($manualEvidence['resolved_path'] ?? ''));
        if ($manualEvidencePath !== '') {
            $lines[] = sprintf('- Evidence file: `%s`', $manualEvidencePath);
        }
        foreach ((array) ($manualEvidence['issues'] ?? []) as $issue) {
            $lines[] = sprintf('- Issue: %s', (string) $issue);
        }
        if ((array) ($report['manual_checks'] ?? []) === []) {
            $lines[] = '- No target-specific manual checks were required.';
        } else {
            foreach ((array) ($report['manual_checks'] ?? []) as $check) {
                $evidence = (array) ($check['evidence'] ?? []);
                $note = trim((string) ($evidence['notes'] ?? ''));
                $details = [];
                $performedBy = trim((string) ($evidence['performed_by'] ?? ''));
                if ($performedBy !== '') {
                    $details[] = sprintf('by %s', $performedBy);
                }
                $performedAt = trim((string) ($evidence['performed_at_utc'] ?? ''));
                if ($performedAt !== '') {
                    $details[] = sprintf('at %s', $performedAt);
                }
                if ($note !== '') {
                    $details[] = $note;
                }
                $lines[] = sprintf(
                    '- [%s] %s%s',
                    strtoupper((string) ($check['status'] ?? 'unknown')),
                    (string) ($check['label'] ?? $check['key'] ?? ''),
                    $details !== [] ? ' - '.implode('; ', $details) : ''
                );
            }
        }

        $releaseHandoff = (array) ($report['release_handoff'] ?? []);
        $candidate = (array) ($releaseHandoff['candidate'] ?? []);
        $manualSummary = (array) ($releaseHandoff['manual_evidence'] ?? []);
        $lines[] = '';
        $lines[] = '## Release Handoff';
        $lines[] = '';
        $lines[] = '| Field | Value |';
        $lines[] = '| --- | --- |';
        $lines[] = sprintf(
            '| candidate_available | %s |',
            ((bool) ($candidate['available'] ?? false)) ? 'yes' : 'no'
        );
        $lines[] = sprintf('| package_basename | `%s` |', (string) ($candidate['package_basename'] ?? 'not-available'));
        $lines[] = sprintf('| package_path | `%s` |', (string) ($candidate['package_path'] ?? ''));
        $lines[] = sprintf('| release_manifest_snapshot | `%s` |', (string) ($candidate['release_manifest_snapshot_path'] ?? ''));
        $lines[] = sprintf(
            '| manual_evidence | `%s` |',
            (string) (($manualSummary['path'] ?? null) ?: 'not-supplied')
        );
        $lines[] = sprintf(
            '| required_manual_checks | `%d/%d pass` |',
            (int) ($manualSummary['required_pass_count'] ?? 0),
            (int) ($manualSummary['required_check_count'] ?? 0)
        );
        $lines[] = sprintf(
            '| recommended_manual_checks | `%d/%d pass` |',
            (int) ($manualSummary['recommended_pass_count'] ?? 0),
            (int) ($manualSummary['recommended_check_count'] ?? 0)
        );

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

        $rollbackNote = trim((string) data_get($releaseHandoff, 'rollback_kit.note', ''));
        if ($rollbackNote !== '') {
            $lines[] = '';
            $lines[] = 'Rollback note:';
            $lines[] = '- '.$rollbackNote;
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
                    $details[] = '`'.str_replace('|', '\|', (string) $command).'`';
                }
                foreach ((array) ($action['notes'] ?? []) as $note) {
                    $details[] = str_replace('|', '\|', (string) $note);
                }

                $lines[] = sprintf(
                    '| %s | %s | `%s` | %s |',
                    strtoupper((string) ($action['kind'] ?? 'action')),
                    str_replace('|', '\|', (string) ($action['label'] ?? '')),
                    str_replace('|', '\|', (string) ($action['runbook_path'] ?? 'docs/runbooks/booking-launch-readiness.md')),
                    $details !== [] ? implode('<br>', $details) : '-'
                );
            }
        }

        $lines[] = '';
        $lines[] = '## Automation Gaps';
        $lines[] = '';
        foreach ((array) ($report['automation_gaps'] ?? []) as $gap) {
            $lines[] = sprintf('- %s', (string) ($gap['description'] ?? ''));
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function groupLabel(string $groupKey): string
    {
        return (string) (((array) config('booking_launch_readiness.groups', []))[$groupKey] ?? $groupKey);
    }
}
