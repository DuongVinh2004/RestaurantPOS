<?php

use App\Models\CustomerAccessSession;
use App\Models\StaffApiKey;
use App\Models\User;
use App\Services\ApiArtifacts\ApiConsumerArtifactService;
use App\Services\ApiContract\OpenApiSpecService;
use App\Services\BookingDeploySafetyService;
use App\Services\BookingDoctorService;
use App\Services\BookingMaintenanceService;
use App\Services\CoreOpsGateService;
use App\Services\CustomerAccessSessionService;
use App\Services\DataLifecycle\DataRetentionService;
use App\Services\DisasterRecovery\DisasterRecoveryDrillService;
use App\Services\FeatureFlagManagementService;
use App\Services\LaunchReadinessService;
use App\Services\NotificationOutboxHealthService;
use App\Services\NotificationOutboxService;
use App\Services\OperationalAlertService;
use App\Services\OperationalInsightsService;
use App\Services\OpsGateArtifactService;
use App\Services\OpsHeartbeatService;
use App\Services\Performance\PerformanceVerificationService;
use App\Services\ReleaseArtifactManifestService;
use App\Services\ReleaseArtifactNormalizerService;
use App\Services\ReleasePackageService;
use App\Services\Reporting\ReportingSnapshotService;
use App\Services\RoundFiveGateService;
use App\Services\RouteInventoryGateService;
use App\Services\RuntimeSettingService;
use App\Services\SiteBootstrapService;
use App\Services\Staff\StaffWaitingListService;
use App\Services\StaffApiKeyGovernanceService;
use App\Services\Uat\UatScenarioPackService;
use App\Support\AuditEvent;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

Artisan::command('customer-auth:access-sessions:issue
    {user_id : Customer user id}
    {--expires-at= : Explicit UTC expiry timestamp}
    {--ttl-minutes= : Relative TTL in minutes}
    {--session-id= : Explicit customer session id}
    {--guest-name= : Guest display name}
    {--phone= : Guest phone number}
    {--source= : Operational issuance source}
    {--session-label= : Session label stored in metadata}
    {--device-id= : Device identifier stored in metadata}
    {--created-ip= : Source IP address}
    {--user-agent= : Source user agent}
    {--json : Output machine-readable JSON}', function () use ($customerAccessSessionConsolePayload, $consoleValidationPayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $userId = (int) $command->argument('user_id');
    $user = User::query()->with('role')->find($userId);
    if (! $user instanceof User) {
        $payload = ['error' => 'not_found', 'message' => 'Customer user not found.'];
        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        $command->error($payload['message']);

        return 1;
    }

    $ttlMinutes = max(1, (int) ($command->option('ttl-minutes') ?: config('customer_auth.access_session_ttl_minutes', 20160)));
    $expiresAt = $command->option('expires-at')
        ? Carbon::parse((string) $command->option('expires-at'))->utc()
        : now('UTC')->addMinutes($ttlMinutes);

    $context = array_filter([
        'session_id' => $command->option('session-id'),
        'guest_name' => $command->option('guest-name'),
        'phone' => $command->option('phone'),
        'source' => $command->option('source') ?: 'console.bootstrap',
        'session_label' => $command->option('session-label'),
        'device_id' => $command->option('device-id'),
        'created_ip' => $command->option('created-ip'),
        'user_agent' => $command->option('user-agent'),
    ], static fn ($value): bool => $value !== null && trim((string) $value) !== '');

    try {
        $issued = app(CustomerAccessSessionService::class)->issueForUser($user, $expiresAt, $context);
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

    /** @var CustomerAccessSession $session */
    $session = $issued['access_session']->loadMissing('user.role');
    $payload = [
        'plain_text_token' => $issued['plain_text_token'],
        'access_session' => $customerAccessSessionConsolePayload($session),
    ];

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Customer access session issued.');
    $command->table(['Field', 'Value'], [
        ['plain_text_token', (string) $payload['plain_text_token']],
        ['access_session_id', (string) $payload['access_session']['access_session_id']],
        ['user_id', (string) $payload['access_session']['user_id']],
        ['session_id', (string) ($payload['access_session']['session_id'] ?? '')],
        ['expires_at_utc', (string) ($payload['access_session']['expires_at_utc'] ?? '')],
        ['token_last_eight', (string) ($payload['access_session']['token_last_eight'] ?? '')],
    ]);

    return 0;
})->purpose('Issue a dedicated customer access session token for controlled self-service bootstrap.');

