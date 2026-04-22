<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleaseArtifactContractTest extends TestCase
{
    public function test_schema_dump_contains_required_contract_fragments(): void
    {
        $artifact = config('booking_release.artifacts.schema_dump');
        $path = base_path((string) ($artifact['path'] ?? 'database/schema/mysql-schema.sql'));
        $requiredFragments = array_values((array) ($artifact['required_fragments'] ?? []));

        $this->assertTrue(File::exists($path), 'Schema dump artifact is missing.');

        $contents = File::get($path);
        $missing = [];

        foreach ($requiredFragments as $fragment) {
            if ($fragment === '') {
                continue;
            }

            if (! str_contains($contents, (string) $fragment)) {
                $missing[] = (string) $fragment;
            }
        }

        $this->assertSame([], $missing, 'Schema dump is missing required contract fragments: '.implode(', ', $missing));
    }

    public function test_optional_full_dump_when_present_contains_required_contract_fragments(): void
    {
        $artifact = config('booking_release.artifacts.full_dump');
        $path = base_path((string) ($artifact['path'] ?? 'db_all.sql'));
        $requiredFragments = array_values((array) ($artifact['required_fragments'] ?? []));

        $this->assertTrue(File::exists($path), 'Full dump artifact is missing.');

        $contents = File::get($path);
        $missing = [];

        foreach ($requiredFragments as $fragment) {
            if ($fragment === '') {
                continue;
            }

            if (! str_contains($contents, (string) $fragment)) {
                $missing[] = (string) $fragment;
            }
        }

        $this->assertSame([], $missing, 'Full dump is missing required contract fragments: '.implode(', ', $missing));
    }

    public function test_required_sql_patch_inventory_contains_release_critical_late_round_patches(): void
    {
        $required = (array) config('booking_release.required_sql_patches', []);

        foreach ([
            '2026_03_15_000022_staff_auth_and_integrity_hardening_hotfix.sql',
            '2026_03_15_000023_menu_price_and_active_voucher_integrity.sql',
            '2026_03_15_000024_payment_reconciliation_and_table_audit_round.sql',
            '2026_03_21_000026_customer_waiting_list_notified_response_flow.sql',
            '2026_03_21_000027_customer_reservation_deposit_self_service_intent.sql',
            '2026_03_21_000028_customer_deposit_payment_sessions.sql',
            '2026_03_21_000029_customer_bill_payment_sessions.sql',
            '2026_03_22_000030_payment_provider_webhook_receipts.sql',
            '2026_03_25_000031_customer_access_sessions_foundation.sql',
            '2026_03_25_000033_waiting_list_customer_session_access.sql',
            '2026_03_27_000035_inventory_recipe_stock_foundation.sql',
            '2026_04_05_000044_staff_conversation_inbox_foundation.sql',
            '2026_04_05_000045_notification_platform_v2_foundation.sql',
            '2026_04_05_000046_unified_audit_trail_foundation.sql',
            '2026_04_05_000047_branch_scheduling_policy_foundation.sql',
            '2026_04_05_000047_data_lifecycle_foundation.sql',
            '2026_04_05_000048_feature_flags_foundation.sql',
        ] as $patch) {
            $this->assertContains($patch, $required);
        }
    }

    public function test_sql_patches_do_not_define_mysql_identifiers_longer_than_sixty_four_characters(): void
    {
        $patchFiles = File::files(base_path('database/patches'));
        $violations = [];

        foreach ($patchFiles as $file) {
            if ($file->getExtension() !== 'sql') {
                continue;
            }

            preg_match_all('/`([^`]+)`/', File::get($file->getPathname()), $matches);

            foreach ((array) ($matches[1] ?? []) as $identifier) {
                if (strlen((string) $identifier) > 64) {
                    $violations[] = sprintf('%s:%s (%d)', $file->getFilename(), (string) $identifier, strlen((string) $identifier));
                }
            }
        }

        $this->assertSame([], array_values(array_unique($violations)), 'SQL patches contain identifiers longer than MySQL allows: '.implode(', ', array_values(array_unique($violations))));
    }
}
