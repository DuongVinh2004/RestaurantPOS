<?php

declare(strict_types=1);

namespace App\Platform\Release\Services;

use App\Platform\ApiContract\ApiArtifacts\ApiConsumerArtifactService;
use App\Platform\ApiContract\Services\OpenApiSpecService;
use App\Platform\Harness\HarnessSuiteService;

class ReleaseBuildService
{
    public function __construct(
        private readonly OpenApiSpecService $openApiSpecService,
        private readonly ApiConsumerArtifactService $apiConsumerArtifactService,
        private readonly ReleaseArtifactManifestService $releaseArtifactManifestService,
        private readonly ReleasePackageService $releasePackageService,
        private readonly HarnessSuiteService $harnessSuiteService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(
        ?string $packageId = null,
        bool $overwrite = false,
        ?string $uatManifestPath = null,
    ): array {
        $openApi = $this->openApiSpecService->export();
        $apiArtifacts = $this->apiConsumerArtifactService->generate(
            specPath: (string) ($openApi['path'] ?? ''),
            refreshOpenApi: false,
            uatManifestPath: $uatManifestPath,
        );
        $harness = $this->harnessSuiteService->buildReleaseBuildContext(
            manifestPath: $uatManifestPath,
            feContractPayload: $apiArtifacts,
        );

        $releaseManifestSnapshot = $this->releaseArtifactManifestService->snapshot();
        $releaseManifestSnapshot = $this->releaseArtifactManifestService->writeSnapshot($releaseManifestSnapshot);
        $frozenManifestSnapshot = $this->releaseArtifactManifestService->inspectFrozenSnapshot($releaseManifestSnapshot);

        $issues = [];
        $warnings = [];

        if (($releaseManifestSnapshot['status'] ?? 'fail') === 'fail') {
            foreach ((array) ($releaseManifestSnapshot['issues'] ?? []) as $issue) {
                $issues[] = (string) $issue;
            }
        } elseif (($releaseManifestSnapshot['status'] ?? 'ok') === 'warning') {
            foreach ((array) ($releaseManifestSnapshot['issues'] ?? []) as $warning) {
                $warnings[] = (string) $warning;
            }
        }

        if (! ($harness['web_auth']['ok'] ?? false)) {
            $issues[] = 'Web auth/session harness failed one or more error-grade checks.';
        }

        if ($uatManifestPath !== null && trim($uatManifestPath) !== '') {
            foreach ((array) (($harness['golden_flows'] ?? [])['notes'] ?? []) as $note) {
                if (trim((string) $note) !== '') {
                    $warnings[] = 'Golden flow harness: '.trim((string) $note);
                }
            }
        }

        if (! ($frozenManifestSnapshot['ok'] ?? false)) {
            foreach ((array) ($frozenManifestSnapshot['issues'] ?? []) as $issue) {
                $issues[] = (string) $issue;
            }
        }

        $package = null;

        if ($issues === []) {
            $package = $this->releasePackageService->package(
                $packageId,
                verifyFrozen: true,
                overwrite: $overwrite,
            );

            foreach ((array) ($package['issues'] ?? []) as $issue) {
                $issues[] = (string) $issue;
            }

            foreach ((array) ($package['warnings'] ?? []) as $warning) {
                $warnings[] = (string) $warning;
            }
        }

        $issues = array_values(array_unique(array_filter(array_map('trim', $issues), static fn (string $issue): bool => $issue !== '')));
        $warnings = array_values(array_unique(array_filter(array_map('trim', $warnings), static fn (string $warning): bool => $warning !== '')));

        $packageOk = is_array($package) && ($package['ok'] ?? false);

        return [
            'ok' => $issues === [] && $packageOk,
            'status' => $issues === [] && $packageOk ? 'ok' : 'fail',
            'canonical_path' => [
                'php artisan booking:api-contract --write',
                'php artisan booking:api-artifacts:generate',
                'php artisan booking:release-manifest --write',
                'php artisan booking:package-release --verify-frozen',
            ],
            'harness' => $harness,
            'openapi' => $openApi,
            'api_artifacts' => $apiArtifacts,
            'release_manifest' => [
                'snapshot' => $releaseManifestSnapshot,
                'frozen_snapshot' => $frozenManifestSnapshot,
            ],
            'package' => $package,
            'issues' => $issues,
            'warnings' => $warnings,
            'meta' => [
                'package_id_requested' => $packageId,
                'overwrite_requested' => $overwrite,
                'uat_manifest_path' => $uatManifestPath,
            ],
        ];
    }
}
