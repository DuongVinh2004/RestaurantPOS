<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApiArtifactReleaseManifestSyncTest extends TestCase
{
    public function test_composer_api_artifacts_script_follows_the_explicit_openapi_to_manifest_sequence(): void
    {
        $composer = json_decode((string) File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            '@php artisan booking:api-contract --write',
            '@php artisan booking:api-artifacts:generate',
            '@php artisan booking:release-manifest --write',
        ], $composer['scripts']['api:artifacts'] ?? null);
    }

    public function test_composer_release_package_script_uses_the_canonical_release_build_command(): void
    {
        $composer = json_decode((string) File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            '@php artisan booking:release-build',
        ], $composer['scripts']['release:package'] ?? null);
    }

    public function test_composer_release_loop_script_uses_the_canonical_release_loop_command(): void
    {
        $composer = json_decode((string) File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            '@php artisan booking:release-loop',
        ], $composer['scripts']['release:loop'] ?? null);
    }

    public function test_powershell_api_artifact_wrapper_refreshes_openapi_and_manifest_explicitly(): void
    {
        $script = (string) File::get(base_path('scripts/api/Generate-ApiArtifacts.ps1'));

        $this->assertStringContainsString('if ($RefreshOpenApi)', $script);
        $this->assertStringContainsString("'booking:api-contract'", $script);
        $this->assertStringContainsString("'booking:release-manifest'", $script);
        $this->assertStringNotContainsString("'booking:api-artifacts:generate', '--json', '--refresh-openapi'", $script);
    }

    public function test_release_wrappers_use_the_canonical_release_build_command(): void
    {
        $shellWrapper = (string) File::get(base_path('scripts/release/package_release.sh'));
        $cmdWrapper = (string) File::get(base_path('scripts/release/package_release.cmd'));

        $this->assertStringContainsString('booking:release-build', $shellWrapper);
        $this->assertStringNotContainsString('booking:package-release', $shellWrapper);
        $this->assertStringContainsString('booking:release-build', $cmdWrapper);
        $this->assertStringNotContainsString('booking:package-release --verify-frozen', $cmdWrapper);
    }

    public function test_ci_release_gate_uses_the_canonical_release_loop_command(): void
    {
        $script = (string) File::get(base_path('scripts/ci/booking-release-gate.sh'));

        $this->assertStringContainsString('booking:release-loop', $script);
        $this->assertStringContainsString('booking-full-gate.sh', $script);
        $this->assertStringContainsString('php artisan serve', $script);
    }

    public function test_launch_readiness_runbook_uses_operator_manual_evidence_paths_instead_of_test_fixtures(): void
    {
        $runbook = (string) File::get(base_path('docs/runbooks/booking-launch-readiness.md'));

        $this->assertStringContainsString('booking:manual-evidence:init --target=staging --candidate=20260420 --json', $runbook);
        $this->assertStringContainsString('booking:manual-evidence:init --target=limited-production --candidate=20260420 --json', $runbook);
        $this->assertStringContainsString(
            'storage/app/booking_release/manual_evidence/limited-production-20260405.json',
            $runbook
        );
        $this->assertStringContainsString('storage/app/booking_release/manual_evidence/staging-20260420.json', $runbook);
        $this->assertStringNotContainsString('tests/fixtures/launch_readiness_manual_evidence.json', $runbook);
        $this->assertStringContainsString('Do not point limited-production sign-off at `tests/fixtures/*`', $runbook);
        $this->assertStringContainsString('`release_handoff` section', $runbook);
        $this->assertStringContainsString('release_handoff.candidate.package_basename', $runbook);
    }

    public function test_api_consumer_artifact_runbook_documents_generated_provenance_lanes(): void
    {
        $runbook = (string) File::get(base_path('docs/runbooks/api-consumer-artifacts.md'));

        $this->assertStringContainsString('## Provenance Lanes', $runbook);
        $this->assertStringContainsString('Backend contract freeze', $runbook);
        $this->assertStringContainsString('composer api:artifacts', $runbook);
        $this->assertStringContainsString('build/api-consumer/postman/RestaurantPOS.uat.postman_environment.json', $runbook);
        $this->assertStringContainsString('storage/app/booking_release/release_manifest_snapshot.json', $runbook);
        $this->assertStringContainsString('Customer-web sync', $runbook);
        $this->assertStringContainsString('npm --prefix customer-web run sync:contracts', $runbook);
        $this->assertStringContainsString('customer-web/src/lib/contracts/generated/restaurantpos-sdk.ts', $runbook);
        $this->assertStringContainsString('storage/app/uat/scenario-pack.json', $runbook);
        $this->assertStringContainsString(
            'Do not hand-edit files under `build/api-consumer`, `storage/app/booking_release`, or `customer-web/src/lib/contracts/generated`.',
            $runbook
        );
    }

    public function test_deploy_runbook_requires_recorded_known_good_package_basenames_for_rollback(): void
    {
        $runbook = (string) File::get(base_path('docs/runbooks/booking-deploy-runbook.md'));

        $this->assertStringContainsString('build/booking-release/latest-package.json', $runbook);
        $this->assertStringContainsString('restaurantpos-backend-release-20260420t004220z', $runbook);
        $this->assertStringContainsString('<recorded-known-good-package-basename>.package.sha256', $runbook);
        $this->assertStringContainsString('<recorded-known-good-package-basename>.tar.gz', $runbook);
    }

    public function test_release_packaging_runbook_points_operator_to_release_handoff_fields(): void
    {
        $runbook = (string) File::get(base_path('docs/runbooks/booking-release-packaging-runbook.md'));

        $this->assertStringContainsString('release_handoff.candidate.package_basename', $runbook);
        $this->assertStringContainsString('release_handoff.candidate.sidecars.*', $runbook);
        $this->assertStringContainsString('release_handoff.archive_paths[]', $runbook);
    }

    public function test_api_contract_docs_list_the_current_locked_rollout_aliases(): void
    {
        $contractRunbook = (string) File::get(base_path('docs/runbooks/booking-api-contract.md'));
        $finalAudit = (string) File::get(base_path('docs/runbooks/booking-final-audit.md'));

        $this->assertStringContainsString(
            'These are the only locked route alias groups remaining in `tests/fixtures/route_inventory_gate.json` for rollout safety.',
            $contractRunbook
        );
        $this->assertStringContainsString('GET /api/v1/staff/table-board', $contractRunbook);
        $this->assertStringContainsString('Compatibility header alias: `X-Idempotency-Key`', $contractRunbook);
        $this->assertStringContainsString('`voucher/release`', $finalAudit);
        $this->assertStringContainsString('`loyalty/release`', $finalAudit);
        $this->assertStringContainsString('`table-board`', $finalAudit);
        $this->assertStringNotContainsString('`voucher/remove`', $finalAudit);
    }
}
