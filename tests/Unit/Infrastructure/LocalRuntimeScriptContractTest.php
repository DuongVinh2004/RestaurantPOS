<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use Tests\TestCase;

final class LocalRuntimeScriptContractTest extends TestCase
{
    public function test_package_json_exposes_unified_local_runtime_commands(): void
    {
        $packageJson = file_get_contents(base_path('package.json'));
        self::assertNotFalse($packageJson);

        /** @var array{scripts?: array<string, string>} $decoded */
        $decoded = json_decode($packageJson, true, 512, JSON_THROW_ON_ERROR);
        $scripts = $decoded['scripts'] ?? [];

        self::assertSame(
            'powershell -ExecutionPolicy Bypass -File scripts/ops/local-runtime.ps1 -Action up',
            $scripts['runtime:up'] ?? null,
        );
        self::assertSame(
            'powershell -ExecutionPolicy Bypass -File scripts/ops/local-runtime.ps1 -Action down',
            $scripts['runtime:down'] ?? null,
        );
        self::assertSame(
            'powershell -ExecutionPolicy Bypass -File scripts/ops/local-runtime.ps1 -Action restart',
            $scripts['runtime:restart'] ?? null,
        );

        self::assertFileExists(base_path('scripts/ops/local-runtime.ps1'));
        self::assertFileExists(base_path('scripts/ops/start-local-mysql.ps1'));
        self::assertFileExists(base_path('scripts/ops/start-local-redis.ps1'));
        self::assertFileExists(base_path('scripts/ops/local-runtime-preflight.mjs'));
    }

    public function test_runtime_docs_reference_the_one_liner_local_lane(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        self::assertNotFalse($readme);
        self::assertStringContainsString('npm run runtime:up', $readme);
        self::assertStringContainsString('npm run runtime:down', $readme);
        self::assertStringContainsString('composer bootstrap:booking', $readme);
        self::assertStringContainsString('Runtime gate recovery checklist', $readme);

        $runbook = file_get_contents(base_path('docs/runbooks/booking-local-windows-vscode-cmd-runbook.md'));
        self::assertNotFalse($runbook);
        self::assertStringContainsString('npm run runtime:up', $runbook);
        self::assertStringContainsString('npm run runtime:down', $runbook);
        self::assertStringContainsString('npm run runtime:preflight', $runbook);
    }

    public function test_runtime_runbook_covers_dependency_recovery_blockers(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/booking-local-windows-vscode-cmd-runbook.md'));
        self::assertNotFalse($runbook);

        foreach ([
            'MySQL connection refused',
            'Redis connection refused',
            'scheduler',
            'SQL bootstrap not applied',
            'composer bootstrap:booking',
            'php artisan booking:deploy-check --mode=preflight',
            'tools/mysql/verify_release_contract.sql',
        ] as $expected) {
            self::assertStringContainsString($expected, $runbook, $expected);
        }
    }

    public function test_runtime_script_keeps_repo_local_backend_on_127001_unless_app_url_explicitly_pins_an_endpoint(): void
    {
        $script = file_get_contents(base_path('scripts/ops/local-runtime.ps1'));
        self::assertNotFalse($script);

        self::assertStringContainsString('function Test-AppUrlPinsServeEndpoint', $script);
        self::assertStringContainsString('$explicitPort = $AppUrl -match \'^[a-z]+://[^/]+:\d+(?:/|$)\'', $script);
        self::assertStringContainsString('$hasNonRootPath = -not [string]::IsNullOrWhiteSpace($trimmedPath) -and $trimmedPath -ne \'/\'', $script);
        self::assertStringContainsString('(Test-AppUrlPinsServeEndpoint -AppUrl $appUrl -Uri $uri)', $script);
    }

    public function test_runtime_script_wraps_repo_process_lookups_before_counting_shutdown_targets(): void
    {
        $script = file_get_contents(base_path('scripts/ops/local-runtime.ps1'));
        self::assertNotFalse($script);

        self::assertStringContainsString('$redisProcesses = @(Get-RepoRedisProcesses)', $script);
        self::assertStringContainsString('$mySqlProcesses = @(Get-RepoMySqlProcesses)', $script);
        self::assertStringContainsString('if ($redisProcesses.Count -gt 0 -and (Test-Path $startRedisScript)) {', $script);
        self::assertStringContainsString('if ($mySqlProcesses.Count -gt 0 -and (Test-Path $startMySqlScript)) {', $script);
    }

    public function test_dev_backend_mysql_failure_message_preserves_npm_commands(): void
    {
        $script = file_get_contents(base_path('scripts/ops/dev-backend.ps1'));
        self::assertNotFalse($script);

        self::assertStringContainsString(
            'throw (\'MySQL runtime could not be ensured. Run `npm run mysql:local` (or `npm run mysql:local:restart`)',
            $script,
        );
    }

    public function test_windows_runtime_wrappers_can_discover_common_local_tool_paths(): void
    {
        $devBackend = file_get_contents(base_path('scripts/ops/dev-backend.ps1'));
        $localRuntime = file_get_contents(base_path('scripts/ops/local-runtime.ps1'));
        self::assertNotFalse($devBackend);
        self::assertNotFalse($localRuntime);

        foreach ([$devBackend, $localRuntime] as $script) {
            self::assertStringContainsString('function Add-KnownWindowsDevToolPaths', $script);
            self::assertStringContainsString('C:\xampp\php', $script);
            self::assertStringContainsString('.config\herd-lite\bin', $script);
            self::assertStringContainsString('C:\xampp\mysql\bin', $script);
        }
    }

    public function test_runtime_dependency_scripts_can_reuse_external_mysql_and_redis_services(): void
    {
        $mysqlScript = file_get_contents(base_path('scripts/ops/start-local-mysql.ps1'));
        $redisScript = file_get_contents(base_path('scripts/ops/start-local-redis.ps1'));
        self::assertNotFalse($mysqlScript);
        self::assertNotFalse($redisScript);

        self::assertStringContainsString('MYSQLD_BIN', $mysqlScript);
        self::assertStringContainsString('MySQL-compatible service is already running', $mysqlScript);
        self::assertStringContainsString('C:\xampp\mysql\bin\mysql.exe', $mysqlScript);

        self::assertStringContainsString('function Test-RedisPing', $redisScript);
        self::assertStringContainsString('Redis-compatible service is already running', $redisScript);
    }

    public function test_mysql_runtime_script_rejects_non_mysql8_server_binary_for_repo_local_bootstrap(): void
    {
        $script = file_get_contents(base_path('scripts/ops/start-local-mysql.ps1'));
        self::assertNotFalse($script);

        self::assertStringContainsString('function Test-MySqlServerBinaryCompatible', $script);
        self::assertStringContainsString('$versionOutput -match \'MySQL\' -and $versionOutput -match \'\\b8\\.\'', $script);
        self::assertStringContainsString('function Get-MySqlServerCommand', $script);
        self::assertStringContainsString('not MySQL Server 8 compatible', $script);
        self::assertStringContainsString('not a complete MySQL Server 8 data directory', $script);
    }

    public function test_runtime_preflight_can_discover_common_windows_php_binary(): void
    {
        $script = file_get_contents(base_path('scripts/ops/local-runtime-preflight.mjs'));
        self::assertNotFalse($script);

        self::assertStringContainsString('function resolvePhpBinary', $script);
        self::assertStringContainsString('C:\\\\xampp\\\\php\\\\php.exe', $script);
        self::assertStringContainsString('.config', $script);
        self::assertStringContainsString('doctorCommand: [resolvePhpBinary(processEnv),', $script);
    }
}
