<?php

use App\Modules\PrivacyCompliance\Application\Workflows\Retention\RetentionEnforcementWorkflow;
use App\Platform\ApiContract\ApiArtifacts\ApiConsumerArtifactService;
use App\Platform\ApiContract\Services\OpenApiSpecService;
use App\Platform\ApiContract\Services\OpsGateArtifactService;
use App\Platform\ApiContract\Services\RouteContractReconcilerService;
use App\Platform\ApiContract\Services\RouteInventoryGateService;
use App\Platform\Backup\DisasterRecovery\DisasterRecoveryDrillService;
use App\Platform\Health\Services\BookingDoctorService;
use App\Platform\Health\Services\OpsHeartbeatService;
use App\Platform\Metrics\Services\OperationalAlertService;
use App\Platform\Metrics\Services\OperationalInsightsService;
use App\Platform\Performance\PerformanceVerificationService;
use App\Platform\Release\Services\BookingDeploySafetyService;
use App\Platform\Release\Services\CoreOpsGateService;
use App\Platform\Release\Services\LaunchReadinessManualEvidenceTemplateService;
use App\Platform\Release\Services\LaunchReadinessService;
use App\Platform\Release\Services\ReleaseArtifactManifestService;
use App\Platform\Release\Services\ReleaseArtifactNormalizerService;
use App\Platform\Release\Services\ReleaseBuildService;
use App\Platform\Release\Services\ReleaseLoopService;
use App\Platform\Release\Services\ReleasePackageService;
use App\Platform\Release\Services\RoundFiveGateService;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

// Keep command registration here thin; new operator behavior belongs in
// app/Platform services and should be invoked from a small command closure.
$writeOpsGateArtifactReport = static function (
    string $artifactRoot,
    string $reportPrefix,
    string $scopeKey,
    array $payload,
    string $title,
    array $summaryRows,
    string $artifactKey = 'artifacts'
): array {
    $evaluatedAt = now('UTC');
    $markdown = "# {$title}\n\n";
    $markdown .= '**Generated:** '.$evaluatedAt->toIso8601String()."\n\n";
    $markdown .= "## Summary\n\n";
    foreach ($summaryRows as $key => $value) {
        $markdown .= '- **'.str_replace('_', ' ', ucfirst($key)).":** {$value}\n";
    }

    return app(OpsGateArtifactService::class)->writeReport(
        artifactRoot: $artifactRoot,
        reportPrefix: $reportPrefix,
        scopeKey: $scopeKey,
        payload: $payload,
        markdown: $markdown,
        evaluatedAt: $evaluatedAt,
        artifactKey: $artifactKey,
    );
};

Artisan::command('data-lifecycle:enforce-retention {--dry-run : Preview retention actions without mutating data}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $result = app(RetentionEnforcementWorkflow::class)->enforce((bool) $command->option('dry-run'));
    $payload = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload !== false) {
        $command->line($payload);
    }

    return 0;
})->purpose('Apply retention pruning for auth, notification, and derived conversation artifacts.');

Artisan::command('booking:doctor {--json : Output machine-readable JSON} {--strict : Treat warnings as failures}', function () use ($writeOpsGateArtifactReport) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var BookingDoctorService $service */
    $service = app(BookingDoctorService::class);
    $payload = $service->inspect((bool) $command->option('strict'));
    $validation = (array) ($payload['validation'] ?? []);
    $runtime = (array) ($payload['runtime'] ?? []);
    $exitCode = ($payload['ok'] ?? false) ? 0 : 1;
    $payload = $writeOpsGateArtifactReport(
        artifactRoot: trim((string) config('booking_ops_artifacts.doctor.artifact_root', 'storage/app/booking_release/doctor')),
        reportPrefix: 'booking-doctor',
        scopeKey: (bool) $command->option('strict') ? 'strict' : 'default',
        payload: $payload,
        title: 'Booking Doctor',
        summaryRows: [
            'strict' => (bool) $command->option('strict') ? 'yes' : 'no',
            'validation_error_count' => count((array) ($validation['errors'] ?? [])),
            'validation_warning_count' => count((array) ($validation['warnings'] ?? [])),
            'runtime_check_count' => count($runtime),
            'ok' => ($payload['ok'] ?? false) ? 'yes' : 'no',
        ],
        artifactKey: 'artifacts',
    );

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $exitCode;
    }

    $command->info('Booking doctor');
    $command->newLine();

    $command->table(
        ['Section', 'Key', 'Status', 'Message'],
        collect($validation['checks'] ?? [])->map(function (array $check, string $name) {
            return [
                'validation',
                $name,
                ($check['ok'] ?? false) ? 'OK' : strtoupper((string) ($check['severity'] ?? 'ERROR')),
                (string) ($check['message'] ?? ''),
            ];
        })->values()->all()
    );

    $command->table(
        ['Section', 'Key', 'Status', 'Message'],
        collect($runtime)->map(function (array $check, string $name) {
            return [
                'runtime',
                $name,
                ($check['ok'] ?? false) ? 'OK' : 'FAIL',
                (string) ($check['message'] ?? ''),
            ];
        })->values()->all()
    );

    if (! empty($validation['errors'])) {
        $command->error('Validation errors:');
        foreach ($validation['errors'] as $line) {
            $command->line(' - '.$line);
        }
    }

    if (! empty($validation['warnings'])) {
        $command->warn('Validation warnings:');
        foreach ($validation['warnings'] as $line) {
            $command->line(' - '.$line);
        }
    }

    $command->table(['Artifact', 'Path'], [
        ['reports_root', (string) (($payload['artifacts'] ?? [])['reports_root'] ?? '')],
        ['json', (string) (($payload['artifacts'] ?? [])['json_path'] ?? '')],
        ['markdown', (string) (($payload['artifacts'] ?? [])['markdown_path'] ?? '')],
        ['latest_json', (string) (($payload['artifacts'] ?? [])['latest_json_path'] ?? '')],
        ['latest_markdown', (string) (($payload['artifacts'] ?? [])['latest_markdown_path'] ?? '')],
    ]);

    if ($exitCode === 0) {
        $command->info('booking:doctor passed.');
    } else {
        $command->error('booking:doctor failed.');
    }

    return $exitCode;
})->purpose('Validate booking runtime configuration and core dependencies');

Artisan::command('booking:ops-heartbeat:touch
    {name=scheduler : Heartbeat name to touch}
    {--ttl= : Override heartbeat TTL in seconds}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $name = trim((string) $command->argument('name'));
    $ttlOption = $command->option('ttl');
    $ttlSeconds = is_numeric($ttlOption)
        ? (int) $ttlOption
        : (int) config('booking.scheduler_heartbeat_ttl_seconds', 300);

    if ($name === '') {
        $payload = [
            'ok' => false,
            'error' => 'heartbeat_name_required',
            'message' => 'Heartbeat name must not be empty.',
        ];

        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        $command->error((string) $payload['message']);

        return 1;
    }

    if ($ttlSeconds <= 0) {
        $payload = [
            'ok' => false,
            'error' => 'invalid_ttl',
            'message' => 'Heartbeat TTL must be greater than 0.',
            'meta' => [
                'ttl_seconds' => $ttlSeconds,
            ],
        ];

        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        $command->error((string) $payload['message']);

        return 1;
    }

    app(OpsHeartbeatService::class)->touch($name, $ttlSeconds);
    $lastRun = app(OpsHeartbeatService::class)->getLastRun($name);

    $payload = [
        'ok' => $lastRun !== null,
        'data' => [
            'heartbeat_name' => $name,
            'ttl_seconds' => $ttlSeconds,
            'last_run_at_utc' => $lastRun?->toIso8601String(),
        ],
    ];

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($payload['ok'] ?? false) ? 0 : 1;
    }

    $command->info(sprintf('Touched heartbeat [%s].', $name));
    $command->table(['Field', 'Value'], [
        ['heartbeat_name', $name],
        ['ttl_seconds', (string) $ttlSeconds],
        ['last_run_at_utc', (string) ($payload['data']['last_run_at_utc'] ?? '')],
    ]);

    return ($payload['ok'] ?? false) ? 0 : 1;
})->purpose('Touch an operational heartbeat so runtime diagnostics can verify freshness.');

Artisan::command('booking:route-gate {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var RouteInventoryGateService $service */
    $service = app(RouteInventoryGateService::class);
    $report = $service->inspect();
    $exitCode = ($report['ok'] ?? false) ? 0 : 1;

    if ($command->option('json')) {
        $command->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $exitCode;
    }

    $command->info('Booking route inventory gate');
    $command->newLine();

    $command->table(
        ['Key', 'Status', 'Message'],
        collect($report['checks'] ?? [])->map(static function (array $check, string $name) {
            return [
                $name,
                ($check['ok'] ?? false) ? 'OK' : strtoupper((string) ($check['severity'] ?? 'ERROR')),
                (string) ($check['message'] ?? ''),
            ];
        })->values()->all()
    );

    $summary = $report['summary'] ?? [];
    $command->table(['Summary', 'Value'], [
        ['route_count', (string) ($summary['route_count'] ?? 0)],
        ['expected_route_count', (string) ($summary['expected_route_count'] ?? 0)],
        ['public_controller_count', (string) ($summary['public_controller_count'] ?? 0)],
        ['error_count', (string) ($summary['error_count'] ?? 0)],
        ['warning_count', (string) ($summary['warning_count'] ?? 0)],
    ]);

    if ($exitCode === 0) {
        $command->info('booking:route-gate passed.');
    } else {
        $command->error('booking:route-gate failed.');
    }

    return $exitCode;
})->purpose('Run the locked route inventory gate against the runtime API surface');

