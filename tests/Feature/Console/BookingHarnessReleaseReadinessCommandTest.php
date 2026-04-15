<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\Release\Services\LaunchReadinessService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingHarnessReleaseReadinessCommandTest extends TestCase
{
    public function test_booking_harness_release_readiness_wraps_launch_readiness_with_runtime_context(): void
    {
        $this->app->instance(LaunchReadinessService::class, new class extends LaunchReadinessService
        {
            public function __construct() {}

            public function evaluate(
                string $target = 'staging',
                ?string $manualEvidencePath = null,
                ?string $packageId = null,
                bool $overwritePackage = false,
                int $paymentSampleLimit = 10
            ): array {
                return [
                    'decision' => 'ready',
                    'exit_code' => 0,
                    'target' => [
                        'key' => $target,
                        'label' => ucfirst($target),
                    ],
                    'artifacts' => [
                        'json_path' => 'storage/app/booking_release/launch_readiness/reports/latest-staging.json',
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:harness:release-readiness', [
            '--target' => 'staging',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue((bool) ($payload['ok'] ?? false));
        $this->assertSame('ready', data_get($payload, 'readiness.decision'));
        $this->assertCount(5, (array) data_get($payload, 'golden_flows.scenarios', []));
        $this->assertGreaterThanOrEqual(4, count((array) ($payload['runtime_gates'] ?? [])));
    }
}
