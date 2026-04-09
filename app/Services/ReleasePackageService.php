<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class ReleasePackageService
{
    public function __construct(
        private readonly ReleaseArtifactManifestService $manifestService,
        private readonly ReleaseBuildMetadataService $releaseBuildMetadataService,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   status: string,
     *   package_id: string,
     *   package_basename: string,
     *   package_path: string,
     *   package_exists: bool,
     *   package_sha256?: string,
     *   package_bytes?: int,
     *   output_root: string,
     *   stage_path: string,
     *   include_roots: list<array{path: string, required: bool, type: string}>,
     *   skipped_optional_paths: list<string>,
     *   sidecars: array<string, string>,
     *   inventory: array{file_count: int, total_bytes: int},
     *   release_manifest: array<string, mixed>,
     *   issues: list<string>,
     *   warnings: list<string>
     * }
     */
    public function package(?string $packageId = null, bool $verifyFrozen = false, bool $overwrite = false): array
    {
        $definition = $this->definition();
        $buildMetadata = $this->releaseBuildMetadataService->current();
        $packageId = $this->sanitizePackageId($packageId ?: $this->defaultPackageId($buildMetadata['commit_sha'] ?? null));
        $packageBasename = $definition['package_prefix'].'-'.$packageId;
        $outputRoot = $definition['output_root'];
        $stagePath = trim($outputRoot.'/stage/'.$packageBasename, '/');
        $packagePath = trim($outputRoot.'/'.$packageBasename.'.tar.gz', '/');
        $tarPath = preg_replace('/\.gz$/', '', $packagePath);
        if (! is_string($tarPath) || $tarPath === '') {
            throw new RuntimeException('Unable to derive the intermediate tar path for the release package.');
        }

        $sidecars = [
            'metadata_path' => trim($outputRoot.'/'.$packageBasename.$definition['sidecars']['metadata_suffix'], '/'),
            'inventory_path' => trim($outputRoot.'/'.$packageBasename.$definition['sidecars']['inventory_suffix'], '/'),
            'checksums_path' => trim($outputRoot.'/'.$packageBasename.$definition['sidecars']['checksums_suffix'], '/'),
            'package_sha256_path' => trim($outputRoot.'/'.$packageBasename.$definition['sidecars']['package_sha256_suffix'], '/'),
            'latest_pointer_path' => $definition['sidecars']['latest_pointer_path'],
        ];

        $snapshot = $this->manifestService->snapshot();
        $frozenSnapshot = $this->manifestService->inspectFrozenSnapshot($snapshot);
        $issues = [];
        $warnings = [];
        $includeRoots = [];
        $skippedOptionalPaths = [];

        if (($snapshot['status'] ?? 'fail') === 'fail') {
            $issues[] = 'Release artifact manifest is failing. Resolve manifest issues before packaging the release.';
        } elseif (($snapshot['status'] ?? 'ok') === 'warning') {
            $warnings[] = 'Release artifact manifest has warnings; review optional artifacts before promotion.';
        }

        if ($verifyFrozen && ! ($frozenSnapshot['ok'] ?? false)) {
            $issues[] = sprintf(
                'Frozen release manifest snapshot verification failed with status [%s].',
                (string) ($frozenSnapshot['status'] ?? 'missing')
            );
        }

        if (! class_exists(\PharData::class)) {
            $issues[] = 'PharData support is unavailable; cannot build immutable .tar.gz release artifacts.';
        }

        $absoluteOutputRoot = base_path($outputRoot);
        File::ensureDirectoryExists($absoluteOutputRoot);

        if (File::exists(base_path($packagePath)) && ! $overwrite) {
            $issues[] = sprintf('Release package [%s] already exists. Re-run with overwrite enabled or use a different package id.', $packagePath);
        }

        if ($issues !== []) {
            return $this->failureReport(
                packageId: $packageId,
                packageBasename: $packageBasename,
                packagePath: $packagePath,
                outputRoot: $outputRoot,
                stagePath: $stagePath,
                includeRoots: $includeRoots,
                skippedOptionalPaths: $skippedOptionalPaths,
                sidecars: $sidecars,
                snapshot: $snapshot,
                frozenSnapshot: $frozenSnapshot,
                issues: $issues,
                warnings: $warnings,
            );
        }

        $absoluteStagePath = base_path($stagePath);
        File::deleteDirectory($absoluteStagePath);
        File::ensureDirectoryExists($absoluteStagePath);

        $missingRequiredPaths = [];

        foreach ($definition['include_paths'] as $includePath) {
            $relativeSourcePath = $includePath['path'];
            $required = $includePath['required'];
            $absoluteSourcePath = base_path($relativeSourcePath);

            if (! File::exists($absoluteSourcePath)) {
                if ($required) {
                    $missingRequiredPaths[] = $relativeSourcePath;
                } else {
                    $skippedOptionalPaths[] = $relativeSourcePath;
                }

                continue;
            }

            $includeRoots[] = [
                'path' => $relativeSourcePath,
                'required' => $required,
                'type' => is_dir($absoluteSourcePath) ? 'directory' : 'file',
            ];

            $this->stagePath($absoluteSourcePath, $relativeSourcePath, $absoluteStagePath);
        }

        if ($missingRequiredPaths !== []) {
            $issues[] = sprintf(
                'Release package is missing %d required source path(s): %s',
                count($missingRequiredPaths),
                implode(', ', $missingRequiredPaths)
            );

            File::deleteDirectory($absoluteStagePath);

            return $this->failureReport(
                packageId: $packageId,
                packageBasename: $packageBasename,
                packagePath: $packagePath,
                outputRoot: $outputRoot,
                stagePath: $stagePath,
                includeRoots: $includeRoots,
                skippedOptionalPaths: $skippedOptionalPaths,
                sidecars: $sidecars,
                snapshot: $snapshot,
                frozenSnapshot: $frozenSnapshot,
                issues: $issues,
                warnings: $warnings,
            );
        }

        $inventoryEntries = $this->inventoryEntries($absoluteStagePath, $packageBasename);
        $inventoryPayload = [
            'package_id' => $packageId,
            'package_basename' => $packageBasename,
            'generated_at_utc' => now('UTC')->toIso8601String(),
            'file_count' => count($inventoryEntries),
            'total_bytes' => array_sum(array_map(static fn (array $entry): int => (int) $entry['bytes'], $inventoryEntries)),
            'entries' => $inventoryEntries,
        ];

        $metadataPayload = [
            'package_id' => $packageId,
            'package_basename' => $packageBasename,
            'created_at_utc' => now('UTC')->toIso8601String(),
            'app_env' => (string) config('app.env', 'production'),
            'package_format' => 'tar.gz',
            'include_roots' => $includeRoots,
            'skipped_optional_paths' => $skippedOptionalPaths,
            'release_manifest' => [
                'status' => (string) ($snapshot['status'] ?? 'fail'),
                'definition_sha256' => (string) ($snapshot['definition_sha256'] ?? ''),
                'snapshot_path' => (string) ($snapshot['snapshot_path'] ?? ''),
                'frozen_snapshot' => $frozenSnapshot,
            ],
            'build' => $buildMetadata,
        ];

        $this->writeJson($absoluteStagePath.DIRECTORY_SEPARATOR.'release_metadata.json', $metadataPayload);
        $this->writeJson($absoluteStagePath.DIRECTORY_SEPARATOR.'release_inventory.json', $inventoryPayload);

        $checksumEntries = [];
        foreach ($this->collectStageFiles($absoluteStagePath) as $relativePath) {
            if ($relativePath === 'release_checksums.sha256') {
                continue;
            }

            $absolutePath = $absoluteStagePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $checksumEntries[] = [
                'path' => $relativePath,
                'sha256' => hash_file('sha256', $absolutePath),
            ];
        }
        usort($checksumEntries, static fn (array $left, array $right): int => strcmp((string) $left['path'], (string) $right['path']));

        $checksumsContent = implode("\n", array_map(
            static fn (array $entry): string => sprintf('%s  %s', $entry['sha256'], $entry['path']),
            $checksumEntries
        ))."\n";
        File::put($absoluteStagePath.DIRECTORY_SEPARATOR.'release_checksums.sha256', $checksumsContent);

        try {
            $this->buildArchive(
                stageAbsolutePath: $absoluteStagePath,
                packageBasename: $packageBasename,
                tarAbsolutePath: base_path($tarPath),
                packageAbsolutePath: base_path($packagePath),
                overwrite: $overwrite,
            );
        } catch (Throwable $e) {
            File::deleteDirectory($absoluteStagePath);

            return $this->failureReport(
                packageId: $packageId,
                packageBasename: $packageBasename,
                packagePath: $packagePath,
                outputRoot: $outputRoot,
                stagePath: $stagePath,
                includeRoots: $includeRoots,
                skippedOptionalPaths: $skippedOptionalPaths,
                sidecars: $sidecars,
                snapshot: $snapshot,
                frozenSnapshot: $frozenSnapshot,
                issues: ['Release package build failed: '.$e->getMessage()],
                warnings: $warnings,
            );
        }

        File::copy($absoluteStagePath.DIRECTORY_SEPARATOR.'release_metadata.json', base_path($sidecars['metadata_path']));
        File::copy($absoluteStagePath.DIRECTORY_SEPARATOR.'release_inventory.json', base_path($sidecars['inventory_path']));
        File::copy($absoluteStagePath.DIRECTORY_SEPARATOR.'release_checksums.sha256', base_path($sidecars['checksums_path']));

        $packageSha256 = hash_file('sha256', base_path($packagePath));
        $packageBytes = (int) filesize(base_path($packagePath));
        File::put(base_path($sidecars['package_sha256_path']), sprintf('%s  %s%s', $packageSha256, basename($packagePath), PHP_EOL));

        $latestPointerPayload = [
            'package_id' => $packageId,
            'package_basename' => $packageBasename,
            'generated_at_utc' => now('UTC')->toIso8601String(),
            'package_path' => $packagePath,
            'package_sha256' => $packageSha256,
            'package_bytes' => $packageBytes,
            'metadata_path' => $sidecars['metadata_path'],
            'inventory_path' => $sidecars['inventory_path'],
            'checksums_path' => $sidecars['checksums_path'],
            'package_sha256_path' => $sidecars['package_sha256_path'],
        ];
        File::ensureDirectoryExists(dirname(base_path($sidecars['latest_pointer_path'])));
        File::put(base_path($sidecars['latest_pointer_path']), json_encode($latestPointerPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return [
            'ok' => true,
            'status' => 'ok',
            'package_id' => $packageId,
            'package_basename' => $packageBasename,
            'package_path' => $packagePath,
            'package_exists' => File::exists(base_path($packagePath)),
            'package_sha256' => $packageSha256,
            'package_bytes' => $packageBytes,
            'output_root' => $outputRoot,
            'stage_path' => $stagePath,
            'include_roots' => $includeRoots,
            'skipped_optional_paths' => $skippedOptionalPaths,
            'sidecars' => $sidecars,
            'inventory' => [
                'file_count' => $inventoryPayload['file_count'],
                'total_bytes' => $inventoryPayload['total_bytes'],
            ],
            'release_manifest' => [
                'status' => $snapshot['status'] ?? 'fail',
                'snapshot_path' => $snapshot['snapshot_path'] ?? '',
                'definition_sha256' => $snapshot['definition_sha256'] ?? '',
                'frozen_snapshot' => $frozenSnapshot,
            ],
            'issues' => [],
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{
     *   output_root: string,
     *   package_prefix: string,
     *   include_paths: list<array{path: string, required: bool}>,
     *   sidecars: array{metadata_suffix: string, inventory_suffix: string, checksums_suffix: string, package_sha256_suffix: string, latest_pointer_path: string}
     * }
     */
    public function definition(): array
    {
        $packaging = (array) config('booking_release.packaging', []);
        $includePaths = [];

        foreach ((array) ($packaging['include_paths'] ?? []) as $includePath) {
            if (! is_array($includePath)) {
                continue;
            }

            $path = trim((string) ($includePath['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $includePaths[] = [
                'path' => trim(str_replace('\\', '/', $path), '/'),
                'required' => (bool) ($includePath['required'] ?? false),
            ];
        }

        return [
            'output_root' => trim((string) ($packaging['output_root'] ?? 'build/booking-release'), '/'),
            'package_prefix' => trim((string) ($packaging['package_prefix'] ?? 'restaurantpos-backend-release')),
            'include_paths' => $includePaths,
            'sidecars' => [
                'metadata_suffix' => (string) ($packaging['sidecars']['metadata_suffix'] ?? '.metadata.json'),
                'inventory_suffix' => (string) ($packaging['sidecars']['inventory_suffix'] ?? '.inventory.json'),
                'checksums_suffix' => (string) ($packaging['sidecars']['checksums_suffix'] ?? '.checksums.sha256'),
                'package_sha256_suffix' => (string) ($packaging['sidecars']['package_sha256_suffix'] ?? '.package.sha256'),
                'latest_pointer_path' => trim((string) ($packaging['sidecars']['latest_pointer_path'] ?? 'build/booking-release/latest-package.json'), '/'),
            ],
        ];
    }

    private function defaultPackageId(?string $commitSha = null): string
    {
        $timestamp = now('UTC')->format('Ymd\THis\Z');

        $suffix = '';
        if (is_string($commitSha) && trim($commitSha) !== '') {
            $suffix = '-'.substr(preg_replace('/[^a-zA-Z0-9]+/', '', trim($commitSha)) ?: '', 0, 12);
        }

        return strtolower($timestamp.$suffix);
    }

    private function sanitizePackageId(string $packageId): string
    {
        $normalized = strtolower(trim(preg_replace('/[^a-zA-Z0-9._-]+/', '-', $packageId) ?: '', '-'));
        if ($normalized === '') {
            throw new RuntimeException('Release package id cannot be empty after sanitization.');
        }

        return $normalized;
    }

    private function stagePath(string $absoluteSourcePath, string $relativeSourcePath, string $absoluteStagePath): void
    {
        $relativeSourcePath = trim(str_replace('\\', '/', $relativeSourcePath), '/');
        if (is_file($absoluteSourcePath)) {
            $destination = $absoluteStagePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeSourcePath);
            File::ensureDirectoryExists(dirname($destination));
            File::copy($absoluteSourcePath, $destination);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteSourcePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (! $item instanceof \SplFileInfo || ! $item->isFile()) {
                continue;
            }

            $relativeChild = trim(str_replace('\\', '/', substr($item->getPathname(), strlen($absoluteSourcePath) + 1)), '/');
            $destination = $absoluteStagePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeSourcePath.'/'.$relativeChild);
            File::ensureDirectoryExists(dirname($destination));
            File::copy($item->getPathname(), $destination);
        }
    }

    /**
     * @return list<array{source_path: string, archive_path: string, bytes: int, sha256: string, line_count: int}>
     */
    private function inventoryEntries(string $absoluteStagePath, string $packageBasename): array
    {
        $entries = [];

        foreach ($this->collectStageFiles($absoluteStagePath) as $relativePath) {
            $absolutePath = $absoluteStagePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $contents = (string) File::get($absolutePath);
            $entries[] = [
                'source_path' => $relativePath,
                'archive_path' => $packageBasename.'/'.$relativePath,
                'bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'line_count' => $this->lineCount($contents),
            ];
        }

        usort($entries, static fn (array $left, array $right): int => strcmp((string) $left['source_path'], (string) $right['source_path']));

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function collectStageFiles(string $absoluteStagePath): array
    {
        if (! File::exists($absoluteStagePath)) {
            return [];
        }

        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteStagePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (! $item instanceof \SplFileInfo || ! $item->isFile()) {
                continue;
            }

            $relativePath = trim(str_replace('\\', '/', substr($item->getPathname(), strlen($absoluteStagePath) + 1)), '/');
            $paths[] = $relativePath;
        }

        sort($paths);

        return $paths;
    }

    private function writeJson(string $absolutePath, array $payload): void
    {
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function buildArchive(string $stageAbsolutePath, string $packageBasename, string $tarAbsolutePath, string $packageAbsolutePath, bool $overwrite): void
    {
        foreach ([$tarAbsolutePath, $packageAbsolutePath] as $path) {
            if (File::exists($path)) {
                if (! $overwrite) {
                    throw new RuntimeException(sprintf('Archive path [%s] already exists.', $path));
                }

                File::delete($path);
            }
        }

        File::ensureDirectoryExists(dirname($tarAbsolutePath));

        $tar = new \PharData($tarAbsolutePath);
        foreach ($this->collectStageFiles($stageAbsolutePath) as $relativePath) {
            $absolutePath = $stageAbsolutePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $archivePath = $packageBasename.'/'.$relativePath;
            $tar->addFile($absolutePath, $archivePath);
        }

        unset($tar);

        $tar = new \PharData($tarAbsolutePath);
        $tar->compress(\Phar::GZ);
        unset($tar);

        if (! File::exists($packageAbsolutePath)) {
            throw new RuntimeException(sprintf('Expected compressed release package [%s] was not created.', $packageAbsolutePath));
        }

        File::delete($tarAbsolutePath);
    }

    private function lineCount(string $contents): int
    {
        if ($contents === '') {
            return 0;
        }

        return substr_count($contents, "\n") + (! str_ends_with($contents, "\n") ? 1 : 0);
    }

    /**
     * @param  list<array{path: string, required: bool, type: string}>  $includeRoots
     * @param  list<string>  $skippedOptionalPaths
     * @param  array<string, string>  $sidecars
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $frozenSnapshot
     * @param  list<string>  $issues
     * @param  list<string>  $warnings
     * @return array{
     *   ok: bool,
     *   status: string,
     *   package_id: string,
     *   package_basename: string,
     *   package_path: string,
     *   package_exists: bool,
     *   output_root: string,
     *   stage_path: string,
     *   include_roots: list<array{path: string, required: bool, type: string}>,
     *   skipped_optional_paths: list<string>,
     *   sidecars: array<string, string>,
     *   inventory: array{file_count: int, total_bytes: int},
     *   release_manifest: array<string, mixed>,
     *   issues: list<string>,
     *   warnings: list<string>
     * }
     */
    private function failureReport(
        string $packageId,
        string $packageBasename,
        string $packagePath,
        string $outputRoot,
        string $stagePath,
        array $includeRoots,
        array $skippedOptionalPaths,
        array $sidecars,
        array $snapshot,
        array $frozenSnapshot,
        array $issues,
        array $warnings,
    ): array {
        return [
            'ok' => false,
            'status' => 'fail',
            'package_id' => $packageId,
            'package_basename' => $packageBasename,
            'package_path' => $packagePath,
            'package_exists' => File::exists(base_path($packagePath)),
            'output_root' => $outputRoot,
            'stage_path' => $stagePath,
            'include_roots' => $includeRoots,
            'skipped_optional_paths' => $skippedOptionalPaths,
            'sidecars' => $sidecars,
            'inventory' => [
                'file_count' => 0,
                'total_bytes' => 0,
            ],
            'release_manifest' => [
                'status' => $snapshot['status'] ?? 'fail',
                'snapshot_path' => $snapshot['snapshot_path'] ?? '',
                'definition_sha256' => $snapshot['definition_sha256'] ?? '',
                'frozen_snapshot' => $frozenSnapshot,
            ],
            'issues' => array_values(array_unique(array_map(static fn (string $issue): string => trim($issue), $issues))),
            'warnings' => array_values(array_unique(array_map(static fn (string $warning): string => trim($warning), $warnings))),
        ];
    }
}
