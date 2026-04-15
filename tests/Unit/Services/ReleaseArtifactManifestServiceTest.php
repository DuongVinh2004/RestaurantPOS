<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\Release\Services\ReleaseArtifactManifestService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleaseArtifactManifestServiceTest extends TestCase
{
    private string $root = 'storage/framework/testing/release_manifest';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->root));
        parent::tearDown();
    }

    public function test_snapshot_is_ok_when_required_artifacts_and_patches_are_present(): void
    {
        $schemaPath = base_path($this->root . '/schema.sql');
        $fullDumpPath = base_path($this->root . '/db_all.sql');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::put($schemaPath, "alpha\nbeta\n");
        File::put($fullDumpPath, "gamma\ndelta\n");

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root . '/schema.sql',
                'optional' => false,
                'required_fragments' => ['alpha', 'beta'],
            ],
            'full_dump' => [
                'path' => $this->root . '/db_all.sql',
                'optional' => true,
                'required_fragments' => ['gamma'],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', [
            '2026_03_15_000022_staff_auth_and_integrity_hardening.sql',
        ]);
        config()->set('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        config()->set('booking_release.release_manifest.snapshot_path', $this->root . '/release_manifest_snapshot.json');

        $snapshot = app(ReleaseArtifactManifestService::class)->snapshot();

        $this->assertTrue($snapshot['ok']);
        $this->assertSame('ok', $snapshot['status']);
        $this->assertSame([], $snapshot['issues']);
        $this->assertSame([], $snapshot['artifacts']['schema_dump']['missing_fragments']);
        $this->assertSame([], $snapshot['patches']['missing']);
        $this->assertArrayHasKey('sha256', $snapshot['artifacts']['schema_dump']);
        $this->assertSame($this->root . '/release_manifest_snapshot.json', $snapshot['snapshot_path']);
        $this->assertSame('config/booking_release.php', $snapshot['definition_path']);
    }

    public function test_snapshot_fails_when_required_artifact_is_missing_fragments_or_patch_inventory_is_incomplete(): void
    {
        $schemaPath = base_path($this->root . '/schema.sql');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::put($schemaPath, "alpha\n");

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root . '/schema.sql',
                'optional' => false,
                'required_fragments' => ['alpha', 'beta'],
            ],
            'full_dump' => [
                'path' => $this->root . '/missing-db.sql',
                'optional' => true,
                'required_fragments' => ['gamma'],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', [
            'missing_patch.sql',
        ]);

        $snapshot = app(ReleaseArtifactManifestService::class)->snapshot();

        $this->assertFalse($snapshot['ok']);
        $this->assertSame('fail', $snapshot['status']);
        $this->assertContains('beta', $snapshot['artifacts']['schema_dump']['missing_fragments']);
        $this->assertSame(['missing_patch.sql'], $snapshot['patches']['missing']);
    }


    public function test_snapshot_fails_when_gate_snapshot_is_semantically_empty_even_if_required_fragments_exist(): void
    {
        $definitionPath = base_path($this->root . '/core_ops_gate_suite.json');
        $snapshotPath = base_path($this->root . '/core_ops_gate_snapshot.json');
        File::ensureDirectoryExists(dirname($definitionPath));
        File::put($definitionPath, json_encode([
            'suite' => 'core_ops',
            'tests' => [
                ['path' => 'tests/Feature/Table/TableAvailabilityFeatureTest.php'],
                ['path' => 'tests/Feature/Table/TableHoldHttpFlowTest.php'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        File::put($snapshotPath, json_encode([
            'suite' => 'core_ops',
            'definition_path' => $this->root . '/core_ops_gate_suite.json',
            'snapshot_path' => $this->root . '/core_ops_gate_snapshot.json',
            'summary' => [
                'total' => 0,
                'passed' => 0,
                'failed' => 0,
            ],
            'tests' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        config()->set('booking_release.artifacts', [
            'core_ops_gate_snapshot' => [
                'path' => $this->root . '/core_ops_gate_snapshot.json',
                'optional' => false,
                'required_fragments' => [
                    '"suite": "core_ops"',
                    '"definition_path": "' . $this->root . '/core_ops_gate_suite.json"',
                    '"snapshot_path": "' . $this->root . '/core_ops_gate_snapshot.json"',
                ],
            ],
        ]);
        config()->set('booking_release.core_ops_gate.definition_path', $this->root . '/core_ops_gate_suite.json');
        config()->set('booking_release.core_ops_gate.snapshot_path', $this->root . '/core_ops_gate_snapshot.json');
        config()->set('booking_release.required_sql_patches', []);

        $snapshot = app(ReleaseArtifactManifestService::class)->snapshot();

        $this->assertFalse($snapshot['ok']);
        $this->assertSame('fail', $snapshot['status']);
        $this->assertArrayHasKey('semantic_issues', $snapshot['artifacts']['core_ops_gate_snapshot']);
        $this->assertContains(
            sprintf('Core ops gate snapshot %s summary.total must be greater than zero.', $this->root . '/core_ops_gate_snapshot.json'),
            $snapshot['artifacts']['core_ops_gate_snapshot']['semantic_issues']
        );
        $this->assertContains(
            sprintf('Core ops gate snapshot %s does not contain any executed test entries.', $this->root . '/core_ops_gate_snapshot.json'),
            $snapshot['artifacts']['core_ops_gate_snapshot']['semantic_issues']
        );
    }

    public function test_snapshot_accepts_json_contract_fragments_when_only_whitespace_differs(): void
    {
        $definitionPath = base_path($this->root . '/route_inventory_gate.json');
        File::ensureDirectoryExists(dirname($definitionPath));
        File::put($definitionPath, <<<JSON
{
    "suite":  "route_inventory",
    "expected_routes":  [],
    "smoke_requests":  []
}
JSON . PHP_EOL);

        config()->set('booking_release.artifacts', [
            'route_inventory_gate_definition' => [
                'path' => $this->root . '/route_inventory_gate.json',
                'optional' => false,
                'required_fragments' => [
                    '"suite": "route_inventory"',
                    '"expected_routes"',
                    '"smoke_requests"',
                ],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', []);

        $snapshot = app(ReleaseArtifactManifestService::class)->snapshot();

        $this->assertTrue($snapshot['ok']);
        $this->assertSame('ok', $snapshot['status']);
        $this->assertSame([], $snapshot['issues']);
        $this->assertSame([], $snapshot['artifacts']['route_inventory_gate_definition']['missing_fragments']);
    }

    public function test_snapshot_normalizes_text_artifact_line_endings_before_hashing(): void
    {
        $definitionPath = base_path($this->root . '/route_inventory_gate.json');
        File::ensureDirectoryExists(dirname($definitionPath));
        File::put($definitionPath, "{\r\n  \"suite\": \"route_inventory\"\r\n}\r\n");

        config()->set('booking_release.artifacts', [
            'route_inventory_gate_definition' => [
                'path' => $this->root . '/route_inventory_gate.json',
                'optional' => false,
                'required_fragments' => [
                    '"suite": "route_inventory"',
                ],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', []);

        $snapshot = app(ReleaseArtifactManifestService::class)->snapshot();
        $normalized = "{\n  \"suite\": \"route_inventory\"\n}\n";

        $this->assertTrue($snapshot['ok']);
        $this->assertSame(hash('sha256', $normalized), $snapshot['artifacts']['route_inventory_gate_definition']['sha256']);
        $this->assertSame(strlen($normalized), $snapshot['artifacts']['route_inventory_gate_definition']['bytes']);
        $this->assertSame(substr_count($normalized, "\n") + 1, $snapshot['artifacts']['route_inventory_gate_definition']['line_count']);
    }

    public function test_snapshot_fails_when_generated_consumer_artifacts_are_stale_relative_to_openapi_or_manifest(): void
    {
        $openApiPath = base_path($this->root . '/openapi-v1.json');
        $sdkPath = base_path($this->root . '/restaurantpos-sdk.ts');
        $enumsPath = base_path($this->root . '/restaurantpos-enums.ts');
        $mutationContractPath = base_path($this->root . '/mutation-contracts.md');
        File::ensureDirectoryExists(dirname($openApiPath));
        File::put($openApiPath, "{\"openapi\":\"3.1.0\"}\n");
        File::put($sdkPath, "export class RestaurantPosClient {}\n");
        File::put($enumsPath, "export const reservationStatusValues = [] as const;\n");
        File::put($mutationContractPath, "# RestaurantPOS Mutation Contract Matrix\n");

        $baseTime = time();
        touch($sdkPath, $baseTime - 20);
        touch($enumsPath, $baseTime - 20);
        touch($mutationContractPath, $baseTime - 20);
        touch($openApiPath, $baseTime - 10);
        clearstatcache();

        config()->set('booking_release.artifacts', [
            'openapi_v1_spec' => [
                'path' => $this->root . '/openapi-v1.json',
                'optional' => false,
                'required_fragments' => ['"openapi":"3.1.0"'],
            ],
            'api_consumer_sdk_typescript' => [
                'path' => $this->root . '/restaurantpos-sdk.ts',
                'optional' => false,
                'required_fragments' => ['RestaurantPosClient'],
            ],
            'api_consumer_sdk_enums_typescript' => [
                'path' => $this->root . '/restaurantpos-enums.ts',
                'optional' => false,
                'required_fragments' => ['reservationStatusValues'],
            ],
            'api_consumer_mutation_contract' => [
                'path' => $this->root . '/mutation-contracts.md',
                'optional' => false,
                'required_fragments' => ['Mutation Contract Matrix'],
            ],
        ]);
        config()->set('booking_release.artifact_freshness', [
            'api_consumer_sdk_typescript' => ['openapi_v1_spec'],
            'api_consumer_sdk_enums_typescript' => ['openapi_v1_spec'],
            'api_consumer_mutation_contract' => ['openapi_v1_spec'],
        ]);
        config()->set('booking_release.required_sql_patches', []);

        $snapshot = app(ReleaseArtifactManifestService::class)->snapshot();

        $this->assertFalse($snapshot['ok']);
        $this->assertSame('fail', $snapshot['status']);
        $this->assertArrayHasKey('freshness_issues', $snapshot['artifacts']['api_consumer_sdk_typescript']);
        $this->assertContains(
            sprintf(
                'Generated artifact %s is stale relative to %s. Regenerate the API consumer artifacts before refreshing the release manifest or packaging the handoff.',
                $this->root . '/restaurantpos-sdk.ts',
                $this->root . '/openapi-v1.json',
            ),
            $snapshot['artifacts']['api_consumer_sdk_typescript']['freshness_issues']
        );
    }

    public function test_inspect_frozen_snapshot_ignores_self_referential_release_manifest_metadata(): void
    {
        $schemaPath = base_path($this->root . '/schema.sql');
        $snapshotPath = base_path($this->root . '/release_manifest_snapshot.json');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::put($schemaPath, "alpha\nbeta\n");

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root . '/schema.sql',
                'optional' => false,
                'required_fragments' => ['alpha', 'beta'],
            ],
            'release_manifest_snapshot' => [
                'path' => $this->root . '/release_manifest_snapshot.json',
                'optional' => false,
                'required_fragments' => ['"artifacts"'],
            ],
        ]);
        config()->set('booking_release.required_sql_patches', []);
        config()->set('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        config()->set('booking_release.release_manifest.snapshot_path', $this->root . '/release_manifest_snapshot.json');

        File::put($snapshotPath, json_encode(['placeholder' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $service = app(ReleaseArtifactManifestService::class);
        $frozen = $service->snapshot();
        $this->assertFalse($frozen['ok']);
        $this->assertSame('fail', $frozen['status']);
        $this->assertNotSame([], $frozen['issues']);
        $this->assertContains('"artifacts"', $frozen['artifacts']['release_manifest_snapshot']['missing_fragments']);

        $frozen['meta']['generated_at_utc'] = '2000-01-01T00:00:00Z';
        $frozen['ok'] = false;
        $frozen['status'] = 'fail';
        $frozen['issues'] = ['self reference should be ignored'];
        $frozen['artifacts']['release_manifest_snapshot']['sha256'] = str_repeat('f', 64);
        $frozen['artifacts']['release_manifest_snapshot']['bytes'] = 1;
        $frozen['artifacts']['release_manifest_snapshot']['line_count'] = 1;
        $frozen['artifacts']['release_manifest_snapshot']['missing_fragments'] = ['"artifacts"'];

        File::put($snapshotPath, json_encode($frozen, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $report = $service->inspectFrozenSnapshot();

        $this->assertTrue($report['ok']);
        $this->assertSame('ok', $report['status']);
        $this->assertSame([], $report['issues']);
        $this->assertSame([], $report['mismatch_paths']);
        $this->assertSame($this->root . '/release_manifest_snapshot.json', $report['snapshot_path']);
    }
}
