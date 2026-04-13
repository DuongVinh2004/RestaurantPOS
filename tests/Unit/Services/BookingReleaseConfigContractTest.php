<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;

class BookingReleaseConfigContractTest extends TestCase
{
    public function test_full_dump_uses_same_required_contract_fragments_as_schema_dump(): void
    {
        $schemaFragments = array_values((array) config('booking_release.artifacts.schema_dump.required_fragments', []));
        $fullDumpFragments = array_values((array) config('booking_release.artifacts.full_dump.required_fragments', []));

        $this->assertSame(
            $schemaFragments,
            $fullDumpFragments,
            'Optional full dump must enforce the same canonical release contract fragments as the schema dump when it is present.'
        );
    }

    public function test_required_sql_patch_inventory_includes_round_two_and_round_three_schema_guards(): void
    {
        $requiredPatches = array_values((array) config('booking_release.required_sql_patches', []));

        $this->assertContains(
            '2026_03_15_000023_menu_price_and_active_voucher_integrity.sql',
            $requiredPatches,
            'Release patch inventory must include the round 2 active voucher / menu price integrity SQL patch.'
        );
        $this->assertContains(
            '2026_03_15_000024_payment_reconciliation_and_table_audit_round.sql',
            $requiredPatches,
            'Release patch inventory must include the round 3 payment reconciliation / table audit SQL patch.'
        );
    }

    public function test_release_gate_and_packaging_contracts_are_explicitly_configured(): void
    {
        $this->assertSame('config/booking_release.php', (string) config('booking_release.release_manifest.definition_path'));
        $this->assertSame('storage/app/booking_release/release_manifest_snapshot.json', (string) config('booking_release.release_manifest.snapshot_path'));
        $this->assertSame('tests/fixtures/core_ops_gate_suite.json', (string) config('booking_release.core_ops_gate.definition_path'));
        $this->assertSame('tests/fixtures/round5_gate_suite.json', (string) config('booking_release.round5_gate.definition_path'));
        $this->assertSame('tests/fixtures/route_inventory_gate.json', (string) config('booking_release.route_inventory_gate.definition_path'));

        $requiredPaths = collect((array) config('booking_release.packaging.include_paths', []))
            ->filter(static fn (array $item): bool => (bool) ($item['required'] ?? false))
            ->pluck('path')
            ->values()
            ->all();

        $this->assertContains('.env.example', $requiredPaths);
        $this->assertContains('build/api-consumer', $requiredPaths);
        $this->assertContains('package.json', $requiredPaths);
        $this->assertContains('phpunit.xml', $requiredPaths);
        $this->assertContains('public/index.php', $requiredPaths);
        $this->assertContains('scripts', $requiredPaths);
        $this->assertContains('staff-web', $requiredPaths);
        $this->assertContains('storage/app/booking_release', $requiredPaths);
        $this->assertContains('tests', $requiredPaths);
        $this->assertContains('tools/bootstrap_booking.php', $requiredPaths);
        $this->assertContains('tools/mysql', $requiredPaths);
        $this->assertContains('vite.config.js', $requiredPaths);

        $excludedPaths = array_values((array) config('booking_release.packaging.exclude_paths', []));
        $this->assertContains('staff-web/node_modules', $excludedPaths);
        $this->assertContains('staff-web/dist', $excludedPaths);
    }

    public function test_release_build_metadata_and_consumer_artifact_contracts_are_registered(): void
    {
        $this->assertIsArray(config('booking_release.build_metadata'));
        $this->assertArrayHasKey('commit_sha', (array) config('booking_release.build_metadata'));
        $this->assertArrayHasKey('ref_name', (array) config('booking_release.build_metadata'));
        $this->assertArrayHasKey('run_id', (array) config('booking_release.build_metadata'));
        $this->assertSame(
            'storage/app/booking_release/release_loop',
            (string) config('booking_release.release_loop.artifact_root')
        );

        $this->assertSame(
            'build/api-consumer/postman/RestaurantPOS.postman_collection.json',
            (string) config('booking_release.artifacts.api_consumer_collection.path')
        );
        $this->assertSame(
            'build/api-consumer/sdk/typescript/restaurantpos-sdk.ts',
            (string) config('booking_release.artifacts.api_consumer_sdk_typescript.path')
        );
        $this->assertSame(
            'build/api-consumer/sdk/typescript/restaurantpos-enums.ts',
            (string) config('booking_release.artifacts.api_consumer_sdk_enums_typescript.path')
        );
        $this->assertSame(
            'build/api-consumer/enum-state-map.json',
            (string) config('booking_release.artifacts.api_consumer_enum_state_json.path')
        );
        $this->assertSame(
            'build/api-consumer/mutation-contracts.md',
            (string) config('booking_release.artifacts.api_consumer_mutation_contract.path')
        );
        $this->assertSame(
            ['openapi_v1_spec'],
            (array) config('booking_release.artifact_freshness.api_consumer_sdk_typescript')
        );
    }
}
