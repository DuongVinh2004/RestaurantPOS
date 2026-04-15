<?php

use App\Platform\FeatureFlags\Services\FeatureFlagManagementService;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

$consoleValidationPayload = static function (ValidationException $exception): array {
    return [
        'ok' => false,
        'error' => 'validation_error',
        'errors' => $exception->validator->errors()->messages(),
    ];
};

Artisan::command('booking:feature-flags:list
    {--feature= : Filter a single registered feature key}
    {--branch-id= : Resolve effective state for a single branch scope}
    {--environment= : Resolve using the supplied environment}
    {--json : Output machine-readable JSON}', function () use ($consoleValidationPayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $rows = app(FeatureFlagManagementService::class)->listEffective(
            $command->option('environment') !== null && $command->option('environment') !== ''
                ? (string) $command->option('environment')
                : null,
            $command->option('branch-id') !== null && $command->option('branch-id') !== ''
                ? (int) $command->option('branch-id')
                : null,
            $command->option('feature') !== null && $command->option('feature') !== ''
                ? (string) $command->option('feature')
                : null,
        );
    } catch (ValidationException $exception) {
        $payload = $consoleValidationPayload($exception);

        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        foreach ($payload['errors'] as $field => $messages) {
            foreach ((array) $messages as $message) {
                $command->error(sprintf('%s: %s', $field, (string) $message));
            }
        }

        return 1;
    }

    $payload = [
        'ok' => true,
        'data' => $rows,
        'meta' => [
            'count' => count($rows),
        ],
    ];

    if ($command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Feature flag effective resolution');
    $command->table(['Feature', 'Enabled', 'Source', 'Environment', 'Branch', 'Message'], collect($rows)->map(static function (array $row): array {
        return [
            (string) ($row['feature_key'] ?? ''),
            ($row['enabled'] ?? false) ? 'yes' : 'no',
            (string) ($row['source'] ?? ''),
            (string) ($row['matched_environment'] ?? $row['environment'] ?? ''),
            isset($row['matched_branch_id']) && $row['matched_branch_id'] !== null ? (string) $row['matched_branch_id'] : 'global',
            (string) ($row['message'] ?? ''),
        ];
    })->values()->all());

    return 0;
})->purpose('List effective feature-flag states after environment and branch resolution.');

Artisan::command('booking:feature-flags:set
    {feature : Registered feature key}
    {state : on|off|true|false|1|0}
    {--branch-id= : Optional branch override scope}
    {--environment= : Override environment scope; defaults to wildcard}
    {--reason= : Optional operator note stored with the override}
    {--actor-user-id= : Optional actor user id for audit linkage}
    {--actor-key= : Optional audit actor key}
    {--json : Output machine-readable JSON}', function () use ($consoleValidationPayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $state = strtolower(trim((string) $command->argument('state')));
    $truthy = ['1', 'true', 'on', 'enable', 'enabled', 'yes'];
    $falsy = ['0', 'false', 'off', 'disable', 'disabled', 'no'];

    if (! in_array($state, array_merge($truthy, $falsy), true)) {
        $payload = [
            'ok' => false,
            'error' => 'invalid_state',
            'message' => 'State must be one of: on, off, true, false, 1, 0.',
        ];

        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        $command->error((string) $payload['message']);

        return 1;
    }

    try {
        $result = app(FeatureFlagManagementService::class)->upsertOverride(
            featureKey: (string) $command->argument('feature'),
            enabled: in_array($state, $truthy, true),
            environment: $command->option('environment') !== null && $command->option('environment') !== ''
                ? (string) $command->option('environment')
                : null,
            branchId: $command->option('branch-id') !== null && $command->option('branch-id') !== ''
                ? (int) $command->option('branch-id')
                : null,
            reason: $command->option('reason') !== null && $command->option('reason') !== ''
                ? (string) $command->option('reason')
                : null,
            actorUserId: $command->option('actor-user-id') !== null && $command->option('actor-user-id') !== ''
                ? (int) $command->option('actor-user-id')
                : null,
            actorType: 'console',
            actorKey: $command->option('actor-key') !== null && $command->option('actor-key') !== ''
                ? (string) $command->option('actor-key')
                : 'artisan:booking:feature-flags:set',
        );
    } catch (ValidationException $exception) {
        $payload = $consoleValidationPayload($exception);

        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        foreach ($payload['errors'] as $field => $messages) {
            foreach ((array) $messages as $message) {
                $command->error(sprintf('%s: %s', $field, (string) $message));
            }
        }

        return 1;
    }

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Feature flag override saved.');
    $command->table(['Field', 'Value'], [
        ['action', (string) ($result['action'] ?? '')],
        ['feature_key', (string) data_get($result, 'feature.feature_key', '')],
        ['enabled', data_get($result, 'feature.enabled', false) ? 'yes' : 'no'],
        ['source', (string) data_get($result, 'feature.source', '')],
        ['matched_environment', (string) data_get($result, 'feature.matched_environment', '')],
        ['matched_branch_id', data_get($result, 'feature.matched_branch_id') !== null ? (string) data_get($result, 'feature.matched_branch_id') : 'global'],
    ]);

    return 0;
})->purpose('Create or update a feature-flag override for an environment and optional branch.');

Artisan::command('booking:feature-flags:clear
    {feature : Registered feature key}
    {--branch-id= : Optional branch override scope}
    {--environment= : Override environment scope; defaults to wildcard}
    {--reason= : Optional operator note stored in audit only}
    {--actor-user-id= : Optional actor user id for audit linkage}
    {--actor-key= : Optional audit actor key}
    {--json : Output machine-readable JSON}', function () use ($consoleValidationPayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $result = app(FeatureFlagManagementService::class)->clearOverride(
            featureKey: (string) $command->argument('feature'),
            environment: $command->option('environment') !== null && $command->option('environment') !== ''
                ? (string) $command->option('environment')
                : null,
            branchId: $command->option('branch-id') !== null && $command->option('branch-id') !== ''
                ? (int) $command->option('branch-id')
                : null,
            reason: $command->option('reason') !== null && $command->option('reason') !== ''
                ? (string) $command->option('reason')
                : null,
            actorUserId: $command->option('actor-user-id') !== null && $command->option('actor-user-id') !== ''
                ? (int) $command->option('actor-user-id')
                : null,
            actorType: 'console',
            actorKey: $command->option('actor-key') !== null && $command->option('actor-key') !== ''
                ? (string) $command->option('actor-key')
                : 'artisan:booking:feature-flags:clear',
        );
    } catch (ValidationException $exception) {
        $payload = $consoleValidationPayload($exception);

        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        foreach ($payload['errors'] as $field => $messages) {
            foreach ((array) $messages as $message) {
                $command->error(sprintf('%s: %s', $field, (string) $message));
            }
        }

        return 1;
    }

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Feature flag override cleared.');
    $command->table(['Field', 'Value'], [
        ['action', (string) ($result['action'] ?? '')],
        ['had_override', (bool) ($result['had_override'] ?? false) ? 'yes' : 'no'],
        ['feature_key', (string) data_get($result, 'feature.feature_key', '')],
        ['enabled', data_get($result, 'feature.enabled', false) ? 'yes' : 'no'],
        ['source', (string) data_get($result, 'feature.source', '')],
    ]);

    return 0;
})->purpose('Clear a feature-flag override so effective state falls back to broader scope or config defaults.');
