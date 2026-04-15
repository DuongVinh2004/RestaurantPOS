<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\ApiContract\Services\RouteContractReconcilerService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingRouteContractReconcileCommandTest extends TestCase
{
    public function test_booking_route_contract_reconcile_supports_json_output(): void
    {
        $this->app->instance(RouteContractReconcilerService::class, new class extends RouteContractReconcilerService
        {
            public function __construct() {}

            public function reconcile(?string $routeInventoryPath = null, ?string $staffCapabilitiesPath = null): array
            {
                return [
                    'ok' => false,
                    'status' => 'drift',
                    'route_inventory' => [
                        'path' => $routeInventoryPath ?? 'tests/fixtures/route_inventory_gate.json',
                        'drift' => [
                            'missing_from_locked' => [
                                ['signature' => 'POST api/v1/staff/demo', 'action' => 'App\\Http\\Controllers\\Api\\Staff\\DemoController@store'],
                            ],
                        ],
                        'candidate' => [],
                    ],
                    'staff_capabilities' => [
                        'path' => $staffCapabilitiesPath ?? 'config/staff_capabilities.php',
                        'drift' => [
                            'missing_from_config' => [
                                ['signature' => 'POST api/v1/staff/demo', 'capability' => 'order.manage'],
                            ],
                        ],
                        'candidate' => [],
                    ],
                    'summary' => [
                        'runtime_api_route_count' => 10,
                        'runtime_staff_capability_route_count' => 5,
                        'locked_route_inventory_count' => 9,
                        'locked_staff_capability_route_count' => 4,
                        'route_inventory_issue_count' => 1,
                        'staff_capability_issue_count' => 1,
                        'issue_count' => 2,
                    ],
                    'notes' => [
                        'Runtime route inventory is derived from live Laravel routes; nothing is rewritten unless an explicit --write flag is passed.',
                    ],
                    'writes' => [
                        'route_inventory' => null,
                        'staff_capabilities' => null,
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:route-contract:reconcile', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"status": "drift"', $output);
        $this->assertStringContainsString('"missing_from_config"', $output);
        $this->assertStringContainsString('"write_route_inventory_requested": false', $output);
    }

    public function test_booking_route_contract_reconcile_can_write_and_recheck_the_contract(): void
    {
        $this->app->instance(RouteContractReconcilerService::class, new class extends RouteContractReconcilerService
        {
            private bool $written = false;

            public function __construct() {}

            public function reconcile(?string $routeInventoryPath = null, ?string $staffCapabilitiesPath = null): array
            {
                return [
                    'ok' => $this->written,
                    'status' => $this->written ? 'ok' : 'drift',
                    'route_inventory' => [
                        'path' => $routeInventoryPath ?? 'tests/fixtures/route_inventory_gate.json',
                        'drift' => $this->written ? [] : ['missing_from_locked' => [['signature' => 'POST api/v1/staff/demo']]],
                        'candidate' => [],
                    ],
                    'staff_capabilities' => [
                        'path' => $staffCapabilitiesPath ?? 'config/staff_capabilities.php',
                        'drift' => $this->written ? [] : ['missing_from_config' => [['signature' => 'POST api/v1/staff/demo']]],
                        'candidate' => [],
                    ],
                    'summary' => [
                        'runtime_api_route_count' => 10,
                        'runtime_staff_capability_route_count' => 5,
                        'locked_route_inventory_count' => $this->written ? 10 : 9,
                        'locked_staff_capability_route_count' => $this->written ? 5 : 4,
                        'route_inventory_issue_count' => $this->written ? 0 : 1,
                        'staff_capability_issue_count' => $this->written ? 0 : 1,
                        'issue_count' => $this->written ? 0 : 2,
                    ],
                    'notes' => [],
                    'writes' => [
                        'route_inventory' => null,
                        'staff_capabilities' => null,
                    ],
                ];
            }

            public function writeReconciledArtifacts(
                bool $writeRouteInventory = false,
                bool $writeStaffCapabilities = false,
                ?array $report = null,
                ?string $routeInventoryPath = null,
                ?string $staffCapabilitiesPath = null,
            ): array {
                $this->written = true;

                return [
                    'writes' => [
                        'route_inventory' => $writeRouteInventory ? ($routeInventoryPath ?? 'tests/fixtures/route_inventory_gate.json') : null,
                        'staff_capabilities' => $writeStaffCapabilities ? ($staffCapabilitiesPath ?? 'config/staff_capabilities.php') : null,
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:route-contract:reconcile', [
            '--write-route-inventory' => true,
            '--write-staff-capabilities' => true,
        ]);

        $this->assertSame(0, $exitCode);
    }
}
