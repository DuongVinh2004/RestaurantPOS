<?php

use App\Modules\IdentityAccess\Domain\Models\CustomerAccessSession;
use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

$consoleValidationPayload = static function (ValidationException $exception): array {
    return [
        'error' => 'validation_error',
        'errors' => $exception->errors(),
    ];
};

$customerAccessSessionConsolePayload = static function (CustomerAccessSession $session, bool $verboseSensitive = false) use (
    $consoleMaskSessionId,
    $consoleMaskName,
    $consoleMaskPhone,
    $consoleMaskIp,
    $consoleSummarizeUserAgent,
    $consoleSessionMetadataPayload,
): array {
    $expiresAt = $session->expires_at?->copy()->utc();
    $revokedAt = $session->revoked_at?->copy()->utc();

    return [
        'access_session_id' => $session->getKey(),
        'user_id' => $session->user_id,
        'username' => $session->user?->username,
        'session_id' => $verboseSensitive ? $session->session_id : $consoleMaskSessionId($session->session_id),
        'guest_name' => $verboseSensitive ? $session->guest_name : $consoleMaskName($session->guest_name),
        'phone' => $verboseSensitive ? $session->phone : $consoleMaskPhone($session->phone),
        'is_active' => $revokedAt === null && $expiresAt !== null && $expiresAt->isFuture(),
        'expires_at_utc' => $expiresAt?->toIso8601String(),
        'last_used_at_utc' => $session->last_used_at?->toIso8601String(),
        'revoked_at_utc' => $revokedAt?->toIso8601String(),
        'token_last_eight' => $session->token_last_eight,
        'created_ip' => $verboseSensitive ? $session->created_ip : $consoleMaskIp($session->created_ip),
        'user_agent' => $consoleSummarizeUserAgent($session->user_agent, $verboseSensitive),
        'metadata' => $consoleSessionMetadataPayload($session->session_meta_json, $verboseSensitive),
    ];
};

$staffApiKeyConsolePayload = static function (StaffApiKey $key): array {
    $expiresAt = $key->expires_at?->copy()->utc();
    $revokedAt = $key->revoked_at?->copy()->utc();

    return [
        'staff_api_key_id' => $key->getKey(),
        'user_id' => $key->user_id,
        'username' => $key->user?->username,
        'label' => $key->label,
        'is_active' => $revokedAt === null && ($expiresAt === null || $expiresAt->isFuture()),
        'expires_at_utc' => $expiresAt?->toIso8601String(),
        'last_used_at_utc' => $key->last_used_at?->toIso8601String(),
        'revoked_at_utc' => $revokedAt?->toIso8601String(),
    ];
};

$consoleSecretPayload = static function (?string $secret, bool $reveal = false) use ($consoleSecretMask): array {
    return [
        'plain' => $reveal ? $secret : null,
        'masked' => $consoleSecretMask($secret),
        'revealed' => $reveal && $secret !== null,
    ];
};

