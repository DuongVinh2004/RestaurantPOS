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

        $runbook = file_get_contents(base_path('docs/runbooks/booking-local-windows-vscode-cmd-runbook.md'));
        self::assertNotFalse($runbook);
        self::assertStringContainsString('npm run runtime:up', $runbook);
        self::assertStringContainsString('npm run runtime:down', $runbook);
        self::assertStringContainsString('npm run runtime:preflight', $runbook);
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
}
