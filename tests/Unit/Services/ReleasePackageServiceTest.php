<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ReleaseArtifactManifestService;
use App\Services\ReleasePackageService;
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
    }

    public function test_package_fails_when_frozen_manifest_is_stale_and_verification_is_required(): void
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

        $report = app(ReleasePackageService::class)->package('stale-test', verifyFrozen: true, overwrite: true);

        $this->assertFalse($report['ok']);
        $this->assertSame('fail', $report['status']);
        $this->assertStringContainsString('Frozen release manifest snapshot verification failed', implode(' | ', $report['issues']));
        $this->assertFalse(File::exists(base_path($report['package_path'])));
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
}