$consoleWarnOnSecretReveal = static function (ConsoleCommand $command, string $secretLabel): void {
    $command->warn(sprintf(
        'Plaintext %s is being shown once. It may persist in shell history, logs, recordings, or shared terminals.',
        $secretLabel
    ));
};

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
    {--show-secret-once : Reveal the plaintext token once in this command output}
    {--verbose-sensitive : Show unmasked guest and session metadata fields}
    {--json : Output machine-readable JSON}', function () use ($customerAccessSessionConsolePayload, $consoleValidationPayload, $consoleSecretPayload, $consoleWarnOnSecretReveal) {
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
        $issued = app(CustomerAccessSessionStore::class)->issueForUser($user, $expiresAt, $context);
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
    $revealSecret = (bool) $command->option('show-secret-once');
    $secret = $consoleSecretPayload((string) $issued['plain_text_token'], $revealSecret);
    $payload = [
        'plain_text_token' => $secret['plain'],
        'plain_text_token_masked' => $secret['masked'],
        'secret_revealed' => $secret['revealed'],
        'access_session' => $customerAccessSessionConsolePayload($session, (bool) $command->option('verbose-sensitive')),
    ];

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Customer access session issued.');
    if ($revealSecret) {
        $consoleWarnOnSecretReveal($command, 'customer access session token');
    }
    $command->table(['Field', 'Value'], [
        [$revealSecret ? 'plain_text_token' : 'plain_text_token_masked', (string) ($revealSecret ? $payload['plain_text_token'] : $payload['plain_text_token_masked'])],
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
    {--verbose-sensitive : Show unmasked guest and session metadata fields}
    {--json : Output machine-readable JSON}', function () use ($customerAccessSessionConsolePayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $userIdOption = $command->option('user-id');
    $userId = $userIdOption !== null && $userIdOption !== '' ? (int) $userIdOption : null;

    $rows = array_map(
        fn (CustomerAccessSession $session): array => $customerAccessSessionConsolePayload($session, (bool) $command->option('verbose-sensitive')),
        app(CustomerAccessSessionStore::class)->listSessions($userId, (bool) $command->option('include-revoked'))
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
    {--verbose-sensitive : Show unmasked guest and session metadata fields}
    {--json : Output machine-readable JSON}', function () use ($customerAccessSessionConsolePayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $session = app(CustomerAccessSessionStore::class)->showSession((int) $command->argument('access_session_id'));
    } catch (ModelNotFoundException) {
        $payload = ['error' => 'not_found', 'message' => 'Customer access session not found.'];
        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        $command->error($payload['message']);

        return 1;
    }

    $payload = $customerAccessSessionConsolePayload($session, (bool) $command->option('verbose-sensitive'));
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
    {--verbose-sensitive : Show unmasked guest and session metadata fields}
    {--json : Output machine-readable JSON}', function () use ($customerAccessSessionConsolePayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $record = app(CustomerAccessSessionStore::class)->revokeSession((int) $command->argument('access_session_id'));
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

    $payload = $customerAccessSessionConsolePayload($record, (bool) $command->option('verbose-sensitive'));
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
    {--show-secret-once : Reveal the plaintext API key once in this command output}
    {--json : Output machine-readable JSON}', function () use ($staffApiKeyConsolePayload, $consoleValidationPayload, $consoleSecretPayload, $consoleWarnOnSecretReveal) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $ttlDays = max(1, (int) ($command->option('ttl-days') ?: 90));
    $expiresAt = $command->option('expires-at')
        ? Carbon::parse((string) $command->option('expires-at'))->utc()
        : now('UTC')->addDays($ttlDays);

    try {
        $issued = app(StaffApiKeyStore::class)->issueKey(
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
    $revealSecret = (bool) $command->option('show-secret-once');
    $secret = $consoleSecretPayload((string) $issued['plaintext_key'], $revealSecret);
    $payload = [
        'plaintext_key' => $secret['plain'],
        'plaintext_key_masked' => $secret['masked'],
        'secret_revealed' => $secret['revealed'],
        'staff_api_key' => $staffApiKeyConsolePayload($record),
    ];

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Staff API key issued.');
    if ($revealSecret) {
        $consoleWarnOnSecretReveal($command, 'staff API key');
    }
    $command->table(['Field', 'Value'], [
        [$revealSecret ? 'plaintext_key' : 'plaintext_key_masked', (string) ($revealSecret ? $payload['plaintext_key'] : $payload['plaintext_key_masked'])],
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
        app(StaffApiKeyStore::class)->listKeys($userId, (bool) $command->option('include-revoked'))
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
        $record = app(StaffApiKeyStore::class)->revokeKey(
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
    {--show-secret-once : Reveal the plaintext replacement API key once in this command output}
    {--json : Output machine-readable JSON}', function () use ($staffApiKeyConsolePayload, $consoleValidationPayload, $consoleSecretPayload, $consoleWarnOnSecretReveal) {
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
        $rotated = app(StaffApiKeyStore::class)->rotateKey(
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

    $revealSecret = (bool) $command->option('show-secret-once');
    $secret = $consoleSecretPayload((string) $rotated['plaintext_key'], $revealSecret);
    $payload = [
        'plaintext_key' => $secret['plain'],
        'plaintext_key_masked' => $secret['masked'],
        'secret_revealed' => $secret['revealed'],
        'revoked' => $staffApiKeyConsolePayload($rotated['revoked']->loadMissing('user.role')),
        'replacement' => $staffApiKeyConsolePayload($rotated['record']->loadMissing('user.role')),
    ];

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Staff API key rotated.');
    if ($revealSecret) {
        $consoleWarnOnSecretReveal($command, 'replacement staff API key');
    }
    $command->table(['Field', 'Value'], [
        [$revealSecret ? 'plaintext_key' : 'plaintext_key_masked', (string) ($revealSecret ? $payload['plaintext_key'] : $payload['plaintext_key_masked'])],
        ['revoked_staff_api_key_id', (string) $payload['revoked']['staff_api_key_id']],
        ['replacement_staff_api_key_id', (string) $payload['replacement']['staff_api_key_id']],
        ['user_id', (string) $payload['replacement']['user_id']],
        ['label', (string) $payload['replacement']['label']],
        ['expires_at_utc', (string) ($payload['replacement']['expires_at_utc'] ?? '')],
    ]);

    return 0;
})->purpose('Rotate a staff API key by revoking the current record and issuing a replacement.');
