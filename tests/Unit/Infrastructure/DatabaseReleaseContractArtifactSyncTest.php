<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseReleaseContractArtifactSyncTest extends TestCase
{
    public function test_bootstrap_wrappers_reference_release_contract_verification_script(): void
    {
        foreach ([
            base_path('tools/mysql/bootstrap_release.php'),
            base_path('tools/mysql/bootstrap_release.cmd'),
            base_path('tools/mysql/bootstrap_release.sh'),
        ] as $path) {
            $this->assertTrue(File::exists($path), sprintf('Bootstrap wrapper is missing: %s', $path));
            $this->assertStringContainsString('verify_release_contract.sql', (string) File::get($path), sprintf('Bootstrap wrapper must run release contract verification: %s', $path));
        }
    }

    public function test_release_contract_verification_and_docs_cover_april_five_foundations(): void
    {
        $verifySqlPath = base_path('tools/mysql/verify_release_contract.sql');
        $databaseReadmePath = base_path('database/README_release_bootstrap.md');
        $toolsReadmePath = base_path('tools/mysql/README_bootstrap_release.md');

        $verifySql = (string) File::get($verifySqlPath);
        $databaseReadme = (string) File::get($databaseReadmePath);
        $toolsReadme = (string) File::get($toolsReadmePath);

        foreach ([
            'notification_delivery_attempts',
            'notification_preferences',
            'audit_log_subjects',
            'customer_privacy_requests',
            'feature_flags',
            'recipient_user_id',
            'dedupe_key',
            'business_hours',
            'privacy_anonymized_at',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $verifySql, sprintf('verify_release_contract.sql is missing fragment [%s].', $fragment));
        }

        $this->assertStringContainsString('verify_release_contract.sql', $databaseReadme);
        $this->assertStringContainsString('contract verification', strtolower($databaseReadme));
        $this->assertStringContainsString('verify_release_contract.sql', $toolsReadme);
        $this->assertStringContainsString('notification', strtolower($toolsReadme));
        $this->assertStringContainsString('feature-flag', strtolower($toolsReadme));
    }

    public function test_release_artifacts_do_not_reinstall_runtime_incompatible_payment_refund_triggers(): void
    {
        foreach ([
            base_path('database/schema/mysql-schema.sql'),
            base_path('db_all.sql'),
        ] as $path) {
            $sql = (string) File::get($path);

            $this->assertStringNotContainsString('CREATE TRIGGER `trg_payments__bi_refund_lineage_guard`', $sql);
            $this->assertStringNotContainsString('CREATE TRIGGER `trg_payments__bu_refund_lineage_guard`', $sql);
        }

        $patchPath = base_path('database/patches/2026_04_08_000041_drop_runtime_incompatible_payment_refund_triggers.sql');
        $patchSql = (string) File::get($patchPath);

        $this->assertStringContainsString('DROP TRIGGER IF EXISTS `trg_payments__bi_refund_lineage_guard`', $patchSql);
        $this->assertStringContainsString('DROP TRIGGER IF EXISTS `trg_payments__bu_refund_lineage_guard`', $patchSql);
    }
}
