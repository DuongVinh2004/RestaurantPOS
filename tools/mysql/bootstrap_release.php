<?php

declare(strict_types=1);

$rootDir = realpath(__DIR__ . '/../../');
if ($rootDir === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$options = parseOptions($argv);
$json = isset($options['json']);
$envFile = normalizeEnvFile((string) ($options['env-file'] ?? '.env'), $rootDir);
$envFromFile = loadEnvFile($envFile);
$mysqlBinary = resolveEnvValue('MYSQL_BIN', 'MYSQL_BIN', 'mysql', $envFromFile);

$dbHost = resolveEnvValue('DB_HOST', 'MYSQL_HOST', '127.0.0.1', $envFromFile);
$dbPort = (int) resolveEnvValue('DB_PORT', 'MYSQL_PORT', '3306', $envFromFile);
$dbUser = resolveEnvValue('DB_USERNAME', 'MYSQL_USER', 'root', $envFromFile);
$dbPassword = resolveEnvValue('DB_PASSWORD', 'MYSQL_PASSWORD', '', $envFromFile);
$dbDatabase = resolveEnvValue('DB_DATABASE', 'MYSQL_DATABASE', '', $envFromFile);
$skipCreateDatabase = isset($options['skip-create-db']);

if ($dbDatabase === '') {
    fail('DB_DATABASE (or MYSQL_DATABASE) is required.', $json);
}

$schemaSql = $rootDir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema' . DIRECTORY_SEPARATOR . 'mysql-schema.sql';
$patchDir = $rootDir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'patches';
$verifyContractSql = $rootDir . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'verify_release_contract.sql';
if (! is_file($schemaSql)) {
    fail(sprintf('Schema dump not found at [%s].', relativePath($schemaSql, $rootDir)), $json);
}

if (! is_file($verifyContractSql)) {
    fail(sprintf('Release contract verification script not found at [%s].', relativePath($verifyContractSql, $rootDir)), $json);
}

$patchFiles = glob($patchDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
sort($patchFiles, SORT_NATURAL | SORT_FLAG_CASE);

try {
    if (! $skipCreateDatabase) {
        runMysqlStatement(
            sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
                str_replace('`', '``', $dbDatabase)
            ),
            $mysqlBinary,
            $dbHost,
            $dbPort,
            $dbUser,
            $dbPassword,
            null,
        );
    }

    importSqlFile($schemaSql, $mysqlBinary, $dbHost, $dbPort, $dbUser, $dbPassword, $dbDatabase);

    foreach ($patchFiles as $patchFile) {
        importSqlFile($patchFile, $mysqlBinary, $dbHost, $dbPort, $dbUser, $dbPassword, $dbDatabase);
    }

    importSqlFile($verifyContractSql, $mysqlBinary, $dbHost, $dbPort, $dbUser, $dbPassword, $dbDatabase);
} catch (Throwable $exception) {
    fail($exception->getMessage(), $json);
}

$payload = [
    'ok' => true,
    'status' => 'ok',
    'database' => $dbDatabase,
    'schema_dump' => relativePath($schemaSql, $rootDir),
    'contract_verification' => relativePath($verifyContractSql, $rootDir),
    'patches' => array_map(
        static fn (string $path): string => relativePath($path, $rootDir),
        $patchFiles
    ),
    'meta' => [
        'db_host' => $dbHost,
        'db_port' => $dbPort,
        'patch_count' => count($patchFiles),
        'env_file' => relativePath($envFile, $rootDir),
        'contract_verified' => true,
    ],
];

if ($json) {
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} else {
    fwrite(STDOUT, sprintf(
        "Release database bootstrap completed for %s using %s, %d patch(es), and contract verification via %s.\n",
        $dbDatabase,
        $payload['schema_dump'],
        count($patchFiles),
        $payload['contract_verification'],
    ));
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
 * @return array<string,string>
 */
function loadEnvFile(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = $value;
    }

    return $values;
}

/**
 * @param array<string,string> $envFromFile
 */
function resolveEnvValue(string $primaryKey, string $secondaryKey, string $default, array $envFromFile): string
{
    $runtimePrimary = getenv($primaryKey);
    if ($runtimePrimary !== false && $runtimePrimary !== null && $runtimePrimary !== '') {
        return (string) $runtimePrimary;
    }

    $runtimeSecondary = getenv($secondaryKey);
    if ($runtimeSecondary !== false && $runtimeSecondary !== null && $runtimeSecondary !== '') {
        return (string) $runtimeSecondary;
    }

    foreach ([$primaryKey, $secondaryKey] as $key) {
        $value = $envFromFile[$key] ?? null;
        if ($value !== null && $value !== '') {
            return (string) $value;
        }
    }

    return $default;
}

function normalizeEnvFile(string $envFile, string $rootDir): string
{
    if ($envFile === '') {
        return $rootDir . DIRECTORY_SEPARATOR . '.env';
    }

    if ($envFile[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\\/]/', $envFile) === 1) {
        return $envFile;
    }

    return $rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $envFile);
}

function runMysqlStatement(
    string $statement,
    string $mysqlBinary,
    string $host,
    int $port,
    string $user,
    string $password,
    ?string $database,
): void {
    $command = buildMysqlCommand($mysqlBinary, $host, $port, $user, $password, $database);
    $command[] = '--execute=' . $statement;

    runProcess($command, '');
}

function importSqlFile(
    string $path,
    string $mysqlBinary,
    string $host,
    int $port,
    string $user,
    string $password,
    string $database,
): void {
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException(sprintf('Unable to read SQL file [%s].', $path));
    }

    $command = buildMysqlCommand($mysqlBinary, $host, $port, $user, $password, $database);
    runProcess($command, $contents);
}

/**
 * @return list<string>
 */
function buildMysqlCommand(string $mysqlBinary, string $host, int $port, string $user, string $password, ?string $database): array
{
    $command = [
        $mysqlBinary,
        sprintf('--host=%s', $host),
        sprintf('--port=%d', $port),
        sprintf('--user=%s', $user),
        '--default-character-set=utf8mb4',
    ];

    if ($password !== '') {
        $command[] = sprintf('--password=%s', $password);
    }

    if ($database !== null && $database !== '') {
        $command[] = $database;
    }

    return $command;
}

function runProcess(array $command, string $stdin = ''): void
{
    $escaped = implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $command));
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($escaped, $descriptorSpec, $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start mysql process.');
    }

    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $message = trim((string) $stderr);
        if ($message === '' && $stdout !== false) {
            $message = trim((string) $stdout);
        }

        throw new RuntimeException($message !== '' ? $message : 'mysql process failed.');
    }
}

function relativePath(string $path, string $rootDir): string
{
    $normalizedPath = str_replace('\\', '/', $path);
    $normalizedRoot = rtrim(str_replace('\\', '/', $rootDir), '/');

    if (str_starts_with($normalizedPath, $normalizedRoot . '/')) {
        return substr($normalizedPath, strlen($normalizedRoot) + 1);
    }

    return $normalizedPath;
}

function fail(string $message, bool $json): void
{
    $payload = [
        'ok' => false,
        'status' => 'fail',
        'error' => $message,
    ];

    if ($json) {
        fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    } else {
        fwrite(STDERR, $message . PHP_EOL);
    }

    exit(1);
}
