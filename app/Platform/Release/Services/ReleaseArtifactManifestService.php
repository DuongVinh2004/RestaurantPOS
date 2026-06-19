<?php

declare(strict_types=1);

namespace App\Platform\Release\Services;

use App\Platform\ApiContract\ApiArtifacts\ApiConsumerArtifactService;
use Illuminate\Support\Facades\File;
use Throwable;

class ReleaseArtifactManifestService
{
    /**
     * @var array<string, string>|null
     */
    private ?array $generatedArtifactExpectedFingerprints = null;

    private function normalizeArtifactFingerprintContents(string $contents): string
    {
        return str_replace(["\r\n", "\r"], "\n", $contents);
    }

    private function artifactContainsRequiredFragment(string $contents, string $fragment): bool
    {
        if ($fragment === '') {
            return true;
        }

        if (str_contains($contents, $fragment)) {
            return true;
        }

        $normalizedContents = $this->normalizeFragmentMatchWhitespace($contents);
        $normalizedFragment = $this->normalizeFragmentMatchWhitespace($fragment);

        return $normalizedContents !== ''
            && $normalizedFragment !== ''
            && str_contains($normalizedContents, $normalizedFragment);
    }

    private function normalizeFragmentMatchWhitespace(string $value): string
    {
        return preg_replace('/\s+/u', '', $value) ?? $value;
    }

