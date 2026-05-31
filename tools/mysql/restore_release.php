<?php

declare(strict_types=1);

use App\Platform\Backup\Support\BackupRestoreManifest;

$repoRoot = dirname(__DIR__, 2);
$autoload = $repoRoot.'/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    require_once $repoRoot.'/app/Support/BackupRestoreManifest.php';
}

if (! function_exists('restore_exit')) {
    /**
     * @param  array<string, mixed>  $payload
     */
    function restore_exit(array $payload, int $exitCode): never
    {
        $json = in_array('--json', $_SERVER['argv'] ?? [], true);

        if ($json) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        } else {
            foreach (($payload['errors'] ?? []) as $line) {
                fwrite(STDERR, '[ERROR] '.$line.PHP_EOL);
            }
            foreach (($payload['warnings'] ?? []) as $line) {
                fwrite(STDOUT, '[WARN] '.$line.PHP_EOL);
            }
            foreach (($payload['steps'] ?? []) as $name => $step) {
                $status = strtoupper((string) ($step['status'] ?? 'unknown'));
                $message = (string) ($step['message'] ?? '');
                fwrite(STDOUT, sprintf('[%s] %s: %s', $status, $name, $message).PHP_EOL);
            }
        }

        exit($exitCode);
    }

    function restore_env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    function restore_default_payload(string $repoRoot): array
    {
        return [
            'ok' => false,
            'repo_root' => $repoRoot,
            'timestamp_utc' => gmdate('c'),
            'steps' => [],
            'warnings' => [],
            'errors' => [],
        ];
    }

    function restore_sql_identifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    /**
     * @param  list<string>  $args
     * @param  array<string, string>  $envOverrides
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    function restore_run_process(array $args, array $envOverrides = [], ?string $stdinPath = null, ?string $cwd = null): array
    {
        $descriptors = [
            0 => $stdinPath ? ['file', $stdinPath, 'r'] : ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $environment = array_filter(
            array_merge($_ENV, $_SERVER, $envOverrides),
            static fn (mixed $value): bool => is_scalar($value) || $value === null
        );
        $process = proc_open($args, $descriptors, $pipes, $cwd, $environment);
        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start external process.');
        }

        if (! $stdinPath && is_resource($pipes[0] ?? null)) {
            fclose($pipes[0]);
        }

        $stdout = is_resource($pipes[1] ?? null) ? stream_get_contents($pipes[1]) : '';
        if (is_resource($pipes[1] ?? null)) {
            fclose($pipes[1]);
        }

        $stderr = is_resource($pipes[2] ?? null) ? stream_get_contents($pipes[2]) : '';
        if (is_resource($pipes[2] ?? null)) {
            fclose($pipes[2]);
        }

        $exitCode = proc_close($process);

        return [
            'exit_code' => (int) $exitCode,
            'stdout' => trim((string) $stdout),
            'stderr' => trim((string) $stderr),
        ];
    }

    /**
     * @param  array<string, string>  $connection
     * @return list<string>
     */
    function restore_mysql_args(string $mysqlBinary, array $connection, bool $includeDatabase = true): array
    {
        $args = [
            $mysqlBinary,
            '--protocol=TCP',
            '-h'.$connection['host'],
            '-P'.$connection['port'],
            '-u'.$connection['username'],
            '--batch',
            '--skip-column-names',
            '--default-character-set=utf8mb4',
        ];

        if ($includeDatabase) {
            $args[] = $connection['database'];
        }

        return $args;
    }

    /**
     * @param  array<string, string>  $connection
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    function restore_mysql_query(string $mysqlBinary, array $connection, string $query, bool $includeDatabase = true): array
    {
        $args = restore_mysql_args($mysqlBinary, $connection, $includeDatabase);
        $args[] = '-e';
        $args[] = $query;

        $env = [];
        if ($connection['password'] !== '') {
            $env['MYSQL_PWD'] = $connection['password'];
        }

        return restore_run_process($args, $env);
    }

    /**
     * @param  array<string, string>  $connection
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    function restore_mysql_import(string $mysqlBinary, array $connection, string $sqlFile): array
    {
        $args = restore_mysql_args($mysqlBinary, $connection, true);
        $env = [];
        if ($connection['password'] !== '') {
            $env['MYSQL_PWD'] = $connection['password'];
        }

        return restore_run_process($args, $env, $sqlFile);
    }

    function restore_stage_temp_sql(string $artifactPath): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'restore_sql_');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to create temporary SQL staging file.');
        }

        $targetPath = $tempPath.'.sql';
        if (! @rename($tempPath, $targetPath)) {
            @unlink($tempPath);
            $targetPath = $tempPath;
        }

        if (str_ends_with(strtolower($artifactPath), '.gz')) {
            $source = gzopen($artifactPath, 'rb');
            if (! is_resource($source)) {
                throw new RuntimeException(sprintf('Unable to open compressed SQL artifact: %s', $artifactPath));
            }

            $dest = fopen($targetPath, 'wb');
            if (! is_resource($dest)) {
                gzclose($source);
                throw new RuntimeException(sprintf('Unable to stage SQL artifact: %s', $targetPath));
            }

            while (! gzeof($source)) {
                $chunk = gzread($source, 1024 * 1024);
                if ($chunk === false) {
                    fclose($dest);
                    gzclose($source);
                    throw new RuntimeException(sprintf('Unable to read compressed SQL artifact: %s', $artifactPath));
                }

                if (fwrite($dest, $chunk) === false) {
                    fclose($dest);
                    gzclose($source);
                    throw new RuntimeException(sprintf('Unable to write staged SQL artifact: %s', $targetPath));
                }
            }

            fclose($dest);
            gzclose($source);

            return $targetPath;
        }

        if (! copy($artifactPath, $targetPath)) {
            throw new RuntimeException(sprintf('Unable to stage SQL artifact: %s', $artifactPath));
        }

        return $targetPath;
    }

    /**
     * @param  array<string, string>  $targetConnection
     * @param  array<string, string>  $envOverrides
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    function restore_run_artisan(string $repoRoot, array $targetConnection, array $command): array
    {
        $phpBinary = restore_env('PHP_BINARY_PATH', PHP_BINARY ?: 'php') ?? 'php';
        $artisanPath = $repoRoot.'/artisan';
        $env = [
            'DB_HOST' => $targetConnection['host'],
            'DB_PORT' => $targetConnection['port'],
            'DB_DATABASE' => $targetConnection['database'],
            'DB_USERNAME' => $targetConnection['username'],
            'DB_PASSWORD' => $targetConnection['password'],
        ];

        return restore_run_process(array_merge([$phpBinary, $artisanPath], $command), $env, null, $repoRoot);
    }
}

$options = getopt('', [
    'backup-root::',
    'backup-dir::',
    'manifest::',
    'mysql-binary::',
    'target-host::',
    'target-port::',
    'target-user::',
    'target-password::',
    'target-db::',
    'json',
    'dry-run',
    'drop-target-first',
    'allow-nonempty-target',
    'allow-target-db-match',
    'skip-verify-contract',
    'skip-artisan-checks',
    'skip-full',
    'skip-schema',
]);

$payload = restore_default_payload($repoRoot);

try {
    $backupRoot = (string) ($options['backup-root'] ?? restore_env('BOOKING_BACKUP_ROOT', $repoRoot.'/storage/app/booking_backups') ?? ($repoRoot.'/storage/app/booking_backups'));
    $backupDir = isset($options['backup-dir']) ? trim((string) $options['backup-dir']) : '';
    $manifestPath = isset($options['manifest']) ? trim((string) $options['manifest']) : '';

    if ($manifestPath === '') {
        if ($backupDir !== '') {
            $manifestPath = rtrim($backupDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'manifest.json';
        } else {
            $manifestPath = BackupRestoreManifest::locateLatest($backupRoot);
        }
    }

    $manifest = BackupRestoreManifest::load($manifestPath);
    $manifestDir = dirname($manifestPath);
    $payload['manifest_path'] = $manifestPath;
    $payload['backup_root'] = $backupRoot;
    $payload['backup_label'] = $manifest['label'] ?? basename($manifestDir);

    $fullArtifact = BackupRestoreManifest::artifact($manifest, 'full');
    $schemaArtifact = BackupRestoreManifest::artifact($manifest, 'schema');

    $hasFull = is_array($fullArtifact);
    $hasSchema = is_array($schemaArtifact);
    if (! $hasFull && ! $hasSchema) {
        throw new RuntimeException('Backup manifest does not contain a schema or full artifact entry.');
    }

    $resolvedFull = $hasFull ? BackupRestoreManifest::resolveArtifact($fullArtifact, $manifestDir) : null;
    $resolvedSchema = $hasSchema ? BackupRestoreManifest::resolveArtifact($schemaArtifact, $manifestDir) : null;

    $integrity = [];
    if ($resolvedFull !== null) {
        $integrity['full'] = BackupRestoreManifest::validateResolvedArtifact($resolvedFull);
    }
    if ($resolvedSchema !== null) {
        $integrity['schema'] = BackupRestoreManifest::validateResolvedArtifact($resolvedSchema);
    }

    foreach ($integrity as $artifactName => $report) {
        $payload['steps']['artifact_integrity.'.$artifactName] = [
            'status' => ($report['ok'] ?? false) ? 'ok' : 'fail',
            'message' => ($report['ok'] ?? false)
                ? sprintf('Artifact %s integrity validated.', $artifactName)
                : sprintf('Artifact %s integrity failed.', $artifactName),
            'details' => $report,
        ];

        foreach (($report['errors'] ?? []) as $error) {
            $payload['errors'][] = (string) $error;
        }
    }

    if ($payload['errors'] !== []) {
        restore_exit($payload, 1);
    }

    $targetConnection = [
        'host' => (string) ($options['target-host'] ?? restore_env('RESTORE_DB_HOST', restore_env('DB_HOST', '127.0.0.1'))),
        'port' => (string) ($options['target-port'] ?? restore_env('RESTORE_DB_PORT', restore_env('DB_PORT', '3306'))),
        'username' => (string) ($options['target-user'] ?? restore_env('RESTORE_DB_USERNAME', restore_env('DB_USERNAME', 'root'))),
        'password' => (string) ($options['target-password'] ?? restore_env('RESTORE_DB_PASSWORD', restore_env('DB_PASSWORD', ''))),
        'database' => (string) ($options['target-db'] ?? restore_env('RESTORE_DB_DATABASE', '')),
    ];

    if ($targetConnection['database'] === '') {
        throw new RuntimeException('RESTORE_DB_DATABASE (or --target-db) is required.');
    }

    $sourceDatabase = (string) restore_env('DB_DATABASE', '');
    if ($sourceDatabase !== '' && $sourceDatabase === $targetConnection['database'] && ! isset($options['allow-target-db-match'])) {
        throw new RuntimeException('Target restore database matches DB_DATABASE. Use a scratch target or pass --allow-target-db-match intentionally.');
    }

    $mysqlBinary = (string) ($options['mysql-binary'] ?? restore_env('MYSQL_BIN', 'mysql') ?? 'mysql');
    $dropTargetFirst = isset($options['drop-target-first']);
    $allowNonemptyTarget = isset($options['allow-nonempty-target']);
    $dryRun = isset($options['dry-run']);

    $serverConnection = $targetConnection;
    $serverConnection['database'] = 'information_schema';

    $createResult = restore_mysql_query(
        $mysqlBinary,
        $serverConnection,
        'CREATE DATABASE IF NOT EXISTS '.restore_sql_identifier($targetConnection['database']).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        false
    );

    $payload['steps']['target_database.ensure_exists'] = [
        'status' => ($createResult['exit_code'] === 0) ? 'ok' : 'fail',
        'message' => ($createResult['exit_code'] === 0)
            ? sprintf('Target database %s is available.', $targetConnection['database'])
            : 'Failed to ensure target database exists.',
        'details' => $createResult,
    ];

    if ($createResult['exit_code'] !== 0) {
        $payload['errors'][] = $createResult['stderr'] !== '' ? $createResult['stderr'] : 'MySQL CREATE DATABASE failed.';
        restore_exit($payload, 1);
    }

    if ($dropTargetFirst) {
        $dropResult = restore_mysql_query(
            $mysqlBinary,
            $serverConnection,
            'DROP DATABASE IF EXISTS '.restore_sql_identifier($targetConnection['database']),
            false
        );
        $recreateResult = restore_mysql_query(
            $mysqlBinary,
            $serverConnection,
            'CREATE DATABASE IF NOT EXISTS '.restore_sql_identifier($targetConnection['database']).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            false
        );

        $payload['steps']['target_database.recreate'] = [
            'status' => ($dropResult['exit_code'] === 0 && $recreateResult['exit_code'] === 0) ? 'ok' : 'fail',
            'message' => ($dropResult['exit_code'] === 0 && $recreateResult['exit_code'] === 0)
                ? sprintf('Target database %s was dropped and recreated.', $targetConnection['database'])
                : sprintf('Failed to recreate target database %s.', $targetConnection['database']),
            'details' => [
                'drop' => $dropResult,
                'create' => $recreateResult,
            ],
        ];

        if ($dropResult['exit_code'] !== 0 || $recreateResult['exit_code'] !== 0) {
            $payload['errors'][] = 'Unable to recreate target restore database.';
            restore_exit($payload, 1);
        }
    }

    $tableCountResult = restore_mysql_query(
        $mysqlBinary,
        $targetConnection,
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
    );

    $tableCount = ($tableCountResult['exit_code'] === 0 && is_numeric(trim($tableCountResult['stdout']))) ? (int) trim($tableCountResult['stdout']) : null;
    $payload['steps']['target_database.inspect'] = [
        'status' => ($tableCountResult['exit_code'] === 0) ? 'ok' : 'fail',
        'message' => ($tableCountResult['exit_code'] === 0)
            ? sprintf('Target database currently has %d table(s).', (int) ($tableCount ?? 0))
            : 'Failed to inspect target database table count.',
        'details' => $tableCountResult + ['table_count' => $tableCount],
    ];

    if ($tableCountResult['exit_code'] !== 0) {
        $payload['errors'][] = $tableCountResult['stderr'] !== '' ? $tableCountResult['stderr'] : 'Unable to inspect target database.';
        restore_exit($payload, 1);
    }

    if (($tableCount ?? 0) > 0 && ! $allowNonemptyTarget && ! $dropTargetFirst) {
        $payload['errors'][] = sprintf('Target database %s is not empty. Pass --allow-nonempty-target or --drop-target-first for rehearsal.', $targetConnection['database']);
        restore_exit($payload, 1);
    }

    $preferFull = ! isset($options['skip-full']) && $resolvedFull !== null && ($integrity['full']['ok'] ?? false);
    $allowSchema = ! isset($options['skip-schema']) && $resolvedSchema !== null && ($integrity['schema']['ok'] ?? false);

    $selectedArtifactName = $preferFull ? 'full' : ($allowSchema ? 'schema' : '');
    if ($selectedArtifactName === '') {
        throw new RuntimeException('No usable restore artifact remains after skip flags and integrity checks.');
    }

    /** @var array{exists: bool, path: string, bytes: int|null, sha256: string|null, line_count: int|null} $selectedArtifact */
    $selectedArtifact = $selectedArtifactName === 'full' ? $resolvedFull : $resolvedSchema;

    $payload['selected_artifact'] = [
        'name' => $selectedArtifactName,
        'path' => $selectedArtifact['path'],
    ];

    if ($selectedArtifactName !== 'full') {
        $payload['warnings'][] = 'Restore is using schema-only artifact; data-level rehearsal is incomplete.';
    }

    if ($dryRun) {
        $payload['steps']['restore.import'] = [
            'status' => 'ok',
            'message' => sprintf('Dry run only. Would import %s artifact into %s.', $selectedArtifactName, $targetConnection['database']),
            'details' => [
                'target_database' => $targetConnection['database'],
                'artifact' => $payload['selected_artifact'],
            ],
        ];
        $payload['ok'] = ($payload['errors'] === []);
        restore_exit($payload, $payload['ok'] ? 0 : 1);
    }

    $stagedSqlPath = restore_stage_temp_sql($selectedArtifact['path']);
    try {
        $importResult = restore_mysql_import($mysqlBinary, $targetConnection, $stagedSqlPath);
    } finally {
        @unlink($stagedSqlPath);
    }

    $payload['steps']['restore.import'] = [
        'status' => ($importResult['exit_code'] === 0) ? 'ok' : 'fail',
        'message' => ($importResult['exit_code'] === 0)
            ? sprintf('Imported %s artifact into %s.', $selectedArtifactName, $targetConnection['database'])
            : sprintf('Failed importing %s artifact into %s.', $selectedArtifactName, $targetConnection['database']),
        'details' => $importResult,
    ];

    if ($importResult['exit_code'] !== 0) {
        $payload['errors'][] = $importResult['stderr'] !== '' ? $importResult['stderr'] : 'MySQL import failed.';
        restore_exit($payload, 1);
    }

    $verifyContractPath = $repoRoot.'/tools/mysql/verify_release_contract.sql';
    if (! isset($options['skip-verify-contract']) && is_file($verifyContractPath)) {
        $verifyResult = restore_mysql_import($mysqlBinary, $targetConnection, $verifyContractPath);
        $payload['steps']['verify.release_contract'] = [
            'status' => ($verifyResult['exit_code'] === 0) ? 'ok' : 'fail',
            'message' => ($verifyResult['exit_code'] === 0)
                ? 'Release contract SQL verification passed.'
                : 'Release contract SQL verification failed.',
            'details' => $verifyResult,
        ];

        if ($verifyResult['exit_code'] !== 0) {
            $payload['errors'][] = $verifyResult['stderr'] !== '' ? $verifyResult['stderr'] : 'Release contract verification SQL failed.';
            restore_exit($payload, 1);
        }
    } elseif (! isset($options['skip-verify-contract'])) {
        $payload['warnings'][] = 'tools/mysql/verify_release_contract.sql not found; SQL contract verification skipped.';
    }

    $artisanPath = $repoRoot.'/artisan';
    if (! isset($options['skip-artisan-checks']) && is_file($artisanPath)) {
        $doctorResult = restore_run_artisan($repoRoot, $targetConnection, ['booking:doctor', '--json', '--strict']);
        $deployResult = restore_run_artisan($repoRoot, $targetConnection, ['booking:deploy-check', '--mode=postflight', '--json', '--strict']);

        $payload['steps']['verify.booking_doctor'] = [
            'status' => ($doctorResult['exit_code'] === 0) ? 'ok' : 'fail',
            'message' => ($doctorResult['exit_code'] === 0)
                ? 'booking:doctor passed against restored target.'
                : 'booking:doctor failed against restored target.',
            'details' => $doctorResult,
        ];
        $payload['steps']['verify.booking_deploy_check'] = [
            'status' => ($deployResult['exit_code'] === 0) ? 'ok' : 'fail',
            'message' => ($deployResult['exit_code'] === 0)
                ? 'booking:deploy-check postflight passed against restored target.'
                : 'booking:deploy-check postflight failed against restored target.',
            'details' => $deployResult,
        ];

        if ($doctorResult['exit_code'] !== 0 || $deployResult['exit_code'] !== 0) {
            $payload['errors'][] = 'Artisan verification failed against restored database.';
            restore_exit($payload, 1);
        }
    } elseif (! isset($options['skip-artisan-checks'])) {
        $payload['warnings'][] = 'artisan not found in repository root; Laravel verification checks skipped.';
    }

    $payload['ok'] = true;
    restore_exit($payload, 0);
} catch (Throwable $e) {
    $payload['errors'][] = $e->getMessage();
    restore_exit($payload, 1);
}
