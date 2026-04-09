<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BackupRestoreManifest;
use PHPUnit\Framework\TestCase;

class BackupRestoreManifestTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir() . '/backup_restore_manifest_' . bin2hex(random_bytes(8));
        mkdir($this->workspace, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_load_and_resolve_relative_artifact_path(): void
    {
        $artifactPath = $this->workspace . '/full.sql';
        file_put_contents($artifactPath, "SELECT 1;\nSELECT 2;\n");

        $manifestPath = $this->workspace . '/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'artifacts' => [
                'full' => [
                    'path' => 'full.sql',
                    'bytes' => filesize($artifactPath),
                    'sha256' => hash_file('sha256', $artifactPath),
                    'line_count' => 2,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $manifest = BackupRestoreManifest::load($manifestPath);
        $artifact = BackupRestoreManifest::artifact($manifest, 'full');
        $resolved = BackupRestoreManifest::resolveArtifact($artifact ?? [], dirname($manifestPath));

        $this->assertTrue($resolved['exists']);
        $this->assertSame($artifactPath, $resolved['path']);
    }

    public function test_validate_resolved_artifact_reports_hash_mismatch(): void
    {
        $artifactPath = $this->workspace . '/schema.sql';
        file_put_contents($artifactPath, "CREATE TABLE test (id INT);\n");

        $resolved = [
            'exists' => true,
            'path' => $artifactPath,
            'bytes' => filesize($artifactPath),
            'sha256' => str_repeat('a', 64),
            'line_count' => 1,
        ];

        $report = BackupRestoreManifest::validateResolvedArtifact($resolved);

        $this->assertFalse($report['ok']);
        $this->assertNotEmpty($report['errors']);
        $this->assertStringContainsString('sha256 mismatch', strtolower($report['errors'][0]));
    }

    public function test_locate_latest_points_to_latest_manifest_json(): void
    {
        $path = BackupRestoreManifest::locateLatest($this->workspace);

        $this->assertSame($this->workspace . '/latest-manifest.json', $path);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
