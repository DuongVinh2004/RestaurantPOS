<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BackupArtifactManifest;
use PHPUnit\Framework\TestCase;

final class BackupArtifactManifestTest extends TestCase
{
    public function test_it_describes_existing_backup_artifact(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'backup-artifact-');
        self::assertNotFalse($path);
        file_put_contents($path, "line-1\nline-2\n");

        $description = BackupArtifactManifest::describeFile($path, 'storage/app/booking_backups/example/full.sql.gz');

        self::assertTrue($description['exists']);
        self::assertSame('storage/app/booking_backups/example/full.sql.gz', $description['path']);
        self::assertSame(2, $description['line_count']);
        self::assertSame(hash_file('sha256', $path), $description['sha256']);

        @unlink($path);
    }

    public function test_it_builds_machine_readable_manifest_shape(): void
    {
        $manifest = BackupArtifactManifest::build([
            'generated_at_utc' => '2026-03-21T04:30:00Z',
            'backup_root' => 'storage/app/booking_backups',
            'backup_directory' => 'storage/app/booking_backups/20260321T043000Z-demo',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'restaurant_pos',
            'compress' => true,
            'retention_days' => 14,
            'issues' => [],
        ], [
            'schema' => [
                'exists' => true,
                'path' => 'storage/app/booking_backups/20260321T043000Z-demo/schema.sql.gz',
                'bytes' => 123,
                'sha256' => str_repeat('a', 64),
                'line_count' => 10,
                'portable' => true,
                'compressed' => true,
            ],
        ], ['storage/app/booking_backups/old-demo']);

        self::assertSame('ok', $manifest['status']);
        self::assertSame('restaurant_pos', $manifest['database']['name']);
        self::assertSame(['schema'], $manifest['options']['included_artifacts']);
        self::assertSame(['storage/app/booking_backups/old-demo'], $manifest['pruned_directories']);
    }
}