Artisan::command('booking:route-contract:reconcile
    {--json : Output machine-readable JSON}
    {--write-route-inventory : Rewrite the locked route inventory fixture from the current runtime route surface}
    {--write-staff-capabilities : Rewrite known_capabilities, route_capabilities, and route_aliases from the current runtime inventory}
    {--route-inventory-path= : Override the locked route inventory fixture path}
    {--staff-capabilities-path= : Override the staff capabilities config path}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var RouteContractReconcilerService $service */
    $service = app(RouteContractReconcilerService::class);

    $routeInventoryPath = trim((string) ($command->option('route-inventory-path') ?? ''));
    $staffCapabilitiesPath = trim((string) ($command->option('staff-capabilities-path') ?? ''));
    $writeRouteInventory = (bool) $command->option('write-route-inventory');
    $writeStaffCapabilities = (bool) $command->option('write-staff-capabilities');

    $report = $service->reconcile(
        $routeInventoryPath !== '' ? $routeInventoryPath : null,
        $staffCapabilitiesPath !== '' ? $staffCapabilitiesPath : null,
    );

    if ($writeRouteInventory || $writeStaffCapabilities) {
        $writes = $service->writeReconciledArtifacts(
            writeRouteInventory: $writeRouteInventory,
            writeStaffCapabilities: $writeStaffCapabilities,
            report: $report,
            routeInventoryPath: $routeInventoryPath !== '' ? $routeInventoryPath : null,
            staffCapabilitiesPath: $staffCapabilitiesPath !== '' ? $staffCapabilitiesPath : null,
        );

        $report = $service->reconcile(
            $routeInventoryPath !== '' ? $routeInventoryPath : null,
            $staffCapabilitiesPath !== '' ? $staffCapabilitiesPath : null,
        );
        $report['writes'] = $writes['writes'];
    }

    $report['meta'] = [
        'write_route_inventory_requested' => $writeRouteInventory,
        'write_staff_capabilities_requested' => $writeStaffCapabilities,
    ];

    $exitCode = ($report['ok'] ?? false) ? 0 : 1;

    if ($command->option('json')) {
        $command->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $exitCode;
    }

    $countIssues = static function (array $issues): int {
        $count = 0;

        foreach ($issues as $value) {
            if (! is_array($value)) {
                continue;
            }

            $count += count($value);
        }

        return $count;
    };

    $routeInventoryDrift = (array) (($report['route_inventory'] ?? [])['drift'] ?? []);
    $staffCapabilityDrift = (array) (($report['staff_capabilities'] ?? [])['drift'] ?? []);
    $summary = (array) ($report['summary'] ?? []);

    $command->info('Booking route contract reconcile');
    $command->newLine();
    $command->table(['Summary', 'Value'], [
        ['runtime_api_route_count', (string) ($summary['runtime_api_route_count'] ?? 0)],
        ['runtime_staff_capability_route_count', (string) ($summary['runtime_staff_capability_route_count'] ?? 0)],
        ['locked_route_inventory_count', (string) ($summary['locked_route_inventory_count'] ?? 0)],
        ['locked_staff_capability_route_count', (string) ($summary['locked_staff_capability_route_count'] ?? 0)],
        ['route_inventory_issue_count', (string) ($summary['route_inventory_issue_count'] ?? 0)],
        ['staff_capability_issue_count', (string) ($summary['staff_capability_issue_count'] ?? 0)],
        ['issue_count', (string) ($summary['issue_count'] ?? 0)],
    ]);

    $command->table(['Domain', 'Path', 'Issue Count'], [
        ['route_inventory', (string) (($report['route_inventory'] ?? [])['path'] ?? ''), (string) $countIssues($routeInventoryDrift)],
        ['staff_capabilities', (string) (($report['staff_capabilities'] ?? [])['path'] ?? ''), (string) $countIssues($staffCapabilityDrift)],
    ]);

    if ($writeRouteInventory || $writeStaffCapabilities) {
        $command->table(['Write', 'Path'], [
            ['route_inventory', (string) (($report['writes'] ?? [])['route_inventory'] ?? '')],
            ['staff_capabilities', (string) (($report['writes'] ?? [])['staff_capabilities'] ?? '')],
        ]);
    }

    foreach ((array) ($report['notes'] ?? []) as $note) {
        $command->line(' - '.(string) $note);
    }

    if ($exitCode === 0) {
        $command->info('booking:route-contract:reconcile passed.');
    } else {
        $command->error('booking:route-contract:reconcile failed.');
    }

    return $exitCode;
})->purpose('Diff or explicitly reconcile the locked route inventory and staff capability inventory from the current runtime surface');

Artisan::command('booking:api-contract {--json : Output machine-readable JSON} {--write : Persist the generated OpenAPI artifact to the configured path} {--path= : Override artifact path}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var OpenApiSpecService $service */
    $service = app(OpenApiSpecService::class);
    $relativePath = trim((string) ($command->option('path') ?? ''));
    $payload = (bool) $command->option('write')
        ? $service->export($relativePath !== '' ? $relativePath : null)
        : [
            'spec' => $service->build(),
            'report' => [],
            'path' => $relativePath !== '' ? $relativePath : (string) config('booking_release.api_contract.openapi_path', 'storage/app/booking_release/openapi-v1.json'),
        ];

    if ($payload['report'] === []) {
        $payload['report'] = $service->report((array) ($payload['spec'] ?? []));
    }

    $exitCode = 0;

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $exitCode;
    }

    $summary = (array) (($payload['report'] ?? [])['summary'] ?? []);

    $command->info('Booking API contract');
    $command->newLine();
    $command->line('Artifact: '.(string) ($payload['path'] ?? ''));
    $command->table(['Summary', 'Value'], [
        ['path_count', (string) ($summary['path_count'] ?? 0)],
        ['full_contract_operation_count', (string) ($summary['full_contract_operation_count'] ?? 0)],
        ['fallback_operation_count', (string) ($summary['fallback_operation_count'] ?? 0)],
        ['write_requested', (bool) $command->option('write') ? 'yes' : 'no'],
    ]);

    $fallbackOperations = array_values(array_slice((array) (($payload['report'] ?? [])['fallback_operations'] ?? []), 0, 20));
    if ($fallbackOperations !== []) {
        $command->line('Fallback operations (first 20):');
        foreach ($fallbackOperations as $operation) {
            $command->line(' - '.(string) $operation);
        }
    }

    $command->info('booking:api-contract completed.');

    return $exitCode;
})->purpose('Generate the runtime-backed OpenAPI contract and optionally persist the release artifact');

Artisan::command('booking:api-artifacts:generate {--json : Output machine-readable JSON} {--refresh-openapi : Refresh the frozen OpenAPI artifact before generating consumer artifacts} {--output-root= : Override the consumer artifact output root} {--spec-path= : Override the OpenAPI artifact path} {--uat-manifest= : Generate a UAT-ready Postman environment from the provided manifest path}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var ApiConsumerArtifactService $service */
    $service = app(ApiConsumerArtifactService::class);

    $payload = $service->generate(
        outputRoot: ($value = trim((string) ($command->option('output-root') ?? ''))) !== '' ? $value : null,
        specPath: ($value = trim((string) ($command->option('spec-path') ?? ''))) !== '' ? $value : null,
        refreshOpenApi: (bool) $command->option('refresh-openapi'),
        uatManifestPath: ($value = trim((string) ($command->option('uat-manifest') ?? ''))) !== '' ? $value : null,
    );

    if ((bool) $command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    $command->info('booking:api-artifacts:generate completed.');
    $command->line('Spec: '.(string) ($payload['spec_path'] ?? ''));
    $command->line('Output root: '.(string) ($payload['output_root'] ?? ''));

    foreach ((array) ($payload['artifacts'] ?? []) as $label => $path) {
        $command->line(sprintf('%s: %s', Str::headline((string) $label), (string) $path));
    }

    $summary = (array) ($payload['summary'] ?? []);
    $command->table(
        ['Metric', 'Value'],
        [
            ['Curated groups', (string) ($summary['curated_group_count'] ?? 0)],
            ['Curated operations', (string) ($summary['curated_operation_count'] ?? 0)],
            ['Reference operations', (string) ($summary['reference_operation_count'] ?? 0)],
            ['SDK operations', (string) ($summary['sdk_operation_count'] ?? 0)],
            ['UAT environment', (bool) ($summary['uat_environment_generated'] ?? false) ? 'generated' : 'skipped'],
        ]
    );

    return 0;
})->purpose('Generate Postman environments/collection and the TypeScript consumer SDK foundation from the frozen API contract');

