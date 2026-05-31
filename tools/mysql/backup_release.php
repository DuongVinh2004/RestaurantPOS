<?php

declare(strict_types=1);

$rootDir = realpath(__DIR__.'/../../');
if ($rootDir === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$autoload = $rootDir.'/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    require_once $rootDir.'/app/Platform/Backup/Support/BackupArtifactManifest.php';
    require_once $rootDir.'/app/Platform/Delivery/Release/Application/Verifiers/PortableSqlSanitizer.php';
}

use App\Platform\Backup\Support\BackupArtifactManifest;
use App\Platform\Delivery\Release\Application\Verifiers\PortableSqlSanitizer;

$options = parseOptions($argv);
$dbHost = envOr('DB_HOST', envOr('MYSQL_HOST', '127.0.0.1'));
$dbPort = (int) envOr('DB_PORT', envOr('MYSQL_PORT', '3306'));
$dbUser = envOr('DB_USERNAME', envOr('MYSQL_USER', 'root'));
$dbPassword = envOr('DB_PASSWORD', envOr('MYSQL_PASSWORD', ''));
$dbDatabase = envOr('DB_DATABASE', envOr('MYSQL_DATABASE', ''));
$backupRootInput = (string) ($options['output-dir'] ?? envOr('BOOKING_BACKUP_ROOT', $rootDir.'/storage/app/booking_backups'));
$backupRoot = normalizePath($backupRootInput, $rootDir);
$retentionDays = max(0, (int) ($options['retention-days'] ?? envOr('BOOKING_BACKUP_RETENTION_DAYS', '14')));
$compress = parseBool($options['compress'] ?? envOr('BOOKING_BACKUP_COMPRESS', 'true'));
$includeSchema = ! isset($options['skip-schema']);
$includeFull = ! isset($options['skip-full']);
$prune = ! isset($options['no-prune']);
$json = isset($options['json']);

if ($dbDatabase === '') {
    fail('DB_DATABASE (or MYSQL_DATABASE) is required.', $json);
}

if (! $includeSchema && ! $includeFull) {
    fail('At least one artifact must be enabled. Remove --skip-schema or --skip-full.', $json);
}

if (! is_dir($backupRoot) && ! mkdir($backupRoot, 0777, true) && ! is_dir($backupRoot)) {
    fail(sprintf('Unable to create backup root: %s', $backupRoot), $json);
}

$timestamp = gmdate('Ymd\THis\Z');
$directoryName = $timestamp.'-'.preg_replace('/[^A-Za-z0-9_.-]+/', '-', $dbDatabase);
$backupDir = $backupRoot.DIRECTORY_SEPARATOR.$directoryName;
if (! mkdir($backupDir, 0777, true) && ! is_dir($backupDir)) {
    fail(sprintf('Unable to create backup directory: %s', $backupDir), $json);
}

$issues = [];
$artifacts = [];
$checksumLines = [];

try {
    if ($includeSchema) {
        $schemaSql = dumpDatabase(
            [
                '--no-data',
                '--routines',
                '--events',
                '--triggers',
                '--single-transaction',
                '--skip-comments',
                '--set-gtid-purged=OFF',
            ],
            $dbHost,
            $dbPort,
            $dbUser,
            $dbPassword,
            $dbDatabase,
        );

        $sanitized = PortableSqlSanitizer::sanitize($schemaSql);
        $schemaPath = $backupDir.DIRECTORY_SEPARATOR.'schema.sql';
        writeContents($schemaPath, $sanitized['sql']);
        $finalSchemaPath = $compress ? gzipFile($schemaPath) : $schemaPath;
        if ($compress) {
            @unlink($schemaPath);
        }

        $relativePath = basename($finalSchemaPath);
        $artifacts['schema'] = BackupArtifactManifest::describeFile($finalSchemaPath, $relativePath) + [
            'portable' => true,
            'compressed' => $compress,
            'sanitized' => (bool) $sanitized['changed'],
        ];
        $checksumLines[] = $artifacts['schema']['sha256'].'  '.basename($finalSchemaPath);
    }

    if ($includeFull) {
        $fullSql = dumpDatabase(
            [
                '--routines',
                '--events',
                '--triggers',
                '--single-transaction',
                '--skip-comments',
                '--set-gtid-purged=OFF',
            ],
            $dbHost,
            $dbPort,
            $dbUser,
            $dbPassword,
            $dbDatabase,
        );

        $fullPath = $backupDir.DIRECTORY_SEPARATOR.'full.sql';
        writeContents($fullPath, $fullSql);
        $finalFullPath = $compress ? gzipFile($fullPath) : $fullPath;
        if ($compress) {
            @unlink($fullPath);
        }

        $relativePath = basename($finalFullPath);
        $artifacts['full'] = BackupArtifactManifest::describeFile($finalFullPath, $relativePath) + [
            'portable' => false,
            'compressed' => $compress,
        ];
        $checksumLines[] = $artifacts['full']['sha256'].'  '.basename($finalFullPath);
    }

    $pruned = $prune ? pruneOldBackups($backupRoot, $backupDir, $retentionDays, $rootDir) : [];

    $manifest = BackupArtifactManifest::build([
        'generated_at_utc' => gmdate('c'),
        'backup_root' => relativePath($backupRoot, $rootDir),
        'backup_directory' => relativePath($backupDir, $rootDir),
        'db_host' => $dbHost,
        'db_port' => $dbPort,
        'db_database' => $dbDatabase,
        'compress' => $compress,
        'retention_days' => $retentionDays,
        'issues' => $issues,
    ], $artifacts, $pruned);

    $manifestPath = $backupDir.DIRECTORY_SEPARATOR.'manifest.json';
    writeContents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    $checksumLines[] = hash_file('sha256', $manifestPath).'  manifest.json';
    writeContents($backupDir.DIRECTORY_SEPARATOR.'checksums.sha256', implode(PHP_EOL, $checksumLines).PHP_EOL);
    writeContents($backupRoot.DIRECTORY_SEPARATOR.'latest-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    $result = [
        'ok' => true,
        'status' => 'ok',
        'backup_directory' => relativePath($backupDir, $rootDir),
        'manifest_path' => relativePath($manifestPath, $rootDir),
        'artifacts' => $artifacts,
        'pruned_directories' => $pruned,
        'meta' => [
            'database' => $dbDatabase,
            'compress' => $compress,
            'retention_days' => $retentionDays,
            'timestamp_utc' => gmdate('c'),
        ],
    ];

    if ($json) {
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    } else {
        fwrite(STDOUT, sprintf("Backup written to %s\n", $result['backup_directory']));
        foreach ($artifacts as $name => $artifact) {
            fwrite(STDOUT, sprintf(" - %s: %s (%d bytes)\n", $name, $artifact['path'], (int) ($artifact['bytes'] ?? 0)));
        }
        fwrite(STDOUT, sprintf("Manifest: %s\n", $result['manifest_path']));
        if ($pruned !== []) {
            fwrite(STDOUT, "Pruned:\n");
            foreach ($pruned as $path) {
                fwrite(STDOUT, ' - '.$path.PHP_EOL);
            }
        }
    }

    exit(0);
} catch (Throwable $e) {
    rrmdir($backupDir);
    fail($e->getMessage(), $json);
}

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

