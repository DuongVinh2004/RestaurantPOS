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
}
