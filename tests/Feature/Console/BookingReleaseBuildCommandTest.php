<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\ReleaseBuildService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingReleaseBuildCommandTest extends TestCase
{
    public function test_booking_release_build_supports_json_output(): void
    {
        $service = new class extends ReleaseBuildService
        {
            public function __construct() {}

            public function build(?string $packageId = null, bool $overwrite = false, ?string $uatManifestPath = null): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'canonical_path' => [
                        'php artisan booking:api-contract --write',
                        'php artisan booking:api-artifacts:generate',
                        'php artisan booking:release-manifest --write',
                        'php artisan booking:package-release --verify-frozen',
                    ],
                    'openapi' => [
                        'path' => 'storage/app/booking_release/openapi-v1.json',
                    ],
                    'api_artifacts' => [
                        'output_root' => 'build/api-consumer',
                    ],
                    'harness' => [
                        'web_auth' => [
                            'ok' => true,
                        ],
                        'golden_flows' => [
                            'scenario_count' => 5,
                            'manifest_available' => true,
                        ],
                        'recommended_commands' => [
                            'php artisan booking:harness:web-auth --json',
                            'php artisan booking:harness:golden-flows --json --manifest-path=storage/app/uat/scenario-pack.json',
                        ],
                    ],
                    'release_manifest' => [
                        'snapshot' => [
                            'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                        ],
                        'frozen_snapshot' => [
                            'status' => 'ok',
                        ],
                    ],
                    'package' => [
                        'package_id' => $packageId ?? 'generated',
                        'package_path' => 'build/booking-release/restaurantpos-backend-release-generated.tar.gz',
                    ],
                    'issues' => [],
                    'warnings' => [],
                    'meta' => [
                        'overwrite_requested' => $overwrite,
                        'uat_manifest_path' => $uatManifestPath,
                    ],
                ];
            }
        };
        $this->app->instance(ReleaseBuildService::class, $service);

        $exitCode = Artisan::call('booking:release-build', [
            '--json' => true,
            '--package-id' => 'manual-test',
            '--overwrite' => true,
            '--uat-manifest' => 'storage/app/uat/scenario-pack.json',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"package_id": "manual-test"', $output);
        $this->assertStringContainsString('"canonical_path"', $output);
        $this->assertStringContainsString('"output_root": "build/api-consumer"', $output);
        $this->assertStringContainsString('"harness"', $output);
        $this->assertStringContainsString('"scenario_count": 5', $output);
    }

    public function test_booking_release_build_fails_in_table_mode_when_pipeline_fails(): void
    {
        $service = new class extends ReleaseBuildService
        {
            public function __construct() {}

            public function build(?string $packageId = null, bool $overwrite = false, ?string $uatManifestPath = null): array
            {
                return [
                    'ok' => false,
                    'status' => 'fail',
                    'canonical_path' => [
                        'php artisan booking:api-contract --write',
                        'php artisan booking:api-artifacts:generate',
                        'php artisan booking:release-manifest --write',
                        'php artisan booking:package-release --verify-frozen',
                    ],
                    'openapi' => [
                        'path' => 'storage/app/booking_release/openapi-v1.json',
                    ],
                    'api_artifacts' => [
                        'output_root' => 'build/api-consumer',
                    ],
                    'harness' => [
                        'web_auth' => [
                            'ok' => false,
                        ],
                        'golden_flows' => [
                            'scenario_count' => 5,
                            'manifest_available' => false,
                        ],
                        'recommended_commands' => [
                            'php artisan booking:harness:web-auth --json',
                        ],
                    ],
                    'release_manifest' => [
                        'snapshot' => [
                            'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                        ],
                        'frozen_snapshot' => [
                            'status' => 'stale',
                        ],
                    ],
                    'package' => null,
                    'issues' => [
                        'Frozen release manifest snapshot verification failed with status [stale].',
                    ],
                    'warnings' => [],
                    'meta' => [],
                ];
            }
        };
        $this->app->instance(ReleaseBuildService::class, $service);

        $exitCode = Artisan::call('booking:release-build');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('booking:release-build failed.', $output);
        $this->assertStringContainsString('Frozen release manifest snapshot verification failed with status [stale].', $output);
        $this->assertStringContainsString('web_auth_harness_ok', $output);
        $this->assertStringContainsString('Recommended release gates', $output);
    }
}