Artisan::command('booking:release-build {--json : Output machine-readable JSON} {--overwrite : Overwrite an existing package with the same package id} {--package-id= : Explicit package id to use} {--uat-manifest= : Generate a UAT-ready Postman environment from the provided manifest path before packaging}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var ReleaseBuildService $service */
    $service = app(ReleaseBuildService::class);
    $report = $service->build(
        packageId: ($value = trim((string) ($command->option('package-id') ?? ''))) !== '' ? $value : null,
        overwrite: (bool) $command->option('overwrite'),
        uatManifestPath: ($value = trim((string) ($command->option('uat-manifest') ?? ''))) !== '' ? $value : null,
    );
    $exitCode = ($report['ok'] ?? false) ? 0 : 1;

    if ($command->option('json')) {
        $command->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $exitCode;
    }

    $command->info('Booking release build');
    $command->newLine();
    $command->table(['Step', 'Result'], [
        ['openapi_path', (string) (($report['openapi'] ?? [])['path'] ?? '')],
        ['api_artifacts_output_root', (string) (($report['api_artifacts'] ?? [])['output_root'] ?? '')],
        ['release_manifest_snapshot_path', (string) (((array) (($report['release_manifest'] ?? [])['snapshot'] ?? []))['snapshot_path'] ?? '')],
        ['package_path', (string) (((array) ($report['package'] ?? []))['package_path'] ?? '')],
        ['web_auth_harness_ok', (bool) data_get($report, 'harness.web_auth.ok', false) ? 'yes' : 'no'],
        ['golden_flow_count', (string) data_get($report, 'harness.golden_flows.scenario_count', 0)],
        ['golden_flow_manifest_available', (bool) data_get($report, 'harness.golden_flows.manifest_available', false) ? 'yes' : 'no'],
    ]);

    $command->table(['Canonical path'], collect((array) ($report['canonical_path'] ?? []))
        ->map(static fn (string $step): array => [$step])
        ->values()
        ->all());

    $command->table(['Recommended release gates'], collect((array) data_get($report, 'harness.recommended_commands', []))
        ->map(static fn (string $step): array => [$step])
        ->values()
        ->all());

    foreach ((array) ($report['issues'] ?? []) as $issue) {
        $command->line($issue);
    }

    foreach ((array) ($report['warnings'] ?? []) as $warning) {
        $command->line($warning);
    }

    if ($exitCode === 0) {
        $command->info('booking:release-build completed.');
    } else {
        $command->error('booking:release-build failed.');
    }

    return $exitCode;
})->purpose('Run the canonical release chain from OpenAPI through consumer artifacts and frozen manifest into the immutable package.');

Artisan::command('booking:deploy-check {--mode=preflight : preflight|postflight} {--json : Output machine-readable JSON} {--strict : Treat warnings as failures}', function () use ($writeOpsGateArtifactReport) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $mode = strtolower(trim((string) $command->option('mode')));
    if (! in_array($mode, ['preflight', 'postflight'], true)) {
        $command->error('Invalid --mode. Supported values: preflight, postflight.');

        return 1;
    }

    /** @var BookingDeploySafetyService $service */
    $service = app(BookingDeploySafetyService::class);
    $report = $service->inspect($mode);
    $exitCode = (! ($report['ok'] ?? false) || ($command->option('strict') && ! empty($report['warnings'] ?? []))) ? 1 : 0;

    $payload = [
        'ok' => ($exitCode === 0),
        'mode' => $mode,
        'report' => $report,
        'meta' => [
            'strict' => (bool) $command->option('strict'),
            'timestamp_utc' => now('UTC')->toIso8601String(),
        ],
    ];
    $payload = $writeOpsGateArtifactReport(
        artifactRoot: trim((string) config('booking_ops_artifacts.deploy_check.artifact_root', 'storage/app/booking_release/deploy_checks')),
        reportPrefix: 'booking-deploy-check',
        scopeKey: $mode.((bool) $command->option('strict') ? '-strict' : ''),
        payload: $payload,
        title: 'Booking Deploy Check',
        summaryRows: [
            'mode' => $mode,
            'strict' => (bool) $command->option('strict') ? 'yes' : 'no',
            'error_count' => count((array) ($report['errors'] ?? [])),
            'warning_count' => count((array) ($report['warnings'] ?? [])),
            'artifact_error_count' => (string) (($report['summary'] ?? [])['artifact_error_count'] ?? 0),
            'artifact_warning_count' => (string) (($report['summary'] ?? [])['artifact_warning_count'] ?? 0),
            'ok' => ($payload['ok'] ?? false) ? 'yes' : 'no',
        ],
        artifactKey: 'artifacts',
    );

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $exitCode;
    }

    $command->info(sprintf('Booking deploy %s', $mode));
    $command->newLine();

    $command->table(
        ['Section', 'Key', 'Status', 'Message'],
        collect($report['checks'] ?? [])->map(function (array $check, string $name) {
            return [
                str_starts_with($name, 'data.') ? 'data' : (str_starts_with($name, 'migrations.') ? 'migrations' : (str_starts_with($name, 'ops.') ? 'ops' : 'deploy')),
                $name,
                ($check['ok'] ?? false) ? 'OK' : strtoupper((string) ($check['severity'] ?? 'ERROR')),
                (string) ($check['message'] ?? ''),
            ];
        })->values()->all()
    );

    if (! empty($report['errors'])) {
        $command->error('Errors:');
        foreach ($report['errors'] as $line) {
            $command->line(' - '.$line);
        }
    }

    if (! empty($report['warnings'])) {
        $command->warn('Warnings:');
        foreach ($report['warnings'] as $line) {
            $command->line(' - '.$line);
        }
    }

    $summary = $report['summary'] ?? [];
    $command->table(['Summary', 'Value'], [
        ['environment_error_count', (string) ($summary['environment_error_count'] ?? 0)],
        ['environment_warning_count', (string) ($summary['environment_warning_count'] ?? 0)],
        ['pending_migration_count', (string) ($summary['pending_migration_count'] ?? 0)],
        ['data_guard_error_count', (string) ($summary['data_guard_error_count'] ?? 0)],
        ['data_guard_warning_count', (string) ($summary['data_guard_warning_count'] ?? 0)],
        ['artifact_error_count', (string) ($summary['artifact_error_count'] ?? 0)],
        ['artifact_warning_count', (string) ($summary['artifact_warning_count'] ?? 0)],
        ['ops_error_count', (string) ($summary['ops_error_count'] ?? 0)],
        ['ops_warning_count', (string) ($summary['ops_warning_count'] ?? 0)],
    ]);

    $command->table(['Artifact', 'Path'], [
        ['reports_root', (string) (($payload['artifacts'] ?? [])['reports_root'] ?? '')],
        ['json', (string) (($payload['artifacts'] ?? [])['json_path'] ?? '')],
        ['markdown', (string) (($payload['artifacts'] ?? [])['markdown_path'] ?? '')],
        ['latest_json', (string) (($payload['artifacts'] ?? [])['latest_json_path'] ?? '')],
        ['latest_markdown', (string) (($payload['artifacts'] ?? [])['latest_markdown_path'] ?? '')],
    ]);

    if ($exitCode === 0) {
        $command->info(sprintf('booking:deploy-check (%s) passed.', $mode));
    } else {
        $command->error(sprintf('booking:deploy-check (%s) failed.', $mode));
    }

    return $exitCode;
})->purpose('Run booking deploy preflight/postflight checks before or after a release');

