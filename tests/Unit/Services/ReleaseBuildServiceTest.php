<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ApiArtifacts\ApiConsumerArtifactService;
use App\Services\ApiContract\OpenApiSpecService;
use App\Services\Harness\HarnessSuiteService;
use App\Services\ReleaseArtifactManifestService;
use App\Services\ReleaseBuildService;
use App\Services\ReleasePackageService;
use Tests\TestCase;

class ReleaseBuildServiceTest extends TestCase
{
    public function test_build_runs_the_canonical_release_chain_before_packaging(): void
    {
        $openApiService = new class extends OpenApiSpecService
        {
            public function __construct() {}

            public function export(?string $relativePath = null): array
            {
                return [
                    'spec' => ['openapi' => '3.1.0'],
                    'report' => ['summary' => ['path_count' => 1]],
                    'path' => $relativePath ?? 'storage/app/booking_release/openapi-v1.json',
                ];
            }
        };

        $apiArtifactService = new class extends ApiConsumerArtifactService
        {
            /** @var list<array<string, mixed>> */
            public array $calls = [];

            public function __construct() {}

            public function generate(?string $outputRoot = null, ?string $specPath = null, bool $refreshOpenApi = false, ?string $uatManifestPath = null): array
            {
                $this->calls[] = [
                    'output_root' => $outputRoot,
                    'spec_path' => $specPath,
                    'refresh_openapi' => $refreshOpenApi,
                    'uat_manifest_path' => $uatManifestPath,
                ];

                return [
                    'ok' => true,
                    'output_root' => $outputRoot ?? 'build/api-consumer',
                    'spec_path' => $specPath,
                    'summary' => [],
                ];
            }
        };

        $manifestService = new class extends ReleaseArtifactManifestService
        {
            /** @var array<string, mixed>|null */
            public ?array $writtenSnapshot = null;

            public function snapshot(): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'issues' => [],
                    'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                ];
            }

            public function writeSnapshot(?array $snapshot = null, ?string $relativePath = null): array
            {
                $this->writtenSnapshot = $snapshot ?? $this->snapshot();

                return $this->writtenSnapshot;
            }

            public function inspectFrozenSnapshot(?array $currentSnapshot = null, ?string $relativePath = null): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'issues' => [],
                    'path' => $relativePath ?? 'storage/app/booking_release/release_manifest_snapshot.json',
                ];
            }
        };

        $packageService = new class extends ReleasePackageService
        {
            /** @var list<array<string, mixed>> */
            public array $calls = [];

            public function __construct() {}

            public function package(?string $packageId = null, bool $verifyFrozen = false, bool $overwrite = false): array
            {
                $this->calls[] = [
                    'package_id' => $packageId,
                    'verify_frozen' => $verifyFrozen,
                    'overwrite' => $overwrite,
                ];

                return [
                    'ok' => true,
                    'status' => 'ok',
                    'package_id' => $packageId ?? 'generated',
                    'package_path' => 'build/booking-release/restaurantpos-backend-release-generated.tar.gz',
                    'issues' => [],
                    'warnings' => [],
                ];
            }
        };

        $harnessService = new class extends HarnessSuiteService
        {
            public function __construct() {}

            public function buildReleaseBuildContext(?string $manifestPath = null, array $feContractPayload = []): array
            {
                return [
                    'ok' => true,
                    'web_auth' => [
                        'ok' => true,
                        'headers' => [
                            'customer_auth' => 'X-Customer-Token',
                            'staff_auth' => 'X-Staff-Key',
                            'session' => 'X-Session-Id',
                        ],
                        'checks' => [],
                        'verify' => [],
                    ],
                    'fe_contract' => [
                        'ok' => true,
                        'spec_path' => (string) ($feContractPayload['spec_path'] ?? ''),
                        'output_root' => (string) ($feContractPayload['output_root'] ?? ''),
                        'summary' => (array) ($feContractPayload['summary'] ?? []),
                        'artifacts' => [],
                    ],
                    'golden_flows' => [
                        'manifest_path' => $manifestPath,
                        'manifest_available' => true,
                        'scenario_count' => 5,
                        'scenario_keys' => [
                            'customer_reservation_journey',
                            'deposit_self_pay',
                            'dine_in_checkout',
                            'refund_path',
                            'waiting_list_roundtrip',
                        ],
                        'runtime_gate_commands' => [
                            'php artisan booking:deploy-check --mode=preflight',
                        ],
                        'notes' => [],
                    ],
                    'recommended_commands' => [
                        'php artisan booking:harness:web-auth --json',
                        'php artisan booking:harness:golden-flows --json --manifest-path=storage/app/uat/scenario-pack.json',
                    ],
                ];
            }
        };

        $service = new ReleaseBuildService(
            $openApiService,
            $apiArtifactService,
            $manifestService,
            $packageService,
            $harnessService,
        );

        $report = $service->build(
            packageId: 'manual-test',
            overwrite: true,
            uatManifestPath: 'storage/app/uat/scenario-pack.json',
        );

        $this->assertTrue($report['ok']);
        $this->assertSame([
            'php artisan booking:api-contract --write',
            'php artisan booking:api-artifacts:generate',
            'php artisan booking:release-manifest --write',
            'php artisan booking:package-release --verify-frozen',
        ], $report['canonical_path']);
        $this->assertCount(1, $apiArtifactService->calls);
        $this->assertSame('storage/app/booking_release/openapi-v1.json', $apiArtifactService->calls[0]['spec_path']);
        $this->assertFalse((bool) $apiArtifactService->calls[0]['refresh_openapi']);
        $this->assertSame('storage/app/uat/scenario-pack.json', $apiArtifactService->calls[0]['uat_manifest_path']);
        $this->assertCount(1, $packageService->calls);
        $this->assertSame('manual-test', $packageService->calls[0]['package_id']);
        $this->assertTrue((bool) $packageService->calls[0]['verify_frozen']);
        $this->assertTrue((bool) $packageService->calls[0]['overwrite']);
        $this->assertTrue((bool) data_get($report, 'harness.web_auth.ok'));
        $this->assertSame(5, data_get($report, 'harness.golden_flows.scenario_count'));
        $this->assertContains(
            'php artisan booking:harness:web-auth --json',
            (array) data_get($report, 'harness.recommended_commands', [])
        );
    }

    public function test_build_stops_before_packaging_when_release_manifest_step_fails(): void
    {
        $openApiService = new class extends OpenApiSpecService
        {
            public function __construct() {}

            public function export(?string $relativePath = null): array
            {
                return [
                    'spec' => ['openapi' => '3.1.0'],
                    'report' => ['summary' => ['path_count' => 1]],
                    'path' => $relativePath ?? 'storage/app/booking_release/openapi-v1.json',
                ];
            }
        };

        $apiArtifactService = new class extends ApiConsumerArtifactService
        {
            public function __construct() {}

            public function generate(?string $outputRoot = null, ?string $specPath = null, bool $refreshOpenApi = false, ?string $uatManifestPath = null): array
            {
                return [
                    'ok' => true,
                    'output_root' => $outputRoot ?? 'build/api-consumer',
                    'spec_path' => $specPath,
                    'summary' => [],
                ];
            }
        };

        $manifestService = new class extends ReleaseArtifactManifestService
        {
            public function snapshot(): array
            {
                return [
                    'ok' => false,
                    'status' => 'fail',
                    'issues' => ['Release package is missing build/api-consumer.'],
                    'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                ];
            }

            public function writeSnapshot(?array $snapshot = null, ?string $relativePath = null): array
            {
                return $snapshot ?? $this->snapshot();
            }

            public function inspectFrozenSnapshot(?array $currentSnapshot = null, ?string $relativePath = null): array
            {
                return [
                    'ok' => false,
                    'status' => 'stale',
                    'issues' => ['Frozen release manifest snapshot verification failed with status [stale].'],
                    'path' => $relativePath ?? 'storage/app/booking_release/release_manifest_snapshot.json',
                ];
            }
        };

        $packageService = new class extends ReleasePackageService
        {
            public int $callCount = 0;

            public function __construct() {}

            public function package(?string $packageId = null, bool $verifyFrozen = false, bool $overwrite = false): array
            {
                $this->callCount++;

                return [
                    'ok' => true,
                    'status' => 'ok',
                    'package_id' => $packageId ?? 'generated',
                    'package_path' => 'build/booking-release/restaurantpos-backend-release-generated.tar.gz',
                    'issues' => [],
                    'warnings' => [],
                ];
            }
        };

        $harnessService = new class extends HarnessSuiteService
        {
            public function __construct() {}

            public function buildReleaseBuildContext(?string $manifestPath = null, array $feContractPayload = []): array
            {
                return [
                    'ok' => false,
                    'web_auth' => [
                        'ok' => false,
                        'headers' => [],
                        'checks' => [],
                        'verify' => [],
                    ],
                    'fe_contract' => [
                        'ok' => true,
                        'spec_path' => (string) ($feContractPayload['spec_path'] ?? ''),
                        'output_root' => (string) ($feContractPayload['output_root'] ?? ''),
                        'summary' => [],
                        'artifacts' => [],
                    ],
                    'golden_flows' => [
                        'manifest_path' => $manifestPath,
                        'manifest_available' => false,
                        'scenario_count' => 5,
                        'scenario_keys' => [],
                        'runtime_gate_commands' => [],
                        'notes' => ['no manifest path supplied; scenario definitions are reported without resolved UAT identifiers'],
                    ],
                    'recommended_commands' => [],
                ];
            }
        };

        $service = new ReleaseBuildService(
            $openApiService,
            $apiArtifactService,
            $manifestService,
            $packageService,
            $harnessService,
        );

        $report = $service->build(uatManifestPath: 'storage/app/uat/scenario-pack.json');

        $this->assertFalse($report['ok']);
        $this->assertNull($report['package']);
        $this->assertSame(0, $packageService->callCount);
        $this->assertContains('Release package is missing build/api-consumer.', $report['issues']);
        $this->assertContains('Frozen release manifest snapshot verification failed with status [stale].', $report['issues']);
        $this->assertContains('Web auth/session harness failed one or more error-grade checks.', $report['issues']);
        $this->assertContains(
            'Golden flow harness: no manifest path supplied; scenario definitions are reported without resolved UAT identifiers',
            $report['warnings']
        );
    }
}