Artisan::command('customer-auth:access-sessions:list
    {--user-id= : Filter by customer user id}
    {--include-revoked : Include expired or revoked records}
    {--json : Output machine-readable JSON}', function () use ($customerAccessSessionConsolePayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $userIdOption = $command->option('user-id');
    $userId = $userIdOption !== null && $userIdOption !== '' ? (int) $userIdOption : null;

    $rows = array_map(
        $customerAccessSessionConsolePayload,
        app(CustomerAccessSessionService::class)->listSessions($userId, (bool) $command->option('include-revoked'))
    );

    if ($command->option('json')) {
        $command->line(json_encode([
            'ok' => true,
            'data' => $rows,
            'meta' => [
                'count' => count($rows),
                'include_revoked' => (bool) $command->option('include-revoked'),
                'user_id' => $userId,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    if ($rows === []) {
        $command->info('No customer access sessions matched the supplied filters.');

        return 0;
    }

    $command->table(
        ['Session', 'User', 'Session Id', 'Status', 'Expires At (UTC)', 'Last Used (UTC)'],
        array_map(static function (array $row): array {
            return [
                (string) $row['access_session_id'],
                sprintf('%d %s', (int) $row['user_id'], (string) ($row['username'] ?? '')),
                (string) ($row['session_id'] ?? ''),
                ($row['is_active'] ?? false) ? 'active' : 'inactive',
                (string) ($row['expires_at_utc'] ?? ''),
                (string) ($row['last_used_at_utc'] ?? ''),
            ];
        }, $rows)
    );

    return 0;
})->purpose('List customer access sessions for operational bootstrap and revocation review.');

Artisan::command('customer-auth:access-sessions:show
    {access_session_id : Customer access session id}
    {--json : Output machine-readable JSON}', function () use ($customerAccessSessionConsolePayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $session = app(CustomerAccessSessionService::class)->showSession((int) $command->argument('access_session_id'));
    } catch (ModelNotFoundException) {
        $payload = ['error' => 'not_found', 'message' => 'Customer access session not found.'];
        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        $command->error($payload['message']);

        return 1;
    }

    $payload = $customerAccessSessionConsolePayload($session);
    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->table(['Field', 'Value'], collect($payload)->map(static fn ($value, $key): array => [
        (string) $key,
        is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (string) ($value ?? ''),
    ])->values()->all());

    return 0;
})->purpose('Inspect a single customer access session and its current terminal state.');

Artisan::command('customer-auth:access-sessions:revoke
    {access_session_id : Customer access session id}
    {--json : Output machine-readable JSON}', function () use ($customerAccessSessionConsolePayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $record = app(CustomerAccessSessionService::class)->revokeSession((int) $command->argument('access_session_id'));
    } catch (ModelNotFoundException) {
        $record = null;
    }

    if (! $record instanceof CustomerAccessSession) {
        $payload = ['error' => 'not_found', 'message' => 'Customer access session not found.'];
        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        $command->error($payload['message']);

        return 1;
    }

    $payload = $customerAccessSessionConsolePayload($record);
    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info(sprintf('Customer access session %d revoked.', (int) $record->getKey()));

    return 0;
})->purpose('Revoke a customer access session without requiring external token infrastructure.');