Artisan::command('booking:ops-snapshot {--json : Output machine-readable JSON} {--payment-limit=10 : Maximum payment integrity samples to include}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $paymentLimit = max(1, min(50, (int) $command->option('payment-limit')));
    $capturedAt = Carbon::now('UTC');

    /** @var OperationalInsightsService $service */
    $service = app(OperationalInsightsService::class);
    $snapshot = $service->snapshot($capturedAt, $paymentLimit);

    if ($command->option('json')) {
        $command->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return collect($snapshot)->contains(fn (array $section) => (($section['status'] ?? 'ok') === 'fail')) ? 1 : 0;
    }

    $command->table(['Section', 'Status', 'Reasons'], collect($snapshot)->map(function (array $section, string $name) {
        return [
            $name,
            (string) ($section['status'] ?? 'ok'),
            implode(',', (array) ($section['reasons'] ?? [])),
        ];
    })->values()->all());

    $command->table(['Metric', 'Value'], [
        ['payment_over_refunded_source_count', (string) (($snapshot['payment_integrity']['over_refunded_source_count'] ?? 0))],
        ['payment_refund_without_source_count', (string) (($snapshot['payment_integrity']['refund_without_source_count'] ?? 0))],
        ['payment_cross_reservation_refund_count', (string) (($snapshot['payment_integrity']['cross_reservation_refund_count'] ?? 0))],
        ['payment_currency_mismatch_refund_count', (string) (($snapshot['payment_integrity']['currency_mismatch_refund_count'] ?? 0))],
        ['payment_invalid_refund_target_count', (string) (($snapshot['payment_integrity']['invalid_refund_target_count'] ?? 0))],
        ['outbox_failed_count', (string) (($snapshot['notification_outbox']['failed_count'] ?? 0))],
        ['voucher_stale_lock_count', (string) (($snapshot['voucher_locks']['stale_lock_count'] ?? 0))],
        ['session_unlinked_hold_count', (string) (($snapshot['session_linkage']['active_unlinked_session_hold_count'] ?? 0))],
        ['reporting_snapshot_total_row_count', (string) (($snapshot['reporting_snapshots']['total_row_count'] ?? 0))],
        ['reporting_snapshot_populated_family_count', (string) (($snapshot['reporting_snapshots']['populated_family_count'] ?? 0))],
        ['kitchen_active_ticket_count', (string) (($snapshot['kitchen_kds']['active_ticket_count'] ?? 0))],
        ['kitchen_drift_count', (string) (($snapshot['kitchen_kds']['drift_count'] ?? 0))],
        ['inventory_issue_order_count', (string) (($snapshot['inventory_purchasing']['issue_order_count'] ?? 0))],
        ['inventory_duplicate_purchase_receipt_reference_count', (string) (($snapshot['inventory_purchasing']['duplicate_purchase_receipt_reference_count'] ?? 0))],
        ['inventory_duplicate_purchase_receipt_movement_count', (string) (($snapshot['inventory_purchasing']['duplicate_purchase_receipt_movement_count'] ?? 0))],
        ['inventory_overdue_open_order_count', (string) (($snapshot['inventory_purchasing']['overdue_open_order_count'] ?? 0))],
        ['conversation_unassigned_count', (string) (($snapshot['conversation_inbox']['unassigned_count'] ?? 0))],
        ['conversation_overdue_count', (string) (($snapshot['conversation_inbox']['overdue_count'] ?? 0))],
        ['branch_total_count', (string) (($snapshot['branch_defaults']['total_count'] ?? 0))],
        ['branch_default_count', (string) (($snapshot['branch_defaults']['default_count'] ?? 0))],
    ]);

    return collect($snapshot)->contains(fn (array $section) => (($section['status'] ?? 'ok') === 'fail')) ? 1 : 0;
})->purpose('Show booking operational snapshot for outbox, payments, reporting, kitchen, inventory, conversations, branches, and DB contract');

Artisan::command('booking:artifacts-normalize {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var ReleaseArtifactNormalizerService $service */
    $service = app(ReleaseArtifactNormalizerService::class);
    $report = $service->normalize();
    $exitCode = ($report['ok'] ?? false) ? 0 : 1;

    if ($command->option('json')) {
        $command->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $exitCode;
    }

    $command->info('Booking release artifact normalization');
    $command->newLine();

    $command->table(
        ['Artifact', 'Exists', 'Changed', 'Normalizations'],
        collect($report['artifacts'] ?? [])->map(function (array $artifact, string $name) {
            return [
                $name,
                ($artifact['exists'] ?? false) ? 'yes' : 'no',
                ($artifact['changed'] ?? false) ? 'yes' : 'no',
                implode(', ', (array) ($artifact['normalizations'] ?? [])),
            ];
        })->values()->all()
    );

    if (! empty($report['issues'])) {
        $command->error('Issues:');
        foreach ($report['issues'] as $line) {
            $command->line(' - '.$line);
        }
    }

    if ($exitCode === 0) {
        $command->info('booking:artifacts-normalize completed.');
    } else {
        $command->error('booking:artifacts-normalize failed.');
    }

    return $exitCode;
})->purpose('Normalize release SQL artifacts by stripping definers and promoting final guard columns.');

Artisan::command('booking:release-manifest {--json : Output machine-readable JSON} {--write : Persist the current snapshot to the frozen manifest path} {--verify-frozen : Compare the current snapshot against the frozen manifest snapshot}', function () use ($writeOpsGateArtifactReport) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var ReleaseArtifactManifestService $service */
    $service = app(ReleaseArtifactManifestService::class);
    $snapshot = $service->snapshot();
    $frozenSnapshot = null;

    if ((bool) $command->option('write')) {
        $snapshot = $service->writeSnapshot($snapshot);
    }

    if ((bool) $command->option('verify-frozen')) {
        $frozenSnapshot = $service->inspectFrozenSnapshot($snapshot);
    }

    $exitCode = (($snapshot['status'] ?? 'fail') === 'fail')
        || ($frozenSnapshot !== null && ! ($frozenSnapshot['ok'] ?? false))
        ? 1
        : 0;

    $payload = $snapshot;
    $payload['meta'] = array_merge((array) ($payload['meta'] ?? []), [
        'write_requested' => (bool) $command->option('write'),
        'verify_frozen_requested' => (bool) $command->option('verify-frozen'),
    ]);
    if ($frozenSnapshot !== null) {
        $payload['frozen_snapshot'] = $frozenSnapshot;
    }

    $scopeParts = ['snapshot'];
    if ((bool) $command->option('verify-frozen')) {
        $scopeParts[] = 'verify-frozen';
    }
    if ((bool) $command->option('write')) {
        $scopeParts[] = 'write';
    }

    $payload = $writeOpsGateArtifactReport(
        artifactRoot: trim((string) config('booking_ops_artifacts.release_manifest.artifact_root', 'storage/app/booking_release/release_manifest')),
        reportPrefix: 'booking-release-manifest',
        scopeKey: implode('-', $scopeParts),
        payload: $payload,
        title: 'Booking Release Manifest',
        summaryRows: [
            'status' => (string) ($payload['status'] ?? 'unknown'),
            'write_requested' => (bool) $command->option('write') ? 'yes' : 'no',
            'verify_frozen_requested' => (bool) $command->option('verify-frozen') ? 'yes' : 'no',
            'issue_count' => count((array) ($payload['issues'] ?? [])),
            'frozen_status' => (string) (($payload['frozen_snapshot'] ?? [])['status'] ?? 'not_requested'),
            'ok' => $exitCode === 0 ? 'yes' : 'no',
        ],
        artifactKey: 'report_artifacts',
    );

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $exitCode;
    }

    $rows = collect($payload['artifacts'] ?? [])->map(function (array $artifact, string $key) {
        $status = ! ($artifact['exists'] ?? false)
            ? (($artifact['optional'] ?? false) ? 'SKIP' : 'FAIL')
            : (((array) ($artifact['missing_fragments'] ?? [])) === [] ? 'OK' : 'FAIL');

        return [
            $key,
            (string) ($artifact['path'] ?? ''),
            $status,
            ($artifact['exists'] ?? false) ? 'yes' : 'no',
            isset($artifact['sha256']) ? substr((string) $artifact['sha256'], 0, 12).'â€¦' : '',
            (string) count((array) ($artifact['missing_fragments'] ?? [])),
        ];
    })->values()->all();

    $command->table(['Artifact', 'Path', 'Status', 'Exists', 'SHA256', 'Missing'], $rows);
    $command->table(['Patch metric', 'Value'], [
        ['present', (string) ($payload['patches']['count'] ?? 0)],
        ['required', (string) ($payload['patches']['required_count'] ?? 0)],
        ['missing', (string) count((array) ($payload['patches']['missing'] ?? []))],
    ]);

    foreach ((array) ($payload['issues'] ?? []) as $issue) {
        $command->line(' - '.$issue);
    }

    if ((bool) $command->option('write')) {
        $command->line('Snapshot: '.(string) ($payload['snapshot_path'] ?? ''));
    }

    if ($frozenSnapshot !== null) {
        $command->line(sprintf(
            'Frozen snapshot: %s [%s]',
            (string) ($frozenSnapshot['path'] ?? ''),
            (string) ($frozenSnapshot['status'] ?? 'unknown'),
        ));

        foreach ((array) ($frozenSnapshot['issues'] ?? []) as $issue) {
            $command->line(' - '.$issue);
        }
    }

    $command->table(['Artifact', 'Path'], [
        ['reports_root', (string) (($payload['report_artifacts'] ?? [])['reports_root'] ?? '')],
        ['json', (string) (($payload['report_artifacts'] ?? [])['json_path'] ?? '')],
        ['markdown', (string) (($payload['report_artifacts'] ?? [])['markdown_path'] ?? '')],
        ['latest_json', (string) (($payload['report_artifacts'] ?? [])['latest_json_path'] ?? '')],
        ['latest_markdown', (string) (($payload['report_artifacts'] ?? [])['latest_markdown_path'] ?? '')],
    ]);

    $command->info(sprintf('booking:release-manifest %s.', $exitCode === 0 ? 'completed' : 'failed'));

    return $exitCode;
})->purpose('Show release artifact checksums, required contract fragments, and SQL patch inventory');

