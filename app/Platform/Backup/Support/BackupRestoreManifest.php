<?php

declare(strict_types=1);

namespace App\Platform\Backup\Support;

use InvalidArgumentException;
use RuntimeException;

class BackupRestoreManifest
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $path): array
    {
        if ($path === '') {
            throw new InvalidArgumentException('Manifest path cannot be empty.');
        }

        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Backup manifest not found: %s', $path));
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Unable to read backup manifest: %s', $path));
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('Backup manifest is not valid JSON: %s', $path));
        }

        return $decoded;
    }

    public static function locateLatest(string $backupRoot): string
    {
        $backupRoot = rtrim($backupRoot, '\\/');
        if ($backupRoot === '') {
            throw new InvalidArgumentException('Backup root cannot be empty.');
        }

        return $backupRoot.'/latest-manifest.json';
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>|null
     */
    public static function artifact(array $manifest, string $key): ?array
    {
        $artifacts = $manifest['artifacts'] ?? null;
        if (is_array($artifacts) && isset($artifacts[$key]) && is_array($artifacts[$key])) {
            return $artifacts[$key];
        }

        $legacy = $manifest[$key] ?? null;
        if (is_array($legacy)) {
            return $legacy;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $artifact
     * @return array{exists: bool, path: string, bytes: int|null, sha256: string|null, line_count: int|null}
     */
    public static function resolveArtifact(array $artifact, string $manifestDir): array
    {
        $relativePath = trim((string) ($artifact['path'] ?? ''));
        $resolvedPath = self::resolvePath($relativePath, $manifestDir);

        return [
            'exists' => ($resolvedPath !== '' && is_file($resolvedPath)),
            'path' => $resolvedPath,
            'bytes' => isset($artifact['bytes']) ? (int) $artifact['bytes'] : null,
            'sha256' => isset($artifact['sha256']) ? (string) $artifact['sha256'] : null,
            'line_count' => isset($artifact['line_count']) ? (int) $artifact['line_count'] : null,
        ];
    }

    /**
     * @param  array{exists: bool, path: string, bytes: int|null, sha256: string|null, line_count: int|null}  $resolved
     * @return array{ok: bool, errors: array<int, string>, actual: array<string, mixed>}
     */
    public static function validateResolvedArtifact(array $resolved): array
    {
        $errors = [];
        $path = (string) ($resolved['path'] ?? '');
        $exists = ($path !== '' && is_file($path));

        $actual = [
            'exists' => $exists,
            'path' => $path,
            'bytes' => $exists ? filesize($path) : null,
            'sha256' => $exists ? hash_file('sha256', $path) : null,
            'line_count' => $exists ? self::lineCount($path) : null,
        ];

        if (! $exists) {
            $errors[] = sprintf('Artifact file missing: %s', $path !== '' ? $path : '[empty path]');
        }

        if ($exists && isset($resolved['bytes']) && $resolved['bytes'] !== null && (int) $resolved['bytes'] !== (int) $actual['bytes']) {
            $errors[] = sprintf('Artifact byte count mismatch for %s.', basename($path));
        }

        if ($exists && isset($resolved['sha256']) && $resolved['sha256'] !== null && ! hash_equals((string) $resolved['sha256'], (string) $actual['sha256'])) {
            $errors[] = sprintf('Artifact sha256 mismatch for %s.', basename($path));
        }

        return [
            'ok' => ($errors === []),
            'errors' => $errors,
            'actual' => $actual,
        ];
    }

    public static function resolvePath(string $path, string $baseDir): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (self::isAbsolutePath($path)) {
            return $path;
        }

        return rtrim($baseDir, '\\/').'/'.ltrim($path, '\\/');
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }

    private static function lineCount(string $path): int
    {
        $handle = fopen($path, 'rb');
        if (! is_resource($handle)) {
            throw new RuntimeException(sprintf('Unable to open artifact for line counting: %s', $path));
        }

        $lines = 0;
        while (! feof($handle)) {
            $chunk = fread($handle, 8192);
            if ($chunk === false) {
                fclose($handle);
                throw new RuntimeException(sprintf('Unable to read artifact for line counting: %s', $path));
            }

            $lines += substr_count($chunk, "\n");
        }
        fclose($handle);

        if (filesize($path) > 0) {
            $tail = file_get_contents($path, false, null, max(0, filesize($path) - 1), 1);
            if ($tail !== false && $tail !== "\n") {
                $lines += 1;
            }
        }

        return $lines;
    }
}
