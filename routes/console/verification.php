<?php

declare(strict_types=1);

use App\Services\Verification\VerificationSelectorService;
use Illuminate\Console\Command as ConsoleCommand;

use function Symfony\Component\String\u;

use Illuminate\Support\Facades\Artisan;
Artisan::command('booking:verify-select
    {--path=* : Explicit changed paths to analyze}
    {--base= : Optional Git base ref for branch diff collection}
    {--stdin : Read newline-delimited paths from standard input when Git metadata is unavailable}
    {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    /** @var VerificationSelectorService $service */
    $service = app(VerificationSelectorService::class);

    $stdin = null;
    if ((bool) $command->option('stdin')) {
        $stdin = stream_get_contents(STDIN);
    }

    try {
        $payload = $service->buildReport(
            explicitPaths: array_values(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), (array) $command->option('path')), static fn (string $value): bool => $value !== '')),
            base: trim((string) ($command->option('base') ?? '')) !== '' ? trim((string) $command->option('base')) : null,
            stdin: $stdin,
        );
    } catch (RuntimeException $exception) {
        if ((bool) $command->option('json')) {
            $command->line(json_encode([
                'ok' => false,
                'error' => 'verification_selection_failed',
                'message' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return 1;
        }

        $command->error($exception->getMessage());

        return 1;
    }

    $payload['ok'] = true;

    if ((bool) $command->option('json')) {
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return 0;
    }

    $command->info('Booking verify selector');
    $command->newLine();

    $command->line('Changed files:');
    foreach ((array) ($payload['paths'] ?? []) as $path) {
        $command->line(' - '.(string) $path);
    }

    $command->newLine();
    $command->line('Matched domains:');
    foreach ((array) ($payload['domains'] ?? []) as $domain) {
        $command->line(sprintf(' - %s (%s)', (string) ($domain['label'] ?? $domain['key'] ?? ''), (string) ($domain['key'] ?? 'unknown')));
    }

    $command->newLine();
    $command->line('Recommended skills:');
    foreach ((array) ($payload['skills'] ?? []) as $skill) {
        $command->line(' - $'.(string) $skill);
    }

    $command->newLine();
    $command->line('Recommended commands:');
    foreach ((array) ($payload['commands'] ?? []) as $recommendation) {
        $command->line(sprintf(
            ' - [%s] %s',
            (string) ($recommendation['tier'] ?? 'verify'),
            (string) ($recommendation['command'] ?? '')
        ));
    }

    if ((array) ($payload['notes'] ?? []) !== []) {
        $command->newLine();
        $command->line('Notes:');
        foreach ((array) ($payload['notes'] ?? []) as $note) {
            $command->line(' - '.(string) $note);
        }
    }

    return 0;
})->purpose('Recommend a deterministic verification ladder from explicit paths or Git diff state');
