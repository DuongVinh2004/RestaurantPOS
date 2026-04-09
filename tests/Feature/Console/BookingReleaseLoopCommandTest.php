<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\ReleaseLoopService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingReleaseLoopCommandTest extends TestCase
{
    public function test_booking_release_loop_supports_json_output(): void
    {
        $service = new class extends ReleaseLoopService
        {
            public function __construct() {}

            public function run(
                string $target = 'staging',
                ?string $manualEvidencePath = null,
                ?string $packageId = null,
                bool $overwritePackage = false,
                ?string $manifestPath = null,
                bool $bootstrapUat = false,
                ?string $baseUrl = null,
                ?string $previewCommand = null,
                ?string $previewUrl = null,
                ?string $previewLabel = null,
                bool $skipPreview = false,
                string $staffWebDir = 'staff-web',
            ): array {
                return [
                    'ok' => true,
                    'decision' => 'pass',
                    'target' => [
                        'key' => $target,
                        'label' => ucfirst($target),
                    ],
                    'summary' => [
                        'step_count' => 11,
                        'pass_count' => 10,
                        'skip_count' => 1,
                        'fail_count' => 0,
                    ],
                    'steps' => [
                        [
                            'key' => 'contract_artifacts',
                            'label' => 'Contract artifacts',
                            'status' => 'pass',
                            'summary' => 'ok=true',
                        ],
                    ],
                    'preview' => [
                        'label' => $previewLabel,
                        'url' => $previewUrl,
                        'status' => 'url-recorded',
                    ],
                    'observability' => [
                        'status' => 'missing-configuration',
                        'release' => 'abc123',
                    ],
                    'artifacts' => [
                        'reports_root' => 'storage/app/booking_release/release_loop/reports',
                        'json_path' => 'storage/app/booking_release/release_loop/reports/latest-staging.json',
                    ],
                    'blocking_failures' => [],
                ];
            }
        };
        $this->app->instance(ReleaseLoopService::class, $service);

        $exitCode = Artisan::call('booking:release-loop', [
            '--target' => 'staging',
            '--preview-url' => 'https://preview.example.test',
            '--preview-label' => 'preview',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"decision": "pass"', $output);
        $this->assertStringContainsString('"step_count": 11', $output);
        $this->assertStringContainsString('"url": "https://preview.example.test"', $output);
        $this->assertStringContainsString('"status": "missing-configuration"', $output);
    }

    public function test_booking_release_loop_fails_in_table_mode_when_a_step_blocks(): void
    {
        $service = new class extends ReleaseLoopService
        {
            public function __construct() {}

            public function run(
                string $target = 'staging',
                ?string $manualEvidencePath = null,
                ?string $packageId = null,
                bool $overwritePackage = false,
                ?string $manifestPath = null,
                bool $bootstrapUat = false,
                ?string $baseUrl = null,
                ?string $previewCommand = null,
                ?string $previewUrl = null,
                ?string $previewLabel = null,
                bool $skipPreview = false,
                string $staffWebDir = 'staff-web',
            ): array {
                return [
                    'ok' => false,
                    'decision' => 'block',
                    'target' => [
                        'key' => $target,
                        'label' => ucfirst($target),
                    ],
                    'summary' => [
                        'step_count' => 5,
                        'pass_count' => 4,
                        'skip_count' => 0,
                        'fail_count' => 1,
                    ],
                    'steps' => [
                        [
                            'key' => 'backend_doctor',
                            'label' => 'Backend doctor',
                            'status' => 'fail',
                            'summary' => 'doctor failed',
                        ],
                    ],
                    'artifacts' => [
                        'reports_root' => 'storage/app/booking_release/release_loop/reports',
                        'json_path' => 'storage/app/booking_release/release_loop/reports/latest-staging.json',
                        'markdown_path' => 'storage/app/booking_release/release_loop/reports/latest-staging.md',
                        'latest_json_path' => 'storage/app/booking_release/release_loop/reports/latest-staging.json',
                        'latest_markdown_path' => 'storage/app/booking_release/release_loop/reports/latest-staging.md',
                    ],
                    'preview' => [
                        'label' => 'preview',
                        'url' => null,
                        'status' => 'unconfigured',
                    ],
                    'observability' => [
                        'status' => 'missing-configuration',
                        'release' => 'abc123',
                    ],
                    'blocking_failures' => [
                        [
                            'step_key' => 'backend_doctor',
                            'step_label' => 'Backend doctor',
                            'message' => 'doctor failed',
                        ],
                    ],
                ];
            }
        };
        $this->app->instance(ReleaseLoopService::class, $service);

        $exitCode = Artisan::call('booking:release-loop', [
            '--target' => 'staging',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('booking:release-loop failed.', $output);
        $this->assertStringContainsString('Backend doctor', $output);
        $this->assertStringContainsString('doctor failed', $output);
        $this->assertStringContainsString('missing-configuration', $output);
        $this->assertStringContainsString('abc123', $output);
    }
}