Artisan::command('booking:core-ops-gate {--json : Output machine-readable JSON} {--write : Persist the current snapshot to the frozen snapshot path}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var CoreOpsGateService $service */
    $service = app(CoreOpsGateService::class);
    $snapshot = $service->run((bool) $command->option('write'));
    $exitCode = ($snapshot['ok'] ?? false) ? 0 : 1;

    if ($command->option('json')) {
        $command->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $exitCode;
    }

    $command->info('Booking core ops gate');
    $command->newLine();

    $command->table(
        ['Key', 'Category', 'Status', 'Duration (ms)', 'Path'],
        collect($snapshot['tests'] ?? [])->map(static function (array $test) {
            return [
                (string) ($test['key'] ?? ''),
                (string) ($test['category'] ?? 'feature'),
                ($test['ok'] ?? false) ? 'OK' : 'FAIL',
                (string) ($test['duration_ms'] ?? 0),
                (string) ($test['path'] ?? ''),
            ];
        })->values()->all()
    );

    $summary = (array) ($snapshot['summary'] ?? []);
    $command->table(['Summary', 'Value'], [
        ['total', (string) ($summary['total'] ?? 0)],
        ['passed', (string) ($summary['passed'] ?? 0)],
        ['failed', (string) ($summary['failed'] ?? 0)],
    ]);

    if ((bool) $command->option('write')) {
        $command->line('Snapshot: '.(string) ($snapshot['snapshot_path'] ?? ''));
    }

    $command->info(sprintf('booking:core-ops-gate %s.', $exitCode === 0 ? 'completed' : 'failed'));

    return $exitCode;
})->purpose('Execute the canonical core ops gate and optionally persist its snapshot.');

Artisan::command('booking:round5-gate {--json : Output machine-readable JSON} {--write : Persist the current snapshot to the frozen snapshot path}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var RoundFiveGateService $service */
    $service = app(RoundFiveGateService::class);
    $snapshot = $service->run((bool) $command->option('write'));
    $exitCode = ($snapshot['ok'] ?? false) ? 0 : 1;

    if ($command->option('json')) {
        $command->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $exitCode;
    }

    $command->info('Booking Round 5 gate');
    $command->newLine();

    $command->table(
        ['Key', 'Category', 'Status', 'Duration (ms)', 'Path'],
        collect($snapshot['tests'] ?? [])->map(static function (array $test) {
            return [
                (string) ($test['key'] ?? ''),
                (string) ($test['category'] ?? 'feature'),
                ($test['ok'] ?? false) ? 'OK' : 'FAIL',
                (string) ($test['duration_ms'] ?? 0),
                (string) ($test['path'] ?? ''),
            ];
        })->values()->all()
    );

    $summary = (array) ($snapshot['summary'] ?? []);
    $command->table(['Summary', 'Value'], [
        ['total', (string) ($summary['total'] ?? 0)],
        ['passed', (string) ($summary['passed'] ?? 0)],
        ['failed', (string) ($summary['failed'] ?? 0)],
    ]);

    if ((bool) $command->option('write')) {
        $command->line('Snapshot: '.(string) ($snapshot['snapshot_path'] ?? ''));
    }

    $command->info(sprintf('booking:round5-gate %s.', $exitCode === 0 ? 'completed' : 'failed'));

    return $exitCode;
})->purpose('Execute the canonical Round 5 financial gate and optionally persist its snapshot.');

Artisan::command('booking:alert-check {--json : Output machine-readable JSON} {--dry-run : Build alerts but do not dispatch them} {--fail-on-alert : Exit non-zero when actionable alerts exist} {--payment-sample-limit=10 : Payment sample size for the snapshot}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var OperationalAlertService $service */
    $service = app(OperationalAlertService::class);
    $sampleLimit = max(1, (int) $command->option('payment-sample-limit'));
    $capturedAt = Carbon::now('UTC');
    $snapshot = $service->snapshot($capturedAt, $sampleLimit);
    $alerts = $service->buildAlerts($snapshot, $capturedAt);
    $dispatch = $service->dispatchAlerts($alerts, (bool) $command->option('dry-run'), $capturedAt);

    $payload = [
        'snapshot' => $snapshot,
        'alerts' => $alerts,
        'dispatch' => $dispatch,
        'fail_on_alert' => (bool) $command->option('fail-on-alert'),
    ];

    $hasAlerts = ((int) ($dispatch['triggered_count'] ?? count($alerts))) > 0 || count($alerts) > 0;
    $exitCode = ((bool) $command->option('fail-on-alert') && $hasAlerts) ? 1 : 0;

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $exitCode;
    }

    $command->info('Booking alert check');
    $command->newLine();
    $command->table(['Section', 'Status', 'Severity', 'Reasons'], collect($alerts)->map(static function (array $alert) {
        return [
            (string) ($alert['section'] ?? ''),
            (string) ($alert['status'] ?? ''),
            (string) ($alert['severity'] ?? ''),
            implode(', ', array_map('strval', (array) ($alert['reasons'] ?? []))),
        ];
    })->values()->all());

    foreach ((array) ($dispatch['results'] ?? []) as $result) {
        $line = sprintf('%s [%s]', (string) ($result['section'] ?? ($result['alert']['section'] ?? 'unknown')), (string) ($result['severity'] ?? ($result['alert']['severity'] ?? 'unknown')));
        if (! empty($result['suppression_reason'])) {
            $line .= ' - '.(string) $result['suppression_reason'];
        }
        $command->line($line);
    }

    if ($exitCode === 0) {
        $command->info('booking:alert-check completed.');
    } else {
        $command->error('booking:alert-check detected actionable issues.');
    }

    return $exitCode;
})->purpose('Evaluate operational alerts from booking health snapshots and optionally fail on actionable issues.');

Artisan::command('booking:package-release {--json : Output machine-readable JSON} {--verify-frozen : Verify the frozen release manifest before packaging} {--overwrite : Overwrite an existing package with the same package id} {--package-id= : Explicit package id to use}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;
    /** @var ReleasePackageService $service */
    $service = app(ReleasePackageService::class);

    $report = $service->package(
        $command->option('package-id') ? (string) $command->option('package-id') : null,
        verifyFrozen: (bool) $command->option('verify-frozen'),
        overwrite: (bool) $command->option('overwrite'),
    );
    $exitCode = ($report['ok'] ?? false) ? 0 : 1;

    if ($command->option('json')) {
        $command->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $exitCode;
    }

    $command->info('Booking release packaging');
    $command->newLine();
    $command->table(['Field', 'Value'], [
        ['package_id', (string) ($report['package_id'] ?? '')],
        ['package_basename', (string) ($report['package_basename'] ?? '')],
        ['package_path', (string) ($report['package_path'] ?? '')],
        ['package_sha256', (string) ($report['package_sha256'] ?? '')],
        ['output_root', (string) ($report['output_root'] ?? '')],
        ['stage_path', (string) ($report['stage_path'] ?? '')],
        ['package_exists', ((bool) ($report['package_exists'] ?? false)) ? 'yes' : 'no'],
    ]);
    if ((array) ($report['sidecars'] ?? []) !== []) {
        $command->table(['Sidecar', 'Path'], collect((array) ($report['sidecars'] ?? []))
            ->map(static fn (mixed $path, string $key): array => [$key, (string) $path])
            ->values()
            ->all());
    }

    if (! empty($report['issues'])) {
        foreach ((array) $report['issues'] as $issue) {
            $command->line($issue);
        }
    }
    if (! empty($report['warnings'])) {
        foreach ((array) $report['warnings'] as $warning) {
            $command->line($warning);
        }
    }

    if ($exitCode === 0) {
        $command->info('booking:package-release completed.');
    } else {
        $command->error('booking:package-release failed.');
    }

    return $exitCode;
})->purpose('Create an immutable release package and sidecar inventory from the already-frozen RestaurantPOS release artifacts.');

Artisan::command('booking:manual-evidence:init
    {--target=staging : staging|limited-production}
    {--candidate= : Candidate slug used in the default output filename}
    {--output= : Optional absolute or repo-relative JSON output path}
    {--overwrite : Overwrite an existing template file}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $target = strtolower(trim((string) $command->option('target')));
    if (! in_array($target, ['staging', 'limited-production'], true)) {
        $command->error('Invalid --target. Supported values: staging, limited-production.');

        return 1;
    }

    /** @var LaunchReadinessManualEvidenceTemplateService $service */
    $service = app(LaunchReadinessManualEvidenceTemplateService::class);
    $payload = $service->scaffold(
        target: $target,
        candidate: ($value = trim((string) ($command->option('candidate') ?? ''))) !== '' ? $value : null,
        outputPath: ($value = trim((string) ($command->option('output') ?? ''))) !== '' ? $value : null,
        overwrite: (bool) $command->option('overwrite'),
    );
    $exitCode = (bool) ($payload['ok'] ?? false) ? 0 : 1;

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $exitCode;
    }

    $command->info('Booking launch-readiness manual evidence template');
    $command->newLine();
    $command->table(['Field', 'Value'], [
        ['target', (string) ($payload['target'] ?? $target)],
        ['candidate', (string) ($payload['candidate'] ?? '')],
        ['output_path', (string) ($payload['output_path'] ?? '')],
        ['check_count', (string) ($payload['check_count'] ?? 0)],
    ]);
    $command->table(['Check', 'Source', 'Required'], collect((array) ($payload['manual_checks'] ?? []))
        ->map(static fn (array $row): array => [
            (string) ($row['label'] ?? $row['key'] ?? ''),
            (string) ($row['source'] ?? ''),
            ((bool) ($row['required_for_target'] ?? false)) ? 'yes' : 'no',
        ])->values()->all());

    if ((array) ($payload['issues'] ?? []) !== []) {
        foreach ((array) ($payload['issues'] ?? []) as $issue) {
            $command->line((string) $issue);
        }
    }

    $command->line('Next command: '.(string) ($payload['next_command'] ?? ''));

    if ($exitCode === 0) {
        $command->info('booking:manual-evidence:init completed.');
    } else {
        $command->error('booking:manual-evidence:init failed.');
    }

    return $exitCode;
})->purpose('Scaffold an operator-owned manual evidence JSON template for booking launch readiness.');

