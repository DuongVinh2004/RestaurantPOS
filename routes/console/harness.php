<?php

declare(strict_types=1);

use App\Platform\Harness\HarnessSuiteService;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Support\Facades\Artisan;

Artisan::command('booking:harness:web-auth {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $payload = app(HarnessSuiteService::class)->buildWebAuthReport();
    } catch (Throwable $exception) {
        if ($command->option('json')) {
            $command->line(json_encode([
                'ok' => false,
                'error' => 'web_auth_harness_failed',
                'message' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return 1;
        }

        $command->error($exception->getMessage());

        return 1;
    }

    $exitCode = (bool) ($payload['ok'] ?? false) ? 0 : 1;

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $exitCode;
    }

    $command->info('Booking web auth/session harness');
    $command->table(['Header', 'Value'], collect((array) ($payload['headers'] ?? []))
        ->map(static fn (string $value, string $key): array => [$key, $value])
        ->values()
        ->all());
    $command->table(['Check', 'Severity', 'Status', 'Message'], collect((array) ($payload['checks'] ?? []))
        ->map(static fn (array $check): array => [
            (string) ($check['key'] ?? ''),
            strtoupper((string) ($check['severity'] ?? 'error')),
            (bool) ($check['ok'] ?? false) ? 'OK' : 'FAIL',
            (string) ($check['message'] ?? ''),
        ])->values()->all());

    return $exitCode;
})->purpose('Summarize and verify the split web auth/session contract for customer-web and staff-web.');

Artisan::command('booking:harness:fe-contract
    {--refresh-openapi : Refresh the frozen OpenAPI artifact before generating FE artifacts}
    {--output-root= : Override the consumer artifact output root}
    {--spec-path= : Override the OpenAPI artifact path}
    {--uat-manifest= : Generate a UAT-ready Postman environment from the provided manifest path}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $payload = app(HarnessSuiteService::class)->buildFeContractReport(
            outputRoot: ($value = trim((string) ($command->option('output-root') ?? ''))) !== '' ? $value : null,
            specPath: ($value = trim((string) ($command->option('spec-path') ?? ''))) !== '' ? $value : null,
            refreshOpenApi: (bool) $command->option('refresh-openapi'),
            uatManifestPath: ($value = trim((string) ($command->option('uat-manifest') ?? ''))) !== '' ? $value : null,
        );
    } catch (Throwable $exception) {
        if ($command->option('json')) {
            $command->line(json_encode([
                'ok' => false,
                'error' => 'fe_contract_harness_failed',
                'message' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return 1;
        }

        $command->error($exception->getMessage());

        return 1;
    }

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return (bool) ($payload['ok'] ?? false) ? 0 : 1;
    }

    $command->info('Booking FE contract harness');
    $command->table(['Source', 'Path'], collect((array) ($payload['official_sources'] ?? []))
        ->map(static fn (string $value, string $key): array => [$key, $value])
        ->values()
        ->all());
    $command->table(['Artifact', 'Path'], collect((array) ($payload['artifacts'] ?? []))
        ->map(static fn (string $value, string $key): array => [$key, $value])
        ->values()
        ->all());

    return (bool) ($payload['ok'] ?? false) ? 0 : 1;
})->purpose('Generate and summarize the FE-facing contract artifacts for customer-web and staff-web.');

