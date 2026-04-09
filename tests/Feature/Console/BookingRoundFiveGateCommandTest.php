<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\RoundFiveGateService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingRoundFiveGateCommandTest extends TestCase
{
    public function test_booking_round5_gate_supports_json_output(): void
    {
        $this->app->instance(RoundFiveGateService::class, new class extends RoundFiveGateService {
            public function run(bool $write = false): array
            {
                return [
                    'ok' => true,
                    'suite' => 'round5',
                    'description' => 'Synthetic round5 gate.',
                    'definition_path' => 'tests/fixtures/round5_gate_suite.json',
                    'snapshot_path' => 'storage/app/booking_release/round5_gate_snapshot.json',
                    'tests' => [
                        [
                            'key' => 'financial_matrix',
                            'path' => 'tests/Feature/Staff/StaffCheckoutFinancialIntegrationMatrixTest.php',
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
                        'generated_at_utc' => '2026-03-18T12:00:00Z',
                        'write_requested' => $write,
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:round5-gate', ['--json' => true, '--write' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"suite": "round5"', $output);
        $this->assertStringContainsString('"write_requested": true', $output);
    }

    public function test_booking_round5_gate_reports_failure_in_table_mode(): void
    {
        $this->app->instance(RoundFiveGateService::class, new class extends RoundFiveGateService {
            public function run(bool $write = false): array
            {
                return [
                    'ok' => false,
                    'suite' => 'round5',
                    'description' => 'Synthetic round5 gate.',
                    'definition_path' => 'tests/fixtures/round5_gate_suite.json',
                    'snapshot_path' => 'storage/app/booking_release/round5_gate_snapshot.json',
                    'tests' => [
                        [
                            'key' => 'financial_matrix',
                            'path' => 'tests/Feature/Staff/StaffCheckoutFinancialIntegrationMatrixTest.php',
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
                        'generated_at_utc' => '2026-03-18T12:00:00Z',
                        'write_requested' => $write,
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:round5-gate');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('booking:round5-gate failed.', Artisan::output());
    }
}
