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

$customerAccessSessionConsolePayload = static function (CustomerAccessSession $session) use ($consoleTimestamp): array {
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
        'session_id' => $session->session_id,
        'guest_name' => $session->guest_name,
        'phone' => $session->phone,
        'token_last_eight' => $session->token_last_eight,
        'session_meta' => $session->session_meta_json,
        'created_ip' => $session->created_ip,
        'user_agent' => $session->user_agent,
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
