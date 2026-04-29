<?php

declare(strict_types=1);

namespace App\Platform\Backup\DisasterRecovery;

use App\Platform\ApiContract\Services\OpsGateArtifactService;
use App\Platform\Backup\Support\BackupRestoreManifest;
use Illuminate\Support\Carbon;
use Throwable;

class DisasterRecoveryDrillService
{
    public function __construct(
        private readonly DisasterRecoveryProcessRunner $processRunner,
        private readonly DisasterRecoveryDatabaseProbe $databaseProbe,
        private readonly OpsGateArtifactService $opsGateArtifactService,
    ) {}

    /**
     * @param  array<string, string|null>  $targetOverrides
     * @return array<string, mixed>
     */
    public function run(
        string $mode = 'metadata-verify',
        ?string $manifestPath = null,
        ?string $backupDir = null,
        ?string $backupRoot = null,
        bool $captureBackup = false,
        array $targetOverrides = [],
        bool $dropTargetFirst = false,
        bool $allowNonemptyTarget = false,
    ): array {
        $evaluatedAt = now('UTC');
        $startedAt = microtime(true);
        $modeConfig = $this->resolveMode($mode);
        $inputErrors = [];

        if ($captureBackup && (($manifestPath !== null && trim($manifestPath) !== '') || ($backupDir !== null && trim($backupDir) !== ''))) {
            $inputErrors[] = 'Do not combine --capture-backup with --manifest or --backup-dir; choose one backup source.';
        }

        $backupCapture = null;
        $backupCaptureDurationSeconds = null;
        if ($captureBackup) {
            $captureStartedAt = microtime(true);
            $backupCapture = $this->captureFreshBackup($backupRoot);
            $backupCaptureDurationSeconds = round(microtime(true) - $captureStartedAt, 3);

            if (! ($backupCapture['ok'] ?? false)) {
                foreach ((array) ($backupCapture['errors'] ?? []) as $error) {
                    $inputErrors[] = (string) $error;
                }
            }
        }

        $resolvedManifestPath = $this->resolveManifestPath($manifestPath, $backupDir, $backupRoot, $backupCapture);
        $manifestReport = $this->inspectManifest($resolvedManifestPath, $inputErrors, $evaluatedAt);

        $targetGuard = $this->inspectTargetGuard(
            mode: $mode,
            targetOverrides: $targetOverrides,
            dropTargetFirst: $dropTargetFirst,
            allowNonemptyTarget: $allowNonemptyTarget,
        );

        $restorePayload = null;
        $restoreDurationSeconds = null;
        if ($this->modeRequiresRestore($mode) && ($targetGuard['ok'] ?? false)) {
            $restoreStartedAt = microtime(true);
            $restorePayload = $this->runRestore(
                mode: $mode,
                manifestPath: (string) ($manifestReport['path'] ?? ''),
                targetConnection: (array) ($targetGuard['connection'] ?? []),
                dropTargetFirst: $dropTargetFirst,
                allowNonemptyTarget: $allowNonemptyTarget,
            );
            $restoreDurationSeconds = round(microtime(true) - $restoreStartedAt, 3);
        }

        $doctorPayload = $this->decodeJsonOutput((string) data_get($this->restoreStep((array) $restorePayload, 'verify.booking_doctor'), 'details.stdout', ''));
        $deployPayload = $this->decodeJsonOutput((string) data_get($this->restoreStep((array) $restorePayload, 'verify.booking_deploy_check'), 'details.stdout', ''));

        $probePayload = null;
        $releaseManifestPayload = null;
        $coreOpsPayload = null;
        $postRestoreDurationSeconds = null;

        if ($mode === 'full-isolated-restore' && ($restorePayload['ok'] ?? false) && ($targetGuard['ok'] ?? false)) {
            $postRestoreStartedAt = microtime(true);

            try {
                $probePayload = $this->databaseProbe->inspect((array) ($targetGuard['connection'] ?? []));
            } catch (Throwable $exception) {
                $probePayload = [
                    'ok' => false,
                    'errors' => [$exception->getMessage()],
                    'warnings' => [],
                    'schema_summary' => [],
                    'required_tables' => [],
                    'samples' => [],
                ];
            }

            $releaseManifestPayload = $this->runArtisanJson([
                'booking:release-manifest',
                '--verify-frozen',
                '--json',
            ]);
            $coreOpsPayload = $this->runArtisanJson([
                'booking:core-ops-gate',
                '--json',
            ]);

            $postRestoreDurationSeconds = round(microtime(true) - $postRestoreStartedAt, 3);
        }

        $context = [
            'mode' => $mode,
            'mode_config' => $modeConfig,
            'backup_capture' => $backupCapture,
            'manifest' => $manifestReport,
            'target_guard' => $targetGuard,
            'restore' => $restorePayload,
            'restore_doctor' => $doctorPayload,
            'restore_deploy' => $deployPayload,
            'probe' => $probePayload,
            'release_manifest' => $releaseManifestPayload,
            'core_ops' => $coreOpsPayload,
            'timings' => [
                'backup_capture_seconds' => $backupCaptureDurationSeconds,
                'restore_seconds' => $restoreDurationSeconds,
                'post_restore_verification_seconds' => $postRestoreDurationSeconds,
                'total_seconds' => round(microtime(true) - $startedAt, 3),
            ],
        ];

        $checks = [];
        foreach ((array) ($modeConfig['checks'] ?? []) as $checkKey) {
            $definition = $this->matrixDefinition((string) $checkKey);
            $checks[] = $this->evaluateCheck($definition, $context);
        }

        $groups = $this->summarizeGroups((array) config('booking_disaster_recovery.groups', []), $checks);
        $blockingFailures = $this->flattenFindings($checks, 'blocking');
        $majorWarnings = $this->flattenFindings($checks, 'major');
        $informationalFindings = $this->buildInformationalFindings($checks);

        $decision = 'pass';
        $exitCode = 0;
        if ($blockingFailures !== []) {
            $decision = 'fail';
            $exitCode = 1;
        } elseif ($majorWarnings !== []) {
            $decision = 'pass_with_warnings';
            $exitCode = 2;
        }

        $recoveryObjectives = $this->buildRecoveryObjectives($mode, $manifestReport, (array) ($context['timings'] ?? []));
        $launchEvidence = $this->buildLaunchEvidence(
            mode: $mode,
            evaluatedAt: $evaluatedAt,
            manifestReport: $manifestReport,
            targetGuard: $targetGuard,
            restorePayload: (array) $restorePayload,
            decision: $decision,
        );

        $report = [
            'ok' => $exitCode === 0,
            'decision' => $decision,
            'exit_code' => $exitCode,
            'mode' => [
                'key' => $mode,
                'label' => (string) ($modeConfig['label'] ?? $mode),
                'description' => (string) ($modeConfig['description'] ?? ''),
                'evidence_level' => (string) ($modeConfig['evidence_level'] ?? 'unknown'),
                'claim' => (string) ($modeConfig['claim'] ?? ''),
                'ci_safe' => (bool) ($modeConfig['ci_safe'] ?? false),
            ],
            'summary' => [
                'group_count' => count($groups),
                'check_count' => count($checks),
                'blocking_failure_count' => count($blockingFailures),
                'major_warning_count' => count($majorWarnings),
                'informational_count' => count($informationalFindings),
            ],
            'groups' => $groups,
            'checks' => $checks,
            'blocking_failures' => $blockingFailures,
            'major_warnings' => $majorWarnings,
            'informational_findings' => $informationalFindings,
            'recovery_objectives' => $recoveryObjectives,
            'launch_evidence' => $launchEvidence,
            'automation' => [
                'fully_automated' => [
                    'backup manifest validation',
                    'artifact checksum validation',
                    'restore_release.php orchestration',
                    'verify_release_contract.sql execution',
                    'booking:doctor on restored target',
                    'booking:deploy-check postflight on restored target',
                    'restored schema + data sample probes',
                    'booking:release-manifest --verify-frozen',
                    'booking:core-ops-gate',
                ],
                'staging_only' => [
                    'full-isolated-restore should run only against an explicitly isolated scratch database and is not intended for default CI.',
                ],
                'manual_steps' => [
                    'Review the JSON/Markdown artifact before sign-off.',
                    'Drop or archive the scratch restore database according to the runbook after evidence collection.',
                ],
            ],
            'integrated_sources' => [
                ['source' => 'tools/mysql/backup_release.php', 'role' => 'Fresh backup capture for end-to-end drill evidence'],
                ['source' => 'backup manifests', 'role' => 'Artifact selection, checksum, and RPO evidence'],
                ['source' => 'tools/mysql/restore_release.php', 'role' => 'Restore orchestration into isolated target'],
                ['source' => 'tools/mysql/verify_release_contract.sql', 'role' => 'Schema/release contract verification'],
                ['source' => 'booking:doctor', 'role' => 'Restored-target runtime/schema verification'],
                ['source' => 'booking:deploy-check --mode=postflight', 'role' => 'Restored-target domain invariant verification'],
                ['source' => 'booking:release-manifest --verify-frozen', 'role' => 'Frozen release contract verification after restore'],
                ['source' => 'booking:core-ops-gate', 'role' => 'Canonical build-level core flow regression gate after restore'],
            ],
            'scope' => [
                'fully_automated' => [
                    'metadata-verify',
                    'dry-restore',
                ],
                'manual_or_staging_only' => [
                    'full-isolated-restore requires an isolated scratch database and should be scheduled as a staging/manual drill.',
                ],
            ],
            'sources' => [
                'backup_capture' => $backupCapture,
                'manifest' => $manifestReport,
                'restore' => $restorePayload,
                'restore_doctor' => $doctorPayload,
                'restore_deploy' => $deployPayload,
                'probe' => $probePayload,
                'release_manifest' => $releaseManifestPayload,
                'core_ops' => $coreOpsPayload,
            ],
            'meta' => [
                'evaluated_at_utc' => $evaluatedAt->toIso8601String(),
                'backup_capture_requested' => $captureBackup,
                'target_connection' => $this->maskConnection((array) ($targetGuard['connection'] ?? [])),
            ],
        ];

        return $this->writeArtifacts($report, $mode, $evaluatedAt);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function evaluateCheck(array $definition, array $context): array
    {
        $key = (string) ($definition['key'] ?? '');
        $result = match ($key) {
            'backup_manifest_integrity' => $this->evaluateBackupManifestIntegrity((array) ($context['manifest'] ?? [])),
            'backup_checksum_inventory' => $this->evaluateBackupChecksumInventory((array) ($context['manifest'] ?? [])),
            'backup_freshness_rpo' => $this->evaluateBackupFreshness((array) ($context['manifest'] ?? [])),
            'restore_target_isolation' => $this->evaluateTargetIsolation((array) ($context['target_guard'] ?? [])),
            'restore_dry_plan' => $this->evaluateRestorePlan((array) ($context['restore'] ?? [])),
            'restore_full_execution' => $this->evaluateRestoreExecution((array) ($context['restore'] ?? [])),
            'restore_release_contract_sql' => $this->evaluateRestoreContractSql((array) ($context['restore'] ?? [])),
            'restored_target_doctor' => $this->evaluateRestoredDoctor((array) ($context['restore'] ?? []), (array) ($context['restore_doctor'] ?? [])),
            'restored_target_deploy_postflight' => $this->evaluateRestoredDeploy((array) ($context['restore'] ?? []), (array) ($context['restore_deploy'] ?? [])),
            'restored_schema_integrity' => $this->evaluateSchemaProbe((array) ($context['probe'] ?? [])),
            'restored_data_sample_sanity' => $this->evaluateDataSamples((array) ($context['probe'] ?? [])),
            'release_manifest_after_restore' => $this->evaluateReleaseManifest((array) ($context['release_manifest'] ?? [])),
            'core_ops_gate_after_restore' => $this->evaluateCoreOpsGate((array) ($context['core_ops'] ?? [])),
            'recovery_objectives_rto' => $this->evaluateRecoveryObjectives((array) ($context['timings'] ?? [])),
            default => [
                'status' => 'warn',
                'summary' => sprintf('No DR evaluator is defined for matrix check [%s].', $key),
                'findings' => [
                    [
                        'severity' => 'major',
                        'message' => sprintf('DR matrix check [%s] is configured but has no evaluator implementation.', $key),
                    ],
                ],
                'evidence' => [],
            ],
        };

        return array_merge($definition, [
            'status' => (string) ($result['status'] ?? 'warn'),
            'summary' => (string) ($result['summary'] ?? ''),
            'findings' => array_values((array) ($result['findings'] ?? [])),
            'evidence' => (array) ($result['evidence'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $manifestReport
     * @return array<string, mixed>
     */
    private function evaluateBackupManifestIntegrity(array $manifestReport): array
    {
        $findings = [];

        foreach ((array) ($manifestReport['errors'] ?? []) as $error) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $error,
            ];
        }

        foreach ((array) ($manifestReport['artifact_reports'] ?? []) as $name => $artifactReport) {
            foreach ((array) data_get($artifactReport, 'validation.errors', []) as $error) {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf('%s artifact: %s', (string) $name, (string) $error),
                ];
            }
        }

        if (($manifestReport['manifest'] ?? null) !== null && ((array) ($manifestReport['usable_artifacts'] ?? [])) === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'No usable schema/full restore artifact remains after manifest validation.',
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'Backup manifest and referenced artifacts validated successfully.',
            warnSummary: 'Backup manifest integrity emitted warnings that require review.',
            failSummary: 'Backup manifest or referenced artifacts failed integrity validation.',
            evidence: [
                'manifest_path' => (string) ($manifestReport['path'] ?? ''),
                'database' => (array) ($manifestReport['database'] ?? []),
                'usable_artifacts' => (array) ($manifestReport['usable_artifacts'] ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $manifestReport
     * @return array<string, mixed>
     */
    private function evaluateBackupChecksumInventory(array $manifestReport): array
    {
        $findings = [];
        foreach ((array) data_get($manifestReport, 'checksums.errors', []) as $error) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $error,
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'Checksum inventory covers manifest.json and all referenced restore artifacts.',
            warnSummary: 'Checksum inventory emitted warnings that require review.',
            failSummary: 'Checksum inventory is missing or inconsistent.',
            evidence: [
                'checksums_path' => (string) data_get($manifestReport, 'checksums.path', ''),
                'entries' => (array) data_get($manifestReport, 'checksums.entries', []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $manifestReport
     * @return array<string, mixed>
     */
    private function evaluateBackupFreshness(array $manifestReport): array
    {
        $findings = [];
        $ageSeconds = $manifestReport['age_seconds'] ?? null;
        $targetSeconds = max(0, (int) config('booking_disaster_recovery.rpo_target_minutes', 1440)) * 60;

        if (! is_numeric($ageSeconds)) {
            $findings[] = [
                'severity' => 'major',
                'message' => 'Backup manifest does not expose a valid generated_at_utc timestamp, so RPO could not be measured.',
            ];
        } elseif ($targetSeconds > 0 && (float) $ageSeconds > $targetSeconds) {
            $findings[] = [
                'severity' => 'major',
                'message' => sprintf(
                    'Backup age is %.1f minute(s), which exceeds the configured RPO target of %d minute(s).',
                    ((float) $ageSeconds) / 60,
                    (int) config('booking_disaster_recovery.rpo_target_minutes', 1440),
                ),
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'Backup age is within the configured RPO target.',
            warnSummary: 'Backup age is missing or outside the configured RPO target.',
            failSummary: 'Backup age failed the configured RPO target.',
            evidence: [
                'generated_at_utc' => (string) ($manifestReport['generated_at_utc'] ?? ''),
                'age_seconds' => $ageSeconds,
                'rpo_target_minutes' => (int) config('booking_disaster_recovery.rpo_target_minutes', 1440),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $targetGuard
     * @return array<string, mixed>
     */
    private function evaluateTargetIsolation(array $targetGuard): array
    {
        $findings = [];

        foreach ((array) ($targetGuard['errors'] ?? []) as $error) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $error,
            ];
        }

        foreach ((array) ($targetGuard['warnings'] ?? []) as $warning) {
            $findings[] = [
                'severity' => 'major',
                'message' => (string) $warning,
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'Restore target passed isolated scratch-database safety guards.',
            warnSummary: 'Restore target passed blockers but still emitted warnings.',
            failSummary: 'Restore target failed isolated scratch-database safety guards.',
            evidence: [
                'target_connection' => $this->maskConnection((array) ($targetGuard['connection'] ?? [])),
                'flags' => (array) ($targetGuard['flags'] ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $restore
     * @return array<string, mixed>
     */
    private function evaluateRestorePlan(array $restore): array
    {
        $findings = [];

        if ($restore === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'Dry restore was not executed because the workflow never reached restore_release.php.',
            ];
        }

        foreach ((array) ($restore['errors'] ?? []) as $error) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $error,
            ];
        }

        if (($restore['ok'] ?? null) === false && $findings === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'Dry restore planning did not complete cleanly.',
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'restore_release.php accepted the dry restore plan.',
            warnSummary: 'restore_release.php accepted the dry restore plan with warnings.',
            failSummary: 'restore_release.php rejected the dry restore plan.',
            evidence: [
                'selected_artifact' => (array) ($restore['selected_artifact'] ?? []),
                'steps' => (array) ($restore['steps'] ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $restore
     * @return array<string, mixed>
     */
    private function evaluateRestoreExecution(array $restore): array
    {
        $findings = [];

        if ($restore === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'Full restore did not execute because the workflow never reached restore_release.php.',
            ];
        }

        foreach (['target_database.ensure_exists', 'target_database.inspect', 'restore.import'] as $stepName) {
            $step = $this->restoreStep($restore, $stepName);
            if ($step !== [] && (string) ($step['status'] ?? 'fail') !== 'ok') {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf('%s: %s', $stepName, (string) ($step['message'] ?? 'restore step failed.')),
                ];
            }
        }

        foreach ((array) ($restore['errors'] ?? []) as $error) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $error,
            ];
        }

        foreach ((array) ($restore['warnings'] ?? []) as $warning) {
            $findings[] = [
                'severity' => 'major',
                'message' => (string) $warning,
            ];
        }

        if (($restore['selected_artifact']['name'] ?? null) !== null && (string) ($restore['selected_artifact']['name'] ?? '') !== 'full') {
            $findings[] = [
                'severity' => 'major',
                'message' => 'Restore used a schema-only artifact; data-level recoverability is incomplete.',
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'restore_release.php completed the isolated restore import successfully.',
            warnSummary: 'restore_release.php completed the isolated restore with warnings that require review.',
            failSummary: 'restore_release.php failed one or more isolated restore steps.',
            evidence: [
                'selected_artifact' => (array) ($restore['selected_artifact'] ?? []),
                'steps' => (array) ($restore['steps'] ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $restore
     * @return array<string, mixed>
     */
    private function evaluateRestoreContractSql(array $restore): array
    {
        $step = $this->restoreStep($restore, 'verify.release_contract');
        $findings = [];

        if ($step === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'verify_release_contract.sql did not run against the restored target.',
            ];
        } elseif ((string) ($step['status'] ?? 'fail') !== 'ok') {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) ($step['message'] ?? 'Release contract SQL verification failed.'),
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'verify_release_contract.sql passed against the restored target.',
            warnSummary: 'verify_release_contract.sql emitted warnings that require review.',
            failSummary: 'verify_release_contract.sql failed against the restored target.',
            evidence: [
                'step' => $step,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $restore
     * @param  array<string, mixed>  $doctor
     * @return array<string, mixed>
     */
    private function evaluateRestoredDoctor(array $restore, array $doctor): array
    {
        $step = $this->restoreStep($restore, 'verify.booking_doctor');
        $findings = [];

        if ($step === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'booking:doctor did not execute against the restored target.',
            ];
        } elseif ((string) ($step['status'] ?? 'fail') !== 'ok') {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) ($step['message'] ?? 'booking:doctor failed against the restored target.'),
            ];
        }

        foreach ((array) data_get($doctor, 'validation.errors', []) as $message) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $message,
            ];
        }

        foreach ((array) data_get($doctor, 'validation.warnings', []) as $message) {
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
            passSummary: 'booking:doctor passed against the restored target.',
            warnSummary: 'booking:doctor passed against the restored target with warnings.',
            failSummary: 'booking:doctor found blocking issues on the restored target.',
            evidence: [
                'doctor' => $doctor,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $restore
     * @param  array<string, mixed>  $deploy
     * @return array<string, mixed>
     */
    private function evaluateRestoredDeploy(array $restore, array $deploy): array
    {
        $step = $this->restoreStep($restore, 'verify.booking_deploy_check');
        $findings = [];

        if ($step === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'booking:deploy-check postflight did not execute against the restored target.',
            ];
        } elseif ((string) ($step['status'] ?? 'fail') !== 'ok') {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) ($step['message'] ?? 'booking:deploy-check postflight failed against the restored target.'),
            ];
        }

        foreach ((array) ($deploy['report']['errors'] ?? []) as $message) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $message,
            ];
        }

        foreach ((array) ($deploy['report']['warnings'] ?? []) as $message) {
            $findings[] = [
                'severity' => 'major',
                'message' => (string) $message,
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'booking:deploy-check postflight passed against the restored target.',
            warnSummary: 'booking:deploy-check postflight passed against the restored target with warnings.',
            failSummary: 'booking:deploy-check postflight found blocking invariant failures on the restored target.',
            evidence: [
                'deploy' => $deploy,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $probe
     * @return array<string, mixed>
     */
    private function evaluateSchemaProbe(array $probe): array
    {
        $findings = [];

        if ($probe === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'Restored schema probe did not run.',
            ];
        }

        foreach ((array) ($probe['errors'] ?? []) as $error) {
            if (str_contains((string) $error, 'sample table')) {
                continue;
            }

            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $error,
            ];
        }

        foreach ((array) ($probe['required_tables'] ?? []) as $table => $status) {
            if (! ($status['exists'] ?? false)) {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf('Required restored table [%s] is missing.', (string) $table),
                ];
            }
        }

        if ((int) data_get($probe, 'schema_summary.table_count', 0) <= 0) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'Restored target reports zero base tables after import.',
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'Restored schema inventory completed successfully and required tables are present.',
            warnSummary: 'Restored schema inventory emitted warnings that require review.',
            failSummary: 'Restored schema inventory found missing or inconsistent schema state.',
            evidence: [
                'schema_summary' => (array) ($probe['schema_summary'] ?? []),
                'required_tables' => (array) ($probe['required_tables'] ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $probe
     * @return array<string, mixed>
     */
    private function evaluateDataSamples(array $probe): array
    {
        $findings = [];

        if ($probe === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'Restored data sample probe did not run.',
            ];
        }

        foreach ((array) ($probe['samples'] ?? []) as $table => $sample) {
            if (isset($sample['error']) && trim((string) $sample['error']) !== '') {
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf('Restored sample query failed for [%s]: %s', (string) $table, (string) $sample['error']),
                ];
            }
        }

        foreach ((array) ($probe['warnings'] ?? []) as $warning) {
            $findings[] = [
                'severity' => 'major',
                'message' => (string) $warning,
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'Critical restored tables were sampled successfully.',
            warnSummary: 'Critical restored tables were sampled, but anchor table state still requires review.',
            failSummary: 'Critical restored tables could not be sampled cleanly.',
            evidence: [
                'samples' => (array) ($probe['samples'] ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $releaseManifest
     * @return array<string, mixed>
     */
    private function evaluateReleaseManifest(array $releaseManifest): array
    {
        $findings = [];

        if ($releaseManifest === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'booking:release-manifest --verify-frozen did not run after restore.',
            ];
        }

        foreach ((array) ($releaseManifest['issues'] ?? []) as $issue) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => (string) $issue,
            ];
        }

        if (($releaseManifest['ok'] ?? null) === false && $findings === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'booking:release-manifest --verify-frozen failed after restore.',
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'Frozen release manifest still matches after the restore drill.',
            warnSummary: 'Frozen release manifest emitted warnings after the restore drill.',
            failSummary: 'Frozen release manifest verification failed after the restore drill.',
            evidence: [
                'release_manifest' => $releaseManifest,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $coreOps
     * @return array<string, mixed>
     */
    private function evaluateCoreOpsGate(array $coreOps): array
    {
        $findings = [];

        if ($coreOps === []) {
            $findings[] = [
                'severity' => 'blocking',
                'message' => 'booking:core-ops-gate did not run after restore.',
            ];
        }

        foreach ((array) ($coreOps['tests'] ?? []) as $test) {
            if ($test['ok'] ?? false) {
                continue;
            }

            $findings[] = [
                'severity' => 'blocking',
                'message' => sprintf(
                    'Core ops gate test [%s] failed (%s).',
                    (string) ($test['key'] ?? $test['path'] ?? 'unknown_test'),
                    trim((string) ($test['output_tail'] ?? 'no output'))
                ),
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'booking:core-ops-gate passed after the restore drill.',
            warnSummary: 'booking:core-ops-gate emitted warnings after the restore drill.',
            failSummary: 'booking:core-ops-gate failed after the restore drill.',
            evidence: [
                'summary' => (array) ($coreOps['summary'] ?? []),
                'suite' => (string) ($coreOps['suite'] ?? ''),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $timings
     * @return array<string, mixed>
     */
    private function evaluateRecoveryObjectives(array $timings): array
    {
        $findings = [];
        $actual = $this->restoreObjectiveSeconds($timings);
        $targetSeconds = max(0, (int) config('booking_disaster_recovery.rto_target_minutes', 60)) * 60;

        if ($actual === null) {
            $findings[] = [
                'severity' => 'major',
                'message' => 'RTO could not be measured because restore + post-restore verification timings are incomplete.',
            ];
        } elseif ($targetSeconds > 0 && $actual > $targetSeconds) {
            $findings[] = [
                'severity' => 'major',
                'message' => sprintf(
                    'Measured restore + verification time is %.1f minute(s), which exceeds the configured RTO target of %d minute(s).',
                    $actual / 60,
                    (int) config('booking_disaster_recovery.rto_target_minutes', 60),
                ),
            ];
        }

        return $this->resultFromFindings(
            $findings,
            passSummary: 'Measured restore + verification time is within the configured RTO target.',
            warnSummary: 'Measured restore + verification time is missing or outside the configured RTO target.',
            failSummary: 'Measured restore + verification time failed the configured RTO target.',
            evidence: [
                'restore_seconds' => $timings['restore_seconds'] ?? null,
                'post_restore_verification_seconds' => $timings['post_restore_verification_seconds'] ?? null,
                'rto_actual_seconds' => $actual,
                'rto_target_minutes' => (int) config('booking_disaster_recovery.rto_target_minutes', 60),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $manifestReport
     * @param  array<string, mixed>  $timings
     * @return array<string, mixed>
     */
    private function buildRecoveryObjectives(string $mode, array $manifestReport, array $timings): array
    {
        $backupAgeSeconds = $manifestReport['age_seconds'] ?? null;
        $rpoTargetMinutes = (int) config('booking_disaster_recovery.rpo_target_minutes', 1440);
        $rtoTargetMinutes = (int) config('booking_disaster_recovery.rto_target_minutes', 60);
        $rtoActualSeconds = $this->restoreObjectiveSeconds($timings);

        return [
            'rpo_target_minutes' => $rpoTargetMinutes,
            'backup_age_seconds' => $backupAgeSeconds,
            'rpo_status' => ! is_numeric($backupAgeSeconds)
                ? 'not_measured'
                : (((float) $backupAgeSeconds) <= ($rpoTargetMinutes * 60) ? 'pass' : 'warn'),
            'rto_target_minutes' => $rtoTargetMinutes,
            'rto_actual_seconds' => $rtoActualSeconds,
            'rto_status' => $mode !== 'full-isolated-restore'
                ? 'not_measured'
                : ($rtoActualSeconds !== null && $rtoActualSeconds <= ($rtoTargetMinutes * 60) ? 'pass' : 'warn'),
            'backup_capture_seconds' => $timings['backup_capture_seconds'] ?? null,
            'restore_seconds' => $timings['restore_seconds'] ?? null,
            'post_restore_verification_seconds' => $timings['post_restore_verification_seconds'] ?? null,
            'total_seconds' => $timings['total_seconds'] ?? null,
        ];
    }

    /**
     * @param  list<string>  $inputErrors
     * @return array<string, mixed>
     */
    private function inspectManifest(?string $manifestPath, array $inputErrors, Carbon $evaluatedAt): array
    {
        $report = [
            'path' => $manifestPath,
            'manifest' => null,
            'errors' => array_values($inputErrors),
            'artifact_reports' => [],
            'usable_artifacts' => [],
            'checksums' => [
                'path' => null,
                'entries' => [],
                'errors' => [],
            ],
            'generated_at_utc' => null,
            'age_seconds' => null,
            'database' => [],
        ];

        if ($manifestPath === null || trim($manifestPath) === '') {
            $report['errors'][] = 'Unable to resolve a backup manifest path for the drill.';

            return $report;
        }

        try {
            $manifest = BackupRestoreManifest::load($manifestPath);
        } catch (Throwable $exception) {
            $report['errors'][] = $exception->getMessage();

            return $report;
        }

        $report['manifest'] = $manifest;
        $report['database'] = (array) ($manifest['database'] ?? []);
        $report['generated_at_utc'] = $manifest['generated_at_utc'] ?? null;

        if (is_string($report['generated_at_utc']) && trim($report['generated_at_utc']) !== '') {
            try {
                $generatedAt = Carbon::parse((string) $report['generated_at_utc'])->utc();
                $report['age_seconds'] = $generatedAt->greaterThan($evaluatedAt)
                    ? 0
                    : $generatedAt->diffInSeconds($evaluatedAt);
            } catch (Throwable) {
                $report['age_seconds'] = null;
            }
        }

        $manifestDir = dirname($manifestPath);
        foreach (['schema', 'full'] as $artifactName) {
            $artifact = BackupRestoreManifest::artifact($manifest, $artifactName);
            if (! is_array($artifact)) {
                continue;
            }

            $resolved = BackupRestoreManifest::resolveArtifact($artifact, $manifestDir);
            $validation = BackupRestoreManifest::validateResolvedArtifact($resolved);
            $report['artifact_reports'][$artifactName] = [
                'declared' => $artifact,
                'resolved' => $resolved,
                'validation' => $validation,
            ];

            if ($validation['ok'] ?? false) {
                $report['usable_artifacts'][] = $artifactName;
            }
        }

        if ($report['artifact_reports'] === []) {
            $report['errors'][] = 'Backup manifest does not contain a schema or full artifact entry.';
        }

        $report['checksums'] = $this->inspectChecksumsFile($manifestPath, (array) ($report['artifact_reports'] ?? []));

        return $report;
    }

    /**
     * @param  array<string, mixed>  $artifactReports
     * @return array<string, mixed>
     */
    private function inspectChecksumsFile(string $manifestPath, array $artifactReports): array
    {
        $manifestDir = dirname($manifestPath);
        $checksumsPath = $manifestDir.DIRECTORY_SEPARATOR.'checksums.sha256';
        $report = [
            'path' => $checksumsPath,
            'entries' => [],
            'errors' => [],
        ];

        if (! is_file($checksumsPath)) {
            $report['errors'][] = sprintf('Checksum inventory file is missing: %s', $checksumsPath);

            return $report;
        }

        $contents = file($checksumsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($contents === false) {
            $report['errors'][] = sprintf('Unable to read checksum inventory file: %s', $checksumsPath);

            return $report;
        }

        foreach ($contents as $line) {
            if (! preg_match('/^([a-f0-9]{64})\s{2}(.+)$/i', trim($line), $matches)) {
                $report['errors'][] = sprintf('Malformed checksum line: %s', $line);

                continue;
            }

            $report['entries'][$matches[2]] = strtolower($matches[1]);
        }

        $requiredFiles = ['manifest.json'];
        foreach ($artifactReports as $artifactReport) {
            $path = (string) data_get($artifactReport, 'resolved.path', '');
            if ($path !== '') {
                $requiredFiles[] = basename($path);
            }
        }
        $requiredFiles = array_values(array_unique($requiredFiles));

        foreach ($requiredFiles as $basename) {
            if (! isset($report['entries'][$basename])) {
                $report['errors'][] = sprintf('Checksum inventory is missing entry for [%s].', $basename);

                continue;
            }

            $absolutePath = $basename === 'manifest.json'
                ? $manifestPath
                : $manifestDir.DIRECTORY_SEPARATOR.$basename;
            if (! is_file($absolutePath)) {
                $report['errors'][] = sprintf('Checksum inventory references missing file [%s].', $absolutePath);

                continue;
            }

            $actualHash = hash_file('sha256', $absolutePath);
            if (! is_string($actualHash) || ! hash_equals($report['entries'][$basename], strtolower($actualHash))) {
                $report['errors'][] = sprintf('Checksum mismatch for [%s].', $basename);
            }
        }

        return $report;
    }

    /**
     * @param  array<string, string|null>  $targetOverrides
     * @return array<string, mixed>
     */
    private function inspectTargetGuard(
        string $mode,
        array $targetOverrides,
        bool $dropTargetFirst,
        bool $allowNonemptyTarget,
    ): array {
        if (! $this->modeRequiresRestore($mode)) {
            return [
                'ok' => true,
                'errors' => [],
                'warnings' => [],
                'connection' => [],
                'flags' => [],
            ];
        }

        $connection = [
            'host' => trim((string) ($targetOverrides['host'] ?? getenv('RESTORE_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1')),
            'port' => trim((string) ($targetOverrides['port'] ?? getenv('RESTORE_DB_PORT') ?: getenv('DB_PORT') ?: '3306')),
            'username' => trim((string) ($targetOverrides['username'] ?? getenv('RESTORE_DB_USERNAME') ?: getenv('DB_USERNAME') ?: 'root')),
            'password' => (string) ($targetOverrides['password'] ?? getenv('RESTORE_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: ''),
            'database' => trim((string) ($targetOverrides['database'] ?? getenv('RESTORE_DB_DATABASE') ?: '')),
        ];

        $errors = [];
        $warnings = [];

        if ($connection['database'] === '') {
            $errors[] = 'Scratch restore database is required for dry/full DR drill modes.';
        }

        $sourceDatabase = trim((string) (getenv('DB_DATABASE') ?: data_get(config('database.connections.'.config('database.default')), 'database', '')));
        if ($sourceDatabase !== '' && strcasecmp($sourceDatabase, $connection['database']) === 0) {
            $errors[] = sprintf('Scratch restore database [%s] matches the live/default DB_DATABASE.', $connection['database']);
        }

        $safeTokens = array_values(array_filter(array_map('strval', (array) config('booking_disaster_recovery.safe_target_tokens', []))));
        $normalizedDatabase = strtolower($connection['database']);
        $safeName = $normalizedDatabase !== ''
            && collect($safeTokens)->contains(static fn (string $token): bool => str_contains($normalizedDatabase, strtolower($token)));
        if ($connection['database'] !== '' && ! $safeName) {
            $errors[] = sprintf(
                'Scratch restore database [%s] does not look isolated. Use a name containing one of: %s.',
                $connection['database'],
                implode(', ', $safeTokens),
            );
        }

        if (! $dropTargetFirst && ! $allowNonemptyTarget) {
            $warnings[] = 'Restore will refuse a non-empty scratch target unless --drop-target-first or --allow-nonempty-target is supplied.';
        }

        return [
            'ok' => ($errors === []),
            'errors' => $errors,
            'warnings' => $warnings,
            'connection' => $connection,
            'flags' => [
                'drop_target_first' => $dropTargetFirst,
                'allow_nonempty_target' => $allowNonemptyTarget,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function captureFreshBackup(?string $backupRoot): ?array
    {
        $arguments = ['--json'];
        if ($backupRoot !== null && trim($backupRoot) !== '') {
            $arguments[] = '--output-dir='.$this->resolvePath($backupRoot);
        }

        try {
            $process = $this->processRunner->runPhpTool('tools/mysql/backup_release.php', $arguments);
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'errors' => [$exception->getMessage()],
                'process' => null,
            ];
        }

        $payload = $this->decodeJsonOutput((string) ($process['stdout'] ?? ''));
        if ($payload === []) {
            return [
                'ok' => false,
                'errors' => ['backup_release.php did not return valid JSON output.'],
                'process' => $process,
            ];
        }

        if (($process['exit_code'] ?? 1) !== 0 && ($payload['ok'] ?? false)) {
            $payload['ok'] = false;
        }

        $payload['process'] = $process;

        if (! ($payload['ok'] ?? false)) {
            $payload['errors'] = array_values(array_filter(array_merge(
                (array) ($payload['errors'] ?? []),
                isset($payload['error']) ? [(string) $payload['error']] : [],
                (($process['stderr'] ?? '') !== '') ? [(string) $process['stderr']] : [],
            )));
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $backupCapture
     */
    private function resolveManifestPath(?string $manifestPath, ?string $backupDir, ?string $backupRoot, ?array $backupCapture): ?string
    {
        if (is_array($backupCapture) && ($backupCapture['ok'] ?? false) && trim((string) ($backupCapture['manifest_path'] ?? '')) !== '') {
            return $this->resolvePath((string) $backupCapture['manifest_path']);
        }

        if ($manifestPath !== null && trim($manifestPath) !== '') {
            return $this->resolvePath($manifestPath);
        }

        if ($backupDir !== null && trim($backupDir) !== '') {
            return rtrim($this->resolvePath($backupDir), '\\/').DIRECTORY_SEPARATOR.'manifest.json';
        }

        $resolvedBackupRoot = $this->resolvePath($backupRoot ?: (string) config('booking_disaster_recovery.backup_root', 'storage/app/booking_backups'));

        return BackupRestoreManifest::locateLatest($resolvedBackupRoot);
    }

    /**
     * @param  array<string, string>  $targetConnection
     * @return array<string, mixed>
     */
    private function runRestore(
        string $mode,
        string $manifestPath,
        array $targetConnection,
        bool $dropTargetFirst,
        bool $allowNonemptyTarget,
    ): array {
        $arguments = [
            '--manifest='.$manifestPath,
            '--target-host='.$targetConnection['host'],
            '--target-port='.$targetConnection['port'],
            '--target-user='.$targetConnection['username'],
            '--target-password='.$targetConnection['password'],
            '--target-db='.$targetConnection['database'],
            '--json',
        ];

        if ($mode === 'dry-restore') {
            $arguments[] = '--dry-run';
        }

        if ($dropTargetFirst) {
            $arguments[] = '--drop-target-first';
        }

        if ($allowNonemptyTarget) {
            $arguments[] = '--allow-nonempty-target';
        }

        try {
            $process = $this->processRunner->runPhpTool('tools/mysql/restore_release.php', $arguments);
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'errors' => [$exception->getMessage()],
                'warnings' => [],
                'steps' => [],
                'process' => null,
            ];
        }

        $payload = $this->decodeJsonOutput((string) ($process['stdout'] ?? ''));
        if ($payload === []) {
            return [
                'ok' => false,
                'errors' => ['restore_release.php did not return valid JSON output.'],
                'warnings' => [],
                'steps' => [],
                'process' => $process,
            ];
        }

        if (($process['exit_code'] ?? 1) !== 0 && ($payload['ok'] ?? false)) {
            $payload['ok'] = false;
        }

        $payload['process'] = $process;
        $payload['errors'] = array_values(array_filter(array_merge(
            (array) ($payload['errors'] ?? []),
            (($process['stderr'] ?? '') !== '') ? [(string) $process['stderr']] : [],
        )));

        return $payload;
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    private function runArtisanJson(array $arguments): array
    {
        try {
            $process = $this->processRunner->runArtisan($arguments);
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'issues' => [$exception->getMessage()],
                'process' => null,
            ];
        }

        $payload = $this->decodeJsonOutput((string) ($process['stdout'] ?? ''));
        if ($payload === []) {
            return [
                'ok' => false,
                'issues' => ['Command did not return valid JSON output.'],
                'process' => $process,
            ];
        }

        if (($process['exit_code'] ?? 1) !== 0 && ($payload['ok'] ?? false)) {
            $payload['ok'] = false;
        }

        $payload['process'] = $process;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMode(string $mode): array
    {
        $modes = (array) config('booking_disaster_recovery.modes', []);
        if (! isset($modes[$mode]) || ! is_array($modes[$mode])) {
            throw new \InvalidArgumentException(sprintf('Unsupported disaster recovery drill mode [%s].', $mode));
        }

        return $modes[$mode];
    }

    private function modeRequiresRestore(string $mode): bool
    {
        return in_array($mode, ['dry-restore', 'full-isolated-restore'], true);
    }

    /**
     * @param  array<string, mixed>  $manifestReport
     * @param  array<string, mixed>  $targetGuard
     * @param  array<string, mixed>  $restorePayload
     * @return array<string, mixed>
     */
    private function buildLaunchEvidence(
        string $mode,
        Carbon $evaluatedAt,
        array $manifestReport,
        array $targetGuard,
        array $restorePayload,
        string $decision,
    ): array {
        $selectedArtifact = (array) ($restorePayload['selected_artifact'] ?? []);
        $artifactName = trim((string) ($selectedArtifact['name'] ?? ''));
        $artifactPath = trim((string) ($selectedArtifact['path'] ?? ''));
        $manifestPath = trim((string) ($manifestReport['path'] ?? ''));
        $targetDatabase = trim((string) data_get($targetGuard, 'connection.database', ''));
        $verificationCommand = sprintf('php artisan booking:dr-drill --mode=%s --json', $mode);

        if ($manifestPath !== '') {
            $verificationCommand = sprintf(
                'php artisan booking:dr-drill --mode=%s --manifest=%s --json',
                $mode,
                $manifestPath,
            );
        }

        if ($targetDatabase !== '') {
            $verificationCommand .= ' --target-db='.$targetDatabase;
        }

        return [
            'restored_dump_identifier' => $artifactName !== '' || $artifactPath !== ''
                ? trim($artifactName.' '.$artifactPath)
                : $manifestPath,
            'restore_target' => $targetDatabase,
            'verification_command' => $verificationCommand,
            'verification_result' => $decision,
            'timestamp_utc' => $evaluatedAt->toIso8601String(),
            'operator' => trim((string) (getenv('BOOKING_DR_OPERATOR') ?: '')),
            'reviewer' => trim((string) (getenv('BOOKING_DR_REVIEWER') ?: '')),
            'safe_to_commit' => true,
            'secret_fields_included' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function matrixDefinition(string $key): array
    {
        foreach ((array) config('booking_disaster_recovery.matrix', []) as $definition) {
            if (is_array($definition) && (string) ($definition['key'] ?? '') === $key) {
                return $definition;
            }
        }

        return [
            'key' => $key,
            'group' => 'restore_execution_safety',
            'label' => $key,
            'source' => 'unknown',
            'severity' => 'major',
            'pass_criteria' => '',
            'failure_meaning' => '',
            'remediation_hint' => '',
        ];
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
                    'severity' => $severity,
                    'message' => (string) ($finding['message'] ?? ''),
                ];
            }
        }

        return $findings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @return array<int, array<string, mixed>>
     */
    private function buildInformationalFindings(array $checks): array
    {
        $findings = [];

        foreach ($checks as $check) {
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

        return $findings;
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
     * @return array<string, mixed>
     */
    private function writeArtifacts(array $report, string $mode, Carbon $evaluatedAt): array
    {
        return $this->opsGateArtifactService->writeReport(
            artifactRoot: trim((string) config('booking_disaster_recovery.artifact_root', 'storage/app/booking_release/disaster_recovery_drills')),
            reportPrefix: 'dr-drill',
            scopeKey: $mode,
            payload: $report,
            markdown: $this->renderMarkdown($report),
            evaluatedAt: $evaluatedAt,
        );
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderMarkdown(array $report): string
    {
        $lines = [];
        $lines[] = '# Booking Disaster Recovery Drill';
        $lines[] = '';
        $lines[] = sprintf('- Evaluated at: `%s`', (string) (($report['meta'] ?? [])['evaluated_at_utc'] ?? ''));
        $lines[] = sprintf('- Mode: `%s`', (string) (($report['mode'] ?? [])['label'] ?? ''));
        $lines[] = sprintf('- Evidence level: `%s`', (string) (($report['mode'] ?? [])['evidence_level'] ?? 'unknown'));
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
        $lines[] = '## Checks';
        $lines[] = '';
        $lines[] = '| Group | Check | Source | Status | Summary |';
        $lines[] = '| --- | --- | --- | --- | --- |';

        foreach ((array) ($report['checks'] ?? []) as $check) {
            $lines[] = sprintf(
                '| %s | %s | `%s` | %s | %s |',
                $this->groupLabel((string) ($check['group'] ?? '')),
                str_replace('|', '\|', (string) ($check['label'] ?? $check['key'] ?? '')),
                (string) ($check['source'] ?? ''),
                strtoupper((string) ($check['status'] ?? 'unknown')),
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
                $lines[] = sprintf('- [%s] %s', (string) ($finding['check_label'] ?? $finding['check_key'] ?? ''), (string) ($finding['message'] ?? ''));
            }
        }

        $lines[] = '';
        $lines[] = '## Major Warnings';
        $lines[] = '';
        if ((array) ($report['major_warnings'] ?? []) === []) {
            $lines[] = '- None.';
        } else {
            foreach ((array) ($report['major_warnings'] ?? []) as $finding) {
                $lines[] = sprintf('- [%s] %s', (string) ($finding['check_label'] ?? $finding['check_key'] ?? ''), (string) ($finding['message'] ?? ''));
            }
        }

        $objectives = (array) ($report['recovery_objectives'] ?? []);
        $lines[] = '';
        $lines[] = '## Recovery Objectives';
        $lines[] = '';
        $lines[] = sprintf('- RPO target: `%d` minute(s)', (int) ($objectives['rpo_target_minutes'] ?? 0));
        $lines[] = sprintf('- Backup age: `%s` second(s) [%s]', $objectives['backup_age_seconds'] ?? 'n/a', strtoupper((string) ($objectives['rpo_status'] ?? 'unknown')));
        $lines[] = sprintf('- RTO target: `%d` minute(s)', (int) ($objectives['rto_target_minutes'] ?? 0));
        $lines[] = sprintf('- Restore + verify: `%s` second(s) [%s]', $objectives['rto_actual_seconds'] ?? 'n/a', strtoupper((string) ($objectives['rto_status'] ?? 'unknown')));

        $launchEvidence = (array) ($report['launch_evidence'] ?? []);
        $lines[] = '';
        $lines[] = '## Launch Evidence Fields';
        $lines[] = '';
        $lines[] = sprintf('- Restored dump identifier: `%s`', (string) ($launchEvidence['restored_dump_identifier'] ?? ''));
        $lines[] = sprintf('- Restore target: `%s`', (string) ($launchEvidence['restore_target'] ?? ''));
        $lines[] = sprintf('- Verification command: `%s`', (string) ($launchEvidence['verification_command'] ?? ''));
        $lines[] = sprintf('- Verification result: `%s`', (string) ($launchEvidence['verification_result'] ?? ''));
        $lines[] = sprintf('- Timestamp: `%s`', (string) ($launchEvidence['timestamp_utc'] ?? ''));

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function restoreObjectiveSeconds(array $timings): ?float
    {
        if (! is_numeric($timings['restore_seconds'] ?? null) || ! is_numeric($timings['post_restore_verification_seconds'] ?? null)) {
            return null;
        }

        return round(((float) $timings['restore_seconds']) + ((float) $timings['post_restore_verification_seconds']), 3);
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\\\')) {
            return $path;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonOutput(string $output): array
    {
        $output = trim($output);
        if ($output === '') {
            return [];
        }

        try {
            $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $restore
     * @return array<string, mixed>
     */
    private function restoreStep(array $restore, string $stepName): array
    {
        $steps = (array) ($restore['steps'] ?? []);
        $step = $steps[$stepName] ?? [];

        return is_array($step) ? $step : [];
    }

    /**
     * @param  array<string, string>  $connection
     * @return array<string, string>
     */
    private function maskConnection(array $connection): array
    {
        if ($connection === []) {
            return [];
        }

        $masked = $connection;
        if (array_key_exists('password', $masked)) {
            $masked['password'] = $masked['password'] !== '' ? '***' : '';
        }

        return $masked;
    }

    private function groupLabel(string $groupKey): string
    {
        return (string) (((array) config('booking_disaster_recovery.groups', []))[$groupKey] ?? $groupKey);
    }
}
