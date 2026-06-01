<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Platform\ApiContract\Services\RouteInventoryGateService;
use App\Platform\Health\Services\BookingDoctorService;
use App\Platform\Metrics\Services\OperationalAlertService;
use App\Platform\Release\Services\BookingDeploySafetyService;
use App\Platform\Release\Services\CoreOpsGateService;
use App\Platform\Release\Services\ReleaseArtifactManifestService;
use App\Platform\Release\Services\ReleasePackageService;
use App\Platform\Release\Services\RoundFiveGateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LaunchReadinessTruthfulnessTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/launch_readiness_truthfulness';

    private string $tempFixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('booking_launch_readiness.artifact_root', $this->artifactRoot);
        Carbon::setTestNow(Carbon::parse('2026-04-28T10:00:00Z'));
        $this->tempFixturePath = base_path($this->artifactRoot.'/temp_evidence.json');
        File::ensureDirectoryExists(dirname($this->tempFixturePath));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        if (File::exists($this->tempFixturePath)) {
            File::delete($this->tempFixturePath);
        }
        File::deleteDirectory(base_path($this->artifactRoot));

        // Clean environment
        putenv('BACKUP_S3_BUCKET');
        putenv('AWS_ACCESS_KEY_ID');
        putenv('MAIL_USERNAME');
        putenv('MAIL_PASSWORD');
        putenv('SENTRY_LARAVEL_DSN');
        putenv('OPS_ALERTS_WEBHOOK_URL');
        putenv('VNPAY_TMN_CODE');
        putenv('MOMO_PARTNER_CODE');
        putenv('STAGING_BASE_URL');

        parent::tearDown();
    }

    private function bindHealthyDependencies(): void
    {
        config()->set('booking.payment_providers.customer_self_pay.enabled', false);

        $this->app->instance(BookingDoctorService::class, new class extends BookingDoctorService
        {
            public function __construct() {}

            public function inspect(bool $strict = false): array
            {
                return [
                    'ok' => true,
                    'validation' => ['ok' => true, 'errors' => [], 'warnings' => [], 'checks' => []],
                    'runtime' => [
                        'db' => ['ok' => true, 'message' => 'ok'],
                        'redis' => ['ok' => true, 'message' => 'ok'],
                        'scheduler' => ['ok' => true, 'message' => 'ok'],
                        'outbox' => ['ok' => true, 'message' => 'ok'],
                    ],
                    'meta' => ['strict' => $strict, 'timestamp_utc' => '2026-04-05T10:00:00Z'],
                ];
            }
        });

        $this->app->instance(BookingDeploySafetyService::class, new class extends BookingDeploySafetyService
        {
            public function __construct() {}

            public function inspect(string $mode = 'preflight'): array
            {
                return [
                    'ok' => true,
                    'mode' => $mode,
                    'errors' => [],
                    'warnings' => [],
                    'checks' => [],
                    'summary' => [
                        'environment_error_count' => 0, 'environment_warning_count' => 0,
                        'pending_migration_count' => 0, 'data_guard_error_count' => 0,
                        'data_guard_warning_count' => 0, 'artifact_error_count' => 0,
                        'artifact_warning_count' => 0, 'ops_error_count' => 0, 'ops_warning_count' => 0,
                    ],
                ];
            }
        });

        $this->app->instance(RouteInventoryGateService::class, new class extends RouteInventoryGateService
        {
            public function __construct() {}

            public function inspect(): array
            {
                return [
                    'ok' => true,
                    'suite' => 'route_inventory',
                    'checks' => ['expected_routes' => ['ok' => true, 'severity' => 'info', 'message' => 'ok']],
                    'summary' => ['error_count' => 0, 'warning_count' => 0],
                ];
            }
        });

        $this->app->instance(CoreOpsGateService::class, new class extends CoreOpsGateService
        {
            public function __construct() {}

            public function run(bool $write = false): array
            {
                return [
                    'ok' => true,
                    'tests' => [['key' => 'table_hold', 'ok' => true]],
                ];
            }
        });

        $this->app->instance(RoundFiveGateService::class, new class extends RoundFiveGateService
        {
            public function __construct() {}

            public function run(bool $write = false): array
            {
                return [
                    'ok' => true,
                    'tests' => [['key' => 'financial_matrix', 'ok' => true]],
                ];
            }
        });

        $this->app->instance(OperationalAlertService::class, new class extends OperationalAlertService
        {
            public function __construct() {}

            public function snapshot(?Carbon $now = null, int $paymentSampleLimit = 10): array
            {
                return ['notification_outbox' => ['status' => 'ok']];
            }

            public function buildAlerts(array $snapshot, ?Carbon $now = null): array
            {
                return [];
            }
        });

        $this->app->instance(ReleaseArtifactManifestService::class, new class extends ReleaseArtifactManifestService
        {
            public function __construct() {}

            public function snapshot(): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'issues' => [],
                    'artifacts' => ['openapi_v1_spec' => ['exists' => true, 'missing_fragments' => []]],
                    'patches' => ['count' => 0, 'required_count' => 0],
                ];
            }

            public function inspectFrozenSnapshot(?array $currentSnapshot = null, ?string $relativePath = null): array
            {
                return ['ok' => true, 'status' => 'ok', 'issues' => []];
            }
        });

        $this->app->instance(ReleasePackageService::class, new class extends ReleasePackageService
        {
            public function __construct() {}

            public function package(?string $packageId = null, bool $verifyFrozen = false, bool $overwrite = false): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'package_id' => $packageId ?? 'generated',
                    'package_basename' => 'release',
                    'package_path' => 'build/release.tar.gz',
                    'package_exists' => true,
                    'issues' => [],
                    'warnings' => [],
                ];
            }
        });
    }

    private function getValidEvidenceTemplate(): array
    {
        return [
            'checks' => [
                'uat_scenario_pack_replay' => [
                    'status' => 'pass',
                    'performed_by' => 'qa.release',
                    'performed_at_utc' => '2026-04-27T08:30:00Z',
                    'notes' => 'UAT scenarios replayed.',
                    'scenario_pack_version' => 'v1',
                    'replayed_at_utc' => '2026-04-27T08:30:00Z',
                    'verification_result' => 'pass',
                    'production_artifact_contains_demo_credentials' => false,
                    'scenario_results' => [
                        'customer_reservation_hold_access_session' => 'pass',
                        'staff_auth_branch_scope' => 'pass',
                        'walk_in_table_service_session' => 'pass',
                        'order_item_lifecycle' => 'pass',
                        'kds_dispatch_update' => 'pass',
                        'checkout_cashier_shift' => 'pass',
                        'refund_cancel_after_payment' => 'pass',
                        'inventory_consumption_adjustment' => 'pass',
                        'notification_outbox_visibility' => 'pass',
                    ],
                ],
                'disaster_recovery_restore_evidence' => [
                    'status' => 'pass',
                    'performed_by' => 'qa.release',
                    'performed_at_utc' => '2026-04-27T09:00:00Z',
                    'notes' => 'DR restore verified.',
                    'restored_dump_identifier' => 'backup.sql.gz',
                    'restore_target' => 'restaurant_pos_restore_drill',
                    'verification_command' => 'php artisan db:table-check',
                    'verification_result' => 'pass',
                    'operator' => 'qa.release',
                    'reviewer' => 'release.lead',
                ],
                'payment_provider_external_e2e' => [
                    'status' => 'pass',
                    'performed_by' => 'qa.release',
                    'performed_at_utc' => '2026-04-27T09:10:00Z',
                    'notes' => 'Confirmed customer self-pay is disabled and staff settlement remains the day-1 path.',
                    'customer_self_pay_enabled' => false,
                    'staff_settlement_day1_path_confirmed' => true,
                    'provider_code' => 'generic_http_hmac',
                    'provider_mode' => 'disabled',
                    'scopes' => ['deposit' => 'not_enabled', 'bill' => 'not_enabled'],
                    'callback_webhook_tested' => false,
                    'signature_validation_tested' => false,
                    'idempotency_replay_tested' => false,
                    'failure_cancel_path_tested' => false,
                    'settlement_reconciliation_tested' => false,
                ],
                'performance_verification_report' => [
                    'status' => 'pass',
                    'performed_by' => 'qa.release',
                    'performed_at_utc' => '2026-04-27T09:18:00Z',
                    'notes' => 'Performance report verified.',
                    'report_path' => 'storage/app/booking_release/performance/report.json',
                    'profile' => 'staging',
                    'evidence_level' => 'release_candidate',
                    'verification_result' => 'pass',
                    'evaluated_at_utc' => '2026-04-27T09:18:00Z',
                    'local_smoke_only' => false,
                    'scenario_matrix' => [
                        'table_board_load' => 'pass',
                        'open_walk_in_service_session' => 'pass',
                        'add_order_item' => 'pass',
                        'kds_board_dispatch' => 'pass',
                        'checkout_payment_capture' => 'pass',
                        'refund_preview_execute' => 'pass',
                        'inventory_stock_read_adjustment' => 'pass',
                        'reporting_snapshot_read' => 'pass',
                    ],
                ],
                'notification_provider_external_e2e' => [
                    'status' => 'pass',
                    'performed_by' => 'qa.release',
                    'performed_at_utc' => '2026-04-27T09:25:00Z',
                    'notes' => 'SMTP relay verified.',
                ],
                'operator_approval' => [
                    'status' => 'approved',
                    'operator_initials' => 'qa.release',
                    'reviewer_initials' => 'release.lead',
                    'verified_at' => '2026-04-27T09:30:00Z',
                    'role' => 'release-owner',
                    'approved_at' => '2026-04-27T09:30:00Z',
                    'scope' => 'staging-rehearsal',
                    'production_cutover_approved' => false,
                ],
            ],
        ];
    }

    private function setPrereqEnv(): void
    {
        putenv('BACKUP_S3_BUCKET=my-real-s3-bucket');
        putenv('AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE');
        putenv('MAIL_USERNAME=smtp-user');
        putenv('MAIL_PASSWORD=smtp-pass');
        putenv('SENTRY_LARAVEL_DSN=https://sentry.io/123456');
        putenv('OPS_ALERTS_WEBHOOK_URL=https://hooks.slack.com/services/T0000/B0000/XXXX');
        putenv('VNPAY_TMN_CODE=VNPAY_TMN_CODE');
        putenv('MOMO_PARTNER_CODE=MOMO_PARTNER_CODE');
        putenv('STAGING_BASE_URL=https://staging.restaurantpos.vn');
    }

    /**
     * Test 1: Manual evidence has runtime gates PASS but external evidence is missing (no manual evidence file).
     */
    public function test_truthfulness_blocks_when_credentials_and_evidence_are_completely_missing(): void
    {
        $this->bindHealthyDependencies();

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'staging',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('blocked_missing_staging_credentials', $payload['decision'] ?? null);
        $this->assertFalse((bool) ($payload['release_handoff']['operator_approval']['production_cutover_approved'] ?? false));
    }

    /**
     * Test 2: Manual evidence has payment adapter tests PASS but provider callback is missing/test-vector-only.
     */
    public function test_truthfulness_blocks_when_payment_provider_callback_is_simulated_or_test_vector_only(): void
    {
        $this->bindHealthyDependencies();
        $this->setPrereqEnv();

        $evidence = $this->getValidEvidenceTemplate();
        $evidence['checks']['payment_provider_external_e2e']['provider_code'] = 'simulated';
        $evidence['checks']['payment_provider_external_e2e']['notes'] = 'Simulated rehearsal only.';

        file_put_contents($this->tempFixturePath, json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'staging',
            '--manual-evidence' => $this->tempFixturePath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('partial_real_staging_evidence', $payload['decision'] ?? null);
        $this->assertTrue(collect($payload['blocking_failures'])->contains(
            fn (array $f): bool => str_contains($f['message'], 'payment_provider_callbacks')
        ));
    }

    /**
     * Test 3: SMTP smoke uses log mailer or log_mailer.
     */
    public function test_truthfulness_blocks_when_smtp_delivery_uses_log_mailer(): void
    {
        $this->bindHealthyDependencies();
        $this->setPrereqEnv();

        $evidence = $this->getValidEvidenceTemplate();
        $evidence['checks']['notification_provider_external_e2e']['mailer'] = 'log';
        $evidence['checks']['notification_provider_external_e2e']['notes'] = 'Using log_mailer rehearsal.';

        file_put_contents($this->tempFixturePath, json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'staging',
            '--manual-evidence' => $this->tempFixturePath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('partial_real_staging_evidence', $payload['decision'] ?? null);
        $this->assertTrue(collect($payload['blocking_failures'])->contains(
            fn (array $f): bool => str_contains($f['message'], 'smtp_delivery')
        ));
    }

    /**
     * Test 4: S3 DR rehearsal is local-only/dry-run.
     */
    public function test_truthfulness_blocks_when_s3_dr_restore_is_local_only(): void
    {
        $this->bindHealthyDependencies();
        $this->setPrereqEnv();

        $evidence = $this->getValidEvidenceTemplate();
        $evidence['checks']['disaster_recovery_restore_evidence']['notes'] = 'Local-only rehearsal drill completed.';

        file_put_contents($this->tempFixturePath, json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'staging',
            '--manual-evidence' => $this->tempFixturePath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('partial_real_staging_evidence', $payload['decision'] ?? null);
        $this->assertTrue(collect($payload['blocking_failures'])->contains(
            fn (array $f): bool => str_contains($f['message'], 's3_dr_restore')
        ));
    }

    /**
     * Test 5: Tất cả required real external evidence PASS, no critical blockers.
     */
    public function test_truthfulness_passes_when_all_external_evidence_is_pristine(): void
    {
        $this->bindHealthyDependencies();
        $this->setPrereqEnv();

        $evidence = $this->getValidEvidenceTemplate();

        file_put_contents($this->tempFixturePath, json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'staging',
            '--manual-evidence' => $this->tempFixturePath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ready', $payload['decision'] ?? null);
        $this->assertFalse((bool) ($payload['release_handoff']['operator_approval']['production_cutover_approved'] ?? false));
    }

    /**
     * Test 6: Evidence chứa marker security risk hoặc secret-like value.
     */
    public function test_truthfulness_blocks_when_evidence_contains_secret_keys_or_unredacted_pii(): void
    {
        $this->bindHealthyDependencies();
        $this->setPrereqEnv();

        $evidence = $this->getValidEvidenceTemplate();
        // Inject sensitive Slack webhook in notes or custom property
        $evidence['checks']['disaster_recovery_restore_evidence']['notes'] = 'Sentry error webhook hooks.slack.com/services/T0000/B0000/secret';

        file_put_contents($this->tempFixturePath, json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'staging',
            '--manual-evidence' => $this->tempFixturePath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('blocked_security_risk', $payload['decision'] ?? null);
        $this->assertTrue(collect($payload['blocking_failures'])->contains(
            fn (array $f): bool => str_contains($f['message'], 'Sensitive pattern detected')
        ));
    }
}