Artisan::command('booking:release-loop
    {--target=staging : staging|limited-production}
    {--manual-evidence= : Optional launch-readiness manual evidence path}
    {--package-id= : Explicit immutable package id for launch-readiness packaging}
    {--overwrite-package : Overwrite an existing immutable package id}
    {--manifest-path= : Absolute or repo-relative UAT manifest path}
    {--bootstrap-uat : Reset and seed the canonical UAT scenario pack before smoke/readiness}
    {--base-url= : Base API URL used when bootstrapping a fresh UAT scenario pack}
    {--preview-command= : Optional shell command that creates or refreshes the preview deployment}
    {--preview-url= : Preview deployment URL to record in release evidence}
    {--preview-label=preview : Label recorded for the preview deployment}
    {--skip-preview : Skip the preview deployment stage}
    {--staff-web-dir=staff-web : Repo-relative or absolute path to the staff-web workspace}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $target = strtolower(trim((string) $command->option('target')));
    if (! in_array($target, ['staging', 'limited-production'], true)) {
        $command->error('Invalid --target. Supported values: staging, limited-production.');

        return 1;
    }

    /** @var ReleaseLoopService $service */
    $service = app(ReleaseLoopService::class);
    $payload = $service->run(
        target: $target,
        manualEvidencePath: ($value = trim((string) ($command->option('manual-evidence') ?? ''))) !== '' ? $value : null,
        packageId: ($value = trim((string) ($command->option('package-id') ?? ''))) !== '' ? $value : null,
        overwritePackage: (bool) $command->option('overwrite-package'),
        manifestPath: ($value = trim((string) ($command->option('manifest-path') ?? ''))) !== '' ? $value : null,
        bootstrapUat: (bool) $command->option('bootstrap-uat'),
        baseUrl: ($value = trim((string) ($command->option('base-url') ?? ''))) !== '' ? $value : null,
        previewCommand: ($value = trim((string) ($command->option('preview-command') ?? ''))) !== '' ? $value : null,
        previewUrl: ($value = trim((string) ($command->option('preview-url') ?? ''))) !== '' ? $value : null,
        previewLabel: ($value = trim((string) ($command->option('preview-label') ?? ''))) !== '' ? $value : 'preview',
        skipPreview: (bool) $command->option('skip-preview'),
        staffWebDir: trim((string) ($command->option('staff-web-dir') ?? 'staff-web')) !== ''
            ? trim((string) ($command->option('staff-web-dir') ?? 'staff-web'))
            : 'staff-web',
    );

    $exitCode = (bool) ($payload['ok'] ?? false) ? 0 : 1;

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $exitCode;
    }

    $command->info('Booking release loop');
    $command->newLine();
    $previewDisplay = trim((string) data_get($payload, 'preview.url', ''));
    if ($previewDisplay === '') {
        $previewDisplay = trim((string) data_get($payload, 'preview.label', ''));
    }
    if ($previewDisplay === '') {
        $previewDisplay = 'not-configured';
    }
    $command->table(['Field', 'Value'], [
        ['target', (string) data_get($payload, 'target.label', $target)],
        ['decision', (string) ($payload['decision'] ?? 'unknown')],
        ['steps', (string) data_get($payload, 'summary.step_count', 0)],
        ['pass_count', (string) data_get($payload, 'summary.pass_count', 0)],
        ['skip_count', (string) data_get($payload, 'summary.skip_count', 0)],
        ['fail_count', (string) data_get($payload, 'summary.fail_count', 0)],
        ['preview', $previewDisplay],
        ['preview_status', (string) data_get($payload, 'preview.status', 'unknown')],
        ['observability', (string) data_get($payload, 'observability.status', 'unknown')],
        ['release_tag', (string) data_get($payload, 'observability.release', 'n/a')],
    ]);

    $command->table(['Step', 'Status', 'Summary'], collect((array) ($payload['steps'] ?? []))
        ->map(static fn (array $step): array => [
            (string) ($step['label'] ?? $step['key'] ?? ''),
            strtoupper((string) ($step['status'] ?? 'unknown')),
            (string) ($step['summary'] ?? ''),
        ])->values()->all());

    if ((array) ($payload['blocking_failures'] ?? []) !== []) {
        $command->error('Blocking failures:');
        foreach ((array) ($payload['blocking_failures'] ?? []) as $failure) {
            $command->line(sprintf(
                ' - [%s] %s',
                (string) ($failure['step_label'] ?? $failure['step_key'] ?? 'step'),
                (string) ($failure['message'] ?? '')
            ));
        }
    }

    if ((array) ($payload['follow_up_actions'] ?? []) !== []) {
        $command->warn('Follow-up actions:');
        foreach ((array) ($payload['follow_up_actions'] ?? []) as $action) {
            $command->line(sprintf(
                ' - [%s] %s',
                strtoupper((string) ($action['kind'] ?? 'action')),
                (string) ($action['label'] ?? 'follow-up')
            ));
            foreach ((array) ($action['commands'] ?? []) as $step) {
                $command->line('   command: '.(string) $step);
            }
            foreach ((array) ($action['notes'] ?? []) as $note) {
                $command->line('   note: '.(string) $note);
            }
        }
    }

    if ((array) ($payload['release_handoff'] ?? []) !== []) {
        $command->table(['Release handoff', 'Value'], [
            ['package_basename', (string) data_get($payload, 'release_handoff.candidate.package_basename', '')],
            ['package_path', (string) data_get($payload, 'release_handoff.candidate.package_path', '')],
            ['manual_evidence', (string) (data_get($payload, 'release_handoff.manual_evidence.path') ?: 'not-supplied')],
            ['launch_readiness', (string) data_get($payload, 'release_handoff.launch_readiness.decision', 'unavailable')],
            ['preview_status', (string) data_get($payload, 'release_handoff.preview.status', 'unknown')],
            ['observability_status', (string) data_get($payload, 'release_handoff.observability.status', 'unknown')],
        ]);
    }

    $command->table(['Artifact', 'Path'], [
        ['reports_root', (string) data_get($payload, 'artifacts.reports_root', '')],
        ['json', (string) data_get($payload, 'artifacts.json_path', '')],
        ['markdown', (string) data_get($payload, 'artifacts.markdown_path', '')],
        ['latest_json', (string) data_get($payload, 'artifacts.latest_json_path', '')],
        ['latest_markdown', (string) data_get($payload, 'artifacts.latest_markdown_path', '')],
    ]);

    if ($exitCode === 0) {
        $command->info('booking:release-loop completed.');
    } else {
        $command->error('booking:release-loop failed.');
    }

    return $exitCode;
})->purpose('Run the canonical backend + staff-web release loop, including smoke and release evidence artifacts.');

