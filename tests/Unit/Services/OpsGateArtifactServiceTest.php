<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\ApiContract\Services\OpsGateArtifactService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OpsGateArtifactServiceTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/ops_gate_artifacts';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->artifactRoot));

        parent::tearDown();
    }

    public function test_write_report_uses_consistent_reports_layout_and_latest_pointers(): void
    {
        $evaluatedAt = Carbon::parse('2026-04-06 11:22:33', 'UTC');
        $payload = app(OpsGateArtifactService::class)->writeReport(
            artifactRoot: $this->artifactRoot,
            reportPrefix: 'booking-doctor',
            scopeKey: 'strict',
            payload: [
                'ok' => true,
                'decision' => 'pass',
            ],
            markdown: '# Test',
            evaluatedAt: $evaluatedAt,
        );

        $this->assertSame($this->artifactRoot, $payload['artifacts']['root'] ?? null);
        $this->assertSame($this->artifactRoot.'/reports', $payload['artifacts']['reports_root'] ?? null);
        $this->assertSame('booking-doctor', $payload['artifacts']['report_prefix'] ?? null);
        $this->assertSame('strict', $payload['artifacts']['scope_key'] ?? null);
        $this->assertSame('strict', $payload['artifacts']['scope_slug'] ?? null);
        $this->assertSame(
            $this->artifactRoot.'/reports/booking-doctor-strict-20260406t112233z.json',
            $payload['artifacts']['json_path'] ?? null
        );
        $this->assertSame(
            $this->artifactRoot.'/reports/latest-strict.json',
            $payload['artifacts']['latest_json_path'] ?? null
        );
        $this->assertFileExists(base_path((string) ($payload['artifacts']['json_path'] ?? '')));
        $this->assertFileExists(base_path((string) ($payload['artifacts']['markdown_path'] ?? '')));
        $this->assertFileExists(base_path((string) ($payload['artifacts']['latest_json_path'] ?? '')));
        $this->assertFileExists(base_path((string) ($payload['artifacts']['latest_markdown_path'] ?? '')));
        $this->assertSame(
            (string) File::get(base_path((string) ($payload['artifacts']['json_path'] ?? ''))),
            (string) File::get(base_path((string) ($payload['artifacts']['latest_json_path'] ?? '')))
        );
    }
}
