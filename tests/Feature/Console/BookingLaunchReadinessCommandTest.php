<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\Release\Services\BookingDeploySafetyService;
use App\Platform\Health\Services\BookingDoctorService;
use App\Platform\Release\Services\CoreOpsGateService;
use App\Platform\Metrics\Services\OperationalAlertService;
use App\Platform\Release\Services\ReleaseArtifactManifestService;
use App\Platform\Release\Services\ReleasePackageService;
use App\Platform\Release\Services\RoundFiveGateService;
use App\Platform\ApiContract\Services\RouteInventoryGateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class BookingLaunchReadinessCommandTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/launch_readiness_command';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking_launch_readiness.artifact_root', $this->artifactRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->artifactRoot));
        parent::tearDown();
    }

    #[Group('booking-smoke')]
    public function test_booking_launch_readiness_can_pass_clean_staging_with_manual_evidence(): void
    {
        $this->bindHealthyDependencies();

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'staging',
            '--manual-evidence' => 'tests/fixtures/launch_readiness_manual_evidence.json',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ready', $payload['decision'] ?? null);
        $manualStatuses = collect((array) ($payload['manual_checks'] ?? []))
            ->mapWithKeys(static fn (array $check): array => [(string) ($check['key'] ?? '') => (string) ($check['status'] ?? '')])
            ->all();
        $this->assertSame($this->artifactRoot.'/reports', $payload['artifacts']['reports_root'] ?? null);
        $this->assertSame('pass', $manualStatuses['uat_scenario_pack_replay'] ?? null);
        $this->assertSame('pass', $manualStatuses['performance_verification_report'] ?? null);
        $this->assertSame('pass', $manualStatuses['notification_provider_external_e2e'] ?? null);
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['json_path'] ?? '')));
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['markdown_path'] ?? '')));
    }

    #[Group('booking-smoke')]
    public function test_booking_launch_readiness_records_limited_production_manual_sources_and_markdown_context(): void
    {
        $this->bindHealthyDependencies();

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'limited-production',
            '--manual-evidence' => 'tests/fixtures/launch_readiness_manual_evidence.json',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $integratedSources = collect((array) ($payload['integrated_sources'] ?? []))
            ->mapWithKeys(static fn (array $source): array => [(string) ($source['source'] ?? '') => (string) ($source['role'] ?? '')])
            ->all();
        $markdownPath = base_path((string) (($payload['artifacts'] ?? [])['markdown_path'] ?? ''));
        $markdown = (string) file_get_contents($markdownPath);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ready', $payload['decision'] ?? null);
        $this->assertSame('Manual evidence: Canonical UAT scenario pack replay', $integratedSources['UAT/demo scenario pack'] ?? null);
        $this->assertSame('Manual evidence: Performance verification report', $integratedSources['booking:performance-verify'] ?? null);
        $this->assertSame('Manual evidence: Real payment-provider callback rehearsal', $integratedSources['Payment provider / webhook manual rehearsal'] ?? null);
        $this->assertSame('Manual evidence: Real notification delivery rehearsal', $integratedSources['Notification outbox / provider manual rehearsal'] ?? null);
        $this->assertSame('Manual evidence: Multi-process concurrency rehearsal', $integratedSources['Staging concurrency drill'] ?? null);
        $this->assertStringContainsString('## Integrated Sources', $markdown);
        $this->assertStringContainsString('`booking:performance-verify` | Manual evidence: Performance verification report', $markdown);
        $this->assertStringContainsString(
            sprintf('- Evidence file: `%s`', (string) (($payload['manual_evidence'] ?? [])['resolved_path'] ?? '')),
            $markdown
        );
        $this->assertStringContainsString(
            '- [PASS] Real payment-provider callback rehearsal - by qa.release; at 2026-04-05T09:10:00Z; Validated one payment-provider callback rehearsal in staging.',
            $markdown
        );
        $this->assertStringContainsString(
            '- [PASS] Multi-process concurrency rehearsal - by qa.release; at 2026-04-05T09:45:00Z; Completed Redis/MySQL multi-process contention rehearsal.',
            $markdown
        );
    }

    #[Group('booking-smoke')]
    public function test_booking_launch_readiness_returns_warning_exit_code_when_staging_manual_evidence_is_missing(): void
    {
        $this->bindHealthyDependencies();

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'staging',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $manualStatuses = collect((array) ($payload['manual_checks'] ?? []))
            ->mapWithKeys(static fn (array $check): array => [(string) ($check['key'] ?? '') => (string) ($check['status'] ?? '')])
            ->all();

        $this->assertSame(2, $exitCode);
        $this->assertSame('ready_with_warnings', $payload['decision'] ?? null);
        $this->assertSame('warn', $manualStatuses['uat_scenario_pack_replay'] ?? null);
        $this->assertSame('warn', $manualStatuses['performance_verification_report'] ?? null);
        $this->assertSame('warn', $manualStatuses['notification_provider_external_e2e'] ?? null);
    }

    #[Group('booking-smoke')]
    public function test_booking_launch_readiness_blocks_limited_production_when_manual_evidence_is_missing(): void
    {
        $this->bindHealthyDependencies();

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'limited-production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $blockingMessages = array_map(
            static fn (array $finding): string => (string) ($finding['message'] ?? ''),
            (array) ($payload['blocking_failures'] ?? []),
        );

        $this->assertSame(1, $exitCode);
        $this->assertSame('not_ready', $payload['decision'] ?? null);
        $this->assertNotEmpty($blockingMessages);
        $this->assertTrue(
            collect($blockingMessages)->contains(static fn (string $message): bool => str_contains($message, 'Canonical UAT scenario pack replay'))
        );
    }

    #[Group('booking-smoke')]
    public function test_booking_launch_readiness_blocks_limited_production_when_notification_rehearsal_is_missing(): void
    {
        $this->bindHealthyDependencies();

        $fixturePath = base_path($this->artifactRoot.'/manual-evidence-missing-notification.json');
        File::ensureDirectoryExists(dirname($fixturePath));

        $manualEvidence = json_decode((string) file_get_contents(base_path('tests/fixtures/launch_readiness_manual_evidence.json')), true, 512, JSON_THROW_ON_ERROR);
        unset($manualEvidence['checks']['notification_provider_external_e2e']);
        file_put_contents($fixturePath, json_encode($manualEvidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'limited-production',
            '--manual-evidence' => $fixturePath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $blockingMessages = array_map(
            static fn (array $finding): string => (string) ($finding['message'] ?? ''),
            (array) ($payload['blocking_failures'] ?? []),
        );

        $this->assertSame(1, $exitCode);
        $this->assertSame('not_ready', $payload['decision'] ?? null);
        $this->assertTrue(
            collect($blockingMessages)->contains(
                static fn (string $message): bool => str_contains($message, 'Real notification delivery rehearsal')
            )
        );
    }

    #[Group('booking-smoke')]
    public function test_booking_launch_readiness_blocks_limited_production_when_performance_report_is_missing(): void
    {
        $this->bindHealthyDependencies();

        $fixturePath = base_path($this->artifactRoot.'/manual-evidence-missing-performance.json');
        File::ensureDirectoryExists(dirname($fixturePath));

        $manualEvidence = json_decode((string) file_get_contents(base_path('tests/fixtures/launch_readiness_manual_evidence.json')), true, 512, JSON_THROW_ON_ERROR);
        unset($manualEvidence['checks']['performance_verification_report']);
        file_put_contents($fixturePath, json_encode($manualEvidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'limited-production',
            '--manual-evidence' => $fixturePath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $blockingMessages = array_map(
            static fn (array $finding): string => (string) ($finding['message'] ?? ''),
            (array) ($payload['blocking_failures'] ?? []),
        );

        $this->assertSame(1, $exitCode);
        $this->assertSame('not_ready', $payload['decision'] ?? null);
        $this->assertTrue(
            collect($blockingMessages)->contains(
                static fn (string $message): bool => str_contains($message, 'Performance verification report')
            )
        );
    }

    #[Group('booking-smoke')]
    public function test_booking_launch_readiness_surfaces_deploy_check_failures_from_service_contract(): void
    {
        $this->bindHealthyDependencies();

        $this->app->instance(BookingDeploySafetyService::class, new class extends BookingDeploySafetyService
        {
            public function __construct() {}

            public function inspect(string $mode = 'preflight'): array
            {
                return [
                    'ok' => false,
                    'mode' => $mode,
                    'errors' => ['artifacts.release_manifest: frozen snapshot is stale.'],
                    'warnings' => [],
                    'checks' => [],
                    'summary' => [
                        'environment_error_count' => 0,
                        'environment_warning_count' => 0,
                        'pending_migration_count' => 0,
                        'data_guard_error_count' => 0,
                        'data_guard_warning_count' => 0,
                        'artifact_error_count' => 1,
                        'artifact_warning_count' => 0,
                        'ops_error_count' => 0,
                        'ops_warning_count' => 0,
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'staging',
            '--manual-evidence' => 'tests/fixtures/launch_readiness_manual_evidence.json',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $blockingMessages = array_map(
            static fn (array $finding): string => (string) ($finding['message'] ?? ''),
            (array) ($payload['blocking_failures'] ?? []),
        );

        $this->assertSame(1, $exitCode);
        $this->assertSame('not_ready', $payload['decision'] ?? null);
        $this->assertTrue(
            collect($blockingMessages)->contains(static fn (string $message): bool => str_contains($message, 'artifacts.release_manifest: frozen snapshot is stale.'))
        );
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['json_path'] ?? '')));
    }

    #[Group('booking-smoke')]
    public function test_booking_launch_readiness_skips_heavy_downstream_checks_when_runtime_baseline_is_blocked(): void
    {
        $this->bindHealthyDependencies();

        $this->app->instance(BookingDoctorService::class, new class extends BookingDoctorService
        {
            public function __construct() {}

            public function inspect(bool $strict = false): array
            {
                return [
                    'ok' => false,
                    'validation' => [
                        'ok' => true,
                        'errors' => [],
                        'warnings' => [],
                        'checks' => [],
                    ],
                    'runtime' => [
                        'db' => ['ok' => false, 'message' => 'mysql unavailable'],
                        'redis' => ['ok' => false, 'message' => 'redis unavailable'],
                    ],
                    'meta' => [
                        'strict' => $strict,
                        'timestamp_utc' => '2026-04-09T00:00:00Z',
                    ],
                ];
            }
        });

        $this->app->instance(CoreOpsGateService::class, new class extends CoreOpsGateService
        {
            public function __construct() {}

            public function run(bool $write = false): array
            {
                throw new \RuntimeException('core ops gate should have been skipped');
            }
        });

        $this->app->instance(RoundFiveGateService::class, new class extends RoundFiveGateService
        {
            public function __construct() {}

            public function run(bool $write = false): array
            {
                throw new \RuntimeException('round5 gate should have been skipped');
            }
        });

        $this->app->instance(OperationalAlertService::class, new class extends OperationalAlertService
        {
            public function snapshot(?Carbon $now = null, int $paymentSampleLimit = 10): array
            {
                throw new \RuntimeException('alert snapshot should have been skipped');
            }
        });

        $this->app->instance(ReleasePackageService::class, new class extends ReleasePackageService
        {
            public function __construct() {}

            public function package(?string $packageId = null, bool $verifyFrozen = false, bool $overwrite = false): array
            {
                throw new \RuntimeException('release packaging should have been skipped');
            }
        });

        $exitCode = Artisan::call('booking:launch-readiness', [
            '--target' => 'staging',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $checkStatuses = collect((array) ($payload['checks'] ?? []))
            ->mapWithKeys(static fn (array $check): array => [(string) ($check['key'] ?? '') => (string) ($check['status'] ?? '')])
            ->all();

        $this->assertSame(1, $exitCode);
        $this->assertTrue((bool) (($payload['meta'] ?? [])['runtime_baseline_blocked'] ?? false));
        $this->assertSame('skip', $checkStatuses['core_ops_flow_gate'] ?? null);
        $this->assertSame('skip', $checkStatuses['round5_financial_gate'] ?? null);
        $this->assertSame('skip', $checkStatuses['operational_alert_snapshot'] ?? null);
        $this->assertSame('skip', $checkStatuses['release_package_integrity'] ?? null);
    }

    private function bindHealthyDependencies(): void
    {
        $this->app->instance(BookingDoctorService::class, new class extends BookingDoctorService
        {
            public function __construct() {}

            public function inspect(bool $strict = false): array
            {
                return [
                    'ok' => true,
                    'validation' => [
                        'ok' => true,
                        'errors' => [],
                        'warnings' => [],
                        'checks' => [],
                    ],
                    'runtime' => [
                        'db' => ['ok' => true, 'message' => 'ok'],
                        'redis' => ['ok' => true, 'message' => 'ok'],
                        'scheduler' => ['ok' => true, 'message' => 'ok'],
                        'outbox' => ['ok' => true, 'message' => 'ok'],
                    ],
                    'meta' => [
                        'strict' => $strict,
                        'timestamp_utc' => '2026-04-05T10:00:00Z',
                    ],
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
                        'environment_error_count' => 0,
                        'environment_warning_count' => 0,
                        'pending_migration_count' => 0,
                        'data_guard_error_count' => 0,
                        'data_guard_warning_count' => 0,
                        'artifact_error_count' => 0,
                        'artifact_warning_count' => 0,
                        'ops_error_count' => 0,
                        'ops_warning_count' => 0,
                    ],
                ];
            }
        });

        $this->app->instance(RouteInventoryGateService::class, new class extends RouteInventoryGateService
        {
            public function inspect(): array
            {
                return [
                    'ok' => true,
                    'suite' => 'route_inventory',
                    'description' => 'Synthetic route gate.',
                    'definition_path' => 'tests/fixtures/route_inventory_gate.json',
                    'checks' => [
                        'expected_routes' => [
                            'ok' => true,
                            'severity' => 'info',
                            'message' => 'ok',
                        ],
                    ],
                    'summary' => [
                        'route_count' => 10,
                        'expected_route_count' => 10,
                        'public_controller_count' => 3,
                        'error_count' => 0,
                        'warning_count' => 0,
                    ],
                    'meta' => [],
                ];
            }
        });

        $this->app->instance(CoreOpsGateService::class, new class extends CoreOpsGateService
        {
            public function run(bool $write = false): array
            {
                return [
                    'ok' => true,
                    'suite' => 'core_ops',
                    'description' => 'Synthetic core ops gate.',
                    'definition_path' => 'tests/fixtures/core_ops_gate_suite.json',
                    'snapshot_path' => 'storage/app/booking_release/core_ops_gate_snapshot.json',
                    'tests' => [
                        ['key' => 'table_hold_http_flow', 'path' => 'tests/Feature/Table/TableHoldHttpFlowTest.php', 'category' => 'feature', 'ok' => true, 'exit_code' => 0, 'duration_ms' => 42, 'output_tail' => 'ok'],
                    ],
                    'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0],
                    'meta' => [],
                ];
            }
        });

        $this->app->instance(RoundFiveGateService::class, new class extends RoundFiveGateService
        {
            public function run(bool $write = false): array
            {
                return [
                    'ok' => true,
                    'suite' => 'round5',
                    'description' => 'Synthetic round5 gate.',
                    'definition_path' => 'tests/fixtures/round5_gate_suite.json',
                    'snapshot_path' => 'storage/app/booking_release/round5_gate_snapshot.json',
                    'tests' => [
                        ['key' => 'financial_matrix', 'path' => 'tests/Feature/Staff/StaffCheckoutFinancialIntegrationMatrixTest.php', 'category' => 'feature', 'ok' => true, 'exit_code' => 0, 'duration_ms' => 42, 'output_tail' => 'ok'],
                    ],
                    'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0],
                    'meta' => [],
                ];
            }
        });

        $this->app->instance(OperationalAlertService::class, new class extends OperationalAlertService
        {
            public function __construct() {}

            public function snapshot(?Carbon $now = null, int $paymentSampleLimit = 10): array
            {
                return [
                    'notification_outbox' => [
                        'status' => 'ok',
                        'reasons' => [],
                    ],
                ];
            }

            public function buildAlerts(array $snapshot, ?Carbon $now = null): array
            {
                return [];
            }
        });

        $this->app->instance(ReleaseArtifactManifestService::class, new class extends ReleaseArtifactManifestService
        {
            public function snapshot(): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'issues' => [],
                    'artifacts' => [
                        'openapi_v1_spec' => [
                            'path' => 'storage/app/booking_release/openapi-v1.json',
                            'exists' => true,
                            'optional' => false,
                            'missing_fragments' => [],
                        ],
                    ],
                    'patches' => [
                        'present' => [],
                        'required' => [],
                        'missing' => [],
                        'count' => 0,
                        'required_count' => 0,
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-04-05T10:00:00Z',
                    ],
                    'definition_path' => 'config/booking_release.php',
                    'definition_sha256' => str_repeat('a', 64),
                    'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                ];
            }

            public function inspectFrozenSnapshot(?array $currentSnapshot = null, ?string $relativePath = null): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'issues' => [],
                    'mismatch_paths' => [],
                    'definition_path' => 'config/booking_release.php',
                    'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                    'path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                    'live' => $currentSnapshot,
                    'frozen' => $currentSnapshot,
                ];
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
                    'package_basename' => 'restaurantpos-backend-release-generated',
                    'package_path' => 'build/booking-release/restaurantpos-backend-release-generated.tar.gz',
                    'package_exists' => true,
                    'output_root' => 'build/booking-release',
                    'stage_path' => 'build/booking-release/stage/restaurantpos-backend-release-generated',
                    'include_roots' => [],
                    'skipped_optional_paths' => [],
                    'sidecars' => [],
                    'inventory' => [
                        'file_count' => 0,
                        'total_bytes' => 0,
                    ],
                    'release_manifest' => [
                        'status' => 'ok',
                        'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                        'definition_sha256' => str_repeat('b', 64),
                        'frozen_snapshot' => [
                            'status' => 'ok',
                            'path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                        ],
                    ],
                    'issues' => [],
                    'warnings' => [],
                ];
            }
        });
    }
}