Artisan::command('booking:launch-readiness
    {--target=staging : staging|limited-production}
    {--manual-evidence= : Optional JSON file containing manual verification evidence}
    {--package-id= : Override the immutable release package identifier}
    {--overwrite-package : Overwrite an existing immutable package with the same package id}
    {--payment-sample-limit=10 : Payment sample size for alert snapshot evaluation}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var LaunchReadinessService $service */
    $service = app(LaunchReadinessService::class);
    $target = strtolower(trim((string) $command->option('target')));
    if (! in_array($target, ['staging', 'limited-production'], true)) {
        $command->error('Invalid --target. Supported values: staging, limited-production.');

        return 1;
    }

    $payload = $service->evaluate(
        target: $target,
        manualEvidencePath: ($value = trim((string) ($command->option('manual-evidence') ?? ''))) !== '' ? $value : null,
        packageId: ($value = trim((string) ($command->option('package-id') ?? ''))) !== '' ? $value : null,
        overwritePackage: (bool) $command->option('overwrite-package'),
        paymentSampleLimit: max(1, (int) $command->option('payment-sample-limit')),
    );

    $exitCode = (int) ($payload['exit_code'] ?? 1);

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $exitCode;
    }

    $command->info('Booking launch readiness');
    $command->newLine();
    $command->table(['Field', 'Value'], [
        ['target', (string) (($payload['target'] ?? [])['label'] ?? ($payload['target'] ?? [])['key'] ?? '')],
        ['decision', (string) ($payload['decision'] ?? 'unknown')],
        ['exit_code', (string) $exitCode],
        ['evaluated_at_utc', (string) (($payload['meta'] ?? [])['evaluated_at_utc'] ?? '')],
    ]);

    $command->table(
        ['Group', 'Status', 'Blocking', 'Warnings'],
        collect((array) ($payload['groups'] ?? []))->map(static function (array $group): array {
            return [
                (string) ($group['label'] ?? $group['key'] ?? ''),
                strtoupper((string) ($group['status'] ?? 'unknown')),
                (string) ($group['blocking_failure_count'] ?? 0),
                (string) ($group['major_warning_count'] ?? 0),
            ];
        })->values()->all()
    );

    $command->table(
        ['Group', 'Check', 'Source', 'Status', 'Severity'],
        collect(array_merge((array) ($payload['checks'] ?? []), (array) ($payload['manual_checks'] ?? [])))->map(static function (array $check): array {
            return [
                (string) (((array) config('booking_launch_readiness.groups', []))[(string) ($check['group'] ?? '')] ?? ($check['group'] ?? '')),
                (string) ($check['label'] ?? $check['key'] ?? ''),
                (string) ($check['source'] ?? ''),
                strtoupper((string) ($check['status'] ?? 'unknown')),
                strtoupper((string) ($check['severity'] ?? 'info')),
            ];
        })->values()->all()
    );

    if ((array) ($payload['blocking_failures'] ?? []) !== []) {
        $command->error('Blocking failures:');
        foreach ((array) ($payload['blocking_failures'] ?? []) as $finding) {
            $command->line(sprintf(' - [%s] %s', (string) ($finding['check_label'] ?? $finding['check_key'] ?? 'unknown'), (string) ($finding['message'] ?? '')));
        }
    }

    if ((array) ($payload['major_warnings'] ?? []) !== []) {
        $command->warn('Major warnings:');
        foreach ((array) ($payload['major_warnings'] ?? []) as $finding) {
            $command->line(sprintf(' - [%s] %s', (string) ($finding['check_label'] ?? $finding['check_key'] ?? 'unknown'), (string) ($finding['message'] ?? '')));
        }
    }

    if ((array) (($payload['manual_evidence'] ?? [])['issues'] ?? []) !== []) {
        $command->warn('Manual evidence issues:');
        foreach ((array) (($payload['manual_evidence'] ?? [])['issues'] ?? []) as $issue) {
            $command->line(' - '.(string) $issue);
        }
    }

    if ((array) ($payload['follow_up_actions'] ?? []) !== []) {
        $command->warn('Follow-up actions:');
        foreach ((array) ($payload['follow_up_actions'] ?? []) as $action) {
            $command->line(sprintf(
                ' - [%s] %s',
                strtoupper((string) ($action['kind'] ?? 'action')),
                (string) ($action['label'] ?? 'follow-up')
            ));
            foreach ((array) ($action['commands'] ?? []) as $step) {
                $command->line('   command: '.(string) $step);
            }
            foreach ((array) ($action['notes'] ?? []) as $note) {
                $command->line('   note: '.(string) $note);
            }
        }
    }

    if ((array) ($payload['release_handoff'] ?? []) !== []) {
        $command->table(['Release handoff', 'Value'], [
            ['package_basename', (string) data_get($payload, 'release_handoff.candidate.package_basename', '')],
            ['package_path', (string) data_get($payload, 'release_handoff.candidate.package_path', '')],
            ['release_manifest_snapshot', (string) data_get($payload, 'release_handoff.candidate.release_manifest_snapshot_path', '')],
            ['manual_evidence', (string) (data_get($payload, 'release_handoff.manual_evidence.path') ?: 'not-supplied')],
            ['required_manual_checks', sprintf(
                '%d/%d pass',
                (int) data_get($payload, 'release_handoff.manual_evidence.required_pass_count', 0),
                (int) data_get($payload, 'release_handoff.manual_evidence.required_check_count', 0),
            )],
        ]);
    }

    $command->table(['Artifact', 'Path'], [
        ['reports_root', (string) (($payload['artifacts'] ?? [])['reports_root'] ?? '')],
        ['json', (string) (($payload['artifacts'] ?? [])['json_path'] ?? '')],
        ['markdown', (string) (($payload['artifacts'] ?? [])['markdown_path'] ?? '')],
        ['latest_json', (string) (($payload['artifacts'] ?? [])['latest_json_path'] ?? '')],
        ['latest_markdown', (string) (($payload['artifacts'] ?? [])['latest_markdown_path'] ?? '')],
    ]);

    if ($exitCode === 0) {
        $command->info('booking:launch-readiness passed with no warnings.');
    } elseif ($exitCode === 2) {
        $command->warn('booking:launch-readiness completed with warnings.');
    } else {
        $command->error('booking:launch-readiness failed.');
    }

    return $exitCode;
})->purpose('Run the canonical launch-readiness matrix and persist JSON/Markdown release evidence.');

Artisan::command('booking:performance-verify
    {--profile=local : local|staging}
    {--base-url= : Base API URL used when --run executes live scenarios}
    {--manifest-path=storage/app/uat/scenario-pack.json : UAT scenario manifest path for live scenario execution}
    {--scenario=* : Optional scenario keys to run/evaluate}
    {--run : Execute automated scenarios through the repo-native performance harness}
    {--ingest-dir= : Existing raw result directory to evaluate instead of running}
    {--baseline= : Optional baseline snapshot path}
    {--promote-baseline : Save the current report as the latest baseline when blockers are absent}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var PerformanceVerificationService $service */
    $service = app(PerformanceVerificationService::class);
    $profile = strtolower(trim((string) $command->option('profile')));
    if (! in_array($profile, ['local', 'staging'], true)) {
        $command->error('Invalid --profile. Supported values: local, staging.');

        return 1;
    }

    $run = (bool) $command->option('run');
    $ingestDirOption = trim((string) ($command->option('ingest-dir') ?? ''));
    if ($run && $ingestDirOption !== '') {
        $command->error('Use either --run or --ingest-dir, not both.');

        return 1;
    }

    if (! $run && $ingestDirOption === '') {
        $command->error('Provide --run to execute scenarios or --ingest-dir to evaluate existing raw results.');

        return 1;
    }

    $scenarioKeys = collect((array) $command->option('scenario'))
        ->map(static fn (mixed $value): string => trim((string) $value))
        ->filter(static fn (string $value): bool => $value !== '')
        ->values()
        ->all();

    $runMeta = [];
    $ingestDir = $ingestDirOption;

    if ($run) {
        $baseUrl = trim((string) ($command->option('base-url') ?? ''));
        if ($baseUrl === '') {
            $command->error('The --base-url option is required when --run is used.');

            return 1;
        }

        try {
            $runMeta = $service->runHarness(
                profile: $profile,
                baseUrl: $baseUrl,
                manifestPath: trim((string) ($command->option('manifest-path') ?? 'storage/app/uat/scenario-pack.json')),
                scenarioKeys: $scenarioKeys,
            );
        } catch (Throwable $exception) {
            $payload = [
                'ok' => false,
                'decision' => 'fail',
                'exit_code' => 1,
                'error' => 'runner_failed',
                'message' => $exception->getMessage(),
            ];

            if ($command->option('json')) {
                $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return 1;
            }

            $command->error((string) $payload['message']);

            return 1;
        }

        $ingestDir = (string) ($runMeta['raw_dir'] ?? '');
    }

    try {
        $payload = $service->evaluate(
            profile: $profile,
            ingestDir: $ingestDir,
            baselinePath: ($value = trim((string) ($command->option('baseline') ?? ''))) !== '' ? $value : null,
            promoteBaseline: (bool) $command->option('promote-baseline'),
            scenarioKeys: $scenarioKeys,
            runMeta: $runMeta,
        );
    } catch (Throwable $exception) {
        $errorPayload = [
            'ok' => false,
            'decision' => 'fail',
            'exit_code' => 1,
            'error' => 'evaluation_failed',
            'message' => $exception->getMessage(),
        ];

        if ($command->option('json')) {
            $command->line(json_encode($errorPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return 1;
        }

        $command->error((string) $errorPayload['message']);

        return 1;
    }

    $exitCode = (int) ($payload['exit_code'] ?? 1);

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $exitCode;
    }

    $command->info('Booking performance verification');
    $command->newLine();
    $command->table(['Field', 'Value'], [
        ['profile', (string) (($payload['profile'] ?? [])['label'] ?? ($payload['profile'] ?? [])['key'] ?? '')],
        ['decision', (string) ($payload['decision'] ?? 'unknown')],
        ['exit_code', (string) $exitCode],
        ['evaluated_at_utc', (string) (($payload['meta'] ?? [])['evaluated_at_utc'] ?? '')],
        ['ingest_dir', (string) (($payload['raw_artifacts'] ?? [])['ingest_dir'] ?? '')],
    ]);

    $command->table(
        ['Group', 'Status', 'Blocking', 'Warnings'],
        collect((array) ($payload['groups'] ?? []))->map(static function (array $group): array {
            return [
                (string) ($group['label'] ?? $group['key'] ?? ''),
                strtoupper((string) ($group['status'] ?? 'unknown')),
                (string) ($group['blocking_failure_count'] ?? 0),
                (string) ($group['major_warning_count'] ?? 0),
            ];
        })->values()->all()
    );

    $command->table(
        ['Scenario', 'Type', 'Automation', 'Status', 'p95 ms', 'Error rate', 'Throughput'],
        collect((array) ($payload['scenarios'] ?? []))->map(static function (array $scenario): array {
            $metrics = (array) ($scenario['metrics'] ?? []);

            return [
                (string) ($scenario['label'] ?? $scenario['key'] ?? ''),
                (string) ($scenario['type'] ?? ''),
                (string) ($scenario['automation'] ?? ''),
                strtoupper((string) ($scenario['status'] ?? 'unknown')),
                number_format((float) ($metrics['latency_p95_ms'] ?? 0.0), 2, '.', ''),
                number_format((float) ($metrics['unexpected_error_rate'] ?? 0.0), 4, '.', ''),
                number_format((float) ($metrics['throughput_rps'] ?? 0.0), 2, '.', ''),
            ];
        })->values()->all()
    );

    if ((array) ($payload['blocking_failures'] ?? []) !== []) {
        $command->error('Blocking failures:');
        foreach ((array) ($payload['blocking_failures'] ?? []) as $finding) {
            $command->line(sprintf(' - [%s] %s', (string) ($finding['scenario_label'] ?? $finding['scenario_key'] ?? 'unknown'), (string) ($finding['message'] ?? '')));
        }
    }

    if ((array) ($payload['major_warnings'] ?? []) !== []) {
        $command->warn('Major warnings:');
        foreach ((array) ($payload['major_warnings'] ?? []) as $finding) {
            $command->line(sprintf(' - [%s] %s', (string) ($finding['scenario_label'] ?? $finding['scenario_key'] ?? 'unknown'), (string) ($finding['message'] ?? '')));
        }
    }

    if ((array) ($payload['top_bottlenecks'] ?? []) !== []) {
        $command->info('Top bottlenecks:');
        foreach ((array) ($payload['top_bottlenecks'] ?? []) as $row) {
            $command->line(sprintf(
                ' - [%s] score=%s p95=%sms unexpected_error_rate=%s throughput=%srps',
                (string) ($row['scenario_label'] ?? $row['scenario_key'] ?? 'unknown'),
                number_format((float) ($row['score'] ?? 0.0), 4, '.', ''),
                number_format((float) ($row['p95_latency_ms'] ?? 0.0), 2, '.', ''),
                number_format((float) ($row['unexpected_error_rate'] ?? 0.0), 4, '.', ''),
                number_format((float) ($row['throughput_rps'] ?? 0.0), 2, '.', '')
            ));
        }
    }

    $command->table(['Artifact', 'Path'], [
        ['reports_root', (string) (($payload['artifacts'] ?? [])['reports_root'] ?? '')],
        ['json', (string) (($payload['artifacts'] ?? [])['json_path'] ?? '')],
        ['markdown', (string) (($payload['artifacts'] ?? [])['markdown_path'] ?? '')],
        ['latest_json', (string) (($payload['artifacts'] ?? [])['latest_json_path'] ?? '')],
        ['latest_markdown', (string) (($payload['artifacts'] ?? [])['latest_markdown_path'] ?? '')],
        ['baseline', (string) (($payload['baseline'] ?? [])['path'] ?? '')],
        ['latest_baseline', (string) (($payload['baseline'] ?? [])['latest_path'] ?? '')],
    ]);

    if ($exitCode === 0) {
        $command->info('booking:performance-verify passed with no warnings.');
    } elseif ($exitCode === 2) {
        $command->warn('booking:performance-verify completed with warnings.');
    } else {
        $command->error('booking:performance-verify failed.');
    }

    return $exitCode;
})->purpose('Run or evaluate the canonical performance/resilience verification matrix with JSON/Markdown artifacts.');

