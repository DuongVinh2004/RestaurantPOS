<?php

declare(strict_types=1);

namespace App\Platform\Backup\Support;

final class BackupArtifactManifest
{
    /**
     * @param array<string,mixed> $meta
     * @param array<string,array<string,mixed>> $artifacts
     * @param list<string> $pruned
     * @return array<string,mixed>
     */
    public static function build(array $meta, array $artifacts, array $pruned = []): array
    {
        return [
            'status' => (($meta['issues'] ?? []) === []) ? 'ok' : 'warning',
            'generated_at_utc' => (string) ($meta['generated_at_utc'] ?? gmdate('c')),
            'backup_root' => (string) ($meta['backup_root'] ?? ''),
            'backup_directory' => (string) ($meta['backup_directory'] ?? ''),
            'database' => [
                'host' => (string) ($meta['db_host'] ?? ''),
                'port' => (int) ($meta['db_port'] ?? 3306),
                'name' => (string) ($meta['db_database'] ?? ''),
            ],
            'options' => [
                'compress' => (bool) ($meta['compress'] ?? false),
                'retention_days' => (int) ($meta['retention_days'] ?? 0),
                'included_artifacts' => array_values(array_keys($artifacts)),
            ],
            'artifacts' => $artifacts,
            'pruned_directories' => array_values($pruned),
            'issues' => array_values((array) ($meta['issues'] ?? [])),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function describeFile(string $absolutePath, ?string $relativePath = null): array
    {
        if (! is_file($absolutePath)) {
            return [
                'exists' => false,
                'path' => $relativePath ?? $absolutePath,
            ];
        }

        $sha256 = hash_file('sha256', $absolutePath);
        if ($sha256 === false) {
            throw new \RuntimeException(sprintf('Unable to hash file: %s', $absolutePath));
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read file: %s', $absolutePath));
        }

        $normalized = rtrim($contents, "\r\n");
        $lineCount = $normalized === '' ? 0 : substr_count($normalized, "\n") + 1;

        return [
            'exists' => true,
            'path' => $relativePath ?? $absolutePath,
            'bytes' => filesize($absolutePath) ?: 0,
            'sha256' => $sha256,
            'line_count' => $lineCount,
        ];
    }
}