Artisan::command('staff-auth:api-keys:issue
    {user_id : Staff/admin user id}
    {label : Human-readable key label}
    {--expires-at= : Explicit UTC expiry timestamp}
    {--ttl-days= : Relative TTL in days}
    {--json : Output machine-readable JSON}', function () use ($staffApiKeyConsolePayload, $consoleValidationPayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $ttlDays = max(1, (int) ($command->option('ttl-days') ?: 90));
    $expiresAt = $command->option('expires-at')
        ? Carbon::parse((string) $command->option('expires-at'))->utc()
        : now('UTC')->addDays($ttlDays);

    try {
        $issued = app(StaffApiKeyGovernanceService::class)->issueKey(
            userId: (int) $command->argument('user_id'),
            label: (string) $command->argument('label'),
            expiresAt: $expiresAt,
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

    /** @var StaffApiKey $record */
    $record = $issued['record']->loadMissing('user.role');
    $payload = [
        'plaintext_key' => $issued['plaintext_key'],
        'staff_api_key' => $staffApiKeyConsolePayload($record),
    ];

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Staff API key issued.');
    $command->table(['Field', 'Value'], [
        ['plaintext_key', (string) $payload['plaintext_key']],
        ['staff_api_key_id', (string) $payload['staff_api_key']['staff_api_key_id']],
        ['user_id', (string) $payload['staff_api_key']['user_id']],
        ['label', (string) $payload['staff_api_key']['label']],
        ['expires_at_utc', (string) ($payload['staff_api_key']['expires_at_utc'] ?? '')],
    ]);

    return 0;
})->purpose('Issue a database-backed staff API key for controlled day-1 bootstrap.');

Artisan::command('staff-auth:api-keys:list
    {--user-id= : Filter by staff/admin user id}
    {--include-revoked : Include revoked or expired records}
    {--json : Output machine-readable JSON}', function () use ($staffApiKeyConsolePayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $userIdOption = $command->option('user-id');
    $userId = $userIdOption !== null && $userIdOption !== '' ? (int) $userIdOption : null;

    $rows = array_map(
        $staffApiKeyConsolePayload,
        app(StaffApiKeyGovernanceService::class)->listKeys($userId, (bool) $command->option('include-revoked'))
    );

    if ($command->option('json')) {
        $command->line(json_encode([
            'ok' => true,
            'data' => $rows,
            'meta' => [
                'count' => count($rows),
                'include_revoked' => (bool) $command->option('include-revoked'),
                'user_id' => $userId,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    if ($rows === []) {
        $command->info('No staff API keys matched the supplied filters.');

        return 0;
    }

    $command->table(
        ['Key', 'User', 'Label', 'Status', 'Expires At (UTC)', 'Last Used (UTC)'],
        array_map(static function (array $row): array {
            return [
                (string) $row['staff_api_key_id'],
                sprintf('%d %s', (int) $row['user_id'], (string) ($row['username'] ?? '')),
                (string) $row['label'],
                ($row['is_active'] ?? false) ? 'active' : 'inactive',
                (string) ($row['expires_at_utc'] ?? ''),
                (string) ($row['last_used_at_utc'] ?? ''),
            ];
        }, $rows)
    );

    return 0;
})->purpose('List active staff API keys provisioned inside the repository-managed database.');

Artisan::command('staff-auth:api-keys:revoke
    {staff_api_key_id : Staff API key id}
    {--reason= : Optional revoke reason}
    {--json : Output machine-readable JSON}', function () use ($staffApiKeyConsolePayload, $consoleValidationPayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $record = app(StaffApiKeyGovernanceService::class)->revokeKey(
            (int) $command->argument('staff_api_key_id'),
            $command->option('reason') ? (string) $command->option('reason') : null,
        );
    } catch (ModelNotFoundException) {
        $payload = ['error' => 'not_found', 'message' => 'Staff API key not found.'];
        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        $command->error($payload['message']);

        return 1;
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

    $payload = $staffApiKeyConsolePayload($record->loadMissing('user.role'));
    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info(sprintf('Staff API key %d revoked.', (int) $record->getKey()));

    return 0;
})->purpose('Revoke an active staff API key and keep the remaining governance surface inside the system.');

Artisan::command('staff-auth:api-keys:rotate
    {staff_api_key_id : Staff API key id}
    {--label= : Replacement label}
    {--expires-at= : Explicit UTC expiry timestamp}
    {--ttl-days= : Relative replacement TTL in days}
    {--json : Output machine-readable JSON}', function () use ($staffApiKeyConsolePayload, $consoleValidationPayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $expiresAt = null;
    if ($command->option('expires-at')) {
        $expiresAt = Carbon::parse((string) $command->option('expires-at'))->utc();
    } elseif ($command->option('ttl-days') !== null && $command->option('ttl-days') !== '') {
        $expiresAt = now('UTC')->addDays(max(1, (int) $command->option('ttl-days')));
    }

    try {
        $rotated = app(StaffApiKeyGovernanceService::class)->rotateKey(
            staffApiKeyId: (int) $command->argument('staff_api_key_id'),
            replacementLabel: $command->option('label') ? (string) $command->option('label') : null,
            expiresAt: $expiresAt,
        );
    } catch (ModelNotFoundException) {
        $payload = ['error' => 'not_found', 'message' => 'Staff API key not found.'];
        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        $command->error($payload['message']);

        return 1;
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
        'plaintext_key' => $rotated['plaintext_key'],
        'revoked' => $staffApiKeyConsolePayload($rotated['revoked']->loadMissing('user.role')),
        'replacement' => $staffApiKeyConsolePayload($rotated['record']->loadMissing('user.role')),
    ];

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Staff API key rotated.');
    $command->table(['Field', 'Value'], [
        ['plaintext_key', (string) $payload['plaintext_key']],
        ['revoked_staff_api_key_id', (string) $payload['revoked']['staff_api_key_id']],
        ['replacement_staff_api_key_id', (string) $payload['replacement']['staff_api_key_id']],
        ['user_id', (string) $payload['replacement']['user_id']],
        ['label', (string) $payload['replacement']['label']],
        ['expires_at_utc', (string) ($payload['replacement']['expires_at_utc'] ?? '')],
    ]);

    return 0;
})->purpose('Rotate a staff API key by revoking the current record and issuing a replacement.');
