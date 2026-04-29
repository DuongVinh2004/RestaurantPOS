<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\ApiContract\Services\OpsGateArtifactService;
use App\Platform\Performance\PerformanceVerificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class PerformanceVerificationServiceTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/performance_verification_unit';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking_performance_verification.artifact_root', $this->artifactRoot);
        Carbon::setTestNow(Carbon::parse('2026-04-28T10:00:00Z'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory(base_path($this->artifactRoot));

        parent::tearDown();
    }

    public function test_local_profile_is_marked_as_smoke_only_not_launch_evidence(): void
    {
        $report = $this->service()->evaluate(
            profile: 'local',
            ingestDir: 'tests/fixtures/performance_verification/raw-core-clean',
            scenarioKeys: ['availability_read_load'],
        );

        $this->assertTrue((bool) data_get($report, 'evidence_scope.local_smoke_only'));
        $this->assertFalse((bool) data_get($report, 'evidence_scope.staging_performance_evidence'));
        $this->assertStringContainsString(
            'not limited-production evidence',
            (string) data_get($report, 'evidence_scope.note')
        );
    }

    public function test_launch_critical_matrix_defines_day_one_surfaces(): void
    {
        $report = $this->service()->evaluate(
            profile: 'staging',
            ingestDir: 'tests/fixtures/performance_verification/raw-core-clean',
            scenarioKeys: ['webhook_duplicate_storm'],
        );

        $surfaces = collect((array) ($report['launch_critical_matrix'] ?? []))
            ->pluck('surface')
            ->all();

        foreach ((array) config('booking_launch_readiness.performance_required_surfaces') as $surface) {
            $this->assertContains($surface, $surfaces);
        }

        $this->assertTrue((bool) data_get($report, 'evidence_scope.staging_performance_evidence'));
    }

    public function test_experimental_scenarios_are_not_certified_accounting_evidence(): void
    {
        $report = $this->service()->evaluate(
            profile: 'staging',
            ingestDir: 'tests/fixtures/performance_verification/raw-core-clean',
            scenarioKeys: ['webhook_duplicate_storm'],
        );

        $experimental = collect((array) ($report['experimental_matrix'] ?? []))
            ->firstWhere('key', 'webhook_duplicate_storm');

        $this->assertIsArray($experimental);
        $this->assertSame('payment_webhook_duplicate_replay', $experimental['surface'] ?? null);
        $this->assertStringContainsString('not certified accounting evidence', (string) ($experimental['runbook_warning'] ?? ''));
    }

    private function service(): PerformanceVerificationService
    {
        return new PerformanceVerificationService(new OpsGateArtifactService);
    }
}
