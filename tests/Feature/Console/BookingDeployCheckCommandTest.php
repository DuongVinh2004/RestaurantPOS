<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\BookingDeploySafetyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class BookingDeployCheckCommandTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/deploy_check_command';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking_ops_artifacts.deploy_check.artifact_root', $this->artifactRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->artifactRoot));

        parent::tearDown();
    }

    #[Group('booking-smoke')]
    public function test_booking_deploy_check_supports_json_output(): void
    {
        app()->instance(BookingDeploySafetyService::class, new class extends BookingDeploySafetyService
        {
            public function __construct() {}

            public function inspect(string $mode = 'preflight'): array
            {
                return BookingDeployCheckCommandTest::fakeReport($mode);
            }
        });

        $exitCode = Artisan::call('booking:deploy-check', [
            '--mode' => 'postflight',
            '--json' => true,
        ]);

        $output = Artisan::output();
        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"mode": "postflight"', $output);
        $this->assertStringContainsString('"report"', $output);
        $this->assertStringContainsString('"artifact_error_count": 0', $output);
        $this->assertStringContainsString('"artifact_warning_count": 0', $output);
        $this->assertSame($this->artifactRoot.'/reports', $payload['artifacts']['reports_root'] ?? null);
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['json_path'] ?? '')));
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['markdown_path'] ?? '')));
    }

    #[Group('booking-smoke')]
    public function test_booking_deploy_check_table_output_includes_artifact_summary_counts(): void
    {
        app()->instance(BookingDeploySafetyService::class, new class extends BookingDeploySafetyService
        {
            public function __construct() {}

            public function inspect(string $mode = 'preflight'): array
            {
                return BookingDeployCheckCommandTest::fakeReport($mode, summaryOverrides: [
                    'artifact_error_count' => 2,
                    'artifact_warning_count' => 1,
                ]);
            }
        });

        $exitCode = Artisan::call('booking:deploy-check', [
            '--mode' => 'preflight',
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('artifact_error_count', $output);
        $this->assertStringContainsString('2', $output);
        $this->assertStringContainsString('artifact_warning_count', $output);
        $this->assertStringContainsString('1', $output);
    }

    #[Group('booking-smoke')]
    public function test_booking_deploy_check_fails_in_strict_mode_when_warnings_exist(): void
    {
        app()->instance(BookingDeploySafetyService::class, new class extends BookingDeploySafetyService
        {
            public function __construct() {}

            public function inspect(string $mode = 'preflight'): array
            {
                return BookingDeployCheckCommandTest::fakeReport(
                    $mode,
                    warnings: ['migrations.files: Some applied migrations are missing from the release artifact.'],
                    checks: [
                        'migrations.files' => [
                            'ok' => false,
                            'severity' => 'warning',
                            'message' => 'Some applied migrations are missing from the release artifact.',
                        ],
                    ],
                    summaryOverrides: [
                        'data_guard_warning_count' => 1,
                    ],
                );
            }
        });

        $exitCode = Artisan::call('booking:deploy-check', [
            '--mode' => 'preflight',
            '--strict' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('booking:deploy-check (preflight) failed.', Artisan::output());
    }

    /**
     * @param  array<string>  $errors
     * @param  array<string>  $warnings
     * @param  array<string, array{ok: bool, severity: string, message: string}>  $checks
     * @param  array<string, int>  $summaryOverrides
     * @return array{
     *   ok: bool,
     *   mode: string,
     *   errors: array<string>,
     *   warnings: array<string>,
     *   checks: array<string, array{ok: bool, severity: string, message: string}>,
     *   summary: array<string, int>
     * }
     */
    public static function fakeReport(
        string $mode,
        array $errors = [],
        array $warnings = [],
        array $checks = [],
        array $summaryOverrides = [],
    ): array {
        return [
            'ok' => empty($errors),
            'mode' => $mode,
            'errors' => $errors,
            'warnings' => $warnings,
            'checks' => $checks === []
                ? [
                    'environment' => [
                        'ok' => true,
                        'severity' => 'info',
                        'message' => 'ok',
                    ],
                ]
                : $checks,
            'summary' => array_replace([
                'environment_error_count' => 0,
                'environment_warning_count' => 0,
                'pending_migration_count' => 0,
                'data_guard_error_count' => 0,
                'data_guard_warning_count' => 0,
                'artifact_error_count' => 0,
                'artifact_warning_count' => 0,
                'ops_error_count' => 0,
                'ops_warning_count' => 0,
            ], $summaryOverrides),
        ];
    }
}