    /**
     * @return list<string>
     */
    private function semanticArtifactIssues(string $artifactKey, string $relativePath, string $contents): array
    {
        return match ($artifactKey) {
            'core_ops_gate_snapshot' => $this->gateSnapshotIssues(
                suiteKey: 'core_ops_gate',
                artifactLabel: 'Core ops gate snapshot',
                relativePath: $relativePath,
                contents: $contents,
            ),
            'round5_gate_snapshot' => $this->gateSnapshotIssues(
                suiteKey: 'round5_gate',
                artifactLabel: 'Round 5 gate snapshot',
                relativePath: $relativePath,
                contents: $contents,
            ),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function gateSnapshotIssues(string $suiteKey, string $artifactLabel, string $relativePath, string $contents): array
    {
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [sprintf('%s %s is not valid JSON.', $artifactLabel, $relativePath)];
        }

        if (! is_array($decoded)) {
            return [sprintf('%s %s must decode to a JSON object.', $artifactLabel, $relativePath)];
        }

        $definitionPath = trim((string) config(sprintf('booking_release.%s.definition_path', $suiteKey), ''));
        $snapshotPath = trim((string) config(sprintf('booking_release.%s.snapshot_path', $suiteKey), ''));
        $suiteDefinition = $this->readGateDefinitionPaths($definitionPath);
        $issues = [];

        if ($definitionPath !== '' && trim((string) ($decoded['definition_path'] ?? '')) !== $definitionPath) {
            $issues[] = sprintf('%s %s points to definition_path [%s] instead of [%s].', $artifactLabel, $relativePath, (string) ($decoded['definition_path'] ?? ''), $definitionPath);
        }

        if ($snapshotPath !== '' && trim((string) ($decoded['snapshot_path'] ?? '')) !== $snapshotPath) {
            $issues[] = sprintf('%s %s points to snapshot_path [%s] instead of [%s].', $artifactLabel, $relativePath, (string) ($decoded['snapshot_path'] ?? ''), $snapshotPath);
        }

        if ($suiteDefinition['suite'] !== null && trim((string) ($decoded['suite'] ?? '')) !== $suiteDefinition['suite']) {
            $issues[] = sprintf('%s %s reports suite [%s] instead of [%s].', $artifactLabel, $relativePath, (string) ($decoded['suite'] ?? ''), $suiteDefinition['suite']);
        }

        $tests = is_array($decoded['tests'] ?? null) ? array_values(array_filter($decoded['tests'], 'is_array')) : [];
        if ($tests === []) {
            $issues[] = sprintf('%s %s does not contain any executed test entries.', $artifactLabel, $relativePath);
        }

        $summary = is_array($decoded['summary'] ?? null) ? $decoded['summary'] : [];
        $total = (int) ($summary['total'] ?? 0);
        $passed = (int) ($summary['passed'] ?? 0);
        $failed = (int) ($summary['failed'] ?? 0);
        if ($total <= 0) {
            $issues[] = sprintf('%s %s summary.total must be greater than zero.', $artifactLabel, $relativePath);
        }
        if ($total !== count($tests)) {
            $issues[] = sprintf('%s %s summary.total [%d] does not match tests count [%d].', $artifactLabel, $relativePath, $total, count($tests));
        }
        if (($passed + $failed) !== $total) {
            $issues[] = sprintf('%s %s summary passed+failed [%d] does not equal total [%d].', $artifactLabel, $relativePath, $passed + $failed, $total);
        }

        $executedPaths = [];
        foreach ($tests as $test) {
            $path = trim((string) ($test['path'] ?? ''));
            if ($path !== '') {
                $executedPaths[] = $path;
            }
        }
        $executedPaths = array_values(array_unique($executedPaths));
        sort($executedPaths);

        $expectedPaths = $suiteDefinition['paths'];
        $missingPaths = array_values(array_diff($expectedPaths, $executedPaths));
        if ($missingPaths !== []) {
            $issues[] = sprintf('%s %s is missing %d definition-backed test path(s).', $artifactLabel, $relativePath, count($missingPaths));
        }

        return $issues;
    }

    /**
     * @return array{suite: string|null, paths: list<string>}
     */
    private function readGateDefinitionPaths(string $relativeDefinitionPath): array
    {
        if ($relativeDefinitionPath === '') {
            return ['suite' => null, 'paths' => []];
        }

        $absolutePath = base_path($relativeDefinitionPath);
        if (! File::exists($absolutePath)) {
            return ['suite' => null, 'paths' => []];
        }

        try {
            $decoded = json_decode((string) File::get($absolutePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['suite' => null, 'paths' => []];
        }

        if (! is_array($decoded)) {
            return ['suite' => null, 'paths' => []];
        }

        $paths = array_values(array_filter(array_map(
            static fn (mixed $entry): string => is_array($entry) ? trim((string) ($entry['path'] ?? '')) : '',
            (array) ($decoded['tests'] ?? []),
        ), static fn (string $path): bool => $path !== ''));
        $paths = array_values(array_unique($paths));
        sort($paths);

        $suite = trim((string) ($decoded['suite'] ?? ''));

        return [
            'suite' => $suite !== '' ? $suite : null,
            'paths' => $paths,
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   status: string,
     *   issues: list<string>,
     *   artifacts: array<string, array{
     *     path: string,
     *     exists: bool,
     *     optional: bool,
     *     sha256?: string|null,
     *     bytes?: int,
     *     line_count?: int,
     *     modified_at_utc?: string,
     *     modified_epoch?: int,
     *     missing_fragments?: list<string>,
     *     required_fragment_count?: int,
     *     freshness_issues?: list<string>,
     *     skipped?: bool
     *   }>,
     *   patches: array{
     *     present: list<string>,
     *     required: list<string>,
     *     missing: list<string>,
     *     count: int,
     *     required_count: int
     *   },
     *   meta: array{generated_at_utc: string},
     *   definition_path: string,
     *   definition_sha256: string,
     *   snapshot_path: string
     * }
     */
    public function snapshot(): array
    {
        $issues = [];
        $status = 'ok';
        $artifacts = [];

        foreach ((array) config('booking_release.artifacts', []) as $key => $definition) {
            $relativePath = trim((string) ($definition['path'] ?? ''));
            $optional = (bool) ($definition['optional'] ?? false);
            $requiredFragments = array_values(array_filter(
                array_map(static fn ($fragment) => is_scalar($fragment) ? trim((string) $fragment) : '', (array) ($definition['required_fragments'] ?? [])),
                static fn (string $fragment): bool => $fragment !== ''
            ));

            $absolutePath = base_path($relativePath);
            $exists = File::exists($absolutePath);
            $artifact = [
                'path' => $relativePath,
                'exists' => $exists,
                'optional' => $optional,
            ];

            if (! $exists) {
                $artifact['skipped'] = $optional;
                if (! $optional) {
                    $issues[] = sprintf('Required artifact %s is missing.', $relativePath);
                    $status = 'fail';
                } else {
                    $status = $status === 'fail' ? 'fail' : 'warning';
                }

                $artifacts[$key] = $artifact;

                continue;
            }

            $contents = File::get($absolutePath);
            $fingerprintContents = $this->normalizeArtifactFingerprintContents($contents);
            $missingFragments = [];
            foreach ($requiredFragments as $fragment) {
                if (! $this->artifactContainsRequiredFragment($contents, $fragment)) {
                    $missingFragments[] = $fragment;
                }
            }

            $artifact['sha256'] = hash('sha256', $fingerprintContents);
            $artifact['bytes'] = strlen($fingerprintContents);
            $artifact['line_count'] = substr_count($fingerprintContents, "\n") + 1;
            $artifact['modified_epoch'] = File::lastModified($absolutePath);
            $artifact['modified_at_utc'] = now('UTC')->setTimestamp((int) $artifact['modified_epoch'])->toIso8601String();
            $artifact['required_fragment_count'] = count($requiredFragments);
            $artifact['missing_fragments'] = $missingFragments;

            if ($missingFragments !== []) {
                $issues[] = sprintf('%s is missing %d required contract fragment(s).', $relativePath, count($missingFragments));
                $status = 'fail';
            }

            $semanticIssues = $this->semanticArtifactIssues($key, $relativePath, $contents);
            if ($semanticIssues !== []) {
                $artifact['semantic_issues'] = $semanticIssues;
                foreach ($semanticIssues as $issue) {
                    $issues[] = $issue;
                }
                $status = 'fail';
            }

            $artifacts[$key] = $artifact;
        }

        foreach ($this->freshnessIssues($artifacts) as $artifactKey => $freshnessIssues) {
            if ($freshnessIssues === [] || ! isset($artifacts[$artifactKey]) || ! is_array($artifacts[$artifactKey])) {
                continue;
            }

            $artifacts[$artifactKey]['freshness_issues'] = $freshnessIssues;
            foreach ($freshnessIssues as $issue) {
                $issues[] = $issue;
            }
            $status = 'fail';
        }

        $presentPatches = collect(File::glob(database_path('patches/*.sql')) ?: [])
            ->map(static fn (string $path): string => basename($path))
            ->sort()
            ->values()
            ->all();
        $requiredPatches = array_values(array_filter(
            array_map(static fn ($name) => is_scalar($name) ? trim((string) $name) : '', (array) config('booking_release.required_sql_patches', [])),
            static fn (string $name): bool => $name !== ''
        ));
        $missingPatches = array_values(array_diff($requiredPatches, $presentPatches));

        if ($missingPatches !== []) {
            $issues[] = sprintf('Release patch inventory is missing %d required SQL patch artifact(s).', count($missingPatches));
            $status = 'fail';
        }

        $definitionPath = (string) config('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        $snapshotPath = (string) config('booking_release.release_manifest.snapshot_path', 'storage/app/booking_release/release_manifest_snapshot.json');
        $definitionAbsolutePath = base_path($definitionPath);
        $definitionSha256 = File::exists($definitionAbsolutePath)
            ? hash_file('sha256', $definitionAbsolutePath) ?: ''
            : '';

        return [
            'ok' => $status !== 'fail',
            'status' => $status,
            'issues' => $issues,
            'artifacts' => $artifacts,
            'patches' => [
                'present' => $presentPatches,
                'required' => $requiredPatches,
                'missing' => $missingPatches,
                'count' => count($presentPatches),
                'required_count' => count($requiredPatches),
            ],
            'meta' => [
                'generated_at_utc' => now('UTC')->toIso8601String(),
            ],
            'definition_path' => $definitionPath,
            'definition_sha256' => $definitionSha256,
            'snapshot_path' => $snapshotPath,
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   status: string,
     *   issues: list<string>,
     *   mismatch_paths: list<string>,
     *   definition_path: string,
     *   snapshot_path: string,
     *   path: string,
     *   live: array<string, mixed>|null,
     *   frozen: array<string, mixed>|null
     * }
     */
    public function inspectFrozenSnapshot(?array $currentSnapshot = null, ?string $relativePath = null): array
    {
        $definitionPath = (string) config('booking_release.release_manifest.definition_path', 'config/booking_release.php');
        $snapshotPath = $relativePath !== null && trim($relativePath) !== ''
            ? trim($relativePath)
            : (string) config('booking_release.release_manifest.snapshot_path', 'storage/app/booking_release/release_manifest_snapshot.json');
        $absoluteSnapshotPath = base_path($snapshotPath);

        if (! File::exists($absoluteSnapshotPath)) {
            return [
                'ok' => false,
                'status' => 'fail',
                'issues' => [sprintf('Frozen release manifest snapshot %s is missing.', $snapshotPath)],
                'mismatch_paths' => [$snapshotPath],
                'definition_path' => $definitionPath,
                'snapshot_path' => $snapshotPath,
                'path' => $snapshotPath,
                'live' => null,
                'frozen' => null,
            ];
        }

        $decoded = json_decode(File::get($absoluteSnapshotPath), true);
        if (! is_array($decoded)) {
            return [
                'ok' => false,
                'status' => 'fail',
                'issues' => [sprintf('Frozen release manifest snapshot %s is not valid JSON.', $snapshotPath)],
                'mismatch_paths' => [$snapshotPath],
                'definition_path' => $definitionPath,
                'snapshot_path' => $snapshotPath,
                'path' => $snapshotPath,
                'live' => null,
                'frozen' => null,
            ];
        }

        $live = $currentSnapshot ?? $this->snapshot();
        $normalizedFrozen = $this->normalizeSnapshotForComparison($decoded);
        $normalizedLive = $this->normalizeSnapshotForComparison($live);
        $mismatchPaths = $this->collectMismatchPaths($normalizedFrozen, $normalizedLive);
        sort($mismatchPaths);

        $issues = [];
        if ($mismatchPaths !== []) {
            $issues[] = sprintf(
                'Frozen release manifest snapshot %s is stale or mismatched at %d path(s).',
                $snapshotPath,
                count($mismatchPaths)
            );
        }

        return [
            'ok' => $mismatchPaths === [],
            'status' => $mismatchPaths === [] ? 'ok' : 'stale',
            'issues' => $issues,
            'mismatch_paths' => $mismatchPaths,
            'definition_path' => $definitionPath,
            'snapshot_path' => $snapshotPath,
            'path' => $snapshotPath,
            'live' => $live,
            'frozen' => $decoded,
        ];
    }

    public function writeSnapshot(?array $snapshot = null, ?string $relativePath = null): array
    {
        $snapshot ??= $this->snapshot();
        $relativePath = $relativePath !== null && trim($relativePath) !== ''
            ? trim($relativePath)
            : (string) ($snapshot['snapshot_path'] ?? config('booking_release.release_manifest.snapshot_path', 'storage/app/booking_release/release_manifest_snapshot.json'));

        $absolutePath = base_path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        $contents = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        if (File::exists($absolutePath)) {
            $existing = (string) File::get($absolutePath);
            if ($existing === $contents) {
                return $snapshot;
            }

            $decoded = json_decode($existing, true);
            if (
                is_array($decoded)
                && $this->normalizeSnapshotForComparison($decoded) === $this->normalizeSnapshotForComparison($snapshot)
            ) {
                if ($this->freshnessMetadataChanged($decoded, $snapshot)) {
                    File::put($absolutePath, $contents);

                    return $snapshot;
                }

                return $decoded;
            }
        }

        // Keep the frozen snapshot file timestamp aligned with meaningful refreshes, not cosmetic metadata churn.
        File::put($absolutePath, $contents);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $snapshot
     */
    private function freshnessMetadataChanged(array $existing, array $snapshot): bool
    {
        $trackedArtifactKeys = [];

        foreach ((array) config('booking_release.artifact_freshness', []) as $artifactKey => $dependencyKeys) {
            if (is_scalar($artifactKey) && trim((string) $artifactKey) !== '') {
                $trackedArtifactKeys[trim((string) $artifactKey)] = true;
            }

            foreach ((array) $dependencyKeys as $dependencyKey) {
                if (is_scalar($dependencyKey) && trim((string) $dependencyKey) !== '') {
                    $trackedArtifactKeys[trim((string) $dependencyKey)] = true;
                }
            }
        }

        foreach (array_keys($trackedArtifactKeys) as $artifactKey) {
            $existingModifiedEpoch = data_get($existing, 'artifacts.'.$artifactKey.'.modified_epoch');
            $currentModifiedEpoch = data_get($snapshot, 'artifacts.'.$artifactKey.'.modified_epoch');

            if ($existingModifiedEpoch === null || $currentModifiedEpoch === null) {
                continue;
            }

            if ((int) $existingModifiedEpoch !== (int) $currentModifiedEpoch) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function normalizeSnapshotForComparison(array $snapshot): array
    {
        unset(
            $snapshot['meta']['generated_at_utc'],
            $snapshot['ok'],
            $snapshot['status'],
            $snapshot['issues'],
            $snapshot['definition_sha256']
        );

        if (isset($snapshot['artifacts']) && is_array($snapshot['artifacts'])) {
            foreach ($snapshot['artifacts'] as $artifactKey => &$artifact) {
                if (! is_array($artifact)) {
                    continue;
                }

                $artifactPath = trim((string) ($artifact['path'] ?? ''));

                // Preserve content fingerprints for real source artifacts so stale verification
                // still catches release drift (for example a changed schema dump). Only the
                // self-referential release manifest snapshot should drop volatile content metadata.
                if ((string) $artifactKey === 'release_manifest_snapshot') {
                    unset($artifact['sha256'], $artifact['bytes'], $artifact['line_count']);
                }

                unset($artifact['modified_at_utc'], $artifact['modified_epoch']);
            }
            unset($artifact);

            ksort($snapshot['artifacts']);

            // The frozen manifest necessarily contains metadata about itself. Remove that artifact
            // entirely from equality checks to avoid false mismatches caused by self-reference.
            unset($snapshot['artifacts']['release_manifest_snapshot']);
        }

        if (isset($snapshot['patches']) && is_array($snapshot['patches'])) {
            foreach (['present', 'required', 'missing'] as $listKey) {
                if (isset($snapshot['patches'][$listKey]) && is_array($snapshot['patches'][$listKey])) {
                    sort($snapshot['patches'][$listKey]);
                }
            }
        }

        return $this->sortRecursive($snapshot);
    }

    /**
     * @param  array<string, array<string, mixed>>  $artifacts
     * @return array<string, list<string>>
     */
    private function freshnessIssues(array $artifacts): array
    {
        $issuesByArtifact = [];

        foreach ((array) config('booking_release.artifact_freshness', []) as $artifactKey => $dependencyKeys) {
            if (! isset($artifacts[$artifactKey]) || ! is_array($artifacts[$artifactKey])) {
                continue;
            }

            $artifact = $artifacts[$artifactKey];
            if (! ($artifact['exists'] ?? false)) {
                continue;
            }

            $dependencyPaths = [];
            foreach ((array) $dependencyKeys as $dependencyKey) {
                $dependencyKey = is_scalar($dependencyKey) ? trim((string) $dependencyKey) : '';
                if ($dependencyKey === '' || ! isset($artifacts[$dependencyKey]) || ! is_array($artifacts[$dependencyKey])) {
                    continue;
                }

                $dependency = $artifacts[$dependencyKey];
                if (! ($dependency['exists'] ?? false)) {
                    continue;
                }

                $dependencyPaths[] = (string) ($dependency['path'] ?? $dependencyKey);
            }

            try {
                $expectedFingerprint = $this->expectedGeneratedArtifactFingerprint($artifactKey, $artifacts);
            } catch (Throwable $exception) {
                $issuesByArtifact[$artifactKey] ??= [];
                $issuesByArtifact[$artifactKey][] = sprintf(
                    'Generated artifact %s could not be verified against %s: %s',
                    (string) ($artifact['path'] ?? $artifactKey),
                    $dependencyPaths !== [] ? implode(', ', $dependencyPaths) : 'its source artifacts',
                    $exception->getMessage(),
                );

                continue;
            }

            if ($expectedFingerprint !== null) {
                $actualFingerprint = (string) ($artifact['sha256'] ?? '');
                if ($actualFingerprint !== '' && hash_equals($expectedFingerprint, $actualFingerprint)) {
                    continue;
                }

                $issuesByArtifact[$artifactKey] ??= [];
                $issuesByArtifact[$artifactKey][] = sprintf(
                    'Generated artifact %s is stale relative to %s. Regenerate the API consumer artifacts before refreshing the release manifest or packaging the handoff.',
                    (string) ($artifact['path'] ?? $artifactKey),
                    $dependencyPaths !== [] ? implode(', ', $dependencyPaths) : 'its source artifacts',
                );

                continue;
            }

            $artifactFreshnessEpoch = (int) ($artifact['modified_epoch'] ?? 0);
            if ($artifactFreshnessEpoch <= 0) {
                continue;
            }

            foreach ((array) $dependencyKeys as $dependencyKey) {
                $dependencyKey = is_scalar($dependencyKey) ? trim((string) $dependencyKey) : '';
                if ($dependencyKey === '' || ! isset($artifacts[$dependencyKey]) || ! is_array($artifacts[$dependencyKey])) {
                    continue;
                }

                $dependency = $artifacts[$dependencyKey];
                if (! ($dependency['exists'] ?? false)) {
                    continue;
                }

                $dependencyFreshnessEpoch = (int) ($dependency['modified_epoch'] ?? 0);
                if ($dependencyFreshnessEpoch <= 0 || $artifactFreshnessEpoch >= $dependencyFreshnessEpoch) {
                    continue;
                }

                $issuesByArtifact[$artifactKey] ??= [];
                $issuesByArtifact[$artifactKey][] = sprintf(
                    'Generated artifact %s is stale relative to %s. Regenerate the API consumer artifacts before refreshing the release manifest or packaging the handoff.',
                    (string) ($artifact['path'] ?? $artifactKey),
                    (string) ($dependency['path'] ?? $dependencyKey),
                );
            }
        }

        return $issuesByArtifact;
    }

    /**
     * @param  array<string, array<string, mixed>>  $artifacts
     */
    protected function expectedGeneratedArtifactFingerprint(string $artifactKey, array $artifacts): ?string
    {
        if (! array_key_exists($artifactKey, $this->apiConsumerGeneratedArtifactMap())) {
            return null;
        }

        return $this->generatedApiConsumerArtifactExpectedFingerprints($artifacts)[$artifactKey] ?? null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $artifacts
     * @return array<string, string>
     */
    private function generatedApiConsumerArtifactExpectedFingerprints(array $artifacts): array
    {
        if ($this->generatedArtifactExpectedFingerprints !== null) {
            return $this->generatedArtifactExpectedFingerprints;
        }

        $tempOutputRoot = sprintf(
            'storage/framework/cache/release-manifest-api-consumer/%s',
            str_replace('.', '', uniqid('', true)),
        );
        $payload = [];

        try {
            $payload = app(ApiConsumerArtifactService::class)->generate(
                outputRoot: $tempOutputRoot,
                specPath: $this->freshnessSpecPath($artifacts),
                refreshOpenApi: false,
            );

            $fingerprints = [];
            foreach ($this->apiConsumerGeneratedArtifactMap() as $artifactKey => $generatedArtifactKey) {
                $generatedPath = trim((string) (($payload['artifacts'] ?? [])[$generatedArtifactKey] ?? ''));
                if ($generatedPath === '' || ! File::exists(base_path($generatedPath))) {
                    continue;
                }

                $fingerprints[$artifactKey] = hash(
                    'sha256',
                    $this->normalizeArtifactFingerprintContents((string) File::get(base_path($generatedPath))),
                );
            }

            return $this->generatedArtifactExpectedFingerprints = $fingerprints;
        } finally {
            File::deleteDirectory(base_path($tempOutputRoot));
        }
    }

    /**
     * @return array<string, string>
     */
    private function apiConsumerGeneratedArtifactMap(): array
    {
        return [
            'api_consumer_collection' => 'collection',
            'api_consumer_local_environment' => 'local_environment',
            'api_consumer_staging_environment' => 'staging_environment',
            'api_consumer_sdk_typescript' => 'sdk_typescript',
            'api_consumer_sdk_enums_typescript' => 'enum_state_typescript',
            'api_consumer_sdk_readme' => 'sdk_readme',
            'api_consumer_enum_state_json' => 'enum_state_json',
            'api_consumer_mutation_contract' => 'mutation_contract',
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $artifacts
     */
    private function freshnessSpecPath(array $artifacts): ?string
    {
        $relativePath = trim((string) (($artifacts['openapi_v1_spec']['path'] ?? '')));

        return $relativePath !== '' ? $relativePath : null;
    }

    /**
     * @return list<string>
     */
    private function collectMismatchPaths(mixed $expected, mixed $actual, string $path = ''): array
    {
        if (is_array($expected) && is_array($actual)) {
            $mismatches = [];
            $keys = array_values(array_unique(array_merge(array_keys($expected), array_keys($actual))));
            sort($keys);

            foreach ($keys as $key) {
                $childPath = $path === '' ? (string) $key : $path.'.'.(string) $key;
                $expectedHasKey = array_key_exists($key, $expected);
                $actualHasKey = array_key_exists($key, $actual);

                if (! $expectedHasKey || ! $actualHasKey) {
                    $mismatches[] = $childPath;

                    continue;
                }

                $mismatches = array_merge($mismatches, $this->collectMismatchPaths($expected[$key], $actual[$key], $childPath));
            }

            return $mismatches;
        }

        return $expected === $actual ? [] : [$path === '' ? '<root>' : $path];
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $child) {
            $value[$key] = $this->sortRecursive($child);
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
