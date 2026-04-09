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

Artisan::command('booking:bootstrap-site
    {--branch-code= : Branch code to ensure}
    {--branch-name= : Branch name to ensure}
    {--timezone= : Branch timezone}
    {--currency= : Branch currency}
    {--zones= : Comma-separated zone names}
    {--tables-per-zone=4 : Number of tables to provision per zone}
    {--admin-username= : Bootstrap admin username}
    {--admin-name= : Bootstrap admin full name}
    {--staff-username= : Bootstrap staff username}
    {--staff-name= : Bootstrap staff full name}
    {--skip-staff-key : Skip issuing or reusing a bootstrap staff API key}
    {--rotate-staff-key : Rotate the existing bootstrap staff API key}
    {--staff-key-label= : Staff API key label}
    {--staff-key-ttl-days=90 : Staff API key lifetime in days}
    {--json : Output machine-readable JSON}', function () use ($staffApiKeyConsolePayload, $consoleValidationPayload) {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $payload = [
        'branch_code' => $command->option('branch-code'),
        'branch_name' => $command->option('branch-name'),
        'timezone' => $command->option('timezone'),
        'currency' => $command->option('currency'),
        'zones' => $command->option('zones'),
        'tables_per_zone' => (int) $command->option('tables-per-zone'),
        'admin_username' => $command->option('admin-username'),
        'admin_name' => $command->option('admin-name'),
        'staff_username' => $command->option('staff-username'),
        'staff_name' => $command->option('staff-name'),
        'skip_staff_key' => (bool) $command->option('skip-staff-key'),
        'rotate_staff_key' => (bool) $command->option('rotate-staff-key'),
        'staff_key_label' => $command->option('staff-key-label'),
        'staff_key_ttl_days' => (int) $command->option('staff-key-ttl-days'),
    ];

    try {
        $result = app(SiteBootstrapService::class)->bootstrap($payload);
    } catch (ValidationException $exception) {
        $response = $consoleValidationPayload($exception);
        if ($command->option('json')) {
            $command->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }

        foreach ($response['errors'] as $field => $messages) {
            foreach ((array) $messages as $message) {
                $command->error(sprintf('%s: %s', $field, (string) $message));
            }
        }

        return 1;
    }

    if (($result['staff_api_key']['record'] ?? null) instanceof StaffApiKey) {
        $result['staff_api_key']['record'] = $staffApiKeyConsolePayload($result['staff_api_key']['record']);
    }

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Site bootstrap completed.');
    $command->table(['Field', 'Value'], [
        ['branch_code', (string) ($result['branch']['branch_code'] ?? '')],
        ['branch_name', (string) ($result['branch']['branch_name'] ?? '')],
        ['table_count', (string) ($result['tables']['count'] ?? 0)],
        ['menu_categories', (string) ($result['menu']['category_count'] ?? 0)],
        ['menu_items', (string) ($result['menu']['item_count'] ?? 0)],
        ['finance_action', (string) ($result['finance']['action'] ?? '')],
        ['admin_username', (string) ($result['users']['admin']['username'] ?? '')],
        ['staff_username', (string) ($result['users']['staff']['username'] ?? '')],
        ['staff_api_key_action', (string) ($result['staff_api_key']['action'] ?? '')],
        ['staff_api_key_plaintext', (string) ($result['staff_api_key']['plaintext_key'] ?? '')],
    ]);

    return 0;
})->purpose('Idempotently bootstrap the first operational site with branch, tables, menu, finance profile, and staff credentials.');

