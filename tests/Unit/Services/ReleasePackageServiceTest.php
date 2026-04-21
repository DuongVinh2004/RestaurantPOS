<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\Release\Services\ReleaseArtifactManifestService;
use App\Platform\Release\Services\ReleasePackageService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleasePackageServiceTest extends TestCase
{
    private string $root = 'storage/framework/testing/release_package';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->root));
        parent::tearDown();
    }

    public function test_package_builds_immutable_tar_gz_with_sidecars_when_frozen_manifest_is_fresh(): void
    {
        $schemaPath = base_path($this->root.'/schema.sql');
        $packageFile = base_path($this->root.'/src/release.txt');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::ensureDirectoryExists(dirname($packageFile));
        File::put($schemaPath, "alpha\nbeta\n");
        File::put($packageFile, "release-payload\n");

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root.'/schema.sql',
                'optional' => false,
                'required_fragments' => ['alpha', 'beta'],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', []);
        config()->set('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        config()->set('booking_release.release_manifest.snapshot_path', $this->root.'/release_manifest_snapshot.json');
        config()->set('booking_release.packaging.output_root', $this->root.'/build');
        config()->set('booking_release.packaging.package_prefix', 'booking-test-release');
        config()->set('booking_release.packaging.include_paths', [
            ['path' => $this->root.'/src/release.txt', 'required' => true],
        ]);
        config()->set('booking_release.packaging.sidecars.latest_pointer_path', $this->root.'/build/latest-package.json');

        $manifestService = app(ReleaseArtifactManifestService::class);
        $snapshot = $manifestService->snapshot();
        $manifestService->writeSnapshot($snapshot);

        $report = app(ReleasePackageService::class)->package('unit-test', verifyFrozen: true, overwrite: true);

        $this->assertTrue($report['ok']);
        $this->assertSame('ok', $report['status']);
        $this->assertSame($this->root.'/build/booking-test-release-unit-test.tar.gz', $report['package_path']);
        $this->assertFileExists(base_path($report['package_path']));
        $this->assertFileExists(base_path($report['sidecars']['metadata_path']));
        $this->assertFileExists(base_path($report['sidecars']['inventory_path']));
        $this->assertFileExists(base_path($report['sidecars']['checksums_path']));
        $this->assertFileExists(base_path($report['sidecars']['package_sha256_path']));
        $this->assertSame('ok', $report['release_manifest']['frozen_snapshot']['status']);
        $this->assertGreaterThan(0, (int) ($report['inventory']['file_count'] ?? 0));
        $this->assertDirectoryDoesNotExist(base_path($report['stage_path']));
    }

    public function test_package_fails_when_frozen_manifest_is_stale_even_without_explicit_verify_flag(): void
    {
        $schemaPath = base_path($this->root.'/schema.sql');
        $packageFile = base_path($this->root.'/src/release.txt');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::ensureDirectoryExists(dirname($packageFile));
        File::put($schemaPath, "alpha\nbeta\n");
        File::put($packageFile, "release-payload\n");

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root.'/schema.sql',
                'optional' => false,
                'required_fragments' => ['alpha', 'beta'],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', []);
        config()->set('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        config()->set('booking_release.release_manifest.snapshot_path', $this->root.'/release_manifest_snapshot.json');
        config()->set('booking_release.packaging.output_root', $this->root.'/build');
        config()->set('booking_release.packaging.package_prefix', 'booking-test-release');
        config()->set('booking_release.packaging.include_paths', [
            ['path' => $this->root.'/src/release.txt', 'required' => true],
        ]);
        config()->set('booking_release.packaging.sidecars.latest_pointer_path', $this->root.'/build/latest-package.json');

        $manifestService = app(ReleaseArtifactManifestService::class);
        $snapshot = $manifestService->snapshot();
        $manifestService->writeSnapshot($snapshot);
        File::put($schemaPath, "alpha\nbeta\ngamma\n");

        $report = app(ReleasePackageService::class)->package('stale-test', overwrite: true);

        $this->assertFalse($report['ok']);
        $this->assertSame('fail', $report['status']);
        $this->assertStringContainsString('Frozen release manifest snapshot verification failed', implode(' | ', $report['issues']));
        $this->assertFalse(File::exists(base_path($report['package_path'])));
    }

    public function test_package_rebuild_is_byte_stable_for_same_package_id_and_frozen_manifest(): void
    {
        $schemaPath = base_path($this->root.'/schema.sql');
        $packageFile = base_path($this->root.'/src/release.txt');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::ensureDirectoryExists(dirname($packageFile));
        File::put($schemaPath, "alpha\nbeta\n");
        File::put($packageFile, "release-payload\n");

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root.'/schema.sql',
                'optional' => false,
                'required_fragments' => ['alpha', 'beta'],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', []);
        config()->set('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        config()->set('booking_release.release_manifest.snapshot_path', $this->root.'/release_manifest_snapshot.json');
        config()->set('booking_release.packaging.output_root', $this->root.'/build');
        config()->set('booking_release.packaging.package_prefix', 'booking-test-release');
        config()->set('booking_release.packaging.include_paths', [
            ['path' => $this->root.'/src/release.txt', 'required' => true],
        ]);
        config()->set('booking_release.packaging.sidecars.latest_pointer_path', $this->root.'/build/latest-package.json');

        $manifestService = app(ReleaseArtifactManifestService::class);
        $snapshot = $manifestService->snapshot();
        $manifestService->writeSnapshot($snapshot);

        $first = app(ReleasePackageService::class)->package('stable-test', verifyFrozen: true, overwrite: true);
        sleep(1);
        $second = app(ReleasePackageService::class)->package('stable-test', verifyFrozen: true, overwrite: true);

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertSame($first['package_sha256'], $second['package_sha256']);
    }

    public function test_package_uses_configured_build_metadata_for_sidecars_and_default_package_ids(): void
    {
        $schemaPath = base_path($this->root.'/schema.sql');
        $packageFile = base_path($this->root.'/src/release.txt');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::ensureDirectoryExists(dirname($packageFile));
        File::put($schemaPath, "alpha\nbeta\n");
        File::put($packageFile, "release-payload\n");

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root.'/schema.sql',
                'optional' => false,
                'required_fragments' => ['alpha', 'beta'],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', []);
        config()->set('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        config()->set('booking_release.release_manifest.snapshot_path', $this->root.'/release_manifest_snapshot.json');
        config()->set('booking_release.build_metadata', [
            'commit_sha' => 'abcdef1234567890',
            'ref_name' => 'refs/heads/release/demo',
            'run_id' => 'run-123',
        ]);
        config()->set('booking_release.packaging.output_root', $this->root.'/build');
        config()->set('booking_release.packaging.package_prefix', 'booking-test-release');
        config()->set('booking_release.packaging.include_paths', [
            ['path' => $this->root.'/src/release.txt', 'required' => true],
        ]);
        config()->set('booking_release.packaging.sidecars.latest_pointer_path', $this->root.'/build/latest-package.json');

        $manifestService = app(ReleaseArtifactManifestService::class);
        $snapshot = $manifestService->snapshot();
        $manifestService->writeSnapshot($snapshot);

        $report = app(ReleasePackageService::class)->package(null, verifyFrozen: true, overwrite: true);

        $this->assertTrue($report['ok']);
        $this->assertMatchesRegularExpression(
            '#booking-test-release-\d{8}t\d{6}z-\d{6}-abcdef123456\.tar\.gz$#',
            (string) ($report['package_path'] ?? '')
        );
        $this->assertStringEndsWith('-abcdef123456.tar.gz', (string) ($report['package_path'] ?? ''));

        $metadata = json_decode(
            (string) File::get(base_path((string) ($report['sidecars']['metadata_path'] ?? ''))),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('abcdef1234567890', $metadata['build']['commit_sha'] ?? null);
        $this->assertSame('refs/heads/release/demo', $metadata['build']['ref_name'] ?? null);
        $this->assertSame('run-123', $metadata['build']['run_id'] ?? null);
    }

    public function test_package_fails_cleanly_when_the_same_package_id_is_already_locked(): void
    {
        $schemaPath = base_path($this->root.'/schema.sql');
        $packageFile = base_path($this->root.'/src/release.txt');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::ensureDirectoryExists(dirname($packageFile));
        File::put($schemaPath, "alpha\nbeta\n");
        File::put($packageFile, "release-payload\n");

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root.'/schema.sql',
                'optional' => false,
                'required_fragments' => ['alpha', 'beta'],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', []);
        config()->set('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        config()->set('booking_release.release_manifest.snapshot_path', $this->root.'/release_manifest_snapshot.json');
        config()->set('booking_release.packaging.output_root', $this->root.'/build');
        config()->set('booking_release.packaging.package_prefix', 'booking-test-release');
        config()->set('booking_release.packaging.include_paths', [
            ['path' => $this->root.'/src/release.txt', 'required' => true],
        ]);
        config()->set('booking_release.packaging.sidecars.latest_pointer_path', $this->root.'/build/latest-package.json');

        $manifestService = app(ReleaseArtifactManifestService::class);
        $snapshot = $manifestService->snapshot();
        $manifestService->writeSnapshot($snapshot);

        $lockPath = base_path($this->root.'/build/locks/booking-test-release-locked-test.lock');
        File::ensureDirectoryExists(dirname($lockPath));
        mkdir($lockPath);

        $report = app(ReleasePackageService::class)->package('locked-test', verifyFrozen: true, overwrite: true);

        $this->assertFalse($report['ok']);
        $this->assertSame('fail', $report['status']);
        $this->assertStringContainsString('already being built by another process', implode(' | ', $report['issues']));
        $this->assertFalse(File::exists(base_path($report['package_path'])));
    }

    public function test_package_fails_early_when_generated_consumer_artifacts_are_stale(): void
    {
        $openApiPath = base_path($this->root.'/openapi-v1.json');
        $sdkPath = base_path($this->root.'/restaurantpos-sdk.ts');
        $manifestPath = base_path($this->root.'/release_manifest_snapshot.json');
        $packageFile = base_path($this->root.'/src/release.txt');
        File::ensureDirectoryExists(dirname($openApiPath));
        File::ensureDirectoryExists(dirname($packageFile));
        File::put($openApiPath, "{\"openapi\":\"3.1.0\"}\n");
        File::put($sdkPath, "export class RestaurantPosClient {}\n");
        File::put($manifestPath, "{\"artifacts\":{}}\n");
        File::put($packageFile, "release-payload\n");

        $baseTime = time();
        touch($sdkPath, $baseTime - 20);
        touch($openApiPath, $baseTime - 10);
        touch($manifestPath, $baseTime - 30);
        clearstatcache();

        config()->set('booking_release.artifacts', [
            'openapi_v1_spec' => [
                'path' => $this->root.'/openapi-v1.json',
                'optional' => false,
                'required_fragments' => ['"openapi":"3.1.0"'],
            ],
            'api_consumer_sdk_typescript' => [
                'path' => $this->root.'/restaurantpos-sdk.ts',
                'optional' => false,
                'required_fragments' => ['RestaurantPosClient'],
            ],
            'release_manifest_snapshot' => [
                'path' => $this->root.'/release_manifest_snapshot.json',
                'optional' => false,
                'required_fragments' => ['"artifacts"'],
            ],
        ]);
        config()->set('booking_release.artifact_freshness', [
            'api_consumer_sdk_typescript' => ['openapi_v1_spec'],
        ]);
        config()->set('booking_release.required_sql_patches', []);
        config()->set('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        config()->set('booking_release.release_manifest.snapshot_path', $this->root.'/release_manifest_snapshot.json');
        config()->set('booking_release.packaging.output_root', $this->root.'/build');
        config()->set('booking_release.packaging.package_prefix', 'booking-test-release');
        config()->set('booking_release.packaging.include_paths', [
            ['path' => $this->root.'/src/release.txt', 'required' => true],
        ]);
        config()->set('booking_release.packaging.sidecars.latest_pointer_path', $this->root.'/build/latest-package.json');

        $report = app(ReleasePackageService::class)->package('stale-artifacts', overwrite: true);

        $this->assertFalse($report['ok']);
        $this->assertSame('fail', $report['status']);
        $this->assertStringContainsString(
            'Generated artifact '.$this->root.'/restaurantpos-sdk.ts is stale relative to '.$this->root.'/openapi-v1.json.',
            implode(' | ', $report['issues'])
        );
        $this->assertFalse(File::exists(base_path($report['package_path'])));
    }

    public function test_package_skips_staff_web_install_artifacts_when_staging_release_roots(): void
    {
        $schemaPath = base_path($this->root.'/schema.sql');
        $packageFile = base_path($this->root.'/src/release.txt');
        $staffWebSource = base_path($this->root.'/staff-web/src/main.tsx');
        $staffWebNodeModule = base_path($this->root.'/staff-web/node_modules/example/index.js');
        $staffWebDist = base_path($this->root.'/staff-web/dist/assets/app.js');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::ensureDirectoryExists(dirname($packageFile));
        File::ensureDirectoryExists(dirname($staffWebSource));
        File::ensureDirectoryExists(dirname($staffWebNodeModule));
        File::ensureDirectoryExists(dirname($staffWebDist));
        File::put($schemaPath, "alpha\nbeta\n");
        File::put($packageFile, "release-payload\n");
        File::put($staffWebSource, "export const app = true;\n");
        File::put($staffWebNodeModule, "module.exports = true;\n");
        File::put($staffWebDist, "console.log('built');\n");

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root.'/schema.sql',
                'optional' => false,
                'required_fragments' => ['alpha', 'beta'],
            ],
        ]);
        config()->set('booking_release.artifact_freshness', []);
        config()->set('booking_release.required_sql_patches', []);
        config()->set('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        config()->set('booking_release.release_manifest.snapshot_path', $this->root.'/release_manifest_snapshot.json');
        config()->set('booking_release.packaging.output_root', $this->root.'/build');
        config()->set('booking_release.packaging.package_prefix', 'booking-test-release');
        config()->set('booking_release.packaging.exclude_paths', [
            $this->root.'/staff-web/node_modules',
            $this->root.'/staff-web/dist',
        ]);
        config()->set('booking_release.packaging.include_paths', [
            ['path' => $this->root.'/src/release.txt', 'required' => true],
            ['path' => $this->root.'/staff-web', 'required' => true],
        ]);
        config()->set('booking_release.packaging.sidecars.latest_pointer_path', $this->root.'/build/latest-package.json');

        $manifestService = app(ReleaseArtifactManifestService::class);
        $snapshot = $manifestService->snapshot();
        $manifestService->writeSnapshot($snapshot);

        $report = app(ReleasePackageService::class)->package('exclude-install-artifacts', overwrite: true);

        $this->assertTrue($report['ok']);

        $inventory = json_decode(
            (string) File::get(base_path((string) ($report['sidecars']['inventory_path'] ?? ''))),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $paths = collect((array) ($inventory['entries'] ?? []))
            ->pluck('source_path')
            ->values()
            ->all();

        $this->assertContains($this->root.'/staff-web/src/main.tsx', $paths);
        $this->assertNotContains($this->root.'/staff-web/node_modules/example/index.js', $paths);
        $this->assertNotContains($this->root.'/staff-web/dist/assets/app.js', $paths);
    }

    public function test_package_prunes_older_package_sets_to_the_retention_limit(): void
    {
        $schemaPath = base_path($this->root.'/schema.sql');
        $packageFile = base_path($this->root.'/src/release.txt');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::ensureDirectoryExists(dirname($packageFile));
        File::put($schemaPath, "alpha\nbeta\n");
        File::put($packageFile, "release-payload\n");

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root.'/schema.sql',
                'optional' => false,
                'required_fragments' => ['alpha', 'beta'],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', []);
        config()->set('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        config()->set('booking_release.release_manifest.snapshot_path', $this->root.'/release_manifest_snapshot.json');
        config()->set('booking_release.packaging.output_root', $this->root.'/build');
        config()->set('booking_release.packaging.package_prefix', 'booking-test-release');
        config()->set('booking_release.packaging.retained_package_sets', 2);
        config()->set('booking_release.packaging.include_paths', [
            ['path' => $this->root.'/src/release.txt', 'required' => true],
        ]);
        config()->set('booking_release.packaging.sidecars.latest_pointer_path', $this->root.'/build/latest-package.json');

        $manifestService = app(ReleaseArtifactManifestService::class);
        $snapshot = $manifestService->snapshot();
        $manifestService->writeSnapshot($snapshot);

        $first = app(ReleasePackageService::class)->package('retention-001', verifyFrozen: true, overwrite: true);
        $second = app(ReleasePackageService::class)->package('retention-002', verifyFrozen: true, overwrite: true);
        $third = app(ReleasePackageService::class)->package('retention-003', verifyFrozen: true, overwrite: true);

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertTrue($third['ok']);
        $this->assertFileDoesNotExist(base_path($first['package_path']));
        $this->assertFileDoesNotExist(base_path((string) ($first['sidecars']['metadata_path'] ?? '')));
        $this->assertFileExists(base_path($second['package_path']));
        $this->assertFileExists(base_path($third['package_path']));

        $latestPointer = json_decode(
            (string) File::get(base_path($this->root.'/build/latest-package.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('retention-003', $latestPointer['package_id'] ?? null);

        $retainedPackageFiles = collect(File::files(base_path($this->root.'/build')))
            ->map(static fn (\SplFileInfo $file): string => $file->getFilename())
            ->filter(static fn (string $filename): bool => str_starts_with($filename, 'booking-test-release-retention-'))
            ->values()
            ->all();

        $this->assertCount(10, $retainedPackageFiles);
        $this->assertContains('booking-test-release-retention-002.tar.gz', $retainedPackageFiles);
        $this->assertContains('booking-test-release-retention-003.tar.gz', $retainedPackageFiles);
        $this->assertNotContains('booking-test-release-retention-001.tar.gz', $retainedPackageFiles);
    }
}
