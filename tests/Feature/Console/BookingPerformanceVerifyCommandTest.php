<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class BookingPerformanceVerifyCommandTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/performance_verification_command';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking_performance_verification.artifact_root', $this->artifactRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->artifactRoot));
        parent::tearDown();
    }

    #[Group('booking-smoke')]
    public function test_booking_performance_verify_can_pass_clean_local_ingest_and_promote_baseline(): void
    {
        $exitCode = Artisan::call('booking:performance-verify', [
            '--profile' => 'local',
            '--ingest-dir' => 'tests/fixtures/performance_verification/raw-core-clean',
            '--scenario' => [
                'availability_read_load',
                'reservation_create_race',
                'webhook_duplicate_storm',
            ],
            '--baseline' => 'tests/fixtures/performance_verification/baseline-core.json',
            '--promote-baseline' => true,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $availability = collect((array) ($payload['scenarios'] ?? []))
            ->firstWhere('key', 'availability_read_load');

        $this->assertSame(0, $exitCode);
        $this->assertSame('pass', $payload['decision'] ?? null);
        $this->assertTrue((bool) (($payload['baseline'] ?? [])['promoted'] ?? false));
        $this->assertSame($this->artifactRoot.'/reports', $payload['artifacts']['reports_root'] ?? null);
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['json_path'] ?? '')));
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['markdown_path'] ?? '')));
        $this->assertFileExists(base_path((string) (($payload['baseline'] ?? [])['path'] ?? '')));
        $this->assertSame('improved', data_get($availability, 'baseline_delta.latency_p95_ms.direction'));
    }

    #[Group('booking-smoke')]
    public function test_booking_performance_verify_returns_warning_exit_code_when_required_staging_operator_probe_is_missing(): void
    {
        $exitCode = Artisan::call('booking:performance-verify', [
            '--profile' => 'staging',
            '--ingest-dir' => 'tests/fixtures/performance_verification/raw-core-clean',
            '--scenario' => [
                'availability_read_load',
                'reservation_create_race',
                'webhook_duplicate_storm',
                'redis_degradation_fault_probe',
            ],
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $manualScenario = collect((array) ($payload['manual_scenarios'] ?? []))
            ->firstWhere('key', 'redis_degradation_fault_probe');

        $this->assertSame(2, $exitCode);
        $this->assertSame('pass_with_warnings', $payload['decision'] ?? null);
        $this->assertSame('warn', $manualScenario['status'] ?? null);
        $this->assertTrue(
            collect((array) ($payload['major_warnings'] ?? []))
                ->contains(static fn (array $finding): bool => ($finding['scenario_key'] ?? null) === 'redis_degradation_fault_probe')
        );
    }

    #[Group('booking-smoke')]
    public function test_booking_performance_verify_blocks_on_threshold_regression(): void
    {
        $exitCode = Artisan::call('booking:performance-verify', [
            '--profile' => 'staging',
            '--ingest-dir' => 'tests/fixtures/performance_verification/raw-core-fail',
            '--scenario' => [
                'availability_read_load',
                'reservation_create_race',
                'webhook_duplicate_storm',
            ],
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('fail', $payload['decision'] ?? null);
        $this->assertTrue(
            collect((array) ($payload['blocking_failures'] ?? []))
                ->contains(static fn (array $finding): bool => ($finding['scenario_key'] ?? null) === 'availability_read_load')
        );
    }
}
