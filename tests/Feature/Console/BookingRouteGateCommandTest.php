<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\ApiContract\Services\RouteInventoryGateService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingRouteGateCommandTest extends TestCase
{
    public function test_booking_route_gate_supports_json_output(): void
    {
        $this->app->instance(RouteInventoryGateService::class, new class extends RouteInventoryGateService
        {
            public function inspect(): array
            {
                return [
                    'ok' => true,
                    'suite' => 'route_inventory',
                    'description' => 'Synthetic route gate.',
                    'definition_path' => 'tests/fixtures/route_inventory_gate.json',
                    'checks' => [
                        'route_action_methods' => [
                            'ok' => true,
                            'severity' => 'info',
                            'message' => 'ok',
                        ],
                    ],
                    'summary' => [
                        'route_count' => 10,
                        'expected_route_count' => 5,
                        'public_controller_count' => 3,
                        'error_count' => 0,
                        'warning_count' => 0,
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-03-27T12:00:00Z',
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:route-gate', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"suite": "route_inventory"', $output);
        $this->assertStringContainsString('"expected_route_count": 5', $output);
    }

    public function test_booking_route_gate_reports_failure_in_table_mode(): void
    {
        $this->app->instance(RouteInventoryGateService::class, new class extends RouteInventoryGateService
        {
            public function inspect(): array
            {
                return [
                    'ok' => false,
                    'suite' => 'route_inventory',
                    'description' => 'Synthetic route gate.',
                    'definition_path' => 'tests/fixtures/route_inventory_gate.json',
                    'checks' => [
                        'expected_routes' => [
                            'ok' => false,
                            'severity' => 'error',
                            'message' => 'drift',
                        ],
                    ],
                    'summary' => [
                        'route_count' => 10,
                        'expected_route_count' => 5,
                        'public_controller_count' => 3,
                        'error_count' => 1,
                        'warning_count' => 0,
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-03-27T12:00:00Z',
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:route-gate');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('booking:route-gate failed.', Artisan::output());
    }
}
