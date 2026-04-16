<?php

use App\Models\CustomerAccessSession;
use App\Models\StaffApiKey;
use App\Platform\ApiContract\Services\OpsGateArtifactService;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

Artisan::command('inspire', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $command->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$consoleTimestamp = static function (mixed $value): ?string {
    if ($value === null || $value === '') {
        return null;
    }

    return Carbon::parse((string) $value)->utc()->toIso8601String();
};

$consoleMaskString = static function (mixed $value, int $visiblePrefix = 2, int $visibleSuffix = 2, string $mask = '*'): ?string {
    $normalized = trim((string) ($value ?? ''));
    if ($normalized === '') {
        return null;
    }

    $length = mb_strlen($normalized);
    if ($length <= ($visiblePrefix + $visibleSuffix)) {
        return str_repeat($mask, max(4, $length));
    }

    return mb_substr($normalized, 0, $visiblePrefix)
        .str_repeat($mask, max(4, $length - ($visiblePrefix + $visibleSuffix)))
        .mb_substr($normalized, -$visibleSuffix);
};

$consoleMaskPhone = static function (mixed $value) use ($consoleMaskString): ?string {
    return $consoleMaskString($value, 3, 2);
};

$consoleMaskSessionId = static function (mixed $value) use ($consoleMaskString): ?string {
    return $consoleMaskString($value, 6, 4);
};

$consoleMaskName = static function (mixed $value) use ($consoleMaskString): ?string {
    return $consoleMaskString($value, 1, 1);
};

$consoleMaskIp = static function (mixed $value) use ($consoleMaskString): ?string {
    $normalized = trim((string) ($value ?? ''));
    if ($normalized === '') {
        return null;
    }

    if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $segments = explode('.', $normalized);
        $segments[count($segments) - 1] = 'x';

        return implode('.', $segments);
    }

    if (filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $segments = array_values(array_filter(explode(':', $normalized), static fn (string $segment): bool => $segment !== ''));
        if (count($segments) >= 2) {
            return implode(':', array_slice($segments, 0, 2)).':*:*';
        }
    }

    return $consoleMaskString($normalized, 2, 2);
};

$consoleSummarizeUserAgent = static function (mixed $value, bool $verboseSensitive = false): ?string {
    $normalized = trim((string) ($value ?? ''));
    if ($normalized === '') {
        return null;
    }

    if ($verboseSensitive || mb_strlen($normalized) <= 48) {
        return $normalized;
    }

    return mb_substr($normalized, 0, 48).'...';
};

$consoleSessionMetadataPayload = static function (mixed $value, bool $verboseSensitive = false) use ($consoleMaskString): array {
    if (! is_array($value)) {
        return [];
    }

    if ($verboseSensitive) {
        return $value;
    }

    $allowed = [];

    foreach (['source', 'session_label', 'device_id'] as $key) {
        if (! array_key_exists($key, $value) || $value[$key] === null || trim((string) $value[$key]) === '') {
            continue;
        }

        $allowed[$key] = $key === 'device_id'
            ? $consoleMaskString($value[$key], 4, 4)
            : (string) $value[$key];
    }

    return $allowed;
};

$consoleSecretMask = static function (mixed $value) use ($consoleMaskString): ?string {
    return $consoleMaskString($value, 8, 6);
};

