<?php

declare(strict_types=1);

namespace App\Platform\Release\Services;

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

        $doctor = $this->bookingDoctorService->inspect();
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

            $evidence = (array) (($manualEvidence['checks'] ?? [])[(string) ($definition['key'] ?? '')] ?? []);
            $evidenceStatus = strtolower(trim((string) ($evidence['status'] ?? 'missing')));
            $required = in_array($target, $requiredFor, true);

            $status = 'pass';
            $findings = [];
            $summary = 'Manual evidence recorded.';

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
     * @param  array<int, array<string, mixed>>  $manualChecks
     * @param  array<string, mixed>  $manualEvidence
     * @return array<int, array<string, mixed>>
     */
    private function buildFollowUpActions(string $target, array $manualChecks, array $manualEvidence, Carbon $evaluatedAt): array
    {
        $actions = [];
        $manualEvidencePath = $this->displayPath(
            is_string($manualEvidence['resolved_path'] ?? null) && trim((string) ($manualEvidence['resolved_path'] ?? '')) !== ''
                ? (string) $manualEvidence['resolved_path']
                : base_path(sprintf(
                    'storage/app/booking_release/manual_evidence/%s-%s.json',
                    $target,
                    $evaluatedAt->copy()->utc()->format('Ymd')
                ))
        );

        if (! (bool) ($manualEvidence['provided'] ?? false)) {
            $actions[] = [
                'kind' => 'manual_evidence_template',
                'label' => 'Scaffold operator-owned manual evidence template',
                'reason' => 'No manual evidence file was supplied for this launch-readiness run.',
                'runbook_path' => 'docs/runbooks/booking-launch-readiness.md',
                'commands' => [
                    sprintf(
                        'php artisan booking:manual-evidence:init --target=%s --candidate=%s --json',
                        $target,
                        $evaluatedAt->copy()->utc()->format('Ymd')
                    ),
                ],
                'notes' => [
                    sprintf('Use the generated template at %s to record operator evidence before the next promotion review.', $manualEvidencePath),
                ],
                'manual_evidence_path' => $manualEvidencePath,
            ];
        }

        foreach ((array) ($manualEvidence['issues'] ?? []) as $issue) {
            $actions[] = [
                'kind' => 'manual_evidence_issue',
                'label' => 'Repair manual evidence file',
                'reason' => (string) $issue,
                'runbook_path' => 'docs/runbooks/booking-launch-readiness.md',
                'commands' => [
                    sprintf(
                        'php artisan booking:manual-evidence:init --target=%s --candidate=%s --overwrite --json',
                        $target,
                        $evaluatedAt->copy()->utc()->format('Ymd')
                    ),
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

            $checks[(string) $key] = [
                'status' => strtolower(trim((string) ($row['status'] ?? 'missing'))),
                'performed_by' => trim((string) ($row['performed_by'] ?? '')),
                'performed_at_utc' => trim((string) ($row['performed_at_utc'] ?? '')),
                'notes' => trim((string) ($row['notes'] ?? '')),
            ];
        }

        return [
            'provided' => true,
            'resolved_path' => $resolvedPath,
            'checks' => $checks,
            'issues' => [],
        ];
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