function envOr(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }

    return (string) $value;
}

function parseBool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function normalizePath(string $path, string $rootDir): string
{
    if ($path === '') {
        return $rootDir.DIRECTORY_SEPARATOR.'storage/app/booking_backups';
    }

    if ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
        return rtrim($path, '\\/');
    }

    return rtrim($rootDir.DIRECTORY_SEPARATOR.ltrim($path, '\\/'), '\\/');
}

function dumpDatabase(array $dumpOptions, string $host, int $port, string $user, string $password, string $database): string
{
    $command = [
        'mysqldump',
        sprintf('--host=%s', $host),
        sprintf('--port=%d', $port),
        sprintf('--user=%s', $user),
        '--default-character-set=utf8mb4',
    ];

    if ($password !== '') {
        $command[] = sprintf('--password=%s', $password);
    }

    foreach ($dumpOptions as $option) {
        $command[] = $option;
    }

    $command[] = $database;

    $escapedCommand = implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $command));
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($escapedCommand, $descriptor, $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start mysqldump process.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0 || $stdout === false) {
        throw new RuntimeException(sprintf('mysqldump failed: %s', trim((string) $stderr)));
    }

    return $stdout;
}

function writeContents(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException(sprintf('Unable to write file: %s', $path));
    }
}

function gzipFile(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException(sprintf('Unable to read file for compression: %s', $path));
    }

    $compressed = gzencode($contents, 9);
    if ($compressed === false) {
        throw new RuntimeException(sprintf('Unable to compress file: %s', $path));
    }

    $target = $path.'.gz';
    writeContents($target, $compressed);

    return $target;
}

/**
 * @return list<string>
 */
function pruneOldBackups(string $backupRoot, string $currentBackupDir, int $retentionDays, string $rootDir): array
{
    if ($retentionDays <= 0 || ! is_dir($backupRoot)) {
        return [];
    }

    $cutoff = time() - ($retentionDays * 86400);
    $pruned = [];

    foreach (new DirectoryIterator($backupRoot) as $item) {
        if ($item->isDot() || ! $item->isDir()) {
            continue;
        }

        $path = $item->getPathname();
        if ($path === $currentBackupDir) {
            continue;
        }

        if ($item->getMTime() >= $cutoff) {
            continue;
        }

        rrmdir($path);
        $pruned[] = relativePath($path, $rootDir);
    }

    sort($pruned);

    return $pruned;
}

function rrmdir(string $path): void
{
    if (! is_dir($path)) {
        @unlink($path);

        return;
    }

    $items = scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $child = $path.DIRECTORY_SEPARATOR.$item;
        if (is_dir($child)) {
            rrmdir($child);

            continue;
        }

        @unlink($child);
    }

    @rmdir($path);
}

function relativePath(string $path, string $rootDir): string
{
    $normalizedPath = str_replace('\\', '/', $path);
    $normalizedRoot = rtrim(str_replace('\\', '/', $rootDir), '/');

    if (str_starts_with($normalizedPath, $normalizedRoot.'/')) {
        return substr($normalizedPath, strlen($normalizedRoot) + 1);
    }

    return $normalizedPath;
}

function fail(string $message, bool $json): void
{
    if ($json) {
        fwrite(STDOUT, json_encode([
            'ok' => false,
            'status' => 'fail',
            'error' => $message,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    } else {
        fwrite(STDERR, $message.PHP_EOL);
    }

    exit(1);
}
