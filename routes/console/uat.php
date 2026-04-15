<?php

use App\Models\CustomerAccessSession;
use App\Models\StaffApiKey;
use App\Models\User;
use App\Platform\ApiContract\ApiArtifacts\ApiConsumerArtifactService;
use App\Platform\ApiContract\Services\OpenApiSpecService;
use App\Platform\Release\Services\BookingDeploySafetyService;
use App\Platform\Health\Services\BookingDoctorService;
use App\Platform\Health\Services\BookingMaintenanceService;
use App\Platform\Release\Services\CoreOpsGateService;
use App\Services\CustomerAccessSessionService;
use App\Modules\PrivacyAudit\Application\Services\DataRetentionService;
use App\Platform\Backup\DisasterRecovery\DisasterRecoveryDrillService;
use App\Platform\FeatureFlags\Services\FeatureFlagManagementService;
use App\Platform\Release\Services\LaunchReadinessService;
use App\Modules\Notifications\Application\Services\NotificationOutboxHealthService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Platform\Metrics\Services\OperationalAlertService;
use App\Platform\Metrics\Services\OperationalInsightsService;
use App\Platform\ApiContract\Services\OpsGateArtifactService;
use App\Platform\Health\Services\OpsHeartbeatService;
use App\Platform\Performance\PerformanceVerificationService;
use App\Platform\Release\Services\ReleaseArtifactManifestService;
use App\Platform\Release\Services\ReleaseArtifactNormalizerService;
use App\Platform\Release\Services\ReleasePackageService;
use App\Modules\Reporting\Application\Services\ReportingSnapshotService;
use App\Platform\Release\Services\RoundFiveGateService;
use App\Platform\ApiContract\Services\RouteInventoryGateService;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use App\Platform\Release\Services\SiteBootstrapService;
use App\Modules\WaitingList\Application\Services\StaffWaitingListService;
use App\Services\StaffApiKeyGovernanceService;
use App\Platform\Uat\UatScenarioPackService;
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

$consoleValidationPayload = static function (ValidationException $exception): array {
    return [
        'error' => 'validation_error',
        'errors' => $exception->errors(),
    ];
};

Artisan::command('booking:uat-pack:bootstrap
    {--base-url= : Base API URL written into the generated manifest}
    {--manifest-path= : Absolute or repo-relative output path for the generated manifest}
    {--json : Output machine-readable JSON}', function () use ($consoleValidationPayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    try {
        $result = app(UatScenarioPackService::class)->bootstrap(
            $command->option('base-url') !== null && $command->option('base-url') !== ''
                ? (string) $command->option('base-url')
                : null,
            $command->option('manifest-path') !== null && $command->option('manifest-path') !== ''
                ? (string) $command->option('manifest-path')
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

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $summary = (array) ($result['summary'] ?? []);
    $command->info('UAT scenario pack bootstrapped.');
    $command->table(['Field', 'Value'], [
        ['manifest_path', (string) ($result['manifest_path'] ?? '')],
        ['branch_code', (string) data_get($summary, 'branch.branch_code', '')],
        ['branch_name', (string) data_get($summary, 'branch.branch_name', '')],
        ['scenario_count', (string) count((array) data_get($summary, 'supported_scenarios', []))],
    ]);

    $command->newLine();
    $command->table(
        ['Username', 'Role'],
        collect((array) data_get($summary, 'users', []))->map(static fn (array $row): array => [
            (string) ($row['username'] ?? ''),
            (string) ($row['role_name'] ?? ''),
        ])->values()->all()
    );

    return 0;
})->purpose('Reset and seed a canonical RestaurantPOS UAT/demo scenario pack with manifest output.');

Artisan::command('booking:uat-pack:reset
    {--keep-manifest : Keep the previously generated manifest file on disk}
    {--manifest-path= : Absolute or repo-relative manifest path to delete when resetting}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $result = app(UatScenarioPackService::class)->reset(
        deleteManifest: ! (bool) $command->option('keep-manifest'),
        manifestPath: $command->option('manifest-path') !== null && $command->option('manifest-path') !== ''
            ? (string) $command->option('manifest-path')
            : null,
    );

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $deleted = collect((array) ($result['deleted'] ?? []))
        ->filter(static fn (mixed $count): bool => (int) $count > 0)
        ->map(static fn (mixed $count, string $table): array => [$table, (string) $count])
        ->values()
        ->all();

    $command->info('UAT scenario pack data reset completed.');
    $command->table(['Field', 'Value'], [
        ['manifest_path', (string) ($result['manifest_path'] ?? '')],
        ['manifest_deleted', (bool) ($result['manifest_deleted'] ?? false) ? 'yes' : 'no'],
        ['tables_touched', (string) count((array) ($result['deleted'] ?? []))],
    ]);

    if ($deleted !== []) {
        $command->newLine();
        $command->table(['Table', 'Deleted'], $deleted);
    }

    return 0;
})->purpose('Delete canonical UAT/demo scenario-pack data and optionally remove its manifest file.');
