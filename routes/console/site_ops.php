<?php

use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\Reporting\Application\Workflows\ReportingSnapshotWorkflow;
use App\Platform\Release\Services\SiteBootstrapService;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$consoleValidationPayload = static function (ValidationException $exception): array {
    return [
        'error' => 'validation_error',
        'errors' => $exception->errors(),
    ];
};

$staffApiKeyConsolePayload = static function (StaffApiKey $record): array {
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
        'expires_at_utc' => $expiresAt?->toIso8601String(),
        'last_used_at_utc' => $record->last_used_at?->utc()->toIso8601String(),
        'revoked_at_utc' => $revokedAt?->toIso8601String(),
        'created_at_utc' => $record->created_at?->utc()->toIso8601String(),
        'is_active' => $revokedAt === null && ($expiresAt === null || $expiresAt->isFuture()),
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
    {--show-secret-once : Reveal the plaintext bootstrap staff API key once in this command output}
    {--json : Output machine-readable JSON}', function () use ($staffApiKeyConsolePayload, $consoleValidationPayload, $consoleSecretPayload, $consoleWarnOnSecretReveal) {
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

    $revealSecret = (bool) $command->option('show-secret-once');
    $bootstrapSecret = $consoleSecretPayload(
        isset($result['staff_api_key']['plaintext_key']) && is_string($result['staff_api_key']['plaintext_key'])
            ? $result['staff_api_key']['plaintext_key']
            : null,
        $revealSecret,
    );
    $result['staff_api_key']['plaintext_key'] = $bootstrapSecret['plain'];
    $result['staff_api_key']['plaintext_key_masked'] = $bootstrapSecret['masked'];
    $result['staff_api_key']['secret_revealed'] = $bootstrapSecret['revealed'];

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info('Site bootstrap completed.');
    if ($revealSecret && $bootstrapSecret['plain'] !== null) {
        $consoleWarnOnSecretReveal($command, 'bootstrap staff API key');
    }
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
        [$revealSecret ? 'staff_api_key_plaintext' : 'staff_api_key_plaintext_masked', (string) ($revealSecret ? ($result['staff_api_key']['plaintext_key'] ?? '') : ($result['staff_api_key']['plaintext_key_masked'] ?? ''))],
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

    $result = app(ReportingSnapshotWorkflow::class)->rebuild($filters);

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

Artisan::command('booking:backfill-table-state-audit-context
    {--dry-run : Show eligible audit rows without updating them}
    {--window-hours= : How many recent hours to inspect; defaults to booking.ops.table_state_audit_recent_window_hours}
    {--limit=500 : Maximum number of recent audit rows to inspect}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $windowHours = max(1, (int) ($command->option('window-hours') ?: config('booking.ops.table_state_audit_recent_window_hours', 24)));
    $limit = max(1, (int) $command->option('limit'));
    $dryRun = (bool) $command->option('dry-run');

    if (! Schema::hasTable('audit_logs')) {
        $command->error('audit_logs table is not available.');

        return 1;
    }

    if (! Schema::hasTable('reservation_orders')) {
        $command->error('reservation_orders table is not available.');

        return 1;
    }

    $windowStart = Carbon::now('UTC')->subHours($windowHours);
    $rows = DB::table('audit_logs')
        ->where('entity_type', 'restaurant_table')
        ->where('action', 'like', 'table_state_%')
        ->whereNotNull('created_at')
        ->where('created_at', '>=', $windowStart)
        ->orderBy('audit_id')
        ->limit($limit)
        ->get([
            'audit_id',
            'after_json',
            'meta_json',
            'created_at',
        ]);

    $inspected = 0;
    $matched = 0;
    $updated = 0;
    $skippedWithContext = 0;
    $unresolved = 0;

    foreach ($rows as $row) {
        $inspected++;

        $afterPayload = json_decode((string) ($row->after_json ?? ''), true);
        if (! is_array($afterPayload)) {
            $unresolved++;

            continue;
        }

        if (is_array($afterPayload['context'] ?? null) && ($afterPayload['context'] ?? []) !== []) {
            $skippedWithContext++;

            continue;
        }

        $metaPayload = json_decode((string) ($row->meta_json ?? ''), true);
        if (! is_array($metaPayload)) {
            $unresolved++;

            continue;
        }

        $requestPath = trim((string) data_get($metaPayload, 'request.path', ''));
        if (! preg_match('#^api/v1/staff/orders/(\d+)/settlement/finalize$#', $requestPath, $matches)) {
            continue;
        }

        $orderId = (int) ($matches[1] ?? 0);
        if ($orderId <= 0) {
            $unresolved++;

            continue;
        }

        $reservationId = (int) DB::table('reservation_orders')
            ->where('order_id', $orderId)
            ->value('reservation_id');

        if ($reservationId <= 0) {
            $unresolved++;

            continue;
        }

        $matched++;

        $context = [
            'order_id' => $orderId,
            'reservation_id' => $reservationId,
            'source' => 'staff_settlement_finalize',
            'reason' => 'settlement_finalize',
        ];

        $afterPayload['context'] = $context;
        $metaPayload['context'] = array_merge(
            is_array($metaPayload['context'] ?? null) ? $metaPayload['context'] : [],
            $context,
        );

        $command->line(sprintf('Matched audit %d -> order %d -> reservation %d', (int) $row->audit_id, $orderId, $reservationId));

        if (! $dryRun) {
            $affected = DB::table('audit_logs')
                ->where('audit_id', (int) $row->audit_id)
                ->update([
                    'after_json' => json_encode($afterPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'meta_json' => json_encode($metaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

            if ($affected > 0) {
                $updated += $affected;
            }
        }
    }

    $command->table(['inspected', 'matched', 'updated', 'skipped_with_context', 'unresolved', 'dry_run'], [[
        $inspected,
        $matched,
        $updated,
        $skippedWithContext,
        $unresolved,
        $dryRun ? 'yes' : 'no',
    ]]);

    return 0;
})->purpose('Backfill missing restaurant table audit context for recent settlement finalize release rows');
