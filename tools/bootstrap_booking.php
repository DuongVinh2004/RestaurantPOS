<?php

declare(strict_types=1);

$rootDir = realpath(__DIR__.'/..');
if ($rootDir === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$options = parseOptions($argv);
$json = isset($options['json']);
$envFile = (string) ($options['env-file'] ?? '.env');
$artisanEnvironment = resolveArtisanEnvironment($envFile);
$phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';

$steps = [];

try {
    if (! isset($options['skip-db-bootstrap'])) {
        $dbBootstrapArguments = ['tools/mysql/bootstrap_release.php', '--env-file='.$envFile, '--json'];
        if (isset($options['skip-create-db'])) {
            $dbBootstrapArguments[] = '--skip-create-db';
        }

        $steps[] = runPhpScript(
            $rootDir,
            $phpBinary,
            $dbBootstrapArguments,
            'bootstrap_release_db',
        );
    }

    $steps[] = runArtisan($rootDir, $phpBinary, $artisanEnvironment, ['db:seed', '--class=ReferenceDataSeeder', '--force'], 'seed_reference_data');
    $steps[] = runArtisan($rootDir, $phpBinary, $artisanEnvironment, ['config:clear', '--ansi'], 'config_clear');
    $steps[] = runArtisan($rootDir, $phpBinary, $artisanEnvironment, ['cache:clear', '--ansi'], 'cache_clear');
    $steps[] = runArtisan($rootDir, $phpBinary, $artisanEnvironment, ['route:clear', '--ansi'], 'route_clear');
    $steps[] = runArtisan($rootDir, $phpBinary, $artisanEnvironment, ['view:clear', '--ansi'], 'view_clear');

    if (! isset($options['skip-site-bootstrap'])) {
        $steps[] = runArtisan($rootDir, $phpBinary, $artisanEnvironment, ['booking:bootstrap-site', '--json'], 'bootstrap_site');
    }

    if (! isset($options['skip-reporting'])) {
        $steps[] = runArtisan($rootDir, $phpBinary, $artisanEnvironment, ['booking:reporting-snapshots:rebuild', '--days=7', '--json'], 'rebuild_reporting_snapshots');
    }

    if (! isset($options['skip-artifacts-normalize'])) {
        $steps[] = runArtisan($rootDir, $phpBinary, $artisanEnvironment, ['booking:artifacts-normalize', '--json'], 'normalize_release_artifacts');
    }

    if (! isset($options['skip-release-manifest'])) {
        $steps[] = runArtisan($rootDir, $phpBinary, $artisanEnvironment, ['booking:release-manifest', '--json'], 'release_manifest');
    }

    if (! isset($options['skip-runtime-prime'])) {
        $steps[] = runArtisan($rootDir, $phpBinary, $artisanEnvironment, ['booking:ops-heartbeat:touch', 'scheduler', '--json'], 'prime_scheduler_heartbeat');
    }
} catch (Throwable $exception) {
    fail($exception->getMessage(), $json);
}

$payload = [
    'ok' => true,
    'status' => 'ok',
    'steps' => $steps,
    'meta' => [
        'env_file' => $envFile,
    ],
];

if ($json) {
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
} else {
    fwrite(STDOUT, "Booking bootstrap completed.\n");
    foreach ($steps as $step) {
        fwrite(STDOUT, sprintf(" - %s\n", $step['step']));
    }
}

exit(0);

/**
 * @return array<string,string|bool>
 */
function parseOptions(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $argument) {
        if (! str_starts_with($argument, '--')) {
            continue;
        }

        $trimmed = substr($argument, 2);
        if (str_contains($trimmed, '=')) {
            [$key, $value] = explode('=', $trimmed, 2);
            $options[$key] = $value;

            continue;
        }

        $options[$trimmed] = true;
    }

    return $options;
}

/**
 * @param  list<string>  $arguments
 * @return array{step:string,command:string,output:mixed}
 */
function runArtisan(string $rootDir, string $phpBinary, ?string $artisanEnvironment, array $arguments, string $step): array
{
    $command = ['artisan'];
    if ($artisanEnvironment !== null) {
        $command[] = '--env='.$artisanEnvironment;
    }
    $command = array_merge($command, $arguments);

    return [
        'step' => $step,
        'command' => basename($phpBinary).' '.implode(' ', $command),
        'output' => runPhpCommand($rootDir, $phpBinary, $command),
    ];
}

/**
 * @param  list<string>  $arguments
 * @return array{step:string,command:string,output:mixed}
 */
function runPhpScript(string $rootDir, string $phpBinary, array $arguments, string $step): array
{
    return [
        'step' => $step,
        'command' => basename($phpBinary).' '.implode(' ', $arguments),
        'output' => runPhpCommand($rootDir, $phpBinary, $arguments),
    ];
}

/**
 * @param  list<string>  $arguments
 */
function runPhpCommand(string $rootDir, string $phpBinary, array $arguments): mixed
{
    $command = array_merge([$phpBinary], $arguments);
    $escaped = implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $command));

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($escaped, $descriptorSpec, $pipes, $rootDir);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start PHP command.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $error = trim((string) $stderr);
        if ($error === '' && $stdout !== false) {
            $error = trim((string) $stdout);
        }

        throw new RuntimeException($error !== '' ? $error : sprintf('Command [%s] failed.', implode(' ', $command)));
    }

    $stdout = trim((string) $stdout);
    if ($stdout === '') {
        return null;
    }

    $decoded = json_decode($stdout, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }

    return $stdout;
}

function resolveArtisanEnvironment(string $envFile): ?string
{
    $basename = basename($envFile);
    if ($basename === '.env' || ! str_starts_with($basename, '.env.')) {
        return null;
    }

    $environment = trim(substr($basename, 5));

    return $environment !== '' ? $environment : null;
}

function fail(string $message, bool $json): void
{
    $payload = [
        'ok' => false,
        'status' => 'fail',
        'error' => $message,
    ];

    if ($json) {
        fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    } else {
        fwrite(STDERR, $message.PHP_EOL);
    }

    exit(1);
}
