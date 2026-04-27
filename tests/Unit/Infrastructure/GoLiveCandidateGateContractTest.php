<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use Tests\TestCase;

final class GoLiveCandidateGateContractTest extends TestCase
{
    public function test_root_scripts_expose_go_live_candidate_gate(): void
    {
        $composerJson = file_get_contents(base_path('composer.json'));
        $packageJson = file_get_contents(base_path('package.json'));
        self::assertNotFalse($composerJson);
        self::assertNotFalse($packageJson);

        /** @var array{scripts?: array<string, mixed>} $composer */
        $composer = json_decode($composerJson, true, 512, JSON_THROW_ON_ERROR);
        /** @var array{scripts?: array<string, string>} $package */
        $package = json_decode($packageJson, true, 512, JSON_THROW_ON_ERROR);

        self::assertContains('node scripts/release/go-live-check.mjs', $composer['scripts']['release:go-live-check'] ?? []);
        self::assertSame('node scripts/release/go-live-check.mjs', $package['scripts']['release:go-live-check'] ?? null);
        self::assertSame('node --test scripts/release/go-live-check.test.mjs', $package['scripts']['release:go-live-check:test'] ?? null);
    }

    public function test_go_live_script_reuses_existing_release_and_ladder_commands(): void
    {
        $script = file_get_contents(base_path('scripts/release/go-live-check.mjs'));
        self::assertNotFalse($script);

        foreach ([
            'booking:doctor',
            'notifications:outbox-health',
            'booking:route-gate',
            'booking:release-manifest',
            'booking:deploy-check',
            'verify:package',
            'test:security',
            'test:orders',
            'test:kitchen',
            'test:money',
            'test:inventory',
            'test:release-contract',
            'tools/mysql/bootstrap_release.php',
            'npm\', \'run\', \'build',
            'npm\', \'run\', \'smoke:live',
        ] as $expectedFragment) {
            self::assertStringContainsString($expectedFragment, $script, $expectedFragment);
        }
    }

    public function test_go_live_checklist_documents_required_no_go_conditions(): void
    {
        $checklist = file_get_contents(base_path('docs/runbooks/go-live-candidate-checklist.md'));
        self::assertNotFalse($checklist);

        foreach ([
            'git status --porcelain',
            'APP_ENV',
            'APP_DEBUG=false',
            'CUSTOMER_AUTH_JWT_SECRET',
            'STAFF_AUTH_DATABASE_STORE_ENABLED=true',
            'DB reachability',
            'Redis reachability',
            'Scheduler heartbeat',
            'Outbox health',
            'SQL bootstrap/verifier',
            'php artisan booking:route-gate --json',
            'php artisan booking:release-manifest --json',
            'php artisan booking:deploy-check --mode=preflight --strict --json',
            'npm run verify:package',
            'composer test:security',
            'composer test:orders',
            'composer test:kitchen',
            'composer test:money',
            'composer test:inventory',
            'cd staff-web && npm run build',
            'cd staff-web && npm run smoke:live',
            'Backup/restore drill',
            'Rollback plan',
            'Cross-branch data leak',
            'booking:doctor',
            'deploy-check',
            'Missing cashier-shift FK verifier',
            'Idempotency gaps on production mutation routes',
            'Money flow tests not run',
            'Redis locks/idempotency not verified',
            'Staff-web day-1 smoke not run',
        ] as $expectedFragment) {
            self::assertStringContainsString($expectedFragment, $checklist, $expectedFragment);
        }
    }
}
