<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\Release\Services\ReleasePackageService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingPackageReleaseCommandTest extends TestCase
{
    public function test_booking_package_release_supports_json_output(): void
    {
        $service = new class extends ReleasePackageService
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
                    'package_sha256' => str_repeat('a', 64),
                    'package_bytes' => 1234,
                    'output_root' => 'build/booking-release',
                    'stage_path' => 'build/booking-release/stage/restaurantpos-backend-release-generated',
                    'include_roots' => [
                        ['path' => 'app', 'required' => true, 'type' => 'directory'],
                    ],
                    'skipped_optional_paths' => [],
                    'sidecars' => [
                        'metadata_path' => 'build/booking-release/pkg.metadata.json',
                        'inventory_path' => 'build/booking-release/pkg.inventory.json',
                        'checksums_path' => 'build/booking-release/pkg.checksums.sha256',
                        'package_sha256_path' => 'build/booking-release/pkg.package.sha256',
                        'latest_pointer_path' => 'build/booking-release/latest-package.json',
                    ],
                    'inventory' => [
                        'file_count' => 5,
                        'total_bytes' => 9876,
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
        };
        $this->app->instance(ReleasePackageService::class, $service);

        $exitCode = Artisan::call('booking:package-release', [
            '--json' => true,
            '--verify-frozen' => true,
            '--package-id' => 'manual-test',
            '--overwrite' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"package_id": "manual-test"', $output);
        $this->assertStringContainsString('"package_path": "build/booking-release/restaurantpos-backend-release-generated.tar.gz"', $output);
        $this->assertStringContainsString('"sidecars":', $output);
    }

    public function test_booking_package_release_fails_in_table_mode_when_packaging_fails(): void
    {
        $service = new class extends ReleasePackageService
        {
            public function __construct() {}

            public function package(?string $packageId = null, bool $verifyFrozen = false, bool $overwrite = false): array
            {
                return [
                    'ok' => false,
                    'status' => 'fail',
                    'package_id' => 'manual-test',
                    'package_basename' => 'restaurantpos-backend-release-manual-test',
                    'package_path' => 'build/booking-release/restaurantpos-backend-release-manual-test.tar.gz',
                    'package_exists' => false,
                    'output_root' => 'build/booking-release',
                    'stage_path' => 'build/booking-release/stage/restaurantpos-backend-release-manual-test',
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
                            'status' => 'stale',
                            'path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                        ],
                    ],
                    'issues' => ['Frozen release manifest snapshot verification failed with status [stale].'],
                    'warnings' => [],
                ];
            }
        };
        $this->app->instance(ReleasePackageService::class, $service);

        $exitCode = Artisan::call('booking:package-release', ['--verify-frozen' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('booking:package-release failed.', $output);
        $this->assertStringContainsString('Frozen release manifest snapshot verification failed with status [stale].', $output);
    }
}
