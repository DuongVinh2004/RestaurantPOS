<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Services\ReleaseArtifactManifestService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleaseArtifactBundlePresenceTest extends TestCase
{
    public function test_frozen_release_snapshots_are_present_in_source_bundle(): void
    {
        $this->assertFalse((bool) config('booking_release.artifacts.round5_gate_snapshot.optional', true));
        $this->assertSame('storage/app/booking_release/release_manifest_snapshot.json', (string) config('booking_release.release_manifest.snapshot_path'));

        $roundFiveSnapshotPath = base_path('storage/app/booking_release/round5_gate_snapshot.json');
        $releaseManifestSnapshotPath = base_path('storage/app/booking_release/release_manifest_snapshot.json');
        $collectionPath = base_path('build/api-consumer/postman/RestaurantPOS.postman_collection.json');
        $sdkPath = base_path('build/api-consumer/sdk/typescript/restaurantpos-sdk.ts');
        $mutationContractPath = base_path('build/api-consumer/mutation-contracts.md');

        $this->assertFileExists($roundFiveSnapshotPath);
        $this->assertFileExists($releaseManifestSnapshotPath);
        $this->assertFileExists($collectionPath);
        $this->assertFileExists($sdkPath);
        $this->assertFileExists($mutationContractPath);

        $roundFiveSnapshot = (string) File::get($roundFiveSnapshotPath);
        $releaseManifestSnapshot = (string) File::get($releaseManifestSnapshotPath);
        $collection = (string) File::get($collectionPath);
        $sdk = (string) File::get($sdkPath);
        $mutationContract = (string) File::get($mutationContractPath);

        $this->assertStringContainsString('"suite": "round5"', $roundFiveSnapshot);
        $this->assertStringContainsString('"summary"', $roundFiveSnapshot);
        $this->assertStringContainsString('"tests"', $roundFiveSnapshot);

        $this->assertStringContainsString('"definition_path": "config/booking_release.php"', $releaseManifestSnapshot);
        $this->assertStringContainsString('"snapshot_path": "storage/app/booking_release/release_manifest_snapshot.json"', $releaseManifestSnapshot);
        $this->assertStringContainsString('"artifacts"', $releaseManifestSnapshot);
        $this->assertStringContainsString('"api_consumer_collection"', $releaseManifestSnapshot);
        $this->assertStringContainsString('"api_consumer_sdk_typescript"', $releaseManifestSnapshot);
        $this->assertStringContainsString('"api_consumer_mutation_contract"', $releaseManifestSnapshot);
        $this->assertStringContainsString('RestaurantPOS API Consumer Collection', $collection);
        $this->assertStringContainsString('export class RestaurantPosClient', $sdk);
        $this->assertStringContainsString('# RestaurantPOS Mutation Contract Matrix', $mutationContract);
    }

    public function test_frozen_release_manifest_snapshot_matches_the_live_release_bundle(): void
    {
        $report = app(ReleaseArtifactManifestService::class)->inspectFrozenSnapshot();

        $this->assertTrue($report['ok']);
        $this->assertSame('ok', $report['status']);
        $this->assertSame([], $report['issues']);
        $this->assertSame([], $report['mismatch_paths']);
        $this->assertSame(
            'storage/app/booking_release/release_manifest_snapshot.json',
            (string) ($report['path'] ?? '')
        );
    }
}