Artisan::command('booking:reporting-snapshots:rebuild
    {--branch-id= : Optional branch id}
    {--days= : Lookback window in days when explicit dates are omitted}
    {--start-date= : Inclusive UTC start date (YYYY-MM-DD)}
    {--end-date= : Inclusive UTC end date (YYYY-MM-DD)}
    {--sales : Rebuild sales snapshots only when any family switch is supplied}
    {--operations : Rebuild operations snapshots only when any family switch is supplied}
    {--inventory : Rebuild inventory snapshots only when any family switch is supplied}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $maxDays = max(1, (int) config('booking.reporting_snapshot_rebuild_max_days', 90));
    $days = max(1, min((int) ($command->option('days') ?: config('booking.reporting_snapshot_auto_rebuild_lookback_days', 7)), $maxDays));
    $familyFlagsSpecified = (bool) $command->option('sales') || (bool) $command->option('operations') || (bool) $command->option('inventory');
    $filters = [
        'start_date' => $command->option('start-date') ?: now('UTC')->subDays($days - 1)->toDateString(),
        'end_date' => $command->option('end-date') ?: now('UTC')->toDateString(),
        'include_sales' => $familyFlagsSpecified ? (bool) $command->option('sales') : true,
        'include_operations' => $familyFlagsSpecified ? (bool) $command->option('operations') : true,
        'include_inventory' => $familyFlagsSpecified ? (bool) $command->option('inventory') : true,
    ];
    if ($command->option('branch-id') !== null && $command->option('branch-id') !== '') {
        $filters['branch_id'] = (int) $command->option('branch-id');
    }

    $result = app(ReportingSnapshotService::class)->rebuild($filters);

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Reporting snapshots rebuilt.');
    $command->table(['Field', 'Value'], [
        ['start_date', (string) ($result['date_range']['start_date'] ?? '')],
        ['end_date', (string) ($result['date_range']['end_date'] ?? '')],
        ['branch_id', isset($result['branch_id']) && $result['branch_id'] !== null ? (string) $result['branch_id'] : 'all'],
        ['sales_rows', (string) ($result['rebuild']['sales']['row_count'] ?? 0)],
        ['operations_rows', (string) ($result['rebuild']['operations']['row_count'] ?? 0)],
        ['inventory_rows', (string) ($result['rebuild']['inventory']['row_count'] ?? 0)],
        ['warning_count', (string) count((array) ($result['warnings'] ?? []))],
    ]);

    return 0;
})->purpose('Rebuild reporting snapshot read models for controlled day-1 freshness.');

Artisan::command('booking:backfill-confirmed-hold-linkage {--dry-run : Show eligible backfills without updating rows} {--limit=500 : Maximum number of unlinked holds to inspect}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $limit = max(1, (int) $command->option('limit'));
    $dryRun = (bool) $command->option('dry-run');

    $holds = DB::table('table_holds')
        ->whereNull('confirmed_reservation_id')
        ->whereNotNull('session_id')
        ->where('session_id', '<>', '')
        ->whereIn('hold_status', ['Confirmed', 'Holding', 'Pending'])
        ->orderBy('created_at')
        ->limit($limit)
        ->get([
            'hold_id',
            'session_id',
            'user_id',
            'start_time',
            'end_time',
            'created_at',
        ]);

    $inspected = 0;
    $matched = 0;
    $updated = 0;
    $ambiguous = 0;

    foreach ($holds as $hold) {
        $inspected++;

        $holdTableIds = DB::table('table_hold_details')
            ->where('hold_id', (string) $hold->hold_id)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($holdTableIds === []) {
            continue;
        }

        $candidateReservationIds = DB::table('reservations')
            ->where('start_time', '=', $hold->start_time)
            ->where('end_time', '=', $hold->end_time)
            ->when($hold->user_id !== null, fn ($query) => $query->where('user_id', '=', (int) $hold->user_id))
            ->orderBy('reservation_id')
            ->pluck('reservation_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $exactMatches = [];
        foreach ($candidateReservationIds as $reservationId) {
            $reservationTableIds = DB::table('reservation_tables')
                ->where('reservation_id', $reservationId)
                ->orderBy('table_id')
                ->pluck('table_id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            if ($reservationTableIds === $holdTableIds) {
                $exactMatches[] = $reservationId;
            }
        }

        if (count($exactMatches) !== 1) {
            if (count($exactMatches) > 1) {
                $ambiguous++;
                $command->warn(sprintf('Ambiguous hold %s matched multiple reservations: %s', (string) $hold->hold_id, implode(',', $exactMatches)));
            }

            continue;
        }

        $matched++;
        $reservationId = $exactMatches[0];
        $command->line(sprintf('Matched hold %s -> reservation %d', (string) $hold->hold_id, $reservationId));

        if (! $dryRun) {
            $affected = DB::table('table_holds')
                ->where('hold_id', (string) $hold->hold_id)
                ->whereNull('confirmed_reservation_id')
                ->update([
                    'confirmed_reservation_id' => $reservationId,
                    'updated_at' => now('UTC'),
                ]);

            if ($affected > 0) {
                $updated += $affected;
            }
        }
    }

    $command->table(['inspected', 'matched', 'updated', 'ambiguous', 'dry_run'], [[
        $inspected,
        $matched,
        $updated,
        $ambiguous,
        $dryRun ? 'yes' : 'no',
    ]]);

    return 0;
})->purpose('Backfill table_holds.confirmed_reservation_id using exact reservation matches for legacy rows');
