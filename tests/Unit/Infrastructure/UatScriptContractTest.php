<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use Tests\TestCase;

final class UatScriptContractTest extends TestCase
{
    public function test_bootstrap_script_keeps_literal_cli_commands_in_next_step_guidance(): void
    {
        $script = file_get_contents(base_path('scripts/uat/Bootstrap-UatPack.ps1'));
        self::assertNotFalse($script);

        self::assertStringContainsString(
            "Write-Host '  - Run `npm run dev:smoke` from the repo root to prove the local lane.'",
            $script,
        );
        self::assertStringContainsString(
            "Write-Host '  - Run `npm run verify:release:live` from `customer-web` only after Laravel and customer-web are already running.'",
            $script,
        );
        self::assertStringContainsString('$usersLine = if ($users.Count -gt 0) { $users -join \', \' } else { \'(none)\' }', $script);
    }

    public function test_reset_script_keeps_literal_cli_commands_in_next_step_guidance(): void
    {
        $script = file_get_contents(base_path('scripts/uat/Reset-UatPack.ps1'));
        self::assertNotFalse($script);

        self::assertStringContainsString(
            "Write-Host '  - Rebuild the canonical UAT pack with `powershell -ExecutionPolicy Bypass -File scripts\\\\uat\\\\Bootstrap-UatPack.ps1` before live verification.'",
            $script,
        );
        self::assertStringContainsString(
            "Write-Host '  - `npm run dev:all` also refreshes the same manifest for the standard local lane.'",
            $script,
        );
    }
}