$customerAccessSessionConsolePayload = static function (CustomerAccessSession $session, bool $verboseSensitive = false) use (
    $consoleTimestamp,
    $consoleMaskSessionId,
    $consoleMaskName,
    $consoleMaskPhone,
    $consoleMaskIp,
    $consoleSummarizeUserAgent,
    $consoleSessionMetadataPayload,
): array {
    $user = $session->relationLoaded('user') ? $session->user : null;
    $role = $user?->relationLoaded('role') ? $user->role : null;
    $expiresAt = $session->expires_at?->utc();
    $revokedAt = $session->revoked_at?->utc();

    return [
        'access_session_id' => (int) $session->getKey(),
        'user_id' => (int) ($session->user_id ?? 0),
        'username' => $user?->username,
        'full_name' => $user?->full_name,
        'role_id' => $user?->role_id !== null ? (int) $user->role_id : null,
        'role_name' => $role?->role_name,
        'session_id' => $verboseSensitive ? $session->session_id : $consoleMaskSessionId($session->session_id),
        'guest_name' => $verboseSensitive ? $session->guest_name : $consoleMaskName($session->guest_name),
        'phone' => $verboseSensitive ? $session->phone : $consoleMaskPhone($session->phone),
        'token_last_eight' => $session->token_last_eight,
        'session_meta' => $consoleSessionMetadataPayload($session->session_meta_json, $verboseSensitive),
        'created_ip' => $verboseSensitive ? $session->created_ip : $consoleMaskIp($session->created_ip),
        'user_agent' => $consoleSummarizeUserAgent($session->user_agent, $verboseSensitive),
        'expires_at_utc' => $consoleTimestamp($expiresAt),
        'last_used_at_utc' => $consoleTimestamp($session->last_used_at),
        'revoked_at_utc' => $consoleTimestamp($revokedAt),
        'created_at_utc' => $consoleTimestamp($session->created_at),
        'is_active' => $revokedAt === null && $expiresAt !== null && $expiresAt->isFuture(),
    ];
};

$staffApiKeyConsolePayload = static function (StaffApiKey $record) use ($consoleTimestamp): array {
    $user = $record->relationLoaded('user') ? $record->user : null;
    $role = $user?->relationLoaded('role') ? $user->role : null;
    $expiresAt = $record->expires_at?->utc();
    $revokedAt = $record->revoked_at?->utc();

    return [
        'staff_api_key_id' => (int) $record->getKey(),
        'user_id' => (int) ($record->user_id ?? 0),
        'username' => $user?->username,
        'full_name' => $user?->full_name,
        'role_id' => $user?->role_id !== null ? (int) $user->role_id : null,
        'role_name' => $role?->role_name,
        'label' => $record->label,
        'expires_at_utc' => $consoleTimestamp($expiresAt),
        'last_used_at_utc' => $consoleTimestamp($record->last_used_at),
        'revoked_at_utc' => $consoleTimestamp($revokedAt),
        'created_at_utc' => $consoleTimestamp($record->created_at),
        'is_active' => $revokedAt === null && ($expiresAt === null || $expiresAt->isFuture()),
    ];
};

$consoleValidationPayload = static function (ValidationException $exception): array {
    return [
        'error' => 'validation_error',
        'errors' => $exception->errors(),
    ];
};

$opsGateMarkdownPayload = static function (string $title, array $payload, array $summaryRows = []): string {
    $lines = [];
    $lines[] = '# '.$title;
    $lines[] = '';

    foreach ($summaryRows as $label => $value) {
        $lines[] = sprintf('- %s: `%s`', (string) $label, (string) $value);
    }

    $lines[] = '';
    $lines[] = '```json';
    $lines[] = (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $lines[] = '```';

    return implode(PHP_EOL, $lines);
};

$writeOpsGateArtifactReport = static function (
    string $artifactRoot,
    string $reportPrefix,
    string $scopeKey,
    array $payload,
    string $title,
    array $summaryRows = [],
    string $artifactKey = 'artifacts',
) use ($opsGateMarkdownPayload): array {
    /** @var OpsGateArtifactService $service */
    $service = app(OpsGateArtifactService::class);

    return $service->writeReport(
        artifactRoot: $artifactRoot,
        reportPrefix: $reportPrefix,
        scopeKey: $scopeKey,
        payload: $payload,
        markdown: $opsGateMarkdownPayload($title, $payload, $summaryRows),
        artifactKey: $artifactKey,
    );
};

require __DIR__.'/console/feature_flags.php';
require __DIR__.'/console/uat.php';
require __DIR__.'/console/ops_release.php';
require __DIR__.'/console/verification.php';
require __DIR__.'/console/auth_identity.php';
require __DIR__.'/console/site_ops.php';
require __DIR__.'/console/notifications.php';
require __DIR__.'/console/schedule.php';
require __DIR__.'/console/harness.php';
