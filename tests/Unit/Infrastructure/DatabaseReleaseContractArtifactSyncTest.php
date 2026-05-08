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

    public function test_booking_bootstrap_can_forward_skip_create_database_to_release_wrapper(): void
    {
        $bootstrapPath = base_path('tools/bootstrap_booking.php');

        $this->assertTrue(File::exists($bootstrapPath), sprintf('Booking bootstrap wrapper is missing: %s', $bootstrapPath));

        $script = (string) File::get($bootstrapPath);

        $this->assertStringContainsString("'--skip-create-db'", $script);
        $this->assertStringContainsString('tools/mysql/bootstrap_release.php', $script);
        $this->assertStringContainsString('ensureLaravelRuntimeDirectories', $script);
        $this->assertStringContainsString('storage/framework/views', $script);
    }

    public function test_release_bootstrap_keeps_mysql_cli_primary_with_local_docker_fallback(): void
    {
        $bootstrapPath = base_path('tools/mysql/bootstrap_release.php');

        $this->assertTrue(File::exists($bootstrapPath), sprintf('Bootstrap wrapper is missing: %s', $bootstrapPath));

        $script = (string) File::get($bootstrapPath);

        $this->assertStringContainsString('resolveMysqlClient', $script);
        $this->assertStringContainsString('mysql_cli', $script);
        $this->assertStringContainsString('docker_container_mysql_cli', $script);
        $this->assertStringContainsString('docker_compose_mysql_cli', $script);
        $this->assertStringContainsString('docker-compose.testing.yml', $script);
        $this->assertStringContainsString('MYSQL_DOCKER_CONTAINER', $script);
        $this->assertStringContainsString("in_array(\$environment, ['local', 'testing'], true) || getenv('CI') === 'true'", $script);
    }

    public function test_ci_bootstrap_uses_canonical_composer_wrapper_without_creating_database(): void
    {
        $ciBootstrapPath = base_path('scripts/ci/booking-ci-bootstrap.sh');

        $this->assertTrue(File::exists($ciBootstrapPath), sprintf('CI bootstrap script is missing: %s', $ciBootstrapPath));

        $script = (string) File::get($ciBootstrapPath);

        $this->assertStringContainsString('composer bootstrap:booking -- "${bootstrap_args[@]}"', $script);
        $this->assertStringContainsString('bootstrap_args+=(--skip-create-db)', $script);
        $this->assertStringContainsString('bootstrap_args+=(--skip-db-bootstrap)', $script);
        $this->assertStringContainsString('bootstrap_args+=(--skip-site-bootstrap)', $script);
        $this->assertStringContainsString('bootstrap_args+=(--skip-reporting)', $script);
        $this->assertStringNotContainsString('php tools/mysql/bootstrap_release.php --skip-create-db', $script);
        $this->assertStringNotContainsString('bash tools/mysql/bootstrap_release.sh', $script);
    }

    public function test_staff_branch_assignment_patch_seeds_canonical_operational_role_ids(): void
    {
        $patchPath = database_path('patches/2026_04_24_000055_staff_branch_assignments_foundation.sql');

        $this->assertTrue(File::exists($patchPath), sprintf('Staff branch assignment patch is missing: %s', $patchPath));

        $patch = (string) File::get($patchPath);

        $this->assertStringContainsString('INSERT INTO `roles` (`role_id`, `role_name`)', $patch);
        foreach ([
            "(4, 'Server')",
            "(5, 'Waiter')",
            "(6, 'Cashier')",
            "(7, 'Kitchen')",
            "(8, 'Manager')",
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $patch);
        }
        $this->assertStringNotContainsString('INSERT INTO `roles` (`role_name`)', $patch);
    }

    public function test_smoke_gate_refreshes_scheduler_heartbeat_after_cache_clear(): void
    {
        $smokeGatePath = base_path('scripts/ci/booking-smoke-gate.sh');

        $this->assertTrue(File::exists($smokeGatePath), sprintf('Smoke gate script is missing: %s', $smokeGatePath));

        $script = (string) File::get($smokeGatePath);

        $this->assertStringContainsString('php artisan cache:clear || true', $script);
        $this->assertStringContainsString('php artisan booking:ops-heartbeat:touch scheduler --json | tee build/booking-ci/booking-ops-heartbeat-touch.json', $script);
        $this->assertStringContainsString('php artisan booking:ops-heartbeat:touch scheduler --json | tee build/booking-ci/booking-ops-heartbeat-touch-before-ops.json', $script);
        $this->assertStringContainsString('php artisan booking:ops-heartbeat:touch scheduler --json | tee build/booking-ci/booking-ops-heartbeat-touch-before-reliability.json', $script);
        $this->assertStringContainsString('php artisan notifications:outbox-health --json | tee build/booking-ci/booking-outbox-health-smoke.json', $script);
        $this->assertStringContainsString('php artisan booking:reporting-snapshots:rebuild --json | tee build/booking-ci/booking-reporting-snapshots-rebuild-smoke.json', $script);
    }

    public function test_runtime_smoke_rebuilds_reporting_snapshots_before_deploy_check(): void
    {
        $runtimeSmokePath = base_path('scripts/ci/booking-runtime-smoke.sh');

        $this->assertTrue(File::exists($runtimeSmokePath), sprintf('Runtime smoke script is missing: %s', $runtimeSmokePath));

        $script = (string) File::get($runtimeSmokePath);

        $this->assertStringContainsString('php artisan booking:reporting-snapshots:rebuild --json | tee build/booking-ci/runtime-reporting-snapshots-rebuild.json', $script);
        $this->assertStringContainsString('php artisan booking:deploy-check --mode=preflight --strict --json | tee build/booking-ci/runtime-deploy-check-strict.json', $script);
    }

    public function test_reliability_smoke_refreshes_scheduler_heartbeat_before_strict_doctor(): void
    {
        $reliabilitySmokePath = base_path('scripts/ci/booking-reliability-smoke.sh');

        $this->assertTrue(File::exists($reliabilitySmokePath), sprintf('Reliability smoke script is missing: %s', $reliabilitySmokePath));

        $script = (string) File::get($reliabilitySmokePath);

        $this->assertStringContainsString('php artisan booking:ops-heartbeat:touch scheduler --json | tee build/booking-ci/reliability-ops-heartbeat-touch.json', $script);
        $this->assertStringContainsString('php artisan booking:doctor --strict', $script);
        $heartbeatPosition = strpos($script, 'php artisan booking:ops-heartbeat:touch scheduler --json');
        $doctorPosition = strpos($script, 'php artisan booking:doctor --strict');

        $this->assertNotFalse($heartbeatPosition);
        $this->assertNotFalse($doctorPosition);
        $this->assertLessThan(
            $doctorPosition,
            $heartbeatPosition,
            'Reliability smoke must refresh the scheduler heartbeat before strict booking doctor runs.'
        );
    }

    public function test_full_gate_runs_complete_test_static_analysis_and_style_gates(): void
    {
        $fullGatePath = base_path('scripts/ci/booking-full-gate.sh');

        $this->assertTrue(File::exists($fullGatePath), sprintf('Full gate script is missing: %s', $fullGatePath));

        $script = (string) File::get($fullGatePath);

        $this->assertStringContainsString('php artisan test', $script);
        $this->assertStringContainsString('BOOKING_PHPSTAN_MEMORY_LIMIT=${BOOKING_FULL_GATE_PHPSTAN_MEMORY_LIMIT:-3G} bash scripts/ci/booking-phpstan.sh', $script);
        $this->assertStringContainsString('vendor/bin/pint --test', $script);
    }

    public function test_deploy_scripts_include_json_outbox_health_gate(): void
    {
        foreach ([
            base_path('scripts/ci/booking-deploy-preflight.sh'),
            base_path('scripts/ci/booking-deploy-postflight.sh'),
        ] as $path) {
            $this->assertTrue(File::exists($path), sprintf('Deploy script is missing: %s', $path));
            $this->assertStringContainsString('php artisan notifications:outbox-health --json', (string) File::get($path));
        }
    }

    public function test_deploy_preflight_script_emits_strict_json_evidence(): void
    {
        $preflightPath = base_path('scripts/ci/booking-deploy-preflight.sh');

        $this->assertTrue(File::exists($preflightPath), sprintf('Deploy preflight script is missing: %s', $preflightPath));

        $script = (string) File::get($preflightPath);

        $this->assertStringContainsString('php artisan booking:doctor --strict --json | tee build/booking-ci/booking-doctor-preflight-strict.json', $script);
        $this->assertStringContainsString('php artisan notifications:outbox-health --json | tee build/booking-ci/booking-outbox-health-preflight.json', $script);
        $this->assertStringContainsString('php artisan booking:deploy-check --mode=preflight --strict --json | tee build/booking-ci/booking-deploy-check-preflight-strict.json', $script);
    }

    public function test_ci_workflows_enable_mysql_routine_creation_before_bootstrap(): void
    {
        foreach ([
            base_path('.github/workflows/booking-ci.yml'),
            base_path('.github/workflows/booking-release-gate.yml'),
        ] as $path) {
            $this->assertTrue(File::exists($path), sprintf('Workflow file is missing: %s', $path));

            $workflow = (string) File::get($path);

            $this->assertStringContainsString('Allow CI routine creators', $workflow);
            $this->assertStringContainsString('REQUIRE_REDIS_FOR_BOOKING_API: true', $workflow);
            $this->assertStringContainsString('BOOKING_CI_BOOTSTRAP_SITE: true', $workflow);
            $this->assertStringContainsString('BOOKING_CI_BOOTSTRAP_REPORTING: true', $workflow);
            $this->assertStringContainsString('SET GLOBAL log_bin_trust_function_creators = 1;', $workflow);
            $this->assertStringContainsString('SHOW VARIABLES LIKE \'log_bin_trust_function_creators\';', $workflow);
        }
    }

    public function test_release_gate_artifact_upload_excludes_transient_stage_directories(): void
    {
        $workflowPath = base_path('.github/workflows/booking-release-gate.yml');

        $this->assertTrue(File::exists($workflowPath), sprintf('Workflow file is missing: %s', $workflowPath));

        $workflow = (string) File::get($workflowPath);

        $this->assertStringContainsString('build/booking-release/**', $workflow);
        $this->assertStringContainsString('!build/booking-release/stage/**', $workflow);
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
            'staff_branch_assignments',
            'recipient_user_id',
            'dedupe_key',
            'business_hours',
            'privacy_anonymized_at',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $verifySql, sprintf('verify_release_contract.sql is missing fragment [%s].', $fragment));
        }

        $this->assertStringContainsString('verify_release_contract.sql', $databaseReadme);
        $this->assertStringContainsString('contract verification', strtolower($databaseReadme));
        $this->assertStringContainsString('staff_branch_assignments', $databaseReadme);
        $this->assertStringContainsString('verify_release_contract.sql', $toolsReadme);
        $this->assertStringContainsString('notification', strtolower($toolsReadme));
        $this->assertStringContainsString('feature-flag', strtolower($toolsReadme));
        $this->assertStringContainsString('staff branch assignment', strtolower($toolsReadme));
    }

    public function test_release_artifacts_do_not_reinstall_runtime_incompatible_payment_refund_triggers(): void
    {
        foreach ([
            base_path('database/schema/mysql-schema.sql'),
            base_path('db_all.sql'),
        ] as $path) {
            $sql = (string) File::get($path);

            $this->assertStringNotContainsString('CREATE TRIGGER `trg_payments__bi_refund_cap`', $sql);
            $this->assertStringNotContainsString('CREATE TRIGGER `trg_payments__bu_refund_cap`', $sql);
            $this->assertStringNotContainsString('CREATE TRIGGER `trg_payments__bi_refund_lineage_guard`', $sql);
            $this->assertStringNotContainsString('CREATE TRIGGER `trg_payments__bu_refund_lineage_guard`', $sql);
        }

        $patchPath = base_path('database/patches/2026_04_08_000041_drop_runtime_incompatible_payment_refund_triggers.sql');
        $patchSql = (string) File::get($patchPath);
        $verifySql = (string) File::get(base_path('tools/mysql/verify_release_contract.sql'));

        $this->assertStringContainsString('DROP TRIGGER IF EXISTS `trg_payments__bi_refund_cap`', $patchSql);
        $this->assertStringContainsString('DROP TRIGGER IF EXISTS `trg_payments__bu_refund_cap`', $patchSql);
        $this->assertStringContainsString('DROP TRIGGER IF EXISTS `trg_payments__bi_refund_lineage_guard`', $patchSql);
        $this->assertStringContainsString('DROP TRIGGER IF EXISTS `trg_payments__bu_refund_lineage_guard`', $patchSql);
        $this->assertStringContainsString('payments.refund_trigger_contract:ok', $verifySql);
        $this->assertStringContainsString('__runtime_incompatible_payment_refund_triggers_present__', $verifySql);
    }

    public function test_release_artifacts_keep_confirmed_holds_out_of_live_overlap_trigger_scope(): void
    {
        foreach ([
            base_path('database/schema/mysql-schema.sql'),
            base_path('db_all.sql'),
        ] as $path) {
            $sql = (string) File::get($path);

            $this->assertSame(
                4,
                substr_count($sql, "AND th.`hold_status` IN ('Holding', 'Pending')\n           AND th.`expire_at` > CURRENT_TIMESTAMP(6)"),
                sprintf('Expected live hold overlap triggers to ignore Confirmed linkage rows in %s.', $path)
            );
            $this->assertSame(
                2,
                substr_count($sql, "IF v_status IN ('Holding', 'Pending') AND v_expire_at > CURRENT_TIMESTAMP(6) THEN"),
                sprintf('Expected table-hold detail triggers to run only for unexpired Holding/Pending holds in %s.', $path)
            );
            $this->assertStringNotContainsString(
                "th.`hold_status` IN ('Holding', 'Pending', 'Confirmed')\n           AND (th.`hold_status` = 'Confirmed' OR th.`expire_at` > CURRENT_TIMESTAMP(6))",
                $sql
            );
            $this->assertStringNotContainsString(
                "IF v_status IN ('Holding', 'Pending', 'Confirmed') AND (v_status = 'Confirmed' OR v_expire_at > CURRENT_TIMESTAMP(6)) THEN",
                $sql
            );
        }

        $patchSql = (string) File::get(base_path('database/patches/2026_04_20_000053_confirmed_hold_conflict_scope_alignment.sql'));
        $verifySql = (string) File::get(base_path('tools/mysql/verify_release_contract.sql'));

        $this->assertStringContainsString('DROP TRIGGER IF EXISTS `trg_reservation_tables__bi_prevent_overlap`', $patchSql);
        $this->assertStringContainsString('DROP TRIGGER IF EXISTS `trg_table_hold_details__bu_prevent_overlap`', $patchSql);
        $this->assertStringContainsString('table_hold_conflict_scope.confirmed_linkage:ok', $verifySql);
        $this->assertStringContainsString('__stale_confirmed_hold_conflict_triggers__', $verifySql);
    }

    public function test_kitchen_branch_routing_patch_derives_backfill_branch_from_existing_branch_data(): void
    {
        $patchSql = (string) File::get(base_path('database/patches/2026_04_24_000056_kitchen_branch_routing_scope.sql'));

        $this->assertStringContainsString('v_default_branch_id', $patchSql);
        $this->assertStringContainsString('MIN(CASE WHEN b.`is_default` = 1 THEN b.`branch_id` END)', $patchSql);
        $this->assertStringContainsString("MIN(CASE WHEN b.`branch_code` = 'MAIN' THEN b.`branch_id` END)", $patchSql);
        $this->assertStringContainsString('Cannot backfill kitchen_stations.branch_id because no default branch exists.', $patchSql);
        $this->assertStringContainsString('Cannot backfill kitchen_station_category_routes.branch_id because matching kitchen station is missing.', $patchSql);
        $this->assertStringContainsString('ADD COLUMN `branch_id` int unsigned NULL AFTER `station_id`', $patchSql);
        $this->assertStringContainsString('MODIFY COLUMN `branch_id` int unsigned NOT NULL', $patchSql);
        $this->assertStringNotContainsString("ADD COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1'", $patchSql);
        $this->assertStringNotContainsString("MODIFY COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1'", $patchSql);
    }

    public function test_release_artifacts_restore_critical_branch_foreign_keys(): void
    {
        $expectedConstraints = [
            'reservations' => 'fk_reservations__branch_id__branches',
            'table_holds' => 'fk_table_holds__branch_id__branches',
            'cashier_shifts' => 'fk_cashier_shifts__branch_id__branches',
        ];

        foreach ([
            base_path('database/schema/mysql-schema.sql'),
            base_path('db_all.sql'),
        ] as $path) {
            $sql = (string) File::get($path);

            foreach ($expectedConstraints as $table => $constraint) {
                $this->assertStringContainsString(
                    sprintf('CONSTRAINT `%s`', $constraint),
                    $sql,
                    sprintf('Release SQL artifact %s is missing critical branch FK %s.', $path, $constraint)
                );
                $this->assertStringNotContainsString(
                    sprintf('--   %s.%s', $table, $constraint),
                    $sql,
                    sprintf('Release SQL artifact %s still lists %s as omitted.', $path, $constraint)
                );
            }
        }

        $patchSql = (string) File::get(base_path('database/patches/2026_04_24_000054_branch_fk_integrity_guard.sql'));
        $verifySql = (string) File::get(base_path('tools/mysql/verify_release_contract.sql'));
        $databaseReadme = (string) File::get(base_path('database/README_release_bootstrap.md'));
        $toolsReadme = (string) File::get(base_path('tools/mysql/README_bootstrap_release.md'));

        foreach ($expectedConstraints as $constraint) {
            $this->assertStringContainsString($constraint, $patchSql);
            $this->assertStringContainsString($constraint, $verifySql);
        }

        $this->assertStringContainsString('orphan branch rows exist', $patchSql);
        $this->assertStringContainsString('reservations.branch_id_fk:ok', $verifySql);
        $this->assertStringContainsString('table_holds.branch_id_fk:ok', $verifySql);
        $this->assertStringContainsString('cashier_shifts.branch_id_fk:ok', $verifySql);
        $this->assertStringContainsString('Runtime read paths must not auto-create branch rows', $databaseReadme);
        $this->assertStringContainsString('Runtime read paths are expected to surface missing bootstrap state', $toolsReadme);
    }

    public function test_release_artifacts_restore_cashier_shift_finance_user_foreign_keys(): void
    {
        $expectedConstraints = [
            'cashier_user_id' => 'fk_cashier_shifts__cashier_user_id__users',
            'opened_by' => 'fk_cashier_shifts__opened_by__users',
            'closed_by' => 'fk_cashier_shifts__closed_by__users',
        ];

        foreach ([
            base_path('database/schema/mysql-schema.sql'),
            base_path('db_all.sql'),
        ] as $path) {
            $sql = (string) File::get($path);

            foreach ($expectedConstraints as $constraint) {
                $this->assertStringContainsString(
                    sprintf('CONSTRAINT `%s`', $constraint),
                    $sql,
                    sprintf('Release SQL artifact %s is missing cashier shift finance FK %s.', $path, $constraint)
                );
                $this->assertStringNotContainsString(
                    sprintf('--   cashier_shifts.%s', $constraint),
                    $sql,
                    sprintf('Release SQL artifact %s still lists %s as omitted.', $path, $constraint)
                );
            }

            $this->assertStringContainsString('KEY `idx_cashier_shifts__opened_by` (`opened_by`)', $sql);
            $this->assertStringContainsString('KEY `idx_cashier_shifts__closed_by` (`closed_by`)', $sql);
            foreach (array_keys($expectedConstraints) as $column) {
                $this->assertStringContainsString(
                    sprintf('FOREIGN KEY (`%s`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT', $column),
                    $sql,
                    sprintf('Release SQL artifact %s must restrict cashier shift user deletes for %s.', $path, $column)
                );
                $this->assertStringNotContainsString(
                    sprintf('FOREIGN KEY (`%s`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT', $column),
                    $sql,
                    sprintf('Release SQL artifact %s must not null cashier shift audit user %s on delete.', $path, $column)
                );
            }
        }

        $patchSql = (string) File::get(base_path('database/patches/2026_04_26_000058_cashier_shift_user_fk_contract.sql'));
        $verifySql = (string) File::get(base_path('tools/mysql/verify_release_contract.sql'));
        $bookingReleaseConfig = (string) File::get(base_path('config/booking_release.php'));
        $databaseReadme = (string) File::get(base_path('database/README_release_bootstrap.md'));
        $toolsReadme = (string) File::get(base_path('tools/mysql/README_bootstrap_release.md'));

        foreach ($expectedConstraints as $column => $constraint) {
            $this->assertStringContainsString($constraint, $patchSql);
            $this->assertStringContainsString($constraint, $verifySql);
            $this->assertStringContainsString($constraint, $bookingReleaseConfig);
            $this->assertStringContainsString(sprintf("column_name = '%s'", $column), $verifySql);
            $this->assertStringContainsString("referenced_table_name = 'users'", $verifySql);
            $this->assertStringContainsString("referenced_column_name = 'user_id'", $verifySql);
            $this->assertStringContainsString(
                sprintf('FOREIGN KEY (`%s`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT', $column),
                $patchSql
            );
        }

        $this->assertStringContainsString('orphan user rows exist', $patchSql);
        $this->assertStringContainsString('cashier_shifts.cashier_user_id_fk:ok', $verifySql);
        $this->assertStringContainsString('cashier_shifts.opened_by_fk:ok', $verifySql);
        $this->assertStringContainsString('cashier_shifts.closed_by_fk:ok', $verifySql);
        $this->assertStringContainsString('__missing_restore_contract_cashier_shifts_cashier_user_fk__', $verifySql);
        $this->assertStringContainsString('__missing_restore_contract_cashier_shifts_opened_by_fk__', $verifySql);
        $this->assertStringContainsString('__missing_restore_contract_cashier_shifts_closed_by_fk__', $verifySql);
        $this->assertStringContainsString('2026_04_26_000058_cashier_shift_user_fk_contract.sql', $bookingReleaseConfig);
        $this->assertStringContainsString('cashier shift finance user foreign keys', strtolower($databaseReadme));
        $this->assertStringContainsString('cashier shift finance user foreign keys', strtolower($toolsReadme));
    }

    public function test_release_artifacts_link_payments_to_cashier_shifts_for_reconciliation(): void
    {
        foreach ([
            base_path('database/schema/mysql-schema.sql'),
            base_path('db_all.sql'),
        ] as $path) {
            $sql = (string) File::get($path);

            $this->assertStringContainsString('`cashier_shift_id` bigint unsigned DEFAULT NULL', $sql);
            $this->assertStringContainsString('KEY `idx_payments__cashier_shift_id` (`cashier_shift_id`)', $sql);
            $this->assertStringContainsString(
                'CONSTRAINT `fk_payments__cashier_shift_id__cashier_shifts` FOREIGN KEY (`cashier_shift_id`) REFERENCES `cashier_shifts` (`cashier_shift_id`) ON DELETE SET NULL ON UPDATE RESTRICT',
                $sql,
                sprintf('Release SQL artifact %s is missing payments.cashier_shift_id FK.', $path)
            );
        }

        $patchSql = (string) File::get(base_path('database/patches/2026_04_29_000062_payment_cashier_shift_link.sql'));
        $verifySql = (string) File::get(base_path('tools/mysql/verify_release_contract.sql'));
        $bookingReleaseConfig = (string) File::get(base_path('config/booking_release.php'));
        $releaseManifest = (string) File::get(base_path('storage/app/booking_release/release_manifest_snapshot.json'));

        $this->assertStringContainsString('ADD COLUMN `cashier_shift_id` bigint unsigned DEFAULT NULL AFTER `branch_id`', $patchSql);
        $this->assertStringContainsString('idx_payments__cashier_shift_id', $patchSql);
        $this->assertStringContainsString('fk_payments__cashier_shift_id__cashier_shifts', $patchSql);
        $this->assertStringContainsString('orphan cashier shift rows exist', $patchSql);
        $this->assertStringContainsString('payments.cashier_shift_id:ok', $verifySql);
        $this->assertStringContainsString('payments.cashier_shift_id_index:ok', $verifySql);
        $this->assertStringContainsString('payments.cashier_shift_id_fk:ok', $verifySql);
        $this->assertStringContainsString('__missing_restore_contract_payments_cashier_shift_fk__', $verifySql);
        $this->assertStringContainsString('2026_04_29_000062_payment_cashier_shift_link.sql', $bookingReleaseConfig);
        $this->assertStringContainsString('2026_04_29_000062_payment_cashier_shift_link.sql', $releaseManifest);
    }

    public function test_release_contract_gates_completed_paid_service_reservations_missing_bill_snapshot(): void
    {
        foreach ([
            base_path('database/schema/mysql-schema.sql'),
            base_path('db_all.sql'),
        ] as $path) {
            $sql = (string) File::get($path);

            $this->assertStringContainsString('`final_bill_amount` decimal(14,2) DEFAULT NULL', $sql);
            $this->assertStringContainsString('`bill_currency` varchar(10)', $sql);
            $this->assertStringContainsString('`billed_at` datetime(6) DEFAULT NULL', $sql);
        }

        $patchSql = (string) File::get(base_path('database/patches/2026_04_29_000063_completed_paid_bill_snapshot_gate.sql'));
        $verifySql = (string) File::get(base_path('tools/mysql/verify_release_contract.sql'));
        $bookingReleaseConfig = (string) File::get(base_path('config/booking_release.php'));
        $releaseManifest = (string) File::get(base_path('storage/app/booking_release/release_manifest_snapshot.json'));
        $databaseReadme = (string) File::get(base_path('database/README_release_bootstrap.md'));
        $toolsReadme = (string) File::get(base_path('tools/mysql/README_bootstrap_release.md'));

        foreach ([
            'reservations.completed_paid_bill_snapshot:ok',
            '__drifted_completed_paid_reservations_missing_bill_snapshot__',
            "p.`payment_type` IN ('Deposit', 'Final')",
            "p.`status` IN ('Success', 'Partial')",
            "ro.`order_type` = 'OnSpot'",
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $patchSql);
        }

        foreach ([
            'reservations.completed_paid_bill_snapshot:ok',
            '__drifted_completed_paid_reservations_missing_bill_snapshot__',
            "p.payment_type IN ('Deposit', 'Final')",
            "p.status IN ('Success', 'Partial')",
            "ro.order_type = 'OnSpot'",
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $verifySql);
        }

        $this->assertStringContainsString('release_contract_verifier', $bookingReleaseConfig);
        $this->assertStringContainsString('2026_04_29_000063_completed_paid_bill_snapshot_gate.sql', $bookingReleaseConfig);
        $this->assertStringContainsString('2026_04_29_000063_completed_paid_bill_snapshot_gate.sql', $releaseManifest);
        $this->assertStringContainsString('completed paid on-spot service reservations', strtolower($databaseReadme));
        $this->assertStringContainsString('completed paid on-spot reservations missing', strtolower($toolsReadme));
    }

    public function test_schema_dump_db_all_verifier_contain_chk_reservation_order_items_line_total_matches(): void
    {
        foreach ([
            base_path('database/schema/mysql-schema.sql'),
            base_path('db_all.sql'),
        ] as $path) {
            $sql = strtolower((string) File::get($path));

            $this->assertStringContainsString(
                'constraint `chk_reservation_order_items__line_total_matches`',
                $sql,
                sprintf('Release SQL artifact %s is missing the order item line total CHECK.', $path)
            );
            $this->assertStringContainsString(
                '`line_total` = round((`unit_price` * `quantity`),2)',
                $sql,
                sprintf('Release SQL artifact %s must enforce line_total = ROUND(unit_price * quantity, 2).', $path)
            );
        }

        $patchSql = (string) File::get(base_path('database/patches/2026_04_27_000060_order_item_line_total_invariant.sql'));
        $verifySql = (string) File::get(base_path('tools/mysql/verify_release_contract.sql'));
        $bookingReleaseConfig = (string) File::get(base_path('config/booking_release.php'));

        $this->assertStringContainsString('chk_reservation_order_items__line_total_matches', $patchSql);
        $this->assertStringContainsString('ROUND(`unit_price` * `quantity`, 2)', $patchSql);
        $this->assertStringContainsString('drifted line totals exist', $patchSql);
        $this->assertStringContainsString('reservation_order_items.line_total_matches:ok', $verifySql);
        $this->assertStringContainsString('__missing_restore_contract_reservation_order_items_line_total_matches__', $verifySql);
        $this->assertStringContainsString('__drifted_reservation_order_items_line_total__', $verifySql);
        $this->assertStringContainsString('chk_reservation_order_items__line_total_matches', $bookingReleaseConfig);
        $this->assertStringContainsString('2026_04_27_000060_order_item_line_total_invariant.sql', $bookingReleaseConfig);
    }
}
