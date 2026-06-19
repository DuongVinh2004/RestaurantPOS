<?php

use App\Platform\QualityAssurance\UatPackService;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Support\Facades\Artisan;

Artisan::command('booking:uat-pack:bootstrap
    {--base-url= : Base URL for the scenario pack manifest}
    {--manifest-path= : Output manifest path}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line
    $command = $this;

    $payload = [
        'base_url' => $command->option('base-url') ?? 'http://127.0.0.1:8000',
        'manifest_path' => $command->option('manifest-path') ?? 'storage/app/uat/scenario-pack.json',
    ];

    try {
        $result = app(UatPackService::class)->bootstrap($payload);
    } catch (\Exception $exception) {
        if ($command->option('json')) {
            $command->line(json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 1;
        }
        $command->error($exception->getMessage());
        return 1;
    }

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 0;
    }

    $command->info('UAT pack bootstrapped.');
    return 0;
})->purpose('Bootstrap UAT scenario pack and generate manifest');

Artisan::command('booking:uat-pack:reset
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line
    $command = $this;

    try {
        $result = app(UatPackService::class)->reset();
    } catch (\Exception $exception) {
        if ($command->option('json')) {
            $command->line(json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 1;
        }
        $command->error($exception->getMessage());
        return 1;
    }

    if ($command->option('json')) {
        $command->line(json_encode(['ok' => true, 'data' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 0;
    }

    $command->info('UAT pack reset.');
    return 0;
})->purpose('Reset UAT scenario pack data and delete manifest');