Artisan::command('booking:harness:golden-flows
    {--manifest-path= : Absolute or repo-relative UAT manifest path}
    {--base-url= : Base API URL used when bootstrapping a fresh UAT scenario pack}
    {--bootstrap-uat : Reset and seed the canonical UAT scenario pack before resolving flow references}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $payload = app(HarnessSuiteService::class)->buildGoldenFlowReport(
            manifestPath: ($value = trim((string) ($command->option('manifest-path') ?? ''))) !== '' ? $value : null,
            bootstrapUat: (bool) $command->option('bootstrap-uat'),
            baseUrl: ($value = trim((string) ($command->option('base-url') ?? ''))) !== '' ? $value : null,
        );
    } catch (Throwable $exception) {
        if ($command->option('json')) {
            $command->line(json_encode([
                'ok' => false,
                'error' => 'golden_flow_harness_failed',
                'message' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return 1;
        }

        $command->error($exception->getMessage());

        return 1;
    }

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    $command->info('Booking golden flow harness');
    $command->table(['Scenario', 'Smoke command'], collect((array) ($payload['scenarios'] ?? []))
        ->map(static fn (array $scenario): array => [
            (string) ($scenario['label'] ?? $scenario['key'] ?? ''),
            (string) ($scenario['smoke_command'] ?? ''),
        ])->values()->all());

    return 0;
})->purpose('List the canonical golden flows, their manifest references, and deterministic smoke commands.');

Artisan::command('booking:harness:enum-state
    {--output-root= : Override the consumer artifact output root}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $payload = app(HarnessSuiteService::class)->buildEnumStateReport(
            outputRoot: ($value = trim((string) ($command->option('output-root') ?? ''))) !== '' ? $value : null,
        );
    } catch (Throwable $exception) {
        if ($command->option('json')) {
            $command->line(json_encode([
                'ok' => false,
                'error' => 'enum_state_harness_failed',
                'message' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return 1;
        }

        $command->error($exception->getMessage());

        return 1;
    }

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return (bool) ($payload['ok'] ?? false) ? 0 : 1;
    }

    $command->info('Booking enum/state harness');
    $command->table(['Artifact', 'Path'], collect((array) ($payload['artifacts'] ?? []))
        ->map(static fn (string $value, string $key): array => [$key, $value])
        ->values()->all());

    return (bool) ($payload['ok'] ?? false) ? 0 : 1;
})->purpose('Generate FE-facing enum/state artifacts from PHP backed enums.');

Artisan::command('booking:harness:release-readiness
    {--target=staging : staging|limited-production}
    {--manual-evidence= : Optional launch-readiness manual evidence path}
    {--package-id= : Explicit package id passed through to launch-readiness}
    {--overwrite-package : Overwrite an existing package id when launch-readiness packages artifacts}
    {--payment-sample-limit=10 : Payment sample size for launch-readiness alert evaluation}
    {--manifest-path= : Absolute or repo-relative UAT manifest path used by the golden flow harness}
    {--base-url= : Base API URL used when bootstrapping a fresh UAT scenario pack}
    {--bootstrap-uat : Reset and seed the canonical UAT scenario pack before attaching golden flow context}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $target = strtolower(trim((string) $command->option('target')));
    if (! in_array($target, ['staging', 'limited-production'], true)) {
        $command->error('Invalid --target. Supported values: staging, limited-production.');

        return 1;
    }

    try {
        $payload = app(HarnessSuiteService::class)->buildReleaseReadinessReport(
            target: $target,
            manualEvidencePath: ($value = trim((string) ($command->option('manual-evidence') ?? ''))) !== '' ? $value : null,
            packageId: ($value = trim((string) ($command->option('package-id') ?? ''))) !== '' ? $value : null,
            overwritePackage: (bool) $command->option('overwrite-package'),
            paymentSampleLimit: max(1, (int) $command->option('payment-sample-limit')),
            manifestPath: ($value = trim((string) ($command->option('manifest-path') ?? ''))) !== '' ? $value : null,
            bootstrapUat: (bool) $command->option('bootstrap-uat'),
            baseUrl: ($value = trim((string) ($command->option('base-url') ?? ''))) !== '' ? $value : null,
        );
    } catch (Throwable $exception) {
        if ($command->option('json')) {
            $command->line(json_encode([
                'ok' => false,
                'error' => 'release_harness_failed',
                'message' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return 1;
        }

        $command->error($exception->getMessage());

        return 1;
    }

    $exitCode = (int) data_get($payload, 'readiness.exit_code', 1);

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $exitCode;
    }

    $command->info('Booking release/runtime harness');
    $command->table(['Field', 'Value'], [
        ['decision', (string) data_get($payload, 'readiness.decision', 'unknown')],
        ['target', (string) data_get($payload, 'readiness.target.label', data_get($payload, 'readiness.target.key', ''))],
        ['exit_code', (string) $exitCode],
        ['golden_flow_count', (string) count((array) data_get($payload, 'golden_flows.scenarios', []))],
    ]);
    $command->table(['Runtime gate', 'Command'], collect((array) ($payload['runtime_gates'] ?? []))
        ->map(static fn (array $gate): array => [
            (string) ($gate['label'] ?? ''),
            (string) ($gate['command'] ?? ''),
        ])->values()->all());

    return $exitCode;
})->purpose('Attach golden-flow and runtime-gate context to the canonical launch-readiness evaluation.');
