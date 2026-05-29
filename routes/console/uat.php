<?php

use App\Platform\Uat\UatScenarioPackService;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

Artisan::command('booking:qa-branch-247
    {--branch=UATDEMO : The branch code to update}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $isLocalTesting = app()->environment(['local', 'testing']);
    $isUat = app()->environment('uat') && env('QA_AUTOMATION_COMMANDS_ENABLED') === true;

    if (! ($isLocalTesting || $isUat)) {
        $payload = ['error' => 'forbidden', 'message' => 'This command is only available in local/testing/uat environments.'];
        if ($command->option('json')) {
            $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 1;
        }
        $command->error($payload['message']);

        return 1;
    }

    $branchCode = (string) $command->option('branch');
    $branch = DB::table('branches')->where('branch_code', $branchCode)->first();

    if (! $branch) {
        $command->error("Branch {$branchCode} not found.");

        return 1;
    }

    $businessHours = [];
    for ($i = 0; $i <= 6; $i++) {
        $businessHours[] = [
            'day_of_week' => $i,
            'periods' => [['start_time' => '00:00', 'end_time' => '23:59']],
        ];
    }

    $bookingPolicy = json_decode($branch->booking_policy ?? '{}', true);
    if (! is_array($bookingPolicy)) {
        $bookingPolicy = [];
    }
    $bookingPolicy['reservation'] = array_merge((array) ($bookingPolicy['reservation'] ?? []), [
        'same_day_cutoff_time' => '23:59',
        'min_lead_time_minutes' => 5,
        'max_advance_time_minutes' => 43200,
    ]);
    $bookingPolicy['waiting_list'] = array_merge((array) ($bookingPolicy['waiting_list'] ?? []), [
        'default_service_minutes' => 30,
    ]);

    DB::table('branches')->where('branch_id', $branch->branch_id)->update([
        'business_hours' => json_encode($businessHours),
        'booking_policy' => json_encode($bookingPolicy),
        'updated_at' => now('UTC'),
    ]);

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'message' => "Branch {$branchCode} is now 24/7 with 5m lead time"], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }

    $command->info("Branch {$branchCode} is now 24/7 with 5m lead time.");

    return 0;
})->purpose('Set branch to 24/7 with 5m lead time for QA/automation purposes (local/testing/UAT only).');

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
