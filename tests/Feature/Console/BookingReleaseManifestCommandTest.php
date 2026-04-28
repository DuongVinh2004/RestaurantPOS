<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\Release\Services\ReleaseArtifactManifestService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BookingReleaseManifestCommandTest extends TestCase
{
    private string $root = 'storage/framework/testing/release_manifest_command';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking_ops_artifacts.release_manifest.artifact_root', $this->root.'/reports_bundle');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->root));
        parent::tearDown();
    }

    public function test_booking_release_manifest_supports_json_output(): void
    {
        $this->app->instance(ReleaseArtifactManifestService::class, new class extends ReleaseArtifactManifestService
        {
            public function snapshot(): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'issues' => [],
                    'artifacts' => [
                        'schema_dump' => [
                            'path' => 'database/schema/mysql-schema.sql',
                            'exists' => true,
                            'optional' => false,
                            'sha256' => str_repeat('a', 64),
                            'bytes' => 123,
                            'line_count' => 4,
                            'missing_fragments' => [],
                            'required_fragment_count' => 2,
                        ],
                    ],
                    'patches' => [
                        'present' => ['2026_03_15_000022_staff_auth_and_integrity_hardening.sql'],
                        'required' => ['2026_03_15_000022_staff_auth_and_integrity_hardening.sql'],
                        'missing' => [],
                        'count' => 1,
                        'required_count' => 1,
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-03-15T12:00:00Z',
                    ],
                    'definition_path' => 'config/booking_release.php',
                    'definition_sha256' => str_repeat('b', 64),
                    'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                ];
            }
        });

        $exitCode = Artisan::call('booking:release-manifest', ['--json' => true]);
        $output = Artisan::output();
        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"status": "ok"', $output);
        $this->assertStringContainsString('"patches"', $output);
        $this->assertSame($this->root.'/reports_bundle/reports', $payload['report_artifacts']['reports_root'] ?? null);
        $this->assertFileExists(base_path((string) (($payload['report_artifacts'] ?? [])['json_path'] ?? '')));
        $this->assertFileExists(base_path((string) (($payload['report_artifacts'] ?? [])['markdown_path'] ?? '')));
    }

    public function test_booking_release_manifest_no_write_json_emits_payload_without_report_files(): void
    {
        $root = $this->root;
        $this->app->instance(ReleaseArtifactManifestService::class, new class($root) extends ReleaseArtifactManifestService
        {
            public function __construct(private readonly string $root) {}

            public function snapshot(): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'issues' => [],
                    'artifacts' => [
                        'schema_dump' => [
                            'path' => 'database/schema/mysql-schema.sql',
                            'exists' => true,
                            'optional' => false,
                            'sha256' => str_repeat('a', 64),
                            'bytes' => 123,
                            'line_count' => 4,
                            'missing_fragments' => [],
                            'required_fragment_count' => 2,
                        ],
                    ],
                    'patches' => [
                        'present' => ['2026_03_15_000022_staff_auth_and_integrity_hardening.sql'],
                        'required' => ['2026_03_15_000022_staff_auth_and_integrity_hardening.sql'],
                        'missing' => [],
                        'count' => 1,
                        'required_count' => 1,
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-03-15T12:00:00Z',
                    ],
                    'definition_path' => 'config/booking_release.php',
                    'definition_sha256' => str_repeat('b', 64),
                    'snapshot_path' => $this->root.'/release_manifest_snapshot.json',
                ];
            }
        });

        $exitCode = Artisan::call('booking:release-manifest', [
            '--json' => true,
            '--no-write' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status'] ?? null);
        $this->assertArrayHasKey('artifacts', $payload);
        $this->assertArrayHasKey('patches', $payload);
        $this->assertArrayHasKey('report_artifacts', $payload);
        $this->assertSame($this->root.'/reports_bundle/reports', $payload['report_artifacts']['reports_root'] ?? null);
        $this->assertNull($payload['report_artifacts']['json_path'] ?? null);
        $this->assertNull($payload['report_artifacts']['markdown_path'] ?? null);
        $this->assertFalse((bool) ($payload['report_artifacts']['written'] ?? true));
        $this->assertTrue((bool) ($payload['report_artifacts']['write_suppressed'] ?? false));
        $this->assertFalse((bool) ($payload['meta']['write_requested'] ?? true));
        $this->assertTrue((bool) ($payload['meta']['no_write_requested'] ?? false));
        $this->assertDirectoryDoesNotExist(base_path($this->root.'/reports_bundle/reports'));
        $this->assertFileDoesNotExist(base_path($this->root.'/release_manifest_snapshot.json'));
    }

    public function test_booking_release_manifest_supports_write_flag_and_persists_snapshot(): void
    {
        $root = $this->root;
        $this->app->instance(ReleaseArtifactManifestService::class, new class($root) extends ReleaseArtifactManifestService
        {
            public function __construct(private readonly string $root) {}

            public function snapshot(): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'issues' => [],
                    'artifacts' => [
                        'schema_dump' => [
                            'path' => $this->root.'/schema.sql',
                            'exists' => true,
                            'optional' => false,
                            'sha256' => str_repeat('b', 64),
                            'bytes' => 100,
                            'line_count' => 3,
                            'missing_fragments' => [],
                            'required_fragment_count' => 0,
                        ],
                    ],
                    'patches' => [
                        'present' => range(1, 36),
                        'required' => [],
                        'missing' => [],
                        'count' => 36,
                        'required_count' => 0,
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-03-15T12:00:00Z',
                    ],
                    'definition_path' => 'config/booking_release.php',
                    'definition_sha256' => str_repeat('c', 64),
                    'snapshot_path' => $this->root.'/release_manifest_snapshot.json',
                ];
            }

            public function writeSnapshot(?array $snapshot = null, ?string $relativePath = null): array
            {
                $snapshot ??= $this->snapshot();
                $relativePath ??= $this->root.'/release_manifest_snapshot.json';
                $absolutePath = base_path($relativePath);
                File::ensureDirectoryExists(dirname($absolutePath));
                File::put($absolutePath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return $snapshot;
            }
        });

        $snapshotPath = base_path($this->root.'/release_manifest_snapshot.json');

        $exitCode = Artisan::call('booking:release-manifest', ['--write' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($snapshotPath);
        $this->assertStringContainsString('Snapshot: '.$this->root.'/release_manifest_snapshot.json', Artisan::output());
    }

    public function test_release_manifest_write_refreshes_freshness_metadata_when_artifact_hashes_match(): void
    {
        config()->set('booking_release.artifact_freshness', [
            'api_consumer_mutation_contract' => ['openapi_v1_spec'],
        ]);

        $snapshotPath = $this->root.'/release_manifest_snapshot.json';
        $absoluteSnapshotPath = base_path($snapshotPath);
        $existingSnapshot = $this->freshnessMetadataSnapshot(openApiEpoch: 100, mutationEpoch: 90);
        $currentSnapshot = $this->freshnessMetadataSnapshot(openApiEpoch: 100, mutationEpoch: 100);
        $service = app(ReleaseArtifactManifestService::class);

        File::ensureDirectoryExists(dirname($absoluteSnapshotPath));
        File::put($absoluteSnapshotPath, json_encode($existingSnapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $writtenSnapshot = $service->writeSnapshot($currentSnapshot, $snapshotPath);
        $persistedSnapshot = json_decode((string) File::get($absoluteSnapshotPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(100, data_get($writtenSnapshot, 'artifacts.api_consumer_mutation_contract.modified_epoch'));
        $this->assertSame(100, data_get($persistedSnapshot, 'artifacts.api_consumer_mutation_contract.modified_epoch'));
    }

    public function test_booking_release_manifest_fails_when_snapshot_is_broken(): void
    {
        $this->app->instance(ReleaseArtifactManifestService::class, new class extends ReleaseArtifactManifestService
        {
            public function snapshot(): array
            {
                return [
                    'ok' => false,
                    'status' => 'fail',
                    'issues' => ['Required artifact database/schema/mysql-schema.sql is missing.'],
                    'artifacts' => [],
                    'patches' => [
                        'present' => [],
                        'required' => ['missing.sql'],
                        'missing' => ['missing.sql'],
                        'count' => 0,
                        'required_count' => 1,
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-03-15T12:00:00Z',
                    ],
                    'definition_path' => 'config/booking_release.php',
                    'definition_sha256' => str_repeat('d', 64),
                    'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                ];
            }
        });

        $exitCode = Artisan::call('booking:release-manifest');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('booking:release-manifest failed.', Artisan::output());
    }

    public function test_booking_release_manifest_can_verify_frozen_snapshot_and_fails_when_it_is_stale(): void
    {
        $this->app->instance(ReleaseArtifactManifestService::class, new class extends ReleaseArtifactManifestService
        {
            public function snapshot(): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'issues' => [],
                    'artifacts' => [],
                    'patches' => [
                        'present' => [],
                        'required' => [],
                        'missing' => [],
                        'count' => 0,
                        'required_count' => 0,
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-03-15T12:00:00Z',
                    ],
                    'definition_path' => 'config/booking_release.php',
                    'definition_sha256' => str_repeat('e', 64),
                    'snapshot_path' => 'storage/app/booking_release/release_manifest_snapshot.json',
                ];
            }

            public function inspectFrozenSnapshot(?array $currentSnapshot = null, ?string $relativePath = null): array
            {
                return [
                    'ok' => false,
                    'status' => 'stale',
                    'path' => $relativePath ?? 'storage/app/booking_release/release_manifest_snapshot.json',
                    'issues' => ['Release artifact manifest contents differ from the frozen snapshot.'],
                    'mismatch_paths' => ['artifacts'],
                ];
            }
        });

        $exitCode = Artisan::call('booking:release-manifest', [
            '--json' => true,
            '--verify-frozen' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('frozen_snapshot', $payload);
        $this->assertSame('stale', $payload['frozen_snapshot']['status'] ?? null);
        $this->assertTrue((bool) ($payload['meta']['verify_frozen_requested'] ?? false));
    }

    /**
     * @return array<string,mixed>
     */
    private function freshnessMetadataSnapshot(int $openApiEpoch, int $mutationEpoch): array
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
                    'sha256' => str_repeat('a', 64),
                    'bytes' => 100,
                    'line_count' => 5,
                    'modified_epoch' => $openApiEpoch,
                    'modified_at_utc' => '2026-04-24T07:00:00+00:00',
                    'required_fragment_count' => 0,
                    'missing_fragments' => [],
                ],
                'api_consumer_mutation_contract' => [
                    'path' => 'build/api-consumer/mutation-contracts.md',
                    'exists' => true,
                    'optional' => false,
                    'sha256' => str_repeat('b', 64),
                    'bytes' => 200,
                    'line_count' => 10,
                    'modified_epoch' => $mutationEpoch,
                    'modified_at_utc' => '2026-04-24T07:00:00+00:00',
                    'required_fragment_count' => 0,
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
                'generated_at_utc' => '2026-04-24T07:00:00+00:00',
            ],
            'definition_path' => 'config/booking_release.php',
            'definition_sha256' => str_repeat('c', 64),
            'snapshot_path' => $this->root.'/release_manifest_snapshot.json',
        ];
    }
}
