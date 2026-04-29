<?php

namespace App\Platform\Release\Services;

use App\Enums\DepositStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Modules\InventoryProcurement\Application\Workflows\InventoryStockReconciliationService;
use App\Modules\InventoryProcurement\Application\Workflows\PurchaseOrderReconciliationService;
use App\Platform\Health\Services\BookingEnvironmentValidator;
use App\Platform\Metrics\Services\OperationalInsightsService;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BookingDeploySafetyService
{
    public function __construct(
        private readonly BookingEnvironmentValidator $environmentValidator,
        private readonly OperationalInsightsService $operationalInsightsService,
        private readonly PurchaseOrderReconciliationService $purchaseOrderReconciliationService,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   mode: string,
     *   errors: list<string>,
     *   warnings: list<string>,
     *   checks: array<string, array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}>,
     *   summary: array<string, mixed>
     * }
     */
    public function inspect(string $mode = 'preflight'): array
    {
        $mode = in_array($mode, ['preflight', 'postflight'], true) ? $mode : 'preflight';
        $checks = [];

        try {
            $environment = $this->environmentValidator->validate();
        } catch (Throwable $exception) {
            $environment = [
                'ok' => false,
                'errors' => [$this->inspectionFailureMessage('Environment validation', $exception)],
                'warnings' => [],
            ];
        }
        $this->addCheck($checks, 'environment', $this->summarizeEnvironmentValidation($environment));

        $databaseRuntime = $this->inspectDatabaseRuntime();
        $this->addCheck($checks, 'runtime.database', $databaseRuntime);

        $migrationStatus = ($databaseRuntime['ok'] ?? false)
            ? $this->inspectMigrations($mode)
            : $this->skippedDatabaseMigrationStatus((string) ($databaseRuntime['message'] ?? 'Database runtime is unavailable.'));
        $this->addCheck($checks, 'migrations.repository', $migrationStatus['repository']);
        $this->addCheck($checks, 'migrations.files', $migrationStatus['files']);
        $this->addCheck($checks, 'migrations.pending', $migrationStatus['pending']);

        $dataGuards = ($databaseRuntime['ok'] ?? false)
            ? $this->safeGuardSet(
                resolver: fn () => $this->inspectDataGuards(),
                failureLabel: 'Data guard inspection',
            )
            : $this->skippedDatabaseGuardSet('Data guard inspection', (string) ($databaseRuntime['message'] ?? 'Database runtime is unavailable.'));
        foreach ($dataGuards as $name => $check) {
            $this->addCheck($checks, 'data.'.$name, $check);
        }

        foreach ($this->safeGuardSet(
            resolver: fn () => $this->inspectArtifactGuards(),
            failureLabel: 'Artifact guard inspection',
        ) as $name => $check) {
            $this->addCheck($checks, 'artifacts.'.$name, $check);
        }

        $operationalGuards = ($databaseRuntime['ok'] ?? false)
            ? $this->safeGuardSet(
                resolver: fn () => $this->inspectOperationalGuards(),
                failureLabel: 'Operational guard inspection',
            )
            : $this->skippedDatabaseGuardSet('Operational guard inspection', (string) ($databaseRuntime['message'] ?? 'Database runtime is unavailable.'));
        foreach ($operationalGuards as $name => $check) {
            $this->addCheck($checks, 'ops.'.$name, $check);
        }

        $errors = [];
        $warnings = [];

        foreach ($checks as $name => $check) {
            if (! ($check['ok'] ?? false)) {
                $line = sprintf('%s: %s', $name, (string) ($check['message'] ?? ''));
                if (($check['severity'] ?? 'error') === 'warning') {
                    $warnings[] = $line;
                } else {
                    $errors[] = $line;
                }
            }
        }

        return [
            'ok' => empty($errors),
            'mode' => $mode,
            'errors' => $errors,
            'warnings' => $warnings,
            'checks' => $checks,
            'summary' => [
                'environment_error_count' => count($environment['errors'] ?? []),
                'environment_warning_count' => count($environment['warnings'] ?? []),
                'runtime_error_count' => collect($checks)
                    ->filter(fn (array $check, string $name) => str_starts_with($name, 'runtime.') && ! ($check['ok'] ?? false) && ($check['severity'] ?? 'error') !== 'warning')
                    ->count(),
                'runtime_warning_count' => collect($checks)
                    ->filter(fn (array $check, string $name) => str_starts_with($name, 'runtime.') && ! ($check['ok'] ?? false) && ($check['severity'] ?? 'error') === 'warning')
                    ->count(),
                'pending_migration_count' => (int) ($migrationStatus['pending']['meta']['pending_count'] ?? 0),
                'data_guard_error_count' => collect($checks)
                    ->filter(fn (array $check, string $name) => str_starts_with($name, 'data.') && ! ($check['ok'] ?? false) && ($check['severity'] ?? 'error') !== 'warning')
                    ->count(),
                'data_guard_warning_count' => collect($checks)
                    ->filter(fn (array $check, string $name) => str_starts_with($name, 'data.') && ! ($check['ok'] ?? false) && ($check['severity'] ?? 'error') === 'warning')
                    ->count(),
                'artifact_error_count' => collect($checks)
                    ->filter(fn (array $check, string $name) => str_starts_with($name, 'artifacts.') && ! ($check['ok'] ?? false) && ($check['severity'] ?? 'error') !== 'warning')
                    ->count(),
                'artifact_warning_count' => collect($checks)
                    ->filter(fn (array $check, string $name) => str_starts_with($name, 'artifacts.') && ! ($check['ok'] ?? false) && ($check['severity'] ?? 'error') === 'warning')
                    ->count(),
                'ops_error_count' => collect($checks)
                    ->filter(fn (array $check, string $name) => str_starts_with($name, 'ops.') && ! ($check['ok'] ?? false) && ($check['severity'] ?? 'error') !== 'warning')
                    ->count(),
                'ops_warning_count' => collect($checks)
                    ->filter(fn (array $check, string $name) => str_starts_with($name, 'ops.') && ! ($check['ok'] ?? false) && ($check['severity'] ?? 'error') === 'warning')
                    ->count(),
            ],
        ];
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    protected function inspectDatabaseRuntime(): array
    {
        try {
            DB::selectOne('SELECT 1');
        } catch (Throwable $exception) {
            return $this->error('Database runtime is unavailable; skipped database-dependent deploy guards.', [
                'connection' => (string) config('database.default'),
                'exception_class' => $exception::class,
                'error' => trim($exception->getMessage()),
            ]);
        }

        return $this->ok('Database runtime is reachable for deploy guard inspection.', [
            'connection' => (string) config('database.default'),
        ]);
    }

    /**
     * @return array{
     *   repository: array{ok: bool, severity: string, message: string, meta?: array<string, mixed>},
     *   files: array{ok: bool, severity: string, message: string, meta?: array<string, mixed>},
     *   pending: array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     * }
     */
    private function skippedDatabaseMigrationStatus(string $reason): array
    {
        return [
            'repository' => $this->warning('Skipped migration repository inspection because database runtime is unavailable.', [
                'runtime_reason' => $reason,
            ]),
            'files' => $this->warning('Skipped migration file inspection because database runtime is unavailable.', [
                'runtime_reason' => $reason,
            ]),
            'pending' => $this->warning('Skipped pending migration inspection because database runtime is unavailable.', [
                'pending_count' => 0,
                'pending_migrations' => [],
                'runtime_reason' => $reason,
            ]),
        ];
    }

    /**
     * @return array<string, array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}>
     */
    private function skippedDatabaseGuardSet(string $label, string $reason): array
    {
        return [
            'runtime' => $this->warning(sprintf('Skipped %s because database runtime is unavailable.', $label), [
                'runtime_reason' => $reason,
            ]),
        ];
    }

    /**
     * @param  callable(): array<string, array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}>  $resolver
     * @return array<string, array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}>
     */
    private function safeGuardSet(callable $resolver, string $failureLabel): array
    {
        try {
            return $resolver();
        } catch (Throwable $exception) {
            return [
                'runtime' => $this->error(
                    $this->inspectionFailureMessage($failureLabel, $exception),
                    [
                        'exception_class' => $exception::class,
                    ],
                ),
            ];
        }
    }

    private function inspectionFailureMessage(string $label, Throwable $exception): string
    {
        return sprintf('%s failed: %s', $label, trim($exception->getMessage()));
    }

    /**
     * @param  array<string, array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}>  $checks
     * @param  array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}  $result
     */
    private function addCheck(array &$checks, string $name, array $result): void
    {
        $checks[$name] = $result;
    }

    /**
     * @param  array{ok: bool, errors: list<string>, warnings: list<string>}  $validation
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function summarizeEnvironmentValidation(array $validation): array
    {
        $errorCount = count($validation['errors'] ?? []);
        $warningCount = count($validation['warnings'] ?? []);

        if ($errorCount > 0) {
            return $this->error(
                sprintf('Environment validation failed with %d error(s) and %d warning(s).', $errorCount, $warningCount),
                [
                    'error_count' => $errorCount,
                    'warning_count' => $warningCount,
                ]
            );
        }

        if ($warningCount > 0) {
            return $this->warning(
                sprintf('Environment validation passed with %d warning(s).', $warningCount),
                [
                    'error_count' => 0,
                    'warning_count' => $warningCount,
                ]
            );
        }

        return $this->ok('Environment validation passed with no errors or warnings.');
    }

    /**
     * @return array{
     *   repository: array{ok: bool, severity: string, message: string, meta?: array<string, mixed>},
     *   files: array{ok: bool, severity: string, message: string, meta?: array<string, mixed>},
     *   pending: array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     * }
     */
    private function inspectMigrations(string $mode): array
    {
        try {
            /** @var DatabaseMigrationRepository $repository */
            $repository = app('migration.repository');
            if (! $repository->repositoryExists()) {
                return [
                    'repository' => $this->error('Migration repository table is missing. Run migrations bootstrap before deploy.'),
                    'files' => $this->warning('Skipped migration file inspection because migration repository is missing.'),
                    'pending' => $this->warning('Skipped pending migration inspection because migration repository is missing.'),
                ];
            }

            /** @var Migrator $migrator */
            $migrator = app('migrator');
            $paths = array_values(array_unique(array_merge([
                database_path('migrations'),
            ], $migrator->paths())));
            $files = $migrator->getMigrationFiles($paths);
            $fileNames = array_keys($files);
            $ran = $repository->getRan();
            $pending = array_values(array_diff($fileNames, $ran));
            $missingApplied = array_values(array_diff($ran, $fileNames));
            $schemaDumpPath = database_path('schema/mysql-schema.sql');
            $sqlPatchFiles = File::glob(database_path('patches/*.sql')) ?: [];
            $canonicalSqlRelease = count($fileNames) === 0 && File::exists($schemaDumpPath);

            if ($canonicalSqlRelease) {
                return [
                    'repository' => $this->ok('Migration repository is present and readable.'),
                    'files' => $this->ok('Release uses canonical MySQL schema dump + SQL patch artifacts instead of PHP migration files.', [
                        'schema_dump_path' => $schemaDumpPath,
                        'sql_patch_count' => count($sqlPatchFiles),
                        'applied_count' => count($ran),
                    ]),
                    'pending' => $this->ok('No PHP migration files detected; use schema dump + SQL patch bootstrap for release provisioning.', [
                        'pending_count' => 0,
                        'pending_migrations' => [],
                        'sql_patch_count' => count($sqlPatchFiles),
                    ]),
                ];
            }

            $filesCheck = empty($missingApplied)
                ? $this->ok('Applied migrations map cleanly to files in the current release.', [
                    'migration_file_count' => count($fileNames),
                    'applied_count' => count($ran),
                ])
                : $this->warning('Some applied migrations are not present in the current release artifact.', [
                    'missing_applied_migrations' => array_values($missingApplied),
                    'migration_file_count' => count($fileNames),
                    'applied_count' => count($ran),
                ]);

            $pendingCheck = match (true) {
                $mode === 'postflight' && count($pending) > 0 => $this->error(
                    sprintf('Postflight check found %d pending migration(s).', count($pending)),
                    [
                        'pending_count' => count($pending),
                        'pending_migrations' => array_values($pending),
                    ]
                ),
                count($pending) > 0 => $this->ok(
                    sprintf('Preflight found %d pending migration(s) in this release.', count($pending)),
                    [
                        'pending_count' => count($pending),
                        'pending_migrations' => array_values($pending),
                    ]
                ),
                default => $this->ok('No pending migrations detected for this release.', [
                    'pending_count' => 0,
                    'pending_migrations' => [],
                ]),
            };

            return [
                'repository' => $this->ok('Migration repository is present and readable.'),
                'files' => $filesCheck,
                'pending' => $pendingCheck,
            ];
        } catch (Throwable $e) {
            return [
                'repository' => $this->error('Migration inspection failed: '.$e->getMessage()),
                'files' => $this->warning('Skipped migration file inspection because migration inspection failed.'),
                'pending' => $this->warning('Skipped pending migration inspection because migration inspection failed.'),
            ];
        }
    }

    /**
     * @return array<string, array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}>
     */
    protected function inspectOperationalGuards(): array
    {
        $opsSnapshot = $this->operationalInsightsService->snapshot(Carbon::now('UTC'));
        $mysqlRuntime = (array) ($opsSnapshot['mysql_runtime'] ?? []);
        $redisRuntime = (array) ($opsSnapshot['redis_runtime'] ?? []);
        $schedulerHeartbeat = (array) ($opsSnapshot['scheduler_heartbeat'] ?? []);
        $notificationOutbox = (array) ($opsSnapshot['notification_outbox'] ?? []);
        $staffKeys = (array) ($opsSnapshot['staff_api_keys'] ?? []);
        $tableAudit = (array) ($opsSnapshot['table_state_audit'] ?? []);
        $rowVersionContract = (array) ($opsSnapshot['row_version_contract'] ?? []);
        $reportingSnapshots = (array) ($opsSnapshot['reporting_snapshots'] ?? []);
        $kitchenKds = (array) ($opsSnapshot['kitchen_kds'] ?? []);
        $inventoryPurchasing = (array) ($opsSnapshot['inventory_purchasing'] ?? []);
        $conversationInbox = (array) ($opsSnapshot['conversation_inbox'] ?? []);
        $branchDefaults = (array) ($opsSnapshot['branch_defaults'] ?? []);

        return [
            'mysql_runtime' => $this->fromOperationalSnapshot(
                $mysqlRuntime,
                okMessage: 'MySQL runtime probe is healthy.',
                degradedMessage: 'MySQL runtime probe is degraded and should be reviewed before rollout.',
                failMessage: 'MySQL runtime probe failed and deploy data guards cannot be trusted.'
            ),
            'redis_runtime' => $this->fromOperationalSnapshot(
                $redisRuntime,
                okMessage: 'Redis runtime probe is healthy.',
                degradedMessage: 'Redis runtime probe is degraded and should be reviewed before rollout.',
                failMessage: 'Redis runtime probe failed; distributed locks, idempotency, and scheduler heartbeat storage are unsafe.'
            ),
            'scheduler_heartbeat' => $this->fromOperationalSnapshot(
                $schedulerHeartbeat,
                okMessage: 'Scheduler heartbeat is fresh.',
                degradedMessage: 'Scheduler heartbeat is degraded and should be reviewed before rollout.',
                failMessage: 'Scheduler heartbeat is missing or stale; scheduled jobs are not proven live.'
            ),
            'notification_outbox' => $this->fromOperationalSnapshot(
                $notificationOutbox,
                okMessage: 'Notification outbox health looks clean.',
                degradedMessage: 'Notification outbox backlog or failure state needs operator review before rollout.',
                failMessage: 'Notification outbox health is failing and may hide customer/staff notification loss.'
            ),
            'staff_api_keys' => $this->fromOperationalSnapshot(
                $staffKeys,
                okMessage: 'Staff API key health looks clean.',
                degradedMessage: 'Staff API key health has warnings that should be reviewed.',
                failMessage: 'Staff API key health indicates a release-risk state.'
            ),
            'table_state_audit' => $this->fromOperationalSnapshot(
                $tableAudit,
                okMessage: 'Table-state audit coverage looks healthy.',
                degradedMessage: 'Table-state audit coverage has warnings that should be reviewed.',
                failMessage: 'Table-state audit coverage indicates a release-risk state.'
            ),
            'row_version_contract' => $this->fromOperationalSnapshot(
                $rowVersionContract,
                okMessage: 'Staff mutation row_version contract looks complete.',
                degradedMessage: 'Staff mutation row_version contract has warnings that should be reviewed.',
                failMessage: 'Staff mutation row_version contract is incomplete and risks stale writes.'
            ),
            'reporting_snapshots' => $this->fromOperationalSnapshot(
                $reportingSnapshots,
                okMessage: 'Reporting snapshot coverage looks healthy.',
                degradedMessage: 'Reporting snapshot coverage needs review before relying on staff/admin reporting surfaces.',
                failMessage: 'Reporting snapshot coverage is broken and risks live reporting APIs.'
            ),
            'kitchen_kds' => $this->fromOperationalSnapshot(
                $kitchenKds,
                okMessage: 'Kitchen/KDS reconciliation looks healthy.',
                degradedMessage: 'Kitchen/KDS backlog needs operator review before relying on board freshness.',
                failMessage: 'Kitchen/KDS drift is present and risks ticket/order-item correctness.'
            ),
            'inventory_purchasing' => $this->fromOperationalSnapshot(
                $inventoryPurchasing,
                okMessage: 'Inventory and purchasing reconciliation looks healthy.',
                degradedMessage: 'Inventory and purchasing backlog needs operator review before rollout.',
                failMessage: 'Inventory or purchasing lineage drift is present and risks stock correctness.'
            ),
            'conversation_inbox' => $this->fromOperationalSnapshot(
                $conversationInbox,
                okMessage: 'Conversation inbox workflow health looks clean.',
                degradedMessage: 'Conversation inbox backlog needs operator review before rollout.',
                failMessage: 'Conversation inbox assignment drift is present and risks operational triage.'
            ),
            'branch_defaults' => $this->fromOperationalSnapshot(
                $branchDefaults,
                okMessage: 'Branch default-state coverage looks healthy.',
                degradedMessage: 'Branch default-state coverage has warnings that should be reviewed.',
                failMessage: 'Branch default-state coverage is broken and risks multi-branch runtime behavior.'
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function fromOperationalSnapshot(array $snapshot, string $okMessage, string $degradedMessage, string $failMessage): array
    {
        $status = (string) ($snapshot['status'] ?? 'ok');
        $reasons = array_values(array_map('strval', (array) ($snapshot['reasons'] ?? [])));
        $meta = $snapshot;
        unset($meta['status'], $meta['reasons']);
        if ($reasons !== []) {
            $meta['reasons'] = $reasons;
        }

        return match ($status) {
            'fail' => $this->error($failMessage, $meta),
            'degraded' => $this->warning($degradedMessage, $meta),
            default => $this->ok($okMessage, $meta),
        };
    }

    /**
     * @return array<string, array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}>
     */
    protected function inspectDataGuards(): array
    {
        return [
            'deposit_status' => $this->guardDepositStatus(),
            'reservation_order_item_totals' => $this->guardReservationOrderItemTotals(),
            'payment_refund_lineage' => $this->guardPaymentRefundLineage(),
            'payment_refund_trigger_compatibility' => $this->guardPaymentRefundTriggerCompatibility(),
            'purchase_receipt_lineage_uniqueness' => $this->guardPurchaseReceiptLineageUniqueness(),
            'inventory_stock_on_hand_reconciliation' => $this->guardInventoryStockOnHandReconciliation(),
            'reservation_lifecycle' => $this->guardReservationLifecycle(),
            'user_voucher_state' => $this->guardUserVoucherState(),
            'bank_account_defaults' => $this->guardBankAccountDefaults(),
            'active_agent_assignments' => $this->guardActiveAgentAssignments(),
            'session_hold_linkage' => $this->guardSessionHoldLinkage(),
        ];
    }

    protected function inspectArtifactGuards(): array
    {
        return [
            'schema_dump_definers' => $this->guardSchemaDumpDefiners(),
            'full_dump_definers' => $this->guardFullDumpDefiners(),
            'schema_dump_contract' => $this->guardSchemaDumpContract(),
            'full_dump_contract' => $this->guardFullDumpContract(),
            'patch_inventory' => $this->guardPatchInventory(),
            'release_manifest' => $this->guardReleaseManifest(),
            'temporary_files' => $this->guardTemporaryFiles(),
        ];
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardSchemaDumpDefiners(): array
    {
        return $this->guardSqlArtifactDefiners(
            database_path('schema/mysql-schema.sql'),
            'Schema dump',
            'database/schema/mysql-schema.sql',
            false,
        );
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardFullDumpDefiners(): array
    {
        return $this->guardSqlArtifactDefiners(
            base_path('db_all.sql'),
            'Full database dump',
            'db_all.sql',
            true,
        );
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardSqlArtifactDefiners(string $artifactPath, string $label, string $displayPath, bool $optional): array
    {
        if (! File::exists($artifactPath)) {
            return $optional
                ? $this->ok(sprintf('Skipped %s DEFINER guard because %s is not present in the release root.', strtolower($label), $displayPath), [
                    'artifact_path' => $artifactPath,
                    'definer_count' => 0,
                    'skipped' => true,
                ])
                : $this->warning(sprintf('Skipped %s DEFINER guard because %s is missing.', strtolower($label), $displayPath));
        }

        $contents = File::get($artifactPath);
        $matches = [];
        preg_match_all('/DEFINER=`[^`]+`@`[^`]+`/', $contents, $matches);
        $count = count($matches[0] ?? []);

        if ($count > 0) {
            return $this->error(sprintf('%s still contains %d DEFINER clause(s).', $label, $count), [
                'definer_count' => $count,
                'artifact_path' => $artifactPath,
            ]);
        }

        return $this->ok(sprintf('%s does not contain environment-specific DEFINER clauses.', $label), [
            'artifact_path' => $artifactPath,
            'definer_count' => 0,
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardSchemaDumpContract(): array
    {
        return $this->guardConfiguredArtifactContract('schema_dump', 'Schema dump');
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardFullDumpContract(): array
    {
        return $this->guardConfiguredArtifactContract('full_dump', 'Full database dump');
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardPatchInventory(): array
    {
        $snapshot = app(ReleaseArtifactManifestService::class)->snapshot();
        $missing = (array) ($snapshot['patches']['missing'] ?? []);
        $presentCount = (int) ($snapshot['patches']['count'] ?? 0);
        $requiredCount = (int) ($snapshot['patches']['required_count'] ?? 0);

        if ($missing !== []) {
            return $this->error(sprintf('Release patch inventory is missing %d required SQL patch artifact(s).', count($missing)), [
                'missing_patches' => $missing,
                'present_count' => $presentCount,
                'required_count' => $requiredCount,
            ]);
        }

        return $this->ok('Release patch inventory contains the required SQL hardening artifacts.', [
            'present_count' => $presentCount,
            'required_count' => $requiredCount,
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardReleaseManifest(): array
    {
        $snapshot = app(ReleaseArtifactManifestService::class)->snapshot();
        $status = (string) ($snapshot['status'] ?? 'fail');
        $issues = (array) ($snapshot['issues'] ?? []);

        return match ($status) {
            'ok' => $this->ok('Release artifact manifest snapshot is healthy.', [
                'artifact_keys' => array_keys((array) ($snapshot['artifacts'] ?? [])),
                'issue_count' => count($issues),
            ]),
            'warning' => $this->warning('Release artifact manifest completed with warning(s).', [
                'issues' => $issues,
                'artifact_keys' => array_keys((array) ($snapshot['artifacts'] ?? [])),
            ]),
            default => $this->error('Release artifact manifest detected broken or missing release artifacts.', [
                'issues' => $issues,
                'artifact_keys' => array_keys((array) ($snapshot['artifacts'] ?? [])),
            ]),
        };
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardConfiguredArtifactContract(string $artifactKey, string $label): array
    {
        $definition = (array) config(sprintf('booking_release.artifacts.%s', $artifactKey), []);
        $relativePath = trim((string) ($definition['path'] ?? ''));
        $optional = (bool) ($definition['optional'] ?? false);
        $requiredFragments = array_values(array_filter(
            array_map(static fn ($fragment) => is_scalar($fragment) ? trim((string) $fragment) : '', (array) ($definition['required_fragments'] ?? [])),
            static fn (string $fragment): bool => $fragment !== ''
        ));

        if ($relativePath === '') {
            return $this->warning(sprintf('Skipped %s contract guard because no artifact path is configured.', strtolower($label)));
        }

        $artifactPath = base_path($relativePath);
        if (! File::exists($artifactPath)) {
            return $optional
                ? $this->ok(sprintf('Skipped %s contract guard because %s is not present in the release root.', strtolower($label), $relativePath), [
                    'artifact_path' => $artifactPath,
                    'skipped' => true,
                    'required_fragment_count' => count($requiredFragments),
                ])
                : $this->warning(sprintf('Skipped %s contract guard because %s is missing.', strtolower($label), $relativePath));
        }

        $contents = File::get($artifactPath);
        $missing = [];
        foreach ($requiredFragments as $fragment) {
            if (! str_contains($contents, $fragment)) {
                $missing[] = $fragment;
            }
        }

        if ($missing !== []) {
            return $this->error(sprintf('%s is missing %d required contract fragment(s).', $label, count($missing)), [
                'artifact_path' => $artifactPath,
                'missing_fragments' => $missing,
                'required_fragment_count' => count($requiredFragments),
            ]);
        }

        return $this->ok(sprintf('%s contains the required hardening contract fragments.', $label), [
            'artifact_path' => $artifactPath,
            'required_fragment_count' => count($requiredFragments),
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardTemporaryFiles(): array
    {
        $scanRoots = [
            app_path(),
            base_path('bootstrap/cache'),
        ];

        $candidates = collect($scanRoots)
            ->filter(static fn (string $path) => File::exists($path))
            ->flatMap(function (string $path) {
                return collect(File::allFiles($path))->map(fn ($file) => $this->normalizeReleaseRelativePath($file->getPathname()));
            })
            ->filter(static fn (string $path) => preg_match('/(\.tmp|\.bak|\.orig|~)$/', $path) === 1)
            ->values()
            ->all();

        $ignoredMirroredFiles = [];
        $materialFiles = [];

        foreach ($candidates as $candidate) {
            if ($this->isIgnoredMirroredBootstrapCacheTempFile($candidate)) {
                $ignoredMirroredFiles[] = $candidate;

                continue;
            }

            $materialFiles[] = $candidate;
        }

        if ($materialFiles !== []) {
            $meta = ['files' => $materialFiles];
            if ($ignoredMirroredFiles !== []) {
                $meta['ignored_mirrored_files'] = $ignoredMirroredFiles;
            }

            return $this->warning(sprintf('Found %d temporary artifact file(s) under release roots.', count($materialFiles)), $meta);
        }

        if ($ignoredMirroredFiles !== []) {
            return $this->ok(sprintf('Ignored %d mirrored bootstrap cache temp file(s).', count($ignoredMirroredFiles)), [
                'ignored_mirrored_files' => $ignoredMirroredFiles,
            ]);
        }

        return $this->ok('No temporary artifact files were found under the release roots.');
    }

    private function normalizeReleaseRelativePath(string $path): string
    {
        $normalizedPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        $basePathPrefix = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, base_path()), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (str_starts_with($normalizedPath, $basePathPrefix)) {
            $normalizedPath = substr($normalizedPath, strlen($basePathPrefix));
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', ltrim($normalizedPath, DIRECTORY_SEPARATOR));
    }

    private function isIgnoredMirroredBootstrapCacheTempFile(string $relativePath): bool
    {
        if (! str_starts_with($relativePath, 'bootstrap/cache/')) {
            return false;
        }

        $fullPath = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if (! File::exists($fullPath)) {
            return false;
        }

        $directory = dirname($fullPath);
        if (! is_dir($directory)) {
            return false;
        }

        $candidateSize = @filesize($fullPath);
        $candidateHash = @hash_file('sha256', $fullPath);
        if ($candidateSize === false || $candidateHash === false) {
            return false;
        }

        foreach (File::files($directory) as $sibling) {
            $siblingPath = $sibling->getPathname();
            if ($siblingPath === $fullPath) {
                continue;
            }

            $siblingRelativePath = $this->normalizeReleaseRelativePath($siblingPath);
            if (! str_starts_with($siblingRelativePath, 'bootstrap/cache/')) {
                continue;
            }

            if (preg_match('/(\.tmp|\.bak|\.orig|~)$/', $siblingRelativePath) === 1) {
                continue;
            }

            $siblingSize = @filesize($siblingPath);
            if ($siblingSize === false || $siblingSize !== $candidateSize) {
                continue;
            }

            $siblingHash = @hash_file('sha256', $siblingPath);
            if ($siblingHash !== false && hash_equals($candidateHash, $siblingHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardDepositStatus(): array
    {
        if (! Schema::hasTable('reservations') || ! Schema::hasColumn('reservations', 'deposit_status')) {
            return $this->warning('Skipped deposit_status guard because reservations.deposit_status is not available in this schema.');
        }

        $allowed = array_map(static fn (DepositStatus $status) => $status->value, DepositStatus::cases());
        $invalidCount = DB::table('reservations')
            ->whereNotNull('deposit_status')
            ->whereNotIn('deposit_status', $allowed)
            ->count();

        if ($invalidCount > 0) {
            return $this->error(sprintf('Found %d reservation row(s) with invalid deposit_status.', $invalidCount), [
                'allowed_values' => $allowed,
                'invalid_count' => $invalidCount,
            ]);
        }

        return $this->ok('Reservation deposit_status values are ready for fail-fast migrations.', [
            'allowed_values' => $allowed,
            'invalid_count' => 0,
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardReservationOrderItemTotals(): array
    {
        if (! Schema::hasTable('reservation_order_items')) {
            return $this->warning('Skipped reservation_order_items total guard because the table is not available in this schema.');
        }

        $negativeCount = DB::table('reservation_order_items')
            ->where(function ($query) {
                $query->where('unit_price', '<', 0)
                    ->orWhere('line_total', '<', 0)
                    ->orWhere('quantity', '<=', 0);
            })
            ->count();

        $mismatchCount = DB::table('reservation_order_items')
            ->whereRaw('ROUND(unit_price * quantity, 2) <> line_total')
            ->count();

        if ($negativeCount > 0 || $mismatchCount > 0) {
            return $this->error('reservation_order_items contains rows that will fail financial integrity checks.', [
                'negative_or_zero_count' => $negativeCount,
                'line_total_mismatch_count' => $mismatchCount,
            ]);
        }

        return $this->ok('reservation_order_items totals look migration-safe.', [
            'negative_or_zero_count' => 0,
            'line_total_mismatch_count' => 0,
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardPaymentRefundLineage(): array
    {
        if (! Schema::hasTable('payments')) {
            return $this->warning('Skipped payment refund lineage guard because the payments table is not available in this schema.');
        }

        $missingColumns = array_values(array_filter(
            ['payment_id', 'payment_type', 'status', 'refund_of_payment_id'],
            static fn (string $column): bool => ! Schema::hasColumn('payments', $column),
        ));

        if ($missingColumns !== []) {
            return $this->warning('Skipped payment refund lineage guard because payments is missing required columns.', [
                'guard_label' => 'payment_refund_lineage',
                'missing_columns' => $missingColumns,
            ]);
        }

        $validPaymentTypes = ['Deposit', 'Final', 'Refund'];
        $refundableSourceStatuses = [PaymentStatus::Success->value, PaymentStatus::Partial->value];
        $validStatuses = array_map(static fn (PaymentStatus $status): string => $status->value, PaymentStatus::cases());
        $hasAmount = Schema::hasColumn('payments', 'amount');
        $hasCurrency = Schema::hasColumn('payments', 'currency');
        $hasReservation = Schema::hasColumn('payments', 'reservation_id');
        $hasBranch = Schema::hasColumn('payments', 'branch_id');
        $hasIdempotencyKey = Schema::hasColumn('payments', 'idempotency_key');
        $hasTransactionCode = Schema::hasColumn('payments', 'transaction_code');
        $hasPaymentProvider = Schema::hasColumn('payments', 'payment_provider');

        $invalidRefundCount = (int) DB::table('payments')
            ->where('payment_type', 'Refund')
            ->where(function ($query): void {
                $query->whereNull('refund_of_payment_id')
                    ->orWhere('status', '!=', 'Refunded');
            })
            ->count();

        $invalidNonRefundCount = (int) DB::table('payments')
            ->where('payment_type', '!=', 'Refund')
            ->whereNotNull('refund_of_payment_id')
            ->count();

        $invalidTargetCount = (int) DB::table('payments as refund')
            ->leftJoin('payments as source', 'source.payment_id', '=', 'refund.refund_of_payment_id')
            ->where('refund.payment_type', 'Refund')
            ->whereNotNull('refund.refund_of_payment_id')
            ->where(function ($query): void {
                $query->whereNull('source.payment_id')
                    ->orWhereNotIn('source.payment_type', ['Deposit', 'Final']);
            })
            ->count();

        $crossReservationCount = 0;
        if ($hasReservation) {
            $crossReservationCount = (int) DB::table('payments as refund')
                ->join('payments as source', 'source.payment_id', '=', 'refund.refund_of_payment_id')
                ->where('refund.payment_type', 'Refund')
                ->where(function ($query): void {
                    $query->whereColumn('refund.reservation_id', '!=', 'source.reservation_id')
                        ->orWhereNull('refund.reservation_id')
                        ->orWhereNull('source.reservation_id');
                })
                ->count();
        }

        $currencyMismatchCount = 0;
        if ($hasCurrency) {
            $currencyMismatchCount = (int) DB::table('payments as refund')
                ->join('payments as source', 'source.payment_id', '=', 'refund.refund_of_payment_id')
                ->where('refund.payment_type', 'Refund')
                ->where(function ($query): void {
                    $query->whereColumn('refund.currency', '!=', 'source.currency')
                        ->orWhereNull('refund.currency')
                        ->orWhereNull('source.currency');
                })
                ->count();
        }

        $overRefundSourceCount = 0;
        $overRefundSamples = [];
        if ($hasAmount) {
            $overRefundQuery = $this->overRefundSourceQuery($hasReservation, $hasBranch);
            $overRefundSourceCount = (int) DB::query()
                ->fromSub($overRefundQuery, 'over_refund_scan')
                ->count();
            $overRefundSamples = $this->sampleOverRefundSources($overRefundQuery);
        }

        $missingSourceQuery = $this->refundMissingSourceQuery();
        $invalidSourceStateQuery = $this->refundInvalidSourceStateQuery($refundableSourceStatuses);
        $lineageMismatchQuery = $hasReservation || $hasBranch
            ? $this->refundLineageMismatchQuery($hasReservation, $hasBranch)
            : null;
        $currencyMismatchQuery = $hasCurrency ? $this->refundCurrencyMismatchQuery() : null;
        $impossibleStatusTypeQuery = $this->refundImpossibleStatusTypeQuery($validPaymentTypes, $validStatuses, $hasAmount);

        $guards = [
            $this->paymentRefundGuardDetail(
                'refund_missing_source',
                (int) (clone $missingSourceQuery)->count(),
                $this->samplePaymentRefundRows($missingSourceQuery),
                'Refund payment rows must reference an existing source payment.'
            ),
            $this->paymentRefundGuardDetail(
                'refund_invalid_source_state',
                (int) (clone $invalidSourceStateQuery)->count(),
                $this->samplePaymentRefundRows($invalidSourceStateQuery),
                'Refund source payments must be Deposit/Final rows in a refundable captured or partially captured status.'
            ),
            $this->paymentRefundGuardDetail(
                'refund_lineage_mismatch',
                $lineageMismatchQuery instanceof Builder ? (int) (clone $lineageMismatchQuery)->count() : 0,
                $lineageMismatchQuery instanceof Builder ? $this->samplePaymentRefundRows($lineageMismatchQuery) : [],
                'Refund payment rows must stay in the same reservation and branch lineage as their source payment.'
            ),
            $this->paymentRefundGuardDetail(
                'refund_over_source_amount',
                $overRefundSourceCount,
                $overRefundSamples,
                'Refund totals per source payment must not exceed the captured/settled source amount.'
            ),
            $this->paymentRefundGuardDetail(
                'refund_currency_mismatch',
                $currencyMismatchQuery instanceof Builder ? (int) (clone $currencyMismatchQuery)->count() : 0,
                $currencyMismatchQuery instanceof Builder ? $this->samplePaymentRefundRows($currencyMismatchQuery) : [],
                'Refund payment currency must match the source payment currency.'
            ),
            $this->paymentRefundGuardDetail(
                'refund_impossible_status_type',
                (int) (clone $impossibleStatusTypeQuery)->count(),
                $this->samplePaymentRows($impossibleStatusTypeQuery),
                'Payment refund type/status combinations must match the runtime refund state machine.'
            ),
            $this->paymentRefundDuplicateReferenceGuard($hasIdempotencyKey, $hasPaymentProvider, $hasTransactionCode),
        ];

        $failingGuards = array_values(array_filter(
            $guards,
            static fn (array $guard): bool => (int) ($guard['failing_count'] ?? 0) > 0,
        ));
        $failingCount = array_sum(array_map(
            static fn (array $guard): int => (int) ($guard['failing_count'] ?? 0),
            $failingGuards,
        ));
        $sampleIds = $this->paymentRefundGuardSampleIds($failingGuards);

        $meta = [
            'guard_label' => 'payment_refund_lineage',
            'failing_count' => $failingCount,
            'failing_guard_count' => count($failingGuards),
            'sample_ids' => $sampleIds,
            'guards' => $guards,
            'invalid_refund_count' => $invalidRefundCount,
            'invalid_non_refund_count' => $invalidNonRefundCount,
            'invalid_target_count' => $invalidTargetCount,
            'cross_reservation_count' => $crossReservationCount,
            'currency_mismatch_count' => $currencyMismatchCount,
            'over_refund_source_count' => $overRefundSourceCount,
        ];

        if ($failingCount > 0) {
            $meta['remediation'] = 'Review the listed payment IDs and source payment IDs before release. Repair refund lineage through a controlled data fix; do not re-install the dropped runtime refund triggers.';

            return $this->error('payments contains refund lineage rows that will fail integrity checks.', $meta);
        }

        return $this->ok('Payment refund lineage looks migration-safe.', $meta);
    }

    private function refundMissingSourceQuery(): Builder
    {
        return DB::table('payments as refund')
            ->leftJoin('payments as source', 'source.payment_id', '=', 'refund.refund_of_payment_id')
            ->where('refund.payment_type', 'Refund')
            ->where(function ($query): void {
                $query->whereNull('refund.refund_of_payment_id')
                    ->orWhereNull('source.payment_id');
            });
    }

    /**
     * @param  list<string>  $refundableSourceStatuses
     */
    private function refundInvalidSourceStateQuery(array $refundableSourceStatuses): Builder
    {
        return DB::table('payments as refund')
            ->join('payments as source', 'source.payment_id', '=', 'refund.refund_of_payment_id')
            ->where('refund.payment_type', 'Refund')
            ->where(function ($query) use ($refundableSourceStatuses): void {
                $query->whereNull('source.payment_type')
                    ->orWhereNotIn('source.payment_type', ['Deposit', 'Final'])
                    ->orWhereNull('source.status')
                    ->orWhereNotIn('source.status', $refundableSourceStatuses);
            });
    }

    private function refundLineageMismatchQuery(bool $hasReservation, bool $hasBranch): Builder
    {
        return DB::table('payments as refund')
            ->join('payments as source', 'source.payment_id', '=', 'refund.refund_of_payment_id')
            ->where('refund.payment_type', 'Refund')
            ->where(function ($query) use ($hasReservation, $hasBranch): void {
                if ($hasReservation) {
                    $query->whereColumn('refund.reservation_id', '!=', 'source.reservation_id')
                        ->orWhereNull('refund.reservation_id')
                        ->orWhereNull('source.reservation_id');
                }

                if ($hasBranch) {
                    $query->orWhereColumn('refund.branch_id', '!=', 'source.branch_id')
                        ->orWhereNull('refund.branch_id')
                        ->orWhereNull('source.branch_id');
                }
            });
    }

    private function refundCurrencyMismatchQuery(): Builder
    {
        return DB::table('payments as refund')
            ->join('payments as source', 'source.payment_id', '=', 'refund.refund_of_payment_id')
            ->where('refund.payment_type', 'Refund')
            ->where(function ($query): void {
                $query->whereColumn('refund.currency', '!=', 'source.currency')
                    ->orWhereNull('refund.currency')
                    ->orWhereNull('source.currency');
            });
    }

    /**
     * @param  list<string>  $validPaymentTypes
     * @param  list<string>  $validStatuses
     */
    private function refundImpossibleStatusTypeQuery(array $validPaymentTypes, array $validStatuses, bool $hasAmount): Builder
    {
        return DB::table('payments')
            ->where(function ($query) use ($validPaymentTypes, $validStatuses, $hasAmount): void {
                $query->whereNull('payment_type')
                    ->orWhereNotIn('payment_type', $validPaymentTypes)
                    ->orWhereNull('status')
                    ->orWhereNotIn('status', $validStatuses)
                    ->orWhere(function ($query): void {
                        $query->where('payment_type', 'Refund')
                            ->where('status', '!=', PaymentStatus::Refunded->value);
                    })
                    ->orWhere(function ($query): void {
                        $query->where('payment_type', '!=', 'Refund')
                            ->whereNotNull('refund_of_payment_id');
                    });

                if ($hasAmount) {
                    $query->orWhere(function ($query): void {
                        $query->where('payment_type', 'Refund')
                            ->where('amount', '<=', 0);
                    });
                }
            });
    }

    private function overRefundSourceQuery(bool $hasReservation, bool $hasBranch): Builder
    {
        $query = DB::table('payments as source')
            ->join('payments as refund', 'refund.refund_of_payment_id', '=', 'source.payment_id')
            ->where('refund.payment_type', 'Refund')
            ->where('refund.status', PaymentStatus::Refunded->value)
            ->groupBy('source.payment_id', 'source.amount')
            ->selectRaw('source.payment_id as source_payment_id')
            ->selectRaw('source.amount as source_amount')
            ->selectRaw('ROUND(COALESCE(SUM(refund.amount), 0), 2) as refund_amount_total')
            ->selectRaw('COUNT(*) as refund_count')
            ->havingRaw('ROUND(COALESCE(SUM(refund.amount), 0), 2) > ROUND(source.amount, 2)');

        if ($hasReservation) {
            $query->addSelect('source.reservation_id as source_reservation_id')
                ->groupBy('source.reservation_id');
        }

        if ($hasBranch) {
            $query->addSelect('source.branch_id as source_branch_id')
                ->groupBy('source.branch_id');
        }

        return $query;
    }

    /**
     * @return array{guard_label: string, failing_count: int, sample_ids: list<int>, message: string, samples: list<array<string, mixed>>}
     */
    private function paymentRefundGuardDetail(string $label, int $failingCount, array $samples, string $message): array
    {
        return [
            'guard_label' => $label,
            'failing_count' => $failingCount,
            'sample_ids' => $this->paymentRefundSampleIds($samples),
            'message' => $message,
            'samples' => $samples,
        ];
    }

    private function paymentRefundDuplicateReferenceGuard(bool $hasIdempotencyKey, bool $hasPaymentProvider, bool $hasTransactionCode): array
    {
        $samples = [];
        $duplicateGroupCount = 0;

        if ($hasIdempotencyKey) {
            $idempotencyQuery = DB::table('payments')
                ->where('payment_type', 'Refund')
                ->whereNotNull('idempotency_key')
                ->where('idempotency_key', '!=', '')
                ->groupBy('idempotency_key')
                ->selectRaw('COUNT(*) as duplicate_count')
                ->selectRaw('GROUP_CONCAT(payment_id) as payment_ids')
                ->havingRaw('COUNT(*) > 1');

            $duplicateGroupCount += (int) DB::query()->fromSub(clone $idempotencyQuery, 'duplicate_refund_idempotency')->count();
            $samples = array_merge($samples, $this->sampleDuplicatePaymentReferenceGroups($idempotencyQuery, 'idempotency_key'));
        }

        if ($hasPaymentProvider && $hasTransactionCode) {
            $transactionQuery = DB::table('payments')
                ->where('payment_type', 'Refund')
                ->whereNotNull('transaction_code')
                ->where('transaction_code', '!=', '')
                ->groupBy('payment_provider', 'transaction_code')
                ->selectRaw('COUNT(*) as duplicate_count')
                ->selectRaw('GROUP_CONCAT(payment_id) as payment_ids')
                ->havingRaw('COUNT(*) > 1');

            $duplicateGroupCount += (int) DB::query()->fromSub(clone $transactionQuery, 'duplicate_refund_transaction')->count();
            $samples = array_merge($samples, $this->sampleDuplicatePaymentReferenceGroups($transactionQuery, 'payment_provider_transaction_code'));
        }

        return $this->paymentRefundGuardDetail(
            'refund_duplicate_references',
            $duplicateGroupCount,
            $samples,
            'Refund idempotency and external transaction references must identify one refund row unambiguously.'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function samplePaymentRefundRows(Builder $query): array
    {
        $columns = [
            'refund.payment_id as refund_payment_id',
            'refund.refund_of_payment_id as source_payment_id',
            'refund.payment_type as payment_type',
            'refund.status as status',
            'source.payment_type as source_payment_type',
            'source.status as source_status',
        ];

        if (Schema::hasColumn('payments', 'reservation_id')) {
            $columns[] = 'refund.reservation_id as reservation_id';
            $columns[] = 'source.reservation_id as source_reservation_id';
        }

        if (Schema::hasColumn('payments', 'branch_id')) {
            $columns[] = 'refund.branch_id as branch_id';
            $columns[] = 'source.branch_id as source_branch_id';
        }

        if (Schema::hasColumn('payments', 'amount')) {
            $columns[] = 'refund.amount as amount';
            $columns[] = 'source.amount as source_amount';
        }

        if (Schema::hasColumn('payments', 'currency')) {
            $columns[] = 'refund.currency as currency';
            $columns[] = 'source.currency as source_currency';
        }

        return (clone $query)
            ->select($columns)
            ->orderBy('refund.payment_id')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => $this->normalizePaymentRefundSample($row))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function samplePaymentRows(Builder $query): array
    {
        $columns = [
            'payment_id',
            'refund_of_payment_id as source_payment_id',
            'payment_type',
            'status',
        ];

        if (Schema::hasColumn('payments', 'reservation_id')) {
            $columns[] = 'reservation_id';
        }

        if (Schema::hasColumn('payments', 'branch_id')) {
            $columns[] = 'branch_id';
        }

        if (Schema::hasColumn('payments', 'amount')) {
            $columns[] = 'amount';
        }

        if (Schema::hasColumn('payments', 'currency')) {
            $columns[] = 'currency';
        }

        return (clone $query)
            ->select($columns)
            ->orderBy('payment_id')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => $this->normalizePaymentSample($row))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sampleOverRefundSources(Builder $query): array
    {
        return (clone $query)
            ->orderBy('source.payment_id')
            ->limit(5)
            ->get()
            ->map(function (object $row): array {
                $sourcePaymentId = (int) ($row->source_payment_id ?? 0);
                $sourceReservationId = isset($row->source_reservation_id) ? (int) $row->source_reservation_id : null;

                $sample = [
                    'source_payment_id' => $sourcePaymentId,
                    'source_reservation_id' => $sourceReservationId,
                    'source_branch_id' => isset($row->source_branch_id) ? (int) $row->source_branch_id : null,
                    'source_amount' => isset($row->source_amount) ? (string) $row->source_amount : null,
                    'refund_amount_total' => isset($row->refund_amount_total) ? (string) $row->refund_amount_total : null,
                    'refund_count' => isset($row->refund_count) ? (int) $row->refund_count : null,
                    'refund_payment_ids' => $this->refundPaymentIdsForSource($sourcePaymentId),
                    'order_ids' => $this->paymentRefundOrderIds(array_filter([$sourceReservationId])),
                ];

                return $this->filterEmptySampleValues($sample);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sampleDuplicatePaymentReferenceGroups(Builder $query, string $referenceType): array
    {
        return (clone $query)
            ->limit(5)
            ->get()
            ->map(function (object $row) use ($referenceType): array {
                $paymentIds = $this->normalizePaymentIdList((string) ($row->payment_ids ?? ''));

                return [
                    'duplicate_reference_type' => $referenceType,
                    'duplicate_count' => (int) ($row->duplicate_count ?? count($paymentIds)),
                    'payment_ids' => $paymentIds,
                    'sample_ids' => array_slice($paymentIds, 0, 5),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizePaymentRefundSample(object $row): array
    {
        $reservationId = isset($row->reservation_id) ? (int) $row->reservation_id : null;
        $sourceReservationId = isset($row->source_reservation_id) ? (int) $row->source_reservation_id : null;

        return $this->filterEmptySampleValues([
            'payment_id' => isset($row->refund_payment_id) ? (int) $row->refund_payment_id : null,
            'refund_payment_id' => isset($row->refund_payment_id) ? (int) $row->refund_payment_id : null,
            'source_payment_id' => isset($row->source_payment_id) ? (int) $row->source_payment_id : null,
            'reservation_id' => $reservationId,
            'source_reservation_id' => $sourceReservationId,
            'branch_id' => isset($row->branch_id) ? (int) $row->branch_id : null,
            'source_branch_id' => isset($row->source_branch_id) ? (int) $row->source_branch_id : null,
            'order_ids' => $this->paymentRefundOrderIds(array_filter([$reservationId, $sourceReservationId])),
            'payment_type' => isset($row->payment_type) ? (string) $row->payment_type : null,
            'status' => isset($row->status) ? (string) $row->status : null,
            'source_payment_type' => isset($row->source_payment_type) ? (string) $row->source_payment_type : null,
            'source_status' => isset($row->source_status) ? (string) $row->source_status : null,
            'amount' => isset($row->amount) ? (string) $row->amount : null,
            'source_amount' => isset($row->source_amount) ? (string) $row->source_amount : null,
            'currency' => isset($row->currency) ? (string) $row->currency : null,
            'source_currency' => isset($row->source_currency) ? (string) $row->source_currency : null,
        ]);
    }

    private function normalizePaymentSample(object $row): array
    {
        $reservationId = isset($row->reservation_id) ? (int) $row->reservation_id : null;

        return $this->filterEmptySampleValues([
            'payment_id' => isset($row->payment_id) ? (int) $row->payment_id : null,
            'source_payment_id' => isset($row->source_payment_id) ? (int) $row->source_payment_id : null,
            'reservation_id' => $reservationId,
            'branch_id' => isset($row->branch_id) ? (int) $row->branch_id : null,
            'order_ids' => $this->paymentRefundOrderIds(array_filter([$reservationId])),
            'payment_type' => isset($row->payment_type) ? (string) $row->payment_type : null,
            'status' => isset($row->status) ? (string) $row->status : null,
            'amount' => isset($row->amount) ? (string) $row->amount : null,
            'currency' => isset($row->currency) ? (string) $row->currency : null,
        ]);
    }

    /**
     * @return list<int>
     */
    private function refundPaymentIdsForSource(int $sourcePaymentId): array
    {
        if ($sourcePaymentId <= 0) {
            return [];
        }

        return DB::table('payments')
            ->where('refund_of_payment_id', $sourcePaymentId)
            ->where('payment_type', 'Refund')
            ->where('status', PaymentStatus::Refunded->value)
            ->orderBy('payment_id')
            ->limit(5)
            ->pluck('payment_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string|null>  $reservationIds
     * @return list<int>
     */
    private function paymentRefundOrderIds(array $reservationIds): array
    {
        if (! Schema::hasTable('reservation_orders')
            || ! Schema::hasColumn('reservation_orders', 'reservation_id')
            || ! Schema::hasColumn('reservation_orders', 'order_id')) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $reservationIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        return DB::table('reservation_orders')
            ->whereIn('reservation_id', $ids)
            ->orderBy('order_id')
            ->limit(5)
            ->pluck('order_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $samples
     * @return list<int>
     */
    private function paymentRefundSampleIds(array $samples): array
    {
        $ids = [];

        foreach ($samples as $sample) {
            foreach (['payment_id', 'refund_payment_id', 'source_payment_id'] as $key) {
                $id = (int) ($sample[$key] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }

            foreach (['payment_ids', 'refund_payment_ids', 'sample_ids'] as $key) {
                foreach ((array) ($sample[$key] ?? []) as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<array<string, mixed>>  $guards
     * @return list<int>
     */
    private function paymentRefundGuardSampleIds(array $guards): array
    {
        $ids = [];

        foreach ($guards as $guard) {
            foreach ((array) ($guard['sample_ids'] ?? []) as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    private function normalizePaymentIdList(string $paymentIds): array
    {
        if (trim($paymentIds) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (string $id): int => (int) trim($id), explode(',', $paymentIds)),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /**
     * @param  array<string, mixed>  $sample
     * @return array<string, mixed>
     */
    private function filterEmptySampleValues(array $sample): array
    {
        return array_filter(
            $sample,
            static fn (mixed $value): bool => $value !== null && $value !== [],
        );
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardPaymentRefundTriggerCompatibility(): array
    {
        if (! Schema::hasTable('payments')) {
            return $this->warning('Skipped payment refund trigger compatibility guard because the payments table is not available in this schema.');
        }

        $driver = (string) DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->warning('Skipped payment refund trigger compatibility guard because the current database driver is not MySQL-compatible.', [
                'driver' => $driver,
            ]);
        }

        $presentTriggers = DB::table('information_schema.triggers')
            ->selectRaw('TRIGGER_NAME as trigger_name')
            ->whereRaw('TRIGGER_SCHEMA = DATABASE()')
            ->where('EVENT_OBJECT_TABLE', 'payments')
            ->whereIn('TRIGGER_NAME', [
                'trg_payments__bi_refund_cap',
                'trg_payments__bu_refund_cap',
                'trg_payments__bi_refund_lineage_guard',
                'trg_payments__bu_refund_lineage_guard',
            ])
            ->pluck('trigger_name')
            ->map(static fn ($value) => (string) $value)
            ->values()
            ->all();

        if ($presentTriggers !== []) {
            return $this->error('Runtime-incompatible payments refund triggers are still installed; refund execute can fail with MySQL ERROR 1442.', [
                'driver' => $driver,
                'present_triggers' => $presentTriggers,
                'remediation' => 'Run composer bootstrap:booking or apply database/patches/2026_04_08_000041_drop_runtime_incompatible_payment_refund_triggers.sql against the target MySQL database.',
            ]);
        }

        return $this->ok('Payments refund trigger contract looks runtime-safe for refund execution.', [
            'driver' => $driver,
            'present_trigger_count' => 0,
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardReservationLifecycle(): array
    {
        if (! Schema::hasTable('reservations')) {
            return $this->warning('Skipped reservation lifecycle guard because the reservations table is not available in this schema.');
        }

        $invalidCancelled = DB::table('reservations')
            ->where('status', 'Cancelled')
            ->whereNull('cancelled_at')
            ->count();

        $invalidReserved = DB::table('reservations')
            ->where('status', ReservationStatus::checkedInDbValue())
            ->whereNull('checked_in_at')
            ->count();

        $invalidCompleted = DB::table('reservations')
            ->where('status', 'Completed')
            ->whereNull('checked_out_at')
            ->count();

        $invalidNoShow = DB::table('reservations')
            ->where('status', 'NoShow')
            ->whereNull('no_show_at')
            ->count();

        if ($invalidCancelled > 0 || $invalidReserved > 0 || $invalidCompleted > 0 || $invalidNoShow > 0) {
            return $this->error('reservations contains lifecycle timestamps that will fail consistency checks.', [
                'cancelled_missing_cancelled_at' => $invalidCancelled,
                'reserved_missing_checked_in_at' => $invalidReserved,
                'completed_missing_checked_out_at' => $invalidCompleted,
                'noshow_missing_no_show_at' => $invalidNoShow,
            ]);
        }

        return $this->ok('Reservation lifecycle timestamps look migration-safe.', [
            'cancelled_missing_cancelled_at' => 0,
            'reserved_missing_checked_in_at' => 0,
            'completed_missing_checked_out_at' => 0,
            'noshow_missing_no_show_at' => 0,
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardUserVoucherState(): array
    {
        if (! Schema::hasTable('user_vouchers')) {
            return $this->warning('Skipped user_vouchers guard because the table is not available in this schema.');
        }

        $negativeUsedAmount = DB::table('user_vouchers')
            ->whereNotNull('used_amount')
            ->where('used_amount', '<', 0)
            ->count();

        $unusedHasUsageFields = DB::table('user_vouchers')
            ->where('is_used', 0)
            ->where(function ($query) {
                $query->whereNotNull('used_date')
                    ->orWhereNotNull('used_reservation_id')
                    ->orWhereRaw('COALESCE(used_amount, 0) > 0');
            })
            ->count();

        $usedMissingUsageFields = DB::table('user_vouchers')
            ->where('is_used', 1)
            ->where(function ($query) {
                $query->whereNull('used_date')
                    ->orWhereNull('used_reservation_id');
            })
            ->count();

        $usedStillLocked = DB::table('user_vouchers')
            ->where('is_used', 1)
            ->where(function ($query) {
                $query->whereNotNull('lock_token')
                    ->orWhereNotNull('locked_until');
            })
            ->count();

        $lockPairMismatch = DB::table('user_vouchers')
            ->where(function ($query) {
                $query->whereNull('lock_token')->whereNotNull('locked_until');
            })
            ->orWhere(function ($query) {
                $query->whereNotNull('lock_token')->whereNull('locked_until');
            })
            ->count();

        if ($negativeUsedAmount > 0 || $unusedHasUsageFields > 0 || $usedMissingUsageFields > 0 || $usedStillLocked > 0 || $lockPairMismatch > 0) {
            return $this->error('user_vouchers contains rows that will fail voucher consistency checks.', [
                'negative_used_amount_count' => $negativeUsedAmount,
                'unused_with_usage_fields_count' => $unusedHasUsageFields,
                'used_missing_usage_fields_count' => $usedMissingUsageFields,
                'used_still_locked_count' => $usedStillLocked,
                'lock_pair_mismatch_count' => $lockPairMismatch,
            ]);
        }

        return $this->ok('user_vouchers state looks migration-safe.', [
            'negative_used_amount_count' => 0,
            'unused_with_usage_fields_count' => 0,
            'used_missing_usage_fields_count' => 0,
            'used_still_locked_count' => 0,
            'lock_pair_mismatch_count' => 0,
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardBankAccountDefaults(): array
    {
        if (! Schema::hasTable('bank_accounts')) {
            return $this->warning('Skipped bank_accounts default guard because the table is not available in this schema.');
        }

        $duplicateDefaultUsers = DB::query()
            ->fromSub(
                DB::table('bank_accounts')
                    ->select('user_id')
                    ->where('is_default', 1)
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicate_default_users'
            )
            ->count();

        if ($duplicateDefaultUsers > 0) {
            return $this->error('bank_accounts contains users with multiple default accounts.', [
                'duplicate_default_user_count' => $duplicateDefaultUsers,
            ]);
        }

        return $this->ok('bank_accounts default-account state looks migration-safe.', [
            'duplicate_default_user_count' => 0,
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardActiveAgentAssignments(): array
    {
        if (! Schema::hasTable('agent_assignments')) {
            return $this->warning('Skipped active agent assignment guard because the table is not available in this schema.');
        }

        $duplicateActiveConversations = DB::query()
            ->fromSub(
                DB::table('agent_assignments')
                    ->select('conversation_id')
                    ->where('is_active', 1)
                    ->groupBy('conversation_id')
                    ->havingRaw('COUNT(*) > 1'),
                'duplicate_active_conversations'
            )
            ->count();

        if ($duplicateActiveConversations > 0) {
            return $this->error('agent_assignments contains conversations with multiple active assignments.', [
                'duplicate_active_conversation_count' => $duplicateActiveConversations,
            ]);
        }

        return $this->ok('agent_assignments active rows look migration-safe.', [
            'duplicate_active_conversation_count' => 0,
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardSessionHoldLinkage(): array
    {
        if (! Schema::hasTable('table_holds')) {
            return $this->warning('Skipped session hold linkage guard because the table is not available in this schema.');
        }

        $unlinkedCount = (int) DB::table('table_holds')
            ->whereNull('confirmed_reservation_id')
            ->whereNotNull('session_id')
            ->where('session_id', '<>', '')
            ->whereIn('hold_status', ['Confirmed', 'Holding', 'Pending'])
            ->count();

        if ($unlinkedCount > 0) {
            return $this->warning('table_holds still contains active session-bound rows without confirmed reservation linkage.', [
                'unlinked_session_hold_count' => $unlinkedCount,
            ]);
        }

        return $this->ok('table_holds session linkage looks migration-safe.', [
            'unlinked_session_hold_count' => 0,
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardPurchaseReceiptLineageUniqueness(): array
    {
        if (! Schema::hasTable('ingredient_stock_movements')) {
            return $this->warning('Skipped purchase receipt lineage uniqueness guard because ingredient_stock_movements is not available in this schema.');
        }

        $summary = $this->purchaseOrderReconciliationService->duplicatePurchaseReceiptReferenceSummary();
        $duplicateReferenceCount = (int) ($summary['duplicate_reference_count'] ?? 0);
        $duplicateMovementCount = (int) ($summary['duplicate_movement_count'] ?? 0);

        if ($duplicateReferenceCount > 0) {
            return $this->error(
                'Duplicate PurchaseReceipt stock movement references will block the inventory uniqueness patch and keep receipt lineage ambiguous.',
                [
                    'duplicate_reference_count' => $duplicateReferenceCount,
                    'duplicate_movement_count' => $duplicateMovementCount,
                    'examples' => (array) ($summary['examples'] ?? []),
                    'remediation' => 'Deduplicate the listed ingredient_stock_movements reference_id values before applying database/patches/2026_04_13_000051_inventory_stock_movement_reference_uniqueness.sql.',
                ],
            );
        }

        return $this->ok('PurchaseReceipt stock movement reference lineage is unique and safe for the inventory uniqueness patch.', [
            'duplicate_reference_count' => 0,
            'duplicate_movement_count' => 0,
            'examples' => [],
        ]);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function guardInventoryStockOnHandReconciliation(): array
    {
        $summary = app(InventoryStockReconciliationService::class)->summary();

        if (! (bool) ($summary['table_present'] ?? false)) {
            return $this->warning('Skipped inventory stock-on-hand reconciliation because ingredient_stock_movements is not available in this schema.', $summary);
        }

        if ((array) ($summary['missing_columns'] ?? []) !== []) {
            return $this->warning('Skipped inventory stock-on-hand reconciliation because ingredient_stock_movements is missing canonical stock movement columns.', $summary);
        }

        $negativeGroupCount = (int) ($summary['negative_group_count'] ?? 0);
        $impossibleMovementCount = (int) ($summary['impossible_movement_count'] ?? 0);
        if ($negativeGroupCount > 0 || $impossibleMovementCount > 0) {
            return $this->error(
                'Inventory stock movement ledger reconciles to negative or impossible stock state.',
                array_merge($summary, [
                    'remediation' => 'Review the listed ingredient_stock_movements sample ids, repair direct-write stock drift, then rebuild reporting snapshots before rollout.',
                ]),
            );
        }

        return $this->ok('Inventory stock movement ledger reconciles to non-negative stock on hand.', $summary);
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function ok(string $message, array $meta = []): array
    {
        $result = [
            'ok' => true,
            'severity' => 'info',
            'message' => $message,
        ];

        if ($meta !== []) {
            $result['meta'] = $meta;
        }

        return $result;
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function error(string $message, array $meta = []): array
    {
        $result = [
            'ok' => false,
            'severity' => 'error',
            'message' => $message,
        ];

        if ($meta !== []) {
            $result['meta'] = $meta;
        }

        return $result;
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function warning(string $message, array $meta = []): array
    {
        $result = [
            'ok' => false,
            'severity' => 'warning',
            'message' => $message,
        ];

        if ($meta !== []) {
            $result['meta'] = $meta;
        }

        return $result;
    }
}
