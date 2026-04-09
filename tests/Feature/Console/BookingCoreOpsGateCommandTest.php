<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\CoreOpsGateService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingCoreOpsGateCommandTest extends TestCase
{
    public function test_booking_core_ops_gate_supports_json_output(): void
    {
        $this->app->instance(CoreOpsGateService::class, new class extends CoreOpsGateService {
            public function run(bool $write = false): array
            {
                return [
                    'ok' => true,
                    'suite' => 'core_ops',
                    'description' => 'Synthetic core ops gate.',
                    'definition_path' => 'tests/fixtures/core_ops_gate_suite.json',
                    'snapshot_path' => 'storage/app/booking_release/core_ops_gate_snapshot.json',
                    'tests' => [
                        [
                            'key' => 'table_hold_http_flow',
                            'path' => 'tests/Feature/Table/TableHoldHttpFlowTest.php',
                            'category' => 'feature',
                            'ok' => true,
                            'exit_code' => 0,
                            'duration_ms' => 42,
                            'output_tail' => 'ok',
                        ],
                    ],
                    'summary' => [
                        'total' => 1,
                        'passed' => 1,
                        'failed' => 0,
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-03-22T12:00:00Z',
                        'write_requested' => $write,
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:core-ops-gate', ['--json' => true, '--write' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"suite": "core_ops"', $output);
        $this->assertStringContainsString('"write_requested": true', $output);
    }

    public function test_booking_core_ops_gate_reports_failure_in_table_mode(): void
    {
        $this->app->instance(CoreOpsGateService::class, new class extends CoreOpsGateService {
            public function run(bool $write = false): array
            {
                return [
                    'ok' => false,
                    'suite' => 'core_ops',
                    'description' => 'Synthetic core ops gate.',
                    'definition_path' => 'tests/fixtures/core_ops_gate_suite.json',
                    'snapshot_path' => 'storage/app/booking_release/core_ops_gate_snapshot.json',
                    'tests' => [
                        [
                            'key' => 'table_hold_http_flow',
                            'path' => 'tests/Feature/Table/TableHoldHttpFlowTest.php',
                            'category' => 'feature',
                            'ok' => false,
                            'exit_code' => 1,
                            'duration_ms' => 42,
                            'output_tail' => 'fail',
                        ],
                    ],
                    'summary' => [
                        'total' => 1,
                        'passed' => 0,
                        'failed' => 1,
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-03-22T12:00:00Z',
                        'write_requested' => $write,
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:core-ops-gate');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('booking:core-ops-gate failed.', Artisan::output());
    }
}