Artisan::command('booking:dr-drill
    {--mode=metadata-verify : metadata-verify|dry-restore|full-isolated-restore}
    {--manifest= : Explicit backup manifest path}
    {--backup-dir= : Directory containing manifest.json}
    {--backup-root= : Override backup root or fresh backup output root}
    {--capture-backup : Create a fresh backup with tools/mysql/backup_release.php before running the drill}
    {--target-host= : Scratch restore host}
    {--target-port= : Scratch restore port}
    {--target-user= : Scratch restore user}
    {--target-password= : Scratch restore password}
    {--target-db= : Scratch restore database}
    {--drop-target-first : Drop and recreate the scratch target before import}
    {--allow-nonempty-target : Allow restore into a non-empty scratch target}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var DisasterRecoveryDrillService $service */
    $service = app(DisasterRecoveryDrillService::class);
    $mode = strtolower(trim((string) $command->option('mode')));
    if (! in_array($mode, ['metadata-verify', 'dry-restore', 'full-isolated-restore'], true)) {
        $command->error('Invalid --mode. Supported values: metadata-verify, dry-restore, full-isolated-restore.');

        return 1;
    }

    $payload = $service->run(
        mode: $mode,
        manifestPath: ($value = trim((string) ($command->option('manifest') ?? ''))) !== '' ? $value : null,
        backupDir: ($value = trim((string) ($command->option('backup-dir') ?? ''))) !== '' ? $value : null,
        backupRoot: ($value = trim((string) ($command->option('backup-root') ?? ''))) !== '' ? $value : null,
        captureBackup: (bool) $command->option('capture-backup'),
        targetOverrides: [
            'host' => ($value = trim((string) ($command->option('target-host') ?? ''))) !== '' ? $value : null,
            'port' => ($value = trim((string) ($command->option('target-port') ?? ''))) !== '' ? $value : null,
            'username' => ($value = trim((string) ($command->option('target-user') ?? ''))) !== '' ? $value : null,
            'password' => ($value = (string) ($command->option('target-password') ?? '')) !== '' ? $value : null,
            'database' => ($value = trim((string) ($command->option('target-db') ?? ''))) !== '' ? $value : null,
        ],
        dropTargetFirst: (bool) $command->option('drop-target-first'),
        allowNonemptyTarget: (bool) $command->option('allow-nonempty-target'),
    );

    $exitCode = (int) ($payload['exit_code'] ?? 1);

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $exitCode;
    }

    $command->info('Booking disaster recovery drill');
    $command->newLine();
    $command->table(
        ['Field', 'Value'],
        [
            ['mode', (string) data_get($payload, 'mode.label', $mode)],
            ['evidence_level', (string) data_get($payload, 'mode.evidence_level', '')],
            ['decision', strtoupper((string) ($payload['decision'] ?? 'unknown'))],
            ['exit_code', (string) $exitCode],
            ['claim', (string) data_get($payload, 'mode.claim', '')],
            ['reports_root', (string) data_get($payload, 'artifacts.reports_root', '')],
            ['json_artifact', (string) data_get($payload, 'artifacts.json_path', '')],
            ['markdown_artifact', (string) data_get($payload, 'artifacts.markdown_path', '')],
            ['latest_json_artifact', (string) data_get($payload, 'artifacts.latest_json_path', '')],
            ['latest_markdown_artifact', (string) data_get($payload, 'artifacts.latest_markdown_path', '')],
        ],
    );

    $command->newLine();
    $command->table(
        ['Group', 'Status', 'Blocking', 'Warnings'],
        collect((array) ($payload['groups'] ?? []))->map(static function (array $group): array {
            return [
                (string) ($group['label'] ?? $group['key'] ?? ''),
                strtoupper((string) ($group['status'] ?? 'unknown')),
                (string) ($group['blocking_failure_count'] ?? 0),
                (string) ($group['major_warning_count'] ?? 0),
            ];
        })->values()->all(),
    );

    $command->newLine();
    $command->table(
        ['Check', 'Source', 'Status', 'Summary'],
        collect((array) ($payload['checks'] ?? []))->map(static function (array $check): array {
            return [
                (string) ($check['label'] ?? $check['key'] ?? ''),
                (string) ($check['source'] ?? ''),
                strtoupper((string) ($check['status'] ?? 'unknown')),
                (string) ($check['summary'] ?? ''),
            ];
        })->values()->all(),
    );

    if ((array) ($payload['blocking_failures'] ?? []) !== []) {
        $command->newLine();
        $command->error('Blocking failures:');
        foreach ((array) ($payload['blocking_failures'] ?? []) as $finding) {
            $command->line(sprintf(
                ' - [%s] %s',
                (string) ($finding['check_label'] ?? $finding['check_key'] ?? 'unknown'),
                (string) ($finding['message'] ?? '')
            ));
        }
    }

    if ((array) ($payload['major_warnings'] ?? []) !== []) {
        $command->newLine();
        $command->warn('Major warnings:');
        foreach ((array) ($payload['major_warnings'] ?? []) as $finding) {
            $command->line(sprintf(
                ' - [%s] %s',
                (string) ($finding['check_label'] ?? $finding['check_key'] ?? 'unknown'),
                (string) ($finding['message'] ?? '')
            ));
        }
    }

    if ($exitCode === 0) {
        $command->info('booking:dr-drill passed with no warnings.');
    } elseif ($exitCode === 2) {
        $command->warn('booking:dr-drill completed with warnings.');
    } else {
        $command->error('booking:dr-drill failed.');
    }

    return $exitCode;
})->purpose('Run the canonical disaster-recovery drill pack with JSON/Markdown evidence artifacts.');
