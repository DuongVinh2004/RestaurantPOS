<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\PaymentProviderRolloutConfig;
use App\Platform\ApiContract\Services\OpsGateArtifactService;
use App\Platform\ApiContract\Services\RouteInventoryGateService;
use App\Platform\Health\Services\BookingDoctorService;
use App\Platform\Metrics\Services\OperationalAlertService;
use App\Platform\Release\Services\BookingDeploySafetyService;
use App\Platform\Release\Services\CoreOpsGateService;
use App\Platform\Release\Services\LaunchReadinessService;
use App\Platform\Release\Services\ReleaseArtifactManifestService;
use App\Platform\Release\Services\ReleasePackageService;
use App\Platform\Release\Services\RoundFiveGateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class LaunchReadinessServiceTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/launch_readiness_unit';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking_launch_readiness.artifact_root', $this->artifactRoot);
        config()->set('booking.payment_providers.customer_self_pay.enabled', false);
        Carbon::setTestNow(Carbon::parse('2026-04-28T10:00:00Z'));

        // Wire mock external credentials for tests
        putenv('BACKUP_S3_BUCKET=my-real-s3-bucket');
        putenv('AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE');
        putenv('MAIL_USERNAME=smtp-user');
        putenv('MAIL_PASSWORD=smtp-pass');
        putenv('SENTRY_LARAVEL_DSN=https://sentry.io/123456');
        putenv('OPS_ALERTS_WEBHOOK_URL=https://hooks.slack.com/services/T0000/B0000/XXXX');
        putenv('VNPAY_TMN_CODE=VNPAY_TMN_CODE');
        putenv('MOMO_PARTNER_CODE=MOMO_PARTNER_CODE');
        putenv('STAGING_BASE_URL=https://staging.restaurantpos.vn');

        // Set config variables since they are now read via config()
        config()->set('booking_launch_readiness.credentials.DB_PASSWORD', 'testing');
        config()->set('booking_launch_readiness.credentials.REDIS_HOST', '127.0.0.1');
        config()->set('booking_launch_readiness.credentials.AWS_ACCESS_KEY_ID', 'AKIAIOSFODNN7EXAMPLE');
        config()->set('booking_launch_readiness.credentials.BACKUP_S3_BUCKET', 'my-real-s3-bucket');
        config()->set('booking_launch_readiness.credentials.MAIL_USERNAME', 'smtp-user');
        config()->set('booking_launch_readiness.credentials.MAIL_PASSWORD', 'smtp-pass');
        config()->set('booking_launch_readiness.credentials.SENTRY_LARAVEL_DSN', 'https://sentry.io/123456');
        config()->set('booking_launch_readiness.credentials.OPS_ALERTS_WEBHOOK_URL', 'https://hooks.slack.com/services/T0000/B0000/XXXX');
        config()->set('booking_launch_readiness.credentials.VNPAY_TMN_CODE', 'VNPAY_TMN_CODE');
        config()->set('booking_launch_readiness.credentials.MOMO_PARTNER_CODE', 'MOMO_PARTNER_CODE');
        config()->set('booking_launch_readiness.credentials.STAGING_BASE_URL', 'https://staging.restaurantpos.vn');
        config()->set('mail.default', 'smtp');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory(base_path($this->artifactRoot));

        // Clear mock external credentials
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

    public function test_self_pay_disabled_passes_with_staff_settlement_evidence(): void
    {
        config()->set('booking.payment_providers.customer_self_pay.enabled', false);

        $report = $this->service()->evaluate(
            target: 'limited-production',
            manualEvidencePath: $this->writeEvidence($this->validEvidence(selfPayEnabled: false)),
        );

        $payment = collect((array) ($report['manual_checks'] ?? []))->firstWhere('key', 'payment_provider_external_e2e');

        $this->assertTrue((bool) ($report['ok'] ?? false));
        $this->assertSame('ready', $report['decision'] ?? null);
        $this->assertSame('pass', $payment['status'] ?? null);
        $this->assertSame('Customer self-pay is disabled and staff settlement evidence is schema-valid.', $payment['summary'] ?? null);
    }

    public function test_self_pay_enabled_without_payment_evidence_blocks_limited_production(): void
    {
        $this->enableReadySelfPayProvider();

        $evidence = $this->validEvidence(selfPayEnabled: true);
        unset($evidence['checks']['payment_provider_external_e2e']);

        $report = $this->service()->evaluate(
            target: 'limited-production',
            manualEvidencePath: $this->writeEvidence($evidence),
        );

        $messages = collect((array) ($report['blocking_failures'] ?? []))
            ->map(static fn (array $finding): string => (string) ($finding['message'] ?? ''));

        $this->assertFalse((bool) ($report['ok'] ?? true));
        $this->assertSame('not_ready', $report['decision'] ?? null);
        $this->assertTrue($messages->contains(
            static fn (string $message): bool => str_contains($message, 'Payment provider / customer self-pay readiness')
        ));
    }

    public function test_self_pay_enabled_with_valid_provider_evidence_passes(): void
    {
        $this->enableReadySelfPayProvider();

        $report = $this->service()->evaluate(
            target: 'limited-production',
            manualEvidencePath: $this->writeEvidence($this->validEvidence(selfPayEnabled: true)),
        );

        $payment = collect((array) ($report['manual_checks'] ?? []))->firstWhere('key', 'payment_provider_external_e2e');

        $this->assertTrue((bool) ($report['ok'] ?? false));
        $this->assertSame('ready', $report['decision'] ?? null);
        $this->assertSame('pass', $payment['status'] ?? null);
        $this->assertSame('Customer self-pay provider evidence is schema-valid for deposit and bill scopes.', $payment['summary'] ?? null);
    }

    public function test_invalid_payment_evidence_fields_block_and_are_redacted(): void
    {
        $this->enableReadySelfPayProvider();

        $evidence = $this->validEvidence(selfPayEnabled: true);
        $evidence['checks']['payment_provider_external_e2e']['provider_mode'] = 'simulated';
        $evidence['checks']['payment_provider_external_e2e']['provider_credentials'] = [
            'api_secret' => 'sample-not-real',
        ];

        $report = $this->service()->evaluate(
            target: 'limited-production',
            manualEvidencePath: $this->writeEvidence($evidence),
        );

        $messages = collect((array) ($report['blocking_failures'] ?? []))
            ->map(static fn (array $finding): string => (string) ($finding['message'] ?? ''));

        $this->assertFalse((bool) ($report['ok'] ?? true));
        $this->assertTrue($messages->contains(
            static fn (string $message): bool => str_contains($message, 'sensitive-looking field')
        ));
        $this->assertTrue($messages->contains(
            static fn (string $message): bool => str_contains($message, 'provider_mode must be sandbox or live')
        ));
        $this->assertSame(
            '[redacted]',
            data_get($report, 'manual_evidence.checks.payment_provider_external_e2e.provider_credentials.api_secret')
        );
    }

    private function enableReadySelfPayProvider(): void
    {
        config()->set('app.env', 'staging');
        config()->set('booking.payment_providers.customer_self_pay.enabled', true);
        config()->set('booking.payment_providers.scopes.deposit.default_provider', 'generic_http_hmac');
        config()->set('booking.payment_providers.scopes.bill.default_provider', 'generic_http_hmac');
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.mode', 'sandbox');
        config()->set('booking.payment_providers.providers.generic_http_hmac.base_url', 'https://provider.example.test');
        config()->set('booking.payment_providers.providers.generic_http_hmac.deposit.create_endpoint', '/deposit-sessions');
        config()->set('booking.payment_providers.providers.generic_http_hmac.bill.create_endpoint', '/bill-sessions');
        config()->set('booking.payment_providers.providers.generic_http_hmac.request.secret', 'sample-request-secret');
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.secret', 'sample-webhook-secret');
    }

    /**
     * @return array<string, mixed>
     */
    private function validEvidence(bool $selfPayEnabled): array
    {
        $paymentEvidence = $selfPayEnabled
            ? [
                'status' => 'pass',
                'performed_by' => 'qa.release',
                'performed_at_utc' => '2026-04-28T09:10:00Z',
                'notes' => 'Validated sandbox provider readiness for deposit and bill self-pay.',
                'customer_self_pay_enabled' => true,
                'provider_code' => 'generic_http_hmac',
                'provider_mode' => 'sandbox',
                'scopes' => [
                    'deposit' => 'pass',
                    'bill' => 'pass',
                ],
                'callback_webhook_tested' => true,
                'signature_validation_tested' => true,
                'idempotency_replay_tested' => true,
                'failure_cancel_path_tested' => true,
                'settlement_reconciliation_tested' => true,
            ]
            : [
                'status' => 'pass',
                'performed_by' => 'qa.release',
                'performed_at_utc' => '2026-04-28T09:10:00Z',
                'notes' => 'Confirmed staff settlement remains the day-1 payment path.',
                'customer_self_pay_enabled' => false,
                'staff_settlement_day1_path_confirmed' => true,
                'provider_code' => 'generic_http_hmac',
                'provider_mode' => 'disabled',
            ];

        return [
            'checks' => [
                'uat_scenario_pack_replay' => [
                    'status' => 'pass',
                    'performed_by' => 'qa.release',
                    'performed_at_utc' => '2026-04-28T08:30:00Z',
                    'notes' => 'Ran canonical UAT pack.',
                    'scenario_pack_version' => 'day1-golden-v1',
                    'replayed_at_utc' => '2026-04-28T08:30:00Z',
                    'verification_result' => 'pass',
                    'production_artifact_contains_demo_credentials' => false,
                    'scenario_results' => array_fill_keys((array) config('booking_launch_readiness.uat_required_scenarios'), 'pass'),
                ],
                'disaster_recovery_restore_evidence' => [
                    'status' => 'pass',
                    'performed_by' => 'qa.release',
                    'performed_at_utc' => '2026-04-28T08:50:00Z',
                    'notes' => 'Restored into a scratch target.',
                    'restored_dump_identifier' => 'booking-backup-20260428T083000Z-full.sql.gz',
                    'restore_target' => 'restaurant_pos_restore_drill',
                    'verification_command' => 'php artisan booking:dr-drill --mode=full-isolated-restore --manifest=manifest.json --target-db=restaurant_pos_restore_drill --json',
                    'verification_result' => 'pass',
                ],
                'payment_provider_external_e2e' => $paymentEvidence,
                'performance_verification_report' => [
                    'status' => 'pass',
                    'performed_by' => 'qa.release',
                    'performed_at_utc' => '2026-04-28T09:18:00Z',
                    'notes' => 'Archived staging performance evidence.',
                    'report_path' => 'storage/app/booking_release/performance_verification/reports/performance-verification-staging.json',
                    'profile' => 'staging',
                    'evidence_level' => 'release_candidate',
                    'verification_result' => 'pass',
                    'evaluated_at_utc' => '2026-04-28T09:18:00Z',
                    'local_smoke_only' => false,
                    'scenario_matrix' => array_fill_keys((array) config('booking_launch_readiness.performance_required_surfaces'), 'pass'),
                ],
                'notification_provider_external_e2e' => [
                    'status' => 'pass',
                    'performed_by' => 'qa.release',
                    'performed_at_utc' => '2026-04-28T09:25:00Z',
                    'notes' => 'Validated email delivery.',
                ],
                'concurrency_rehearsal' => [
                    'status' => 'pass',
                    'performed_by' => 'qa.release',
                    'performed_at_utc' => '2026-04-28T09:45:00Z',
                    'notes' => 'Completed contention rehearsal.',
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeEvidence(array $payload): string
    {
        $path = base_path($this->artifactRoot.'/manual-evidence.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $path;
    }

    private function service(): LaunchReadinessService
    {
        return new LaunchReadinessService(
            new class extends BookingDoctorService
            {
                public function __construct() {}

                public function inspect(bool $strict = false): array
                {
                    return [
                        'ok' => true,
                        'validation' => ['ok' => true, 'errors' => [], 'warnings' => []],
                        'runtime' => [
                            'db' => ['ok' => true, 'message' => 'ok'],
                            'redis' => ['ok' => true, 'message' => 'ok'],
                            'scheduler' => ['ok' => true, 'message' => 'ok'],
                            'outbox' => ['ok' => true, 'message' => 'ok'],
                        ],
                    ];
                }
            },
            new class extends BookingDeploySafetyService
            {
                public function __construct() {}

                public function inspect(string $mode = 'preflight'): array
                {
                    return ['ok' => true, 'mode' => $mode, 'errors' => [], 'warnings' => [], 'summary' => []];
                }
            },
            new class extends RouteInventoryGateService
            {
                public function inspect(): array
                {
                    return ['ok' => true, 'summary' => ['error_count' => 0, 'warning_count' => 0], 'checks' => []];
                }
            },
            new class extends CoreOpsGateService
            {
                public function run(bool $write = false): array
                {
                    return ['ok' => true, 'summary' => ['failed' => 0], 'tests' => []];
                }
            },
            new class extends RoundFiveGateService
            {
                public function run(bool $write = false): array
                {
                    return ['ok' => true, 'summary' => ['failed' => 0], 'tests' => []];
                }
            },
            new class extends OperationalAlertService
            {
                public function snapshot(?Carbon $now = null, int $paymentSampleLimit = 10): array
                {
                    return [];
                }

                public function buildAlerts(array $snapshot, ?Carbon $now = null): array
                {
                    return [];
                }
            },
            new class extends ReleaseArtifactManifestService
            {
                public function snapshot(): array
                {
                    return [
                        'ok' => true,
                        'status' => 'ok',
                        'issues' => [],
                        'artifacts' => [
                            'openapi_v1_spec' => ['exists' => true, 'optional' => false, 'missing_fragments' => []],
                        ],
                        'patches' => ['missing' => [], 'required_count' => 0],
                        'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                    ];
                }

                public function inspectFrozenSnapshot(?array $currentSnapshot = null, ?string $relativePath = null): array
                {
                    return ['ok' => true, 'status' => 'ok', 'issues' => [], 'mismatch_paths' => [], 'frozen' => $currentSnapshot, 'live' => $currentSnapshot];
                }
            },
            new class extends ReleasePackageService
            {
                public function __construct() {}

                public function package(?string $packageId = null, bool $verifyFrozen = false, bool $overwrite = false): array
                {
                    return [
                        'ok' => true,
                        'package_basename' => 'restaurantpos-backend-release-generated',
                        'package_path' => 'build/booking-release/restaurantpos-backend-release-generated.tar.gz',
                        'sidecars' => [],
                    ];
                }
            },
            new OpsGateArtifactService,
            new PaymentProviderRolloutConfig,
        );
    }
}
