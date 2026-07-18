<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

final class BookingCdContainmentContractTest extends TestCase
{
    public function test_production_deploy_is_manual_protected_and_preflighted_before_restart(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3).'/.github/workflows/booking-cd.yml');

        self::assertNotFalse($workflow);
        self::assertStringContainsString("on:\n  workflow_dispatch:", $workflow);
        self::assertStringNotContainsString("\n  push:", $workflow);
        self::assertStringContainsString('environment: production', $workflow);
        self::assertStringContainsString('permissions:', $workflow);
        self::assertStringContainsString('contents: read', $workflow);
        self::assertStringContainsString('booking:deploy-check --mode=preflight --strict --json', $workflow);

        $preflightPosition = strpos($workflow, 'booking:deploy-check --mode=preflight --strict --json');
        $restartPosition = strpos($workflow, 'up -d');

        self::assertIsInt($preflightPosition);
        self::assertIsInt($restartPosition);
        self::assertLessThan($restartPosition, $preflightPosition, 'Production preflight must run before services are restarted.');
    }
}
